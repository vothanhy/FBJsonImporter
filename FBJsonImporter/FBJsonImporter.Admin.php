<?php
/*
Backend Class to handle options
*/
class FBJsonImporter_Admin {
	private static $error_msg = null;
	
	public static function init() {
		add_action('admin_menu', array('FBJsonImporter_Admin', 'load_menu' ));
		
		if($_POST) {
			update_option( 'fb_post_default_status', $_POST['default_status']);
			self::process_upload_file();
		}
	}
	
	/* Register FB Json Importer under Settings */
	public static function load_menu() {
		add_options_page('FB Json Importer', 'FB Json Importer', 'manage_options', 'upload-form', array( 'FBJsonImporter_Admin', 'display_upload_form'));
	}
	
	/* Display options form */
	public static function display_upload_form() {
		//include FBJSONIMPORTER_PLUGIN_DIR . "views/upload_form.php";
		if(get_option('fb_post_default_status')===false)
			add_option( 'fb_post_default_status', 'draft', '', 'yes' );
		
		$error_msg = self::$error_msg;
		if(!isset($error_msg)) $error_msg = FBJsonImporter::get_last_error();
		self::load_view("upload_form",array(
						'error_msg'=>$error_msg,
						'processed_total'=>FBJsonImporter::get_processed_total(),
						'non_processed_total'=>FBJsonImporter::get_non_processed_total(),
						'processing_time'=>FBJsonImporter::get_processing_time(),
						'total'=>FBJsonImporter::get_total()
						));
	}
	
	/* 
	Load partial view from 'views' folder
	*/
	public static function load_view($view_name, array $args = array()){
		foreach($args AS $key => $val) {
			$$key = $val;
		}
		$view_file = FBJSONIMPORTER_PLUGIN_DIR . 'views/'. $view_name . '.php';
		include($view_file);
	}
	
	public static function process_upload_file() {
		if($_POST['action']=='upload-file') {
			if($_FILES["jsondata"]['error']!=0) {
				self::$error_msg = "Can not upload file. Please check file size. (Max upload size: " . ini_get("upload_max_filesize") . ")";
			} else {
				$fileName = $_FILES["jsondata"]['tmp_name'];
				FBJsonImporter::import_json($fileName);
			}
		}
	}
}
