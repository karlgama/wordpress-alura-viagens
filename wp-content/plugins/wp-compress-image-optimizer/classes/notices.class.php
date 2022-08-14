<?php


/**
 * Class - Notices
 */
class wps_ic_notices extends wps_ic {

	public static $slug;
	public static $options;
	public $templates;

	public function __construct() {

		$this::$slug     = parent::$slug;
		$this->templates = new wps_ic_templates();

		// Connect to API Notice
		$this->connect_api_notice();
	}


	public function connect_api_notice() {
		if ( get_option( 'wps_ic_allow_local' ) == 'true' ) {
			add_action( 'admin_notices', array( $this, 'activate_list_mode' ) );
		}

		if ( empty( parent::$response_key ) ) {
			add_action( 'all_admin_notices', array( $this, 'connect_api_message' ) );
		}

	}


	/**
	 * Render List Media Library Notice
	 */
	public function activate_list_mode() {
		$this->templates->get_notice( 'activate_list_mode' );
	}

	/**
	 * Render Api Connect Notice
	 */
	public function connect_api_message() {

		$screen = get_current_screen();
		if ( $screen->id == 'upload' || $screen->id == 'plugins' ) {
			$this->templates->get_notice( 'connect_api_message' );
		}
	}


}