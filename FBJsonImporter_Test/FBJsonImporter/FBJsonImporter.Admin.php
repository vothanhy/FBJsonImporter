<?php
class FBJsonImporter_Admin {

	public static function init() {
		add_action('admin_menu', array('FBJsonImporter_Admin', 'load_menu' ));
		
		if($_POST) {
			update_option( 'fb_post_default_status', $_POST['default_status']);
			self::process_upload_file();
		}
	}
	
	public static function load_menu() {
		add_options_page('FB Json Importer', 'FB Json Importer', 'manage_options', 'upload-form', array( 'FBJsonImporter_Admin', 'display_upload_form'));
	}
	
	public static function display_upload_form() {
		//include FBJSONIMPORTER_PLUGIN_DIR . "views/upload_form.php";
		if(get_option('fb_post_default_status')===false)
			add_option( 'fb_post_default_status', 'draft', '', 'yes' );
		
		self::load_view("upload_form",array(
						'error_msg'=>FBJsonImporter::get_last_error(),
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
	
			$files = array(
				'Test Import 25' => FBJSONIMPORTER_PLUGIN_DIR . 'testdata/facebook-data.json',
				'Test Import 100' => FBJSONIMPORTER_PLUGIN_DIR . 'testdata/json100.json',
				'Test Import 1000' => FBJSONIMPORTER_PLUGIN_DIR . 'testdata/json1000.json',
				'Test Import 10000' => FBJSONIMPORTER_PLUGIN_DIR . 'testdata/json10000.json',
				'Test Import 20000' => FBJSONIMPORTER_PLUGIN_DIR . 'testdata/json20000.json',
				'Test Import 50000' => FBJSONIMPORTER_PLUGIN_DIR . 'testdata/json50000.json',
				'Test Import 100000' => FBJSONIMPORTER_PLUGIN_DIR . 'testdata/json100000.json',
			); 
			$fileName = $files[$_POST['jsonimporter']];
			echo $fileName;
			//$fileName = $_FILES["jsondata"]['tmp_name'];
			FBJsonImporter::import_json($fileName);
		}
	}
}
