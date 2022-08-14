<?php

class Businessup_Customizer_Notify {

	private $recommended_actions;

	
	private $recommended_plugins;

	
	private static $instance;

	
	private $recommended_actions_title;

	
	private $recommended_plugins_title;

	
	private $dismiss_button;

	
	private $install_button_label;

	
	private $activate_button_label;

	
	private $businessup_deactivate_button_label;

	
	public static function init( $config ) {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof businessup_Customizer_Notify ) ) {
			self::$instance = new businessup_Customizer_Notify;
			if ( ! empty( $config ) && is_array( $config ) ) {
				self::$instance->config = $config;
				self::$instance->setup_config();
				self::$instance->setup_actions();
			}
		}

	}

	
	public function setup_config() {

		global $businessup_customizer_notify_recommended_plugins;
		global $businessup_customizer_notify_recommended_actions;

		global $install_button_label;
		global $activate_button_label;
		global $businessup_deactivate_button_label;

		$this->recommended_actions = isset( $this->config['recommended_actions'] ) ? $this->config['recommended_actions'] : array();
		$this->recommended_plugins = isset( $this->config['recommended_plugins'] ) ? $this->config['recommended_plugins'] : array();

		$this->recommended_actions_title = isset( $this->config['recommended_actions_title'] ) ? $this->config['recommended_actions_title'] : '';
		$this->recommended_plugins_title = isset( $this->config['recommended_plugins_title'] ) ? $this->config['recommended_plugins_title'] : '';
		$this->dismiss_button            = isset( $this->config['dismiss_button'] ) ? $this->config['dismiss_button'] : '';

		$businessup_customizer_notify_recommended_plugins = array();
		$businessup_customizer_notify_recommended_actions = array();

		if ( isset( $this->recommended_plugins ) ) {
			$businessup_customizer_notify_recommended_plugins = $this->recommended_plugins;
		}

		if ( isset( $this->recommended_actions ) ) {
			$businessup_customizer_notify_recommended_actions = $this->recommended_actions;
		}

		$install_button_label    = isset( $this->config['install_button_label'] ) ? $this->config['install_button_label'] : '';
		$activate_button_label   = isset( $this->config['activate_button_label'] ) ? $this->config['activate_button_label'] : '';
		$businessup_deactivate_button_label = isset( $this->config['businessup_deactivate_button_label'] ) ? $this->config['businessup_deactivate_button_label'] : '';

	}

	
	public function setup_actions() {

		// Register the section
		add_action( 'customize_register', array( $this, 'businessup_plugin_notification_customize_register' ) );

		// Enqueue scripts and styles
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'businessup_customizer_notify_scripts_for_customizer' ), 0 );

		/* ajax callback for dismissable recommended actions */
		add_action( 'wp_ajax_quality_customizer_notify_dismiss_action', array( $this, 'businessup_customizer_notify_dismiss_recommended_action_callback' ) );

		add_action( 'wp_ajax_ti_customizer_notify_dismiss_recommended_plugins', array( $this, 'businessup_customizer_notify_dismiss_recommended_plugins_callback' ) );

	}

	
	public function businessup_customizer_notify_scripts_for_customizer() {

		wp_enqueue_style( 'businessup-customizer-notify-css', get_template_directory_uri() . '/inc/ansar/customizer-notice/css/businessup-customizer-notify.css', array());
		wp_style_add_data( 'businessup-customizer-notify-css', 'rtl', 'replace' );

		wp_enqueue_style( 'businessup-plugin-install' );
		wp_enqueue_script( 'businessup-plugin-install' );
		wp_add_inline_script( 'plugin-install', 'var pagenow = "customizer";' );

		wp_enqueue_script( 'businessup-updates' );

		wp_enqueue_script( 'businessup-customizer-notify-js', get_template_directory_uri() . '/inc/ansar/customizer-notice/js/businessup-customizer-notify.js', array( 'customize-controls' ));
		wp_localize_script(
			'businessup-customizer-notify-js', 'businessupCustomizercompanionObject', array(
				'businessup_ajaxurl'   => esc_url(admin_url( 'admin-ajax.php' )),
				'template_directory' => esc_url(get_template_directory_uri()),
				'base_path'          => esc_url(admin_url()),
				'activating_string'  => __( 'Activating', 'businessup' ),
			)
		);

	}

	
	public function businessup_plugin_notification_customize_register( $wp_customize ) {

		
		require_once get_template_directory() . '/inc/ansar/customizer-notice/businessup-customizer-notify-section.php';

		$wp_customize->register_section_type( 'Businessup_Customizer_Notify_Section' );

		$wp_customize->add_section(
			new businessup_Customizer_Notify_Section(
				$wp_customize,
				'businessup-customizer-notify-section',
				array(
					'title'          => $this->recommended_actions_title,
					'plugin_text'    => $this->recommended_plugins_title,
					'dismiss_button' => $this->dismiss_button,
					'priority'       => 0,
				)
			)
		);

	}


		public function businessup_customizer_notify_dismiss_recommended_action_callback() {

		global $businessup_customizer_notify_recommended_actions;

		$action_id = ( isset( $_GET['id'] ) ) ? $_GET['id'] : 0;

		echo esc_html($action_id); 

		if ( ! empty( $action_id ) ) {

			
			if ( get_option( 'businessup_customizer_notify_show' ) ) {

				$businessup_customizer_notify_show_recommended_actions = get_option( 'businessup_customizer_notify_show' );
				switch ( $_GET['todo'] ) {
					case 'add':
						$businessup_customizer_notify_show_recommended_actions[ $action_id ] = true;
						break;
					case 'dismiss':
						$businessup_customizer_notify_show_recommended_actions[ $action_id ] = false;
						break;
				}
				update_option( 'businessup_customizer_notify_show', $businessup_customizer_notify_show_recommended_actions );
				
			} else {
				$businessup_customizer_notify_show_recommended_actions = array();
				if ( ! empty( $businessup_customizer_notify_recommended_actions ) ) {
					foreach ( $businessup_customizer_notify_recommended_actions as $businessup_lite_customizer_notify_recommended_action ) {
						if ( $businessup_lite_customizer_notify_recommended_action['id'] == $action_id ) {
							$businessup_customizer_notify_show_recommended_actions[ $businessup_lite_customizer_notify_recommended_action['id'] ] = false;
						} else {
							$businessup_customizer_notify_show_recommended_actions[ $businessup_lite_customizer_notify_recommended_action['id'] ] = true;
						}
					}
					echo esc_html($businessup_customizer_notify_show_recommended_actions);
				}
			}
		}
		die(); 
	}

	
	public function businessup_customizer_notify_dismiss_recommended_plugins_callback() {

		$action_id = ( isset( $_GET['id'] ) ) ? $_GET['id'] : 0;

		echo esc_html($action_id); 

		if ( ! empty( $action_id ) ) {

			$businessup_lite_customizer_notify_show_recommended_plugins = get_option( 'businessup_customizer_notify_show_recommended_plugins' );

			switch ( $_GET['todo'] ) {
				case 'add':
					$businessup_lite_customizer_notify_show_recommended_plugins[ $action_id ] = false;
					break;
				case 'dismiss':
					$businessup_lite_customizer_notify_show_recommended_plugins[ $action_id ] = true;
					break;
			}
			update_option( 'businessup_customizer_notify_show_recommended_plugins', $businessup_lite_customizer_notify_show_recommended_plugins );
		}
		die(); 
	}

}