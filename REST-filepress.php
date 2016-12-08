<?php
/**
 * Plugin Name: REST FilePress Server
 * Description: Demo for serving files via the WordPress REST API
 * Author: Sven Hinse
 * Author URI:
 * Version: 0.1
 *
 */

namespace REST_filepress;



/**
 * Class plugin
 * The plugin init class
 *
 *
 * @package REST_filepress
 */
class Plugin {

	const REST_URL = 'REST-filepress/v1';
	public static $plugin_dir = "";

	/**
	 *set your files directory in plugin folder here in format: '/<Directory>/
	 */
	const FILES_DIR = "/files/";

	public function run( $plugin_dir ) {

		//save plugin root directory
		Plugin::$plugin_dir = $plugin_dir;

		//add custom post type for file management
		add_action( 'init', array( $this, 'create_filepress_cpt' ) );
		//set CORS headers for REST Requests
		add_action( 'rest_api_init', array( $this, 'set_cors_filter' ) );

	}

	/**
	 * sets the cors headers for cross-domain-requests
	 * @param $value
	 *
	 * @return mixed
	 */
	public function set_CORS_headers( $value ) {

		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: POST, GET, PUT, OPTIONS, DELETE' );
		header( 'Access-Control-Allow-Credentials: true' );
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization' );

		return $value;
	}

	/**
	 * Register a File post type, with REST API support
	 *
	 * Based on example at: http://codex.wordpress.org/Function_Reference/register_post_type
	 */

	function create_filepress_cpt() {

		//include the Controller class we want to use for the cpt
		include_once( 'Lib/CustomPostController.php' );
		$labels = array(
			'name'               => _x( 'FilePress Files', 'post type general name', 'your-plugin-textdomain' ),
			'singular_name'      => _x( 'File', 'post type singular name', 'your-plugin-textdomain' ),
			'menu_name'          => _x( 'FilePress Files', 'admin menu', 'your-plugin-textdomain' ),
			'name_admin_bar'     => _x( 'File', 'add new on admin bar', 'your-plugin-textdomain' ),
			'add_new'            => _x( 'Add New', 'File', 'your-plugin-textdomain' ),
			'add_new_item'       => __( 'Add New File', 'your-plugin-textdomain' ),
			'new_item'           => __( 'New File', 'your-plugin-textdomain' ),
			'edit_item'          => __( 'Edit File', 'your-plugin-textdomain' ),
			'view_item'          => __( 'View File', 'your-plugin-textdomain' ),
			'all_items'          => __( 'All Files', 'your-plugin-textdomain' ),
			'search_items'       => __( 'Search Files', 'your-plugin-textdomain' ),
			'parent_item_colon'  => __( 'Parent Files:', 'your-plugin-textdomain' ),
			'not_found'          => __( 'No Files found.', 'your-plugin-textdomain' ),
			'not_found_in_trash' => __( 'No Files found in Trash.', 'your-plugin-textdomain' )
		);

		$args = array(
			'labels'                => $labels,
			'description'           => __( 'Description.', 'your-plugin-textdomain' ),
			'public'                => TRUE,
			'publicly_queryable'    => TRUE,
			'show_ui'               => TRUE,
			'show_in_menu'          => TRUE,
			'query_var'             => TRUE,
			'rewrite'               => array( 'slug' => 'filepress-file' ),
			'capability_type'       => 'post',
			'has_archive'           => TRUE,
			'hierarchical'          => FALSE,
			'menu_position'         => NULL,
			'show_in_rest'          => TRUE,
			'rest_base'             => 'filepress-files/v1',
			'rest_controller_class' => __NAMESPACE__ . '\Lib\CustomPostController',
			'supports'              => array( 'title', 'editor', 'author', 'thumbnail' )
		);

		register_post_type( 'filepress_file', $args );
	}

	/**
	 *adds a filter at 'rest_api_init' that adds the function to set CORS headers to allow Cross-Domain-Requests
	 */
	public function set_cors_filter() {

		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
		add_filter( 'rest_pre_serve_request', array( $this, 'set_CORS_headers' ) );
	}
}

$plugin_instance = new Plugin();
$plugin_instance->run( __DIR__ );
