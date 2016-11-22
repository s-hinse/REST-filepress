<?php
/**
 *Controller class for file items
 * extends REST-APIs class-wp-post-types-controller
 */
namespace REST_filepress\Lib;

use REST_filepress\Plugin;

/**
 * Class CustomPostController
 *
 * @package REST_filepress\Lib
 */
class CustomPostController extends \WP_REST_Posts_Controller {

	/**
	 * CustomPostController constructor. Calls the parent constructor.
	 *
	 * @param $post_type
	 */
	public function __construct( $post_type ) {

		parent::__construct( $post_type );

	}

	/**
	 * Register the routes for the objects of the controller. Overrides function in parent class-
	 * add an extra route for download
	 */
	public function register_routes() {

		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::CREATABLE ),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) ),
				),
			),
			array(
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
				'args'                => $this->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE ),
			),
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				'args'                => array(
					'force' => array(
						'default'     => FALSE,
						'description' => __( 'Whether to bypass trash and force deletion.' ),
					),
				),
			),

			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		//the extra-route for download
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/download', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_file' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			)
		) );
		//the route for credentials check
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/is-logged-in', array(
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'check_if_logged_in' ),
						)
		) );
	}

	/**
	 * Saves a file and creates a corresponding CPT.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return int|\WP_Error
	 */
	public function create_item( $request ) {

		//$parent_response = parent::create_item( $request );

		$file_data = $request->get_file_params();

		$key      = key( $file_data );
		$filename = $file_data[ $key ][ 'name' ];
		$size     = $file_data[ $key ][ 'size' ];
		$size     = (int) $size / 1024;
		$size     = round( $size, 0 );
		$size .= ' KB';
		$source = $file_data[ $key ][ 'tmp_name' ];
		$target = Plugin::$plugin_dir . Plugin::FILES_DIR . $filename;

		$copy_success = copy( $source, $target );
		if ( ! $copy_success ) {
			//if the file could not be saved, we return an error message
			return new \WP_Error( 'rest_could_not_save_file', __( 'Could not save file.' ), array( 'status' => 500 ) );
		}

		//create a new filepress-post with the file data
		// Gather post data.
		$new_post = array(
			'post_title'   => $filename,
			'post_content' => $size,
			'post_status'  => 'publish',
			'post_type'    => 'filepress_file'

		);
		$response = wp_insert_post( $new_post );

		return $response;

	}

	/**
	 * Overrides the get_item function in parent class.
	 * Instead of returning the item, we read the file name in its content, create a transient and return the file name and the transient's value. The transient is alive for
	 * 10sec. In this time the client has to fetch the file via the "download" endpoint, which checks the transient.
	 *
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function get_item( $request ) {

		//call parent class function to check permissions and get the requested entry
		$parent_response = parent::get_item( $request );
		//return $parent_response;
		//if error return the error and quit
		if ( is_wp_error( $parent_response ) ) {
			return $parent_response;
		}
		$data = $parent_response->get_data();

		//get name of the file to be served
		if ( isset( $data[ 'title' ][ 'raw' ] ) ) {
			$filename = $data[ 'title' ][ 'raw' ];
		} else {
			//if wee can't access the value, we are probably not logged in.
			return new \WP_Error( 'rest_cannot_show', __( 'Sorry, you are not allowed to view this resource.' ), array( 'status' => rest_authorization_required_code() ) );
		}
		//create a random number as access token
		$salt = mt_rand( 0, 100000 );

		//add the salt to transient name to make it unique
		$transient_name = 'filePress' . $salt;
		//set a transient which is checked at file delivery
		set_transient( $transient_name, $salt, 10 );

		$response = new \WP_REST_Response();
		$response->set_data( array( 'file' => $filename, 'salt' => $salt ) );

		return $response;

	}

	/**
	 * Deletes a file and its corresponding post in the database.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error|\WP_REST_Response
	 */
	public function delete_item( $request ) {

		//before deleting the post, we have to retrieve the filename and delete the file.
		$id       = (int) $request[ 'id' ];
		$filename = get_the_title( $id );

		if ( file_exists( Plugin::$plugin_dir . Plugin::FILES_DIR . $filename ) ) {
			unlink( Plugin::$plugin_dir . Plugin::FILES_DIR . $filename );
		} else {
			//if the file could not be found, we return an error message

			return new \WP_Error( 'rest_could_not_delete_file', __( 'Could not delete file.' ), array( 'status' => 500 ) );
		}

		//now we can delete the post

		return parent::delete_item( $request );
	}

	/**
	 * Takes the request for file download, checks if the transient is set and delivers the file.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_Error
	 */
	public function get_file( \WP_REST_Request $request ) {

		$file_data      = $request->get_query_params();
		$filename       = sanitize_file_name( $file_data[ 'file' ] );
		$salt           = sanitize_key( $file_data[ 'salt' ] );
		$transient_name = 'filePress' . $salt;
		if ( get_transient( $transient_name ) == $salt ) {
			$success = $this->deliver_file( $filename );
			if ( ! $success ) {
				return new \WP_Error( 'rest_file_error', __( 'Sorry, this file does not seem to exist on the server.' ), array( 'status' => 500 ) );

			}
		} else {
			return new \WP_Error( 'rest_cannot_show', __( 'Sorry, you are not allowed to view this resource.' ), array( 'status' => rest_authorization_required_code() ) );

		}
	}

	/**
	 * Delivers a file in the plugin's file directory for browser download
	 *
	 * @param $filename
	 */
	private function deliver_file( $filename ) {

		$path = Plugin::$plugin_dir . Plugin::FILES_DIR . $filename;
		if ( file_exists( $path ) ) {
			header( 'Content-Type: application/force-download' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Length: ' . filesize( $path ) );
			header( 'Content-Disposition: attachment; filename=' . $filename );
			$success = readfile( $path );
			if ( $success ) {
				die;
			}
		} else {
			return FALSE;
		}

	}

	/**
	 * checks if the the user is logged in
	 *
	 * @return array|\WP_Error returns HTTP 200 if logged in, HTTP 401, if not
	 */
	public function check_if_logged_in(){
		$is_logged_in = is_user_logged_in();
		if (!$is_logged_in){
			return new \WP_Error( 'rest_wrong_credentials', __( 'You are not logged in.' ), array( 'status' => rest_authorization_required_code() ) );
		}
		else {
			return array ('message'=>'You are logged in','data'=>array('status'=>200));
		}

	}

}