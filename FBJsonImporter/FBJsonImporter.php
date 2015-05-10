<?php
/*
Plugin Name: FBJsonImporter
Plugin URI: http://yvo.com/jsonimporter
Description: This plugin helps to import Facebook posts to Wordpress database
Author: Y Vo
Version: 1.0
Author URI: http://yvo.com/
*/

defined('ABSPATH') or die('INVALID ACCESS');

#ini_set('memory_limit','2G');
#ini_set("memory_limit", "-1");
#set_time_limit(0);

define('FBJSONIMPORTER_PLUGIN_URL', plugin_dir_url( __FILE__ ));
define('FBJSONIMPORTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
define('FBJSONIMPORTER_PROCESS_ROWCOUNT', 2000); //process 2000 facebook posts at a time
define('FBJSONIMPORTER_BATCH_MAX', 80); //generate batch insert sql with maxinum 80 records 

/*
Main class to import json data to Wordpress post.
This is designed for importing massive facebook posts (such as 100000) to Wordpress database.
This is not using Wordpress API functions to insert facebook post to database because 
inserting 1 post at a time is very slow.
*/
class FBJsonImporter {
	private static $last_error = null;
	private static $processed_total = null;
	private static $non_processed_total = null;
	private static $processing_time = null;
	private static $total = null;
	
	public function __construct() {
	}

	private static function init_values() {
		self::$last_error = null;
		self::$processed_total = null;
		self::$non_processed_total = null;
		self::$processing_time = null;
		self::$total = null;
	}
	/**
	 * This is the main function to import json data into Wordpress posts
	 *
	 * @param string $file	Json data file
	 * @return int False if fail to import, the number of rows inserted if import successful.
	 */
	public static function import_json($file) {
		self::init_values();
		
		if(!file_exists($file)) {
			self::$last_error = "File is not existing.";
			return false;
		}
		
		//check common errors
		$arr = json_decode(file_get_contents($file));
		if(empty($arr)) {
			self::$last_error = "Json decode fails. Please check your file format.";
			return false;
		}
		if(count($arr->data)==0) {
			self::$last_error = "Json data is empty. Stop processing.";
			return false;
		}
		
		global $wpdb;
		
		//initiate default values
		$data = array();
		$fb_cats = array(); //store all facebook post categories
		$default_status = get_option('fb_post_default_status');
		$default_comment_status = 'closed';
		$default_ping_status = 'closed';
		$record_num_per_batch = FBJSONIMPORTER_BATCH_MAX; //insert FBJSONIMPORTER_BATCH_MAX posts for one insert sql statement
		$start_time = $start = microtime(true);
		
		//list of post columns
		$post_cols = array('ID','post_author','post_date','post_date_gmt','post_content',
					'post_title','post_excerpt','post_status','comment_status','ping_status',
					'post_password','post_name','to_ping','pinged','post_modified',
					'post_modified_gmt','post_content_filtered','post_parent','guid','menu_order',
					'post_type','post_mime_type','comment_count');
		
		$process_count = 0;
		$processed_total = 0;
		$non_processed_total = 0;
		
		$wpdb->query("START TRANSACTION");
		
		try {
		//Process 2000 facebook posts at a time
		while($process_count<count($arr->data)) {
			//Get 2000 fb post ids to process. 
			$fb_post_ids = array();
			$fb_cats = array();
			
			$max = $process_count+FBJSONIMPORTER_PROCESS_ROWCOUNT;
			if($max>count($arr->data)) $max = count($arr->data);
			
			//skip the duplicated facebook post id, store one only
			for($i=$process_count;$i<$max;$i++) {
				$ids 		= explode("_",$arr->data[$i]->id);
				if(!in_array($ids[1],$fb_post_ids)) $fb_post_ids[] = $ids[1];
				
				$category = $arr->data[$i]->from->category;
				
				//store category and associated fb post id
				if(!isset($fb_cats[$category])) $fb_cats[$category]=array();
				if(!in_array($ids[1],$fb_cats[$category])) $fb_cats[$category][] = $ids[1];
			}
			
			//Using postmeta to store facebook post ids
			//Get all existing FB post IDs in database from postmeta table 
			//based on the current processing list
			$existing_ids = array();
			if(count($fb_post_ids)>0) {
				$query = "SELECT distinct `meta_value` FROM {$wpdb->postmeta} WHERE `meta_key`='fb_post_id' AND `meta_value` IN ('" . implode("','", $fb_post_ids) . "');";
				$results = $wpdb->get_results($query);
				foreach($results as $item) {
					$existing_ids[] = $item->meta_value;
				}
			} else {
				self::$last_error = "Facebook Posts are not found. Stop processing.";
				return false;
			}
			
			//print_r($existing_ids);
			
			//generate the bulk insert sql
			$post_author = get_current_user_id();
			$query = "INSERT INTO {$wpdb->posts} (" . implode(",",$post_cols) . ") VALUES ";
			$count=0;
			$updated_post_ids = array(); //save the list of updated post id for later use
			for($i=$process_count;$i<$max;$i++) {
				$fbpost = $arr->data[$i];
				$ids 		= explode("_", $fbpost->id);
				$fb_post_id 	= $ids[1];
				
				//skip because this post has been inserted into db 
				if(in_array($fb_post_id, $existing_ids) ||
					in_array($fb_post_id, $updated_post_ids)){
					$non_processed_total++;
					continue;
				}
				
				//Store facebook post ids
				$updated_post_ids[] = $fb_post_id;
				
				$count++;
				$created_time = date("Y-m-d H:i:s",strtotime($fbpost->created_time));
				$updated_time = date("Y-m-d H:i:s",strtotime($fbpost->updated_time));
				$message 	= $fbpost->message;
				
				//try to get post name
				$name 		= isset($fbpost->name)?$fbpost->name:'';
				if($name=='') $name = isset($fbpost->story)?$fbpost->story:'';
				if($name=='') $name = $message;
				
				$data = array(
					'ID' => 0,
					'post_author' => $fb_post_id,//use this field temporary, will update it to the actual user id later
					'post_date' => $created_time,
					'post_date_gmt' => $created_time,
					'post_content' => esc_sql($message),
					'post_title' => esc_sql($name),
					'post_excerpt' => '',
					'post_status' => $default_status,
					'comment_status' => $default_comment_status,
					'ping_status' => $default_ping_status,
					'post_password' => '',
					'post_name' => esc_sql(self::wp_unique_post_slug($name)),
					'to_ping' => '',
					'pinged' => '',
					'post_modified' => $updated_time,
					'post_modified_gmt' => $updated_time,
					'post_content_filtered' => '',
					'post_parent' => 0,
					'guid' => '', //this guid will be updated later
					'menu_order' => 0,
					'post_type' => 'post',
					'post_mime_type' => '',
					'comment_count' => 0
				);
				
				//generate post values
				$query .= "('" . implode("','", $data) . "'),";
				
				if($record_num_per_batch==$count) {
					//Insert posts to database
					$query = substr($query, 0, -1); //remove the last comma
					$wpdb->query($query);
					
					$new_post_ids = self::get_new_post_ids($updated_post_ids);
					self::update_post_categories($fb_cats,$new_post_ids);
					
					//save fb post id to postmeta
					self::store_fb_post_ids($updated_post_ids,$new_post_ids);
					
					//update author ids, guid
					self::update_guid_post_author($updated_post_ids);
					
					//reset variables for next bulk sql run
					$processed_total+=$count;
					$count = 0;
					$query = "INSERT INTO {$wpdb->posts} (" . implode(",",$post_cols) . ") VALUES ";
					$updated_post_ids = array();
				}
			}
			
			//run the last bulk insert sql
			if($count>0 && $count < $record_num_per_batch) {
				$query = substr($query, 0, -1);
				$wpdb->query($query);
				
				$new_post_ids = self::get_new_post_ids($updated_post_ids);
				self::update_post_categories($fb_cats,$new_post_ids);
				self::store_fb_post_ids($updated_post_ids,$new_post_ids);
				
				//update author ids, guid
				self::update_guid_post_author($updated_post_ids);
				
				$processed_total+=$count;
			}
			//Continue to process another FBJSONIMPORTER_PROCESS_ROWCOUNT rows
			$process_count+=FBJSONIMPORTER_PROCESS_ROWCOUNT;
		}
		}catch(Exception $ex) {
			$wpdb->query("ROLLBACK");
			self::$last_error = $ex->getMessage();
			return false;
		}
		
		$wpdb->query("COMMIT");
		
		self::$processed_total = $processed_total;
		self::$non_processed_total = $non_processed_total;
		self::$processing_time = microtime(true) - $start_time;
		self::$total = count($arr->data);
		
		return $processed_total;
	}
	
	/*
	* Store facebook post ids into postmeta table
	* @param array $fb_post_ids	List of facebook post ids
	* @return NONE
	*/
	public static function store_fb_post_ids($fb_post_ids,$new_post_ids = array()) {
		global $wpdb;
		
		if(count($fb_post_ids)==0) return;
		if(count($new_post_ids)==0) {
			$new_post_ids = self::get_new_post_ids($fb_post_ids);
		}
		//Get list of new INCREMENTAL post ids
		//and then create a custom field 'fb_post_id' to store fb_post_id
		$query2 = "INSERT INTO {$wpdb->postmeta}(`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES";
		foreach($new_post_ids as $fb_post_id => $post_id) {
			$query2 .= "('0','{$post_id}','fb_post_id','{$fb_post_id}'),";
		}
		$query2 = substr($query2, 0, -1); //remove the last char
		$wpdb->query($query2);
	}
	
	/*
	* Get all newly inserted post ids based on fb post ids
	* @param array $fb_post_ids	List of facebook post ids which were inserted to post table
	* @return list of new post ids
	*/
	public static function get_new_post_ids($fb_post_ids) {
		global $wpdb;
		
		if(count($fb_post_ids)==0) return;
		
		$query = "SELECT ID, post_author FROM {$wpdb->posts} WHERE `post_author` IN (" . implode(",",$fb_post_ids) . ");";
		$results = $wpdb->get_results($query);
		$arr = array();
		foreach($results as $item) {
			$post_id = $item->ID;
			$fb_post_id = $item->post_author; //use post_author as fb_post_id temporarily
			$arr[$fb_post_id] = $post_id;
		}
		return $arr;
	}
	
	/*
	* Update category for all the new posts
	*/
	public static function update_post_categories($fb_cat_list, $new_post_ids=array()) {
		global $wpdb;
		
		if(count($fb_cat_list)==0) return;
		if(count($new_post_ids)==0) return;
		
		foreach($fb_cat_list as $cat=>$ids) {
			$cat_slug = sanitize_title($cat);
			$cat_id = 0;
			$cat_tt_id = 0;
			
			//search category by name
			$cat_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE `slug` = %s", $cat_slug ) );
			if ( $cat_id == null ) {
				//not existing, create a new category
				$wpdb->insert( $wpdb->terms, array('term_id' => 0, 'name' => $cat, 'slug' => $cat_slug, 'term_group' => 0) );
				$cat_id = $wpdb->insert_id;
				$wpdb->insert( $wpdb->term_taxonomy, array('term_id' => $cat_id, 'taxonomy' => 'category', 'description' => $cat, 'parent' => 0, 'count' => 1));
				$cat_tt_id = $wpdb->insert_id;
			} else {
				$cat_tt_id = $wpdb->get_var( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE `taxonomy` = 'category' and `term_id` = %d", $cat_id));
			}
			
			//generate batch sql
			$query = "INSERT INTO {$wpdb->term_relationships}(object_id,term_taxonomy_id,term_order) 
						VALUES";
			foreach($ids as $fb_pid) {
				if(isset($new_post_ids[$fb_pid]))
					$query.= "(" . $new_post_ids[$fb_pid] . "," . $cat_tt_id . ",0),";
			}
			
			$query = substr($query, 0, -1);
			$wpdb->query($query);
			//$wpdb->insert( $wpdb->term_relationships, array('term_taxonomy_id' => $cat_tt_id, 'object_id' => 1));
		}
	}
	
	/*
	* Update post guid and post author
	*/
	public static function update_guid_post_author($updated_post_ids) {
		global $wpdb;
		if(count($updated_post_ids)==0) return;
		$post_author = get_current_user_id();
		$post_guid = get_option( 'home' ) . '/?p=1';
		//update author ids
		$query = "UPDATE {$wpdb->posts} SET post_author='{$post_author}', guid=CONCAT('{$post_guid}/?p=',`ID`)  
					WHERE post_author IN (" . implode(",",$updated_post_ids) . ");";
		$wpdb->query($query);		
	}
	
	/**
	 * This is a simple version of Wordpress API: wp_unique_post_slug.
	 * Computes a unique slug for the post.
	 *
	 * @param string $slug        The desired slug (post_name).
	 * @param int    $post_parent Post parent ID.
	 * @return string Unique slug for the post, based on $post_name (with a -1, -2, etc. suffix)
	 */
	public static function wp_unique_post_slug($slug) {
		global $wpdb;
		$slug = sanitize_title($slug);
		
		// Post slugs must be unique across all posts.
		$check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s LIMIT 1";
		$post_name_check = $wpdb->get_var( $wpdb->prepare($check_sql, $slug));

		if($post_name_check){
			$suffix = 2;
			do {
				$alt_post_name = _truncate_post_slug( $slug, 200 - ( strlen( $suffix ) + 1 ) ) . "-$suffix";
				$post_name_check = $wpdb->get_var( $wpdb->prepare($check_sql, $alt_post_name));
				$suffix++;
			} while ( $post_name_check );
			$slug = $alt_post_name;
		}
		return $slug;
	}

	public static function get_last_error(){
		return self::$last_error;
	}
	public static function get_processed_total(){
		return self::$processed_total;
	}
	public static function get_non_processed_total(){
		return self::$non_processed_total;
	}
	public static function get_processing_time(){
		return self::$processing_time;
	}
	public static function get_total(){
		return self::$total;
	}
}

//Add JsonImport shortcode in case we want to trigger this via shortcode
function FBJsonImporter_shortcode($atts) {
	//$json = new JsonImporter();
	$datafile = isset($atts['datafile'])?$atts['datafile']:'';
	if(empty($datafile)) return;
	FBJsonImporter::import_json($datafile);
	if(FBJsonImporter::get_last_error()!=null) echo "<br/>Error:" . FBJsonImporter::get_last_error();
	else {
		echo "<br/>Uploaded " . FBJsonImporter::get_processed_total() . "/" . FBJsonImporter::get_total() . 
						" in " . FBJsonImporter::get_processing_time() . " (s).";
	}
}
add_shortcode( 'FBJsonImporter', 'FBJsonImporter_shortcode' );

//Register an setting menu 
if(is_admin()){
	require_once(FBJSONIMPORTER_PLUGIN_DIR . 'FBJsonImporter.Admin.php');
	add_action('init', array('FBJsonImporter_Admin','init'));
}