<?php
/*
 * Plugin name: WP Compress | Image Optimizer
 * Plugin URI: https://www.wpcompress.com
 * Author: WP Compress
 * Author URI: https://www.wpcompress.com
 * Version: 5.11.10
 * Description: Automatically compress and optimize images to shrink image file size, improve  times and boost SEO ranks - all without lifting a finger after setup.
 * Text Domain: wp-compress
 * Domain Path: /langs
 */

global $ic_running;
include 'debug.php';
include 'defines.php';
include 'upgrader.php';
include 'addons/cdn/cdn-rewrite.php';
include 'addons/legacy/compress.php';
include 'addons/edge/edge.php';
include 'addons/simple/simple.php';
//CUSTOM_INCLUDE_HERE

spl_autoload_register( function( $class_name ) {
	$class_name = str_replace( 'wps_ic_', '', $class_name );
	$class_name = $class_name . '.class.php';
	if ( file_exists( WPS_IC_DIR . 'classes/' . $class_name ) ) {
		include 'classes/' . $class_name;
	}
} );


class wps_ic {

	public static $slug;
	public static $version;

	public static $api_key;
	public static $response_key;

	public static $settings;
	public static $zone_name;
	public static $quality;
	public static $options;

	public static $js_debug;
	public static $debug;
	public static $local;

	private static $accountStatus;
	public static $media_lib_ajax;

	public $local_compress;
	public $notices;
	public $enqueues;
	public $templates;
	public $menu;
	public $ajax;
	public $media_library;
	public $compress;
	public $controller;
	public $log;
	public $bulk;
	public $queue;
	public $stats;
	public $cdn;
	public $mu;
	public $mainwp;

	/** @var curl */
	public $curl;


	/**
	 * Our main class constructor
	 */
	public function __construct() {
		global $wps_ic;
		self::debug_log( 'Constructor' );

		// Basic plugin info
		self::$slug    = 'wpcompress';
		self::$version = '5.11.10';

		//CUSTOM_CONSTRUCT_HERE

		if ( ! empty( $_GET['dbg_builder'] ) ) {
			$fp = fopen( WPS_IC_DIR . 'debug-builder.txt', 'a+' );
			fwrite( $fp, 'get: ' . print_r( $_GET, true ) . "\r\n" );
			fwrite( $fp, 'post: ' . print_r( $_POST, true ) . "\r\n" );
			fclose( $fp );
		}

		if ( ! empty( $_GET['ignore_ic'] ) ) {
			return;
		}

		if ( self::dontRunif() ) {
			return;
		}

		if ( ! empty( $_GET['dbg_init'] ) ) {
			var_dump( $_GET );
			var_dump( $_SERVER );
			die();
		}

		/**
		 * Force Show WP Compress
		 */
		if ( ! empty( $_GET['unhide'] ) ) {
			$settings                  = get_option( WPS_IC_SETTINGS );
			$settings['hide_compress'] = '0';
			update_option( WPS_IC_SETTINGS, $settings );
		}

		if ( ( ! empty( $_GET['wps_ic_action'] ) || ! empty( $_GET['run_restore'] ) || ! empty( $_GET['run_compress'] ) ) && ! empty( $_GET['apikey'] ) ) {
			$options = get_option( WPS_IC_OPTIONS );
			$apikey  = sanitize_text_field( $_GET['apikey'] );
			if ( $apikey !== $options['api_key'] ) {
				die( 'Hacking?' );
			}
		}

		$this::$js_debug = get_option( 'wps_ic_js_debug' );
		$this::$settings = get_option( WPS_IC_SETTINGS );
		$this::$options  = get_option( WPS_IC_OPTIONS );

		/**
		 * Figure out ZoneName
		 */
		if ( empty( $this::$settings['cname'] ) || ! $this::$settings['cname'] ) {
			$this::$zone_name = get_option( 'ic_cdn_zone_name' );
		} else {
			$custom_cname     = get_option( 'ic_custom_cname' );
			$this::$zone_name = $custom_cname;
		}

		/**
		 * Figure out Quality
		 */
		if ( empty( $this::$settings['optimization'] ) || $this::$settings['optimization'] == '' || $this::$settings['optimization'] == '0' ) {
			$this::$quality = 'intelligent';
		} else {
			$this::$quality = $this::$settings['optimization'];
		}

		if ( empty( $this::$options['css_hash'] ) ) {
			$this::$options['css_hash'] = 5021;
		}

		if ( ! empty( $_GET['radnom_css_hash'] ) ) {
			define( 'WPS_IC_HASH', substr( md5( microtime( true ) ), 0, 6 ) );
		} else {
			if ( ! defined( 'WPS_IC_HASH' ) ) {
				define( 'WPS_IC_HASH', $this::$options['css_hash'] );
			}
		}

		if ( empty( $this::$settings['replace-method'] ) ) {
			$this::$settings['replace-method'] = 'regexp';
			update_option( WPS_IC_SETTINGS, $this::$settings );
		}

		define( 'WPS_IC_REPLACE', $this::$settings['replace-method'] ); // regexp or dom

		// Fallback
		if ( empty( $this::$settings['optimization'] ) ) {
			$this::$settings['optimization'] = 'intelligent';
			update_option( WPS_IC_SETTINGS, $this::$settings );
		}

		// Plugin Settings
		self::$api_key      = $this::$options['api_key'];
		self::$response_key = $this::$options['response_key'];

		$this->upgrader = new wpc_upgrader();
		$this->mainwp   = new wps_ic_mainwp();

		if ( is_admin() ) {

      if (!empty($_GET['edge'])) {
	      $this->edge = new wpc_edge();
	      $this->edge->scanThemeCSS();
	      $this->edge->scanThemeJS();
	      $this->edge->scanPluginCSS();
	      $this->edge->scanPluginJS();
	      $this->edge->getFoundItems();
	      die();
      }

			// Run Multisite
			if ( is_multisite() ) {
				$this->mu = new wps_ic_mu();
			}

			if ( ! $this::$settings ) {
				$options = new wps_ic_options();
				$options->set_missing_options();
			}

			// Deactivate Notification
			add_action( 'admin_footer', array( 'wps_ic', 'deactivate_script' ) );

			self::$local     = new wps_local_compress();
			$this->ajax      = new wps_ic_ajax();
			$this->menu      = new wps_ic_menu();
			$this->enqueues  = new wps_ic_enqueues();
			$this->log       = new wps_ic_log();
			$this->templates = new wps_ic_templates();
			$this->notices   = new wps_ic_notices();

			// Ajax
			if ( empty( $this::$settings['live-cdn'] ) || $this::$settings['live-cdn'] == '0' ) {
				$this->queue           = new wps_ic_queue();
				$this->compress        = new wps_ic_compress();
				$this->controller      = new wps_ic_controller();
				$this->remote_restore  = new wps_ic_remote_restore();
				$this->comms           = new wps_ic_comms();
				$this::$media_lib_ajax = $this->media_library = new wps_ic_media_library_live();
				$this->mu              = new wps_ic_mu();
			} else {
				if ( ! empty( self::$response_key ) ) {

					$this->media_library = new wps_ic_media_library_live();
					$this->stats         = new wps_ic_stats();
					$this->comms         = new wps_ic_comms();
				}
			}

			if ( ! empty( $_GET['reset_compress'] ) ) {
				$this->reset_local_compress();
				die( 'Reset Done' );
			}

			if ( ! empty( $_GET['ic_stats'] ) ) {
				$this->stats->fetch_live_stats();
				die();
			}

			$this->ajax = new wps_ic_ajax();

			// Change PHP Limits
			$wps_ic = $this;

			if ( empty( $this::$settings['live-cdn'] ) || $this::$settings['live-cdn'] == '0' ) {

				// Is it some remote call?
				if ( ! empty( $_GET['apikey'] ) ) {

					if ( self::$api_key !== sanitize_text_field( $_GET['apikey'] ) ) {
						die( 'Bad Call' );
					}

				}

				if ( is_admin() ) {

					if ( ! empty( $_GET['deauth'] ) ) {
						$this->ajax->wps_ic_deauthorize_api();
						wp_safe_redirect( admin_url( 'admin.php?page=' . $wps_ic::$slug . '' ) );
						die();
					}

				}
			}

		} else {

			$this->integration_wp_rocket();
			$this->integration_autoptimize();

			if ( ! in_array( $_SERVER['PHP_SELF'], array( '/wp-login.php', '/wp-register.php' ) ) ) {
				$this->ajax = new wps_ic_ajax();
				$this->menu = new wps_ic_menu();

				if ( empty( $this::$settings['live-cdn'] ) || $this::$settings['live-cdn'] == '0' ) {
					/**
					 * Live Not Active
					 */

					$this->cdn = new wps_cdn_rewrite();
					add_action( 'template_redirect', array( $this->cdn, 'buffer_local_go' ) );
					$this->enqueues = new wps_ic_enqueues();
					$this->comms    = new wps_ic_comms();
				} else {
					/***
					 * Live Active
					 */
					if ( ! empty( self::$response_key ) ) {
						#$this->simple_cdn = new wpc_simple_cdn();
						#add_action( 'template_redirect', array( $this->simple_cdn, 'templateRedirect' ) );

						$this->cdn = new wps_cdn_rewrite();
						add_action( 'template_redirect', array( $this->cdn, 'buffer_callback_v3' ) );
						$this->enqueues = new wps_ic_enqueues();
						$this->comms    = new wps_ic_comms();

					}

				}
			}
		}

		$this->integration_jet_smart_filters();

	}


	public static function isPageBuilderFE() {

		if ( ! empty( $_GET['dbg_bb'] ) ) {
			var_dump( bt_bb_active_for_post_type_fe() );
			var_dump( $_GET );
			var_dump( $_GET['preview'] );
			var_dump( $_GET['bt_bb_fe_preview'] );
			var_dump( is_user_logged_in() );
			var_dump( is_admin() );
			die();
		}

		if ( class_exists( 'BT_BB_Root' ) ) {
			if ( is_user_logged_in() && ! is_admin() ) {
				return true;
			}
		}

		return false;
	}


	public static function isPageBuilder() {
		$page_builders = array(
			'run_compress', //wpc
			'run_restore', //wpc
			'elementor-preview', //elementor
			'fl_builder', //beaver builder
			'et_fb', //divi
			'preview', //WP Preview
			'builder', //builder
			'brizy', //brizy
			'fb-edit', //avada
			'bricks', //bricks
			'ct_template', //ct_template
			'ct_builder', //ct_builder
			'tve', //tve
			'tatsu', //tatsu
			'trp-edit-translation', //thrive
			'brizy-edit-iframe', //brizy
			'ct_builder', //oxygen
			'livecomposer_editor', //livecomposer
			'tatsu', //tatsu
			'tatsu-header', //tatsu-header
			'tatsu-footer', //tatsu-footer
			'tve' //thrive
		);

		foreach ( $page_builders as $page_builder ) {
			if ( isset( $_GET[ $page_builder ] ) ) {
				return true;
			}
		}

		return false;
	}


	public function integration_jet_smart_filters() {
		if ( class_exists( 'Jet_Smart_Filters' ) ) {
			$cdn = new wps_cdn_rewrite();
			add_filter( 'jet-smart-filters/render/ajax/data', array( $cdn, 'jetsmart_ajax_rewrite' ), PHP_INT_MAX, 1 );
		}
	}


	public function rewrite_ajax( $args ) {
		$cdn             = new wps_cdn_rewrite();
		$args['content'] = $cdn->cdn_rewriter( $args['content'] );

		return $args;
	}


	public function integration_autoptimize() {
		if ( function_exists( 'autoptimize' ) ) {
			add_filter( 'autoptimize_filter_get_config', array( $this, 'exclude_from_autoptimize' ), 999, 1 );
		}
	}

	public function integration_wp_rocket() {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			add_filter( 'rocket_exclude_defer_js', array( $this, 'exclude_wpc' ), 999, 1 );
			add_filter( 'rocket_delay_js_exclusions', array( $this, 'exclude_wpc' ), 999, 1 );
			add_filter( 'rocket_exclude_js', array( $this, 'exclude_wpc' ), 999, 1 );
		}
	}


	public function exclude_from_autoptimize( $config ) {
		$config['autoptimize_js_exclude'] = array_merge( $config['autoptimize_js_exclude'], array( 'plugins/wp-compress-image-optimizer' ) );

		return $config;
	}

	public function exclude_wpc( $excluded ) {
		$excluded = array_merge( $excluded, array( '/wp-content/plugins/wp-compress-image-optimizer/assets/js/(.*).js', 'jquery' ) );

		return $excluded;
	}

	public function geoLocateAjax() {

		if ( ! is_multisite() ) {
			$siteurl = site_url();
		} else {
			$siteurl = network_site_url();
		}

		$call = wp_remote_get( 'https://cdn.zapwp.net/?action=geo_locate&domain=' . urlencode( $siteurl ), array( 'timeout' => 30, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0' ) );

		if ( wp_remote_retrieve_response_code( $call ) == 200 ) {
			$body = wp_remote_retrieve_body( $call );
			$body = json_decode( $body );

			if ( $body->success ) {
				update_option( 'wps_ic_geo_locate', $body->data );
			} else {
				update_option( 'wps_ic_geo_locate', array( 'country' => 'EU', 'server' => 'frankfurt.zapwp.net' ) );
			}
		} else {
			update_option( 'wps_ic_geo_locate', array( 'country' => 'EU', 'server' => 'frankfurt.zapwp.net' ) );
		}

		wp_send_json_success( $body->data );
	}


	public function geoLocate() {
		$call = wp_remote_get( 'https://cdn.zapwp.net/?action=geo_locate&domain=' . urlencode( site_url() ), array( 'timeout' => 30, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0' ) );

		if ( wp_remote_retrieve_response_code( $call ) == 200 ) {
			$body = wp_remote_retrieve_body( $call );
			$body = json_decode( $body );

			if ( $body->success ) {
				update_option( 'wps_ic_geo_locate', $body->data );
			} else {
				update_option( 'wps_ic_geo_locate', array( 'country' => 'EU', 'server' => 'frankfurt.zapwp.net' ) );
			}
		} else {
			update_option( 'wps_ic_geo_locate', array( 'country' => 'EU', 'server' => 'frankfurt.zapwp.net' ) );
		}
	}


	public static function dontRunif() {
		if ( self::isPageBuilder() ) {
			return true;
		}

		if ( self::isPageBuilderFE() ) {
			return true;
		}

		// Fix for Feedzy RSS Feed
		if ( ! empty( $_POST['action'] ) && ( $_POST['action'] == 'feedzy' || $_POST['action'] == 'action' || $_POST['action'] == 'elementor' ) ) {
			return true;
		}

		if ( ! empty( $_GET['wps_ic_action'] ) ) {
			return true;
		}

		if ( strpos( $_SERVER['REQUEST_URI'], 'xmlrpc' ) !== false || strpos( $_SERVER['REQUEST_URI'], 'wp-json' ) !== false ) {
			return true;
		}

		if ( ! empty( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) {
			return true;
		}

		if ( ! empty( $_GET['tatsu'] ) || ! empty( $_GET['tatsu-header'] ) || ! empty( $_GET['tatsu-footer'] ) ) {
			return true;
		}

		if ( ( ! empty( $_GET['page'] ) && $_GET['page'] == 'livecomposer_editor' ) ) {
			return true;
		}

		if ( ! empty( $_GET['PageSpeed'] ) ) {
			return true;
		}

		return false;
	}


	/***
	 * Get file size from WP filesystem
	 *
	 * @param $imageID
	 *
	 * @return string
	 */
	public static function get_wp_filesize( $imageID ) {
		$filepath = get_attached_file( $imageID );
		$filesize = filesize( $filepath );
		$filesize = wps_ic_format_bytes( $filesize, null, null, false );

		return $filesize;
	}


	public static function getAccountStatusMemory( $force = false ) {
		if ( ! empty( $_GET['refresh'] ) || $force ) {
			delete_transient( 'wps_ic_account_status' );
		}

		$transient_data = get_transient( 'wps_ic_account_status' );
		if ( ! $transient_data || empty( $transient_data ) ) {
			self::debug_log( 'Not In Memory' );
			self::$accountStatus = self::check_account_status();

			return self::$accountStatus;
		} else {
			self::debug_log( 'In Memory' );
			self::debug_log( print_r( $transient_data, true ) );

			return $transient_data;
		}
	}


	public static function check_account_status( $ignore_transient = false ) {
		self::debug_log( 'Check Account Status' );

		if ( ! empty( $_GET['refresh'] ) || $ignore_transient ) {
			delete_transient( 'wps_ic_account_status' );
		}

		$transient_data = get_transient( 'wps_ic_account_status' );
		if ( ! empty( $transient_data ) && $transient_data !== 'no-site-found' ) {
			self::debug_log( 'Check Account Status - In Transient' );

			return $transient_data;
		}

		self::debug_log( 'Check Account Status - Call API' );

		$options  = get_option( WPS_IC_OPTIONS );
		$settings = get_option( WPS_IC_SETTINGS );

		/**
		 * Site is not connected
		 */
		if ( ! $options || empty( $options['api_key'] ) ) {
			$data                              = array();
			$data['account']['allow_local']    = false;
			$data['account']['allow_live']     = false;
			$data['account']['allow_cname']    = false;
			$data['account']['type']           = 'shared';
			$data['account']['projected_flag'] = 1;

			$data['account'] = (object) $data['account'];

			$data['bytes']['leftover']                = '0';
			$data['bytes']['cdn_bandwidth']           = '0';
			$data['bytes']['cdn_requests']            = '0';
			$data['bytes']['bandwidth_savings']       = '0';
			$data['bytes']['bandwidth_savings_bytes'] = '0';
			$data['bytes']['original_bandwidth']      = '0';
			$data['bytes']['projected']               = '0';
			// Local
			$data['bytes']['local_savings']   = '0';
			$data['bytes']['local_original']  = '0';
			$data['bytes']['local_optimized'] = '0';

			$data['bytes'] = (object) $data['bytes'];

			$data['formatted']['leftover']                = '0 MB';
			$data['formatted']['cdn_bandwidth']           = '0 MB';
			$data['formatted']['cdn_requests']            = '0';
			$data['formatted']['bandwidth_savings']       = '0 MB';
			$data['formatted']['bandwidth_savings_bytes'] = '0 MB';
			$data['formatted']['package_without_extra']   = '0';
			$data['formatted']['original_bandwidth']      = '0 MB';
			$data['formatted']['projected']               = '0 MB';

			// Local
			$data['formatted']['local_savings']   = '0 MB';
			$data['formatted']['local_original']  = '0 MB';
			$data['formatted']['local_optimized'] = '0 MB';

			$data['formatted'] = (object) $data['formatted'];

			$data = (object) $data;

			$body = array( 'success' => true, 'data' => $data );
			$body = (object) $body;

			return $data;
		}

		// Check privileges
		$call = wp_remote_get( WPS_IC_KEYSURL . '?action=get_account_status&version=2&apikey=' . $options['api_key'] . '&range=month&hash=' . md5( mt_rand( 999, 9999 ) ), array( 'timeout' => 10, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0' ) );

		if ( wp_remote_retrieve_response_code( $call ) == 200 ) {
			$body = wp_remote_retrieve_body( $call );
			$body = json_decode( $body );
			$body = $body->data;

			if ( ! empty( $body ) && $body !== 'no-site-found' ) {

				// Vars
				$allow_local    = $body->account->allow_local;
				$allow_live     = $body->account->allow_live;
				$account_status = $body->account->status;
				$quota_type     = $body->account->type;
				$allow_cname    = $body->account->allow_cname;

				// Account Status Transient
				set_transient( 'wps_ic_account_status', $body, 30 );

				if ( ! empty( $allow_cname ) && $allow_cname == 'true' ) {
					set_transient( 'ic_allow_cname', 'true', 15 * 60 );
				}

				// Allow Local
				update_option( 'wps_ic_allow_local', $allow_local );

				// Is account active?
				if ( $account_status == 'deactivated' ) {
					$settings['live-cdn'] = '0'; // TODO: Fix
					update_option( WPS_IC_SETTINGS, $settings );
				}

				return $body;

			} else {
				update_option( 'wps_ic_allow_local', false );

				return false;
			}
		} else {

			$data                              = array();
			$data['account']['allow_local']    = false;
			$data['account']['allow_live']     = false;
			$data['account']['allow_cname']    = false;
			$data['account']['type']           = 'shared';
			$data['account']['projected_flag'] = 1;

			$data['account'] = (object) $data['account'];

			$data['bytes']['leftover']                = '0';
			$data['bytes']['cdn_bandwidth']           = '0';
			$data['bytes']['cdn_requests']            = '0';
			$data['bytes']['bandwidth_savings']       = '0';
			$data['bytes']['bandwidth_savings_bytes'] = '0';
			$data['bytes']['original_bandwidth']      = '0';
			$data['bytes']['projected']               = '0';

			// Local
			$data['bytes']['local_savings']   = '0';
			$data['bytes']['local_original']  = '0';
			$data['bytes']['local_optimized'] = '0';

			$data['bytes'] = (object) $data['bytes'];

			$data['formatted']['leftover']                = '0';
			$data['formatted']['cdn_bandwidth']           = '0';
			$data['formatted']['cdn_requests']            = '0';
			$data['formatted']['bandwidth_savings']       = '0';
			$data['formatted']['bandwidth_savings_bytes'] = '0';
			$data['formatted']['package_without_extra']   = '0';
			$data['formatted']['original_bandwidth']      = '0';
			$data['formatted']['projected']               = '0';

			// Local
			$data['formatted']['local_savings']   = '0 MB';
			$data['formatted']['local_original']  = '0 MB';
			$data['formatted']['local_optimized'] = '0 MB';

			$data['formatted'] = (object) $data['formatted'];
			$data              = (object) $data;

			$body = array( 'success' => true, 'data' => $data );
			$body = (object) $body;

			// Account Status Transient
			set_transient( 'wps_ic_account_status', $body->data, 30 );

			update_option( 'wps_ic_allow_local', false );

			return $body->data;
		}
	}


	public static function check_account() {
		$options = get_option( WPS_IC_OPTIONS );
		if ( ! $options || empty( $options['api_key'] ) ) {
			return false;
		}

		if ( empty( $options['wpc_version'] ) || $options['wpc_version'] != self::$version || ! empty( $_GET['check_account'] ) ) {
			$settings = get_option( WPS_IC_SETTINGS );

			// Check privileges
			$call = wp_remote_get( WPS_IC_KEYSURL . '?action=get_credits&apikey=' . $options['api_key'] . '&v=2&hash=' . md5( mt_rand( 999, 9999 ) ), array( 'timeout' => 10, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0' ) );

			if ( wp_remote_retrieve_response_code( $call ) == 200 ) {
				$body = wp_remote_retrieve_body( $call );
				$body = json_decode( $body );
				$body = $body->data;

				if ( ! empty( $body ) ) {
					update_option( 'wps_ic_allow_local', $body->agency->allow_local );

					if ( $body->agency->allow_local == '0' ) {
						$settings['live-cdn'] = '1';
						update_option( WPS_IC_SETTINGS, $settings );
					}

				} else {
					update_option( 'wps_ic_allow_local', false );

					$settings['live-cdn'] = '1';
					update_option( WPS_IC_SETTINGS, $settings );
				}
			} else {
				update_option( 'wps_ic_allow_local', false );

				$settings['live-cdn'] = '1';
				update_option( WPS_IC_SETTINGS, $settings );
			}

			$options['wpc_version'] = self::$version;
			update_option( WPS_IC_OPTIONS, $options );
		}

		return true;
	}


	/**
	 * WP Init helper
	 */
	public static function init() {
		if ( ! empty( $_GET['trp-edit-translation'] ) || ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['tatsu'] ) || ! empty( $_GET['preview'] ) || ! empty( $_GET['PageSpeed'] ) || ! empty( $_GET['tve'] ) || ! empty( $_GET['et_fb'] ) || ( ! empty( $_GET['fl_builder'] ) || isset( $_GET['fl_builder'] ) ) || ! empty( $_GET['ct_builder'] ) || ! empty( $_GET['fb-edit'] ) || ! empty( $_GET['bricks'] ) || ! empty( $_GET['brizy-edit-iframe'] ) || ! empty( $_GET['brizy-edit'] ) || ( ! empty( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) || ( ! empty( $_GET['page'] ) && $_GET['page'] == 'livecomposer_editor' ) ) {
			// Nothing
		} else {
			$class = __CLASS__;
			new $class;
		}
	}


	public static function mu_activation( $plugin, $network_wide ) {
		if ( is_multisite() && $network_wide ) {
			// It's a multisite and network install
			#wp_safe_redirect(admin_url('options-general.php?page=' . $wps_ic::$slug . '-mu'));
		}
	}


	/**
	 * Activation of the plugin
	 */
	public static function activation( $networkwide ) {

		if ( is_multisite() ) {
			//wp_safe_redirect(admin_url('options-general.php?page=wpcompress-mu'));
			/*
						foreach (get_sites(['fields' => 'ids']) as $blog_id) {
							switch_to_blog($blog_id);
							//do your specific thing here...
							restore_current_blog();
						}*/

		} else {

			delete_option( 'ic_cdn_zone_name' );

			// Setup Default Options
			$options = new wps_ic_options();
			$options->set_defaults();

			if ( ! file_exists( WPS_IC_DIR . 'cache' ) ) {
				// Folder does not exist
				mkdir( WPS_IC_DIR . 'cache', 0755 );
			} else {
				// Folder exists
				if ( ! is_writable( WPS_IC_DIR . 'cache' ) ) {
					chmod( WPS_IC_DIR . 'cache', 0755 );
				}
			}

		}

	}


	/**
	 * Reset local image status
	 */
	public function reset_local_compress() {

		$queue = $this->media_library->find_compressed_images();

		$compressed_images_queue = get_option( 'wps_ic_restore_queue' );

		if ( $compressed_images_queue['queue'] ) {
			foreach ( $compressed_images_queue['queue'] as $i => $image ) {
				$attID = $image;
				delete_post_meta( $attID, 'ic_status' );
				delete_post_meta( $attID, 'ic_stats' );
				delete_post_meta( $attID, 'ic_compressed_images' );
			}
		}
	}


	/**
	 * Deactivation of the plugin
	 */
	public static function deactivation() {
		global $wpdb;

		delete_transient( 'wps_ic_live_stats' );
		delete_transient( 'wps_ic_local_stats' );

		$options['api_key']      = '';
		$options['response_key'] = '';
		$options['orp']          = '';

		update_option( WPS_IC_OPTIONS, $options );

		$settings                  = get_option( WPS_IC_MU_SETTINGS );
		$settings['hide_compress'] = 0;
		$settings['token']         = 0;

		update_option( WPS_IC_MU_SETTINGS, $settings );

		// Remove from active on API
		$options = get_option( WPS_IC_OPTIONS );
		$site    = site_url();
		$apikey  = $options['api_key'];

		// Setup URI
		$uri = WPS_IC_KEYSURL . '?action=disconnect&apikey=' . $apikey . '&site=' . urlencode( $site );

		// Verify API Key is our database and user has is confirmed getresponse
		$get = wp_remote_get( $uri, array( 'timeout' => 60, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0' ) );
	}


	public static function deactivate_script() {
		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_script( 'utils' ); // for user settings
		?>
      <script type="text/javascript">

          jQuery(document).ready(function ($) {
              var row = $('tr[data-slug="wp-compress-image-optimizer"]');
              var span_deactivate = $('span.deactivate', row);
              var link = $('a', span_deactivate);
              var pointer = '';

              $('#wps-ic-leave-active').on('click', function (e) {
                  e.preventDefault();
                  $(pointer).pointer('close');
                  return false;
              });

              $(link).on('click', function (e) {
                  e.preventDefault();

                  pointer = $(this).pointer({
                      content: '<h3>Deactivating may cause...</h3><p><ul style="padding:0px 15px;margin:0px 10px;list-style:disc;">' + '<li>Significantly higher bounce rates</li>' + '<li>Slow loading images for incoming visitors</li>' + '<li>Backups removed from our cloud</li>' + '<li>Our team crying that you’ve left... <?php echo '<img src="' . WPS_IC_URI . '/assets/crying.png" style="width:19px;" />';?></li>' + '</ul><p>If you’ve locally optimized images they’ll stay in the current state upon deactivating. Live optimization will stop immediately.</p><p>If you have any questions or issues, please visit our <a href="https://help' + '.wpcompress.com/en-us/" target="_blank">helpdesk</a>.</p><div' + ' style="padding:15px;"><a id="wps-ic-leave-active" class="button ' + 'button-primary" href="#">Leave active</a> <a id="everything" class="button ' + 'button-secondary" ' + 'href="' + $(
                          link).attr('href') + '">Deactivate Anyway</a></div></p>', position: {
                          my: 'left top', at: 'left top', offset: '0 0'
                      }, close: function () {
                          //
                      }
                  }).pointer('open');

                  $('.wp-pointer-buttons').hide();

                  return false;
              });
          });
      </script><?php
	}


	public static function disable_wp_responsive_images() {
		return 1;
	}


	public function enable_apiv4( $value ) {
		$zone_name = get_option( 'ic_cdn_zone_name' );
		$options   = get_option( WPS_IC_OPTIONS );
		$cname     = get_option( 'ic_custom_cname' );

		$apikey = $options['api_key'];

		if ( $value == 0 ) {
			$status = 'disable';
		} else {
			$status = 'activate';
		}

		$encoded_site = urlencode( site_url() );
		$url          = WPS_IC_KEYSURL . '?action=enable_apiv4&status=' . $status . '&apikey=' . $apikey . '&cname=' . $cname . '&zone_name=' . $zone_name . '&site_url=' . $encoded_site . '&time=' . time() . '&no_cache=' . md5( mt_rand( 999, 9999 ) );
		$call         = wp_remote_get( $url, array( 'timeout' => 60, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0' ) );
	}


	public function find_closes_matching_resolution( $match_resolution ) {
		if ( is_array( self::$resolutions ) ) {
			arsort( self::$resolutions );
			foreach ( self::$resolutions as $device => $resolution ) {
				if ( $resolution > $match_resolution ) {
					continue;
				} else {
					return $resolution;
				}
			}
		}
	}


	public static function debug_log( $message ) {
		if ( get_option( 'ic_debug' ) == 'log' ) {
			$log_file = WPS_IC_DIR . 'debug-log-' . date( 'd-m-Y' ) . '.txt';
			$time     = current_time( 'mysql' );

			if ( ! file_exists( $log_file ) ) {
				fopen( $log_file, 'a' );
			}

			$log = file_get_contents( $log_file );
			$log .= '[' . $time . '] - ' . $message . "\r\n";
			file_put_contents( $log_file, $log );
			fclose( $log_file );
		}
	}


	public function action_log( $attachmentID, $message ) {
		if ( get_option( 'ic_debug' ) == 'true' ) {
			$log_file = WPS_IC_DIR . 'action-log-' . date( 'd-m-Y' ) . '.txt';
			$time     = current_time( 'mysql' );

			if ( ! file_exists( $log_file ) ) {
				fopen( $log_file, 'a' );
			}

			$log = file_get_contents( $log_file );
			$log .= '[' . $time . '] - Attachment ID: ' . $attachmentID . ' - ' . $message . "\r\n";
			file_put_contents( $log_file, $log );
			fclose( $log_file );
		}
	}


	public function trigger_restore() {
		global $wpdb, $wps_ic;
		$queue = $wps_ic->queue->get_hidden_restore_bulk_queue();

		if ( $queue ) {
			foreach ( $queue as $attachment_ID => $values ) {

				// Updating log
				$wps_ic->log->write_log( '0', 'Hidden Bulk - Restore - Single Bulk - ' . $attachment_ID, '1', 'hidden_bulk_restore' );
				$wps_ic->compress->hidden_restore( $attachment_ID );

			}
		} else {
			delete_option( 'wps_ic_hidden_bulk_running' );
		}
		die();
	}


	public function check_folders() {

		if ( ! file_exists( WPS_IC_DIR . 'cache' ) ) {
			// Folder does not exist
			mkdir( WPS_IC_DIR . 'cache', 0755 );
		} else {
			// Folder exists
			if ( ! is_writable( WPS_IC_DIR . 'cache' ) ) {
				chmod( WPS_IC_DIR . 'cache', 0755 );
			}
		}

	}


	public function change_limits() {

		if ( ! defined( 'WP_MEMORY_LIMIT' ) ) {
			define( 'WP_MEMORY_LIMIT', '1024M' );
		} else {
			$mem_limit = WP_MEMORY_LIMIT;
			$mem_limit = str_replace( 'M', '', $mem_limit );

			if ( (int) $mem_limit <= 128 ) {
				define( 'WP_MEMORY_LIMIT', '1024M' );

			}
		}

		ini_set( 'memory_limit', '1024M' );
		ini_set( 'max_execution_time', '120' );
	}


	public function write_htaccess_allow_origin() {
		$htaccess_written = get_option( 'ic_htaccess_allow_origin' );
		$htaccess_version = '5.00.11';

		if ( ! $htaccess_written || $htaccess_written !== $htaccess_version ) {
			$htaccess_writable = self::is_htaccess_writeable();

			if ( $htaccess_writable ) {
				$htaccess              = ABSPATH . '.htaccess';
				$htaccess_old_contents = file_get_contents( $htaccess );

				if ( strpos( $htaccess_old_contents, 'WPC-allow-origin' ) == false ) {

					// Remove HTAcess
					$htaccess_old_contents = trim( $htaccess_old_contents );
					$start                 = strpos( $htaccess_old_contents, '#WPC-allow-origin' );
					$end                   = strpos( $htaccess_old_contents, '#WPC-allow-origin-end' );

					if ( $start == false && $end == false ) {
						$end = $end + strlen( '#WPC-allow-origin-end' );

						$len = $end - $start;
						#$htaccess_updated = substr_replace($htaccess_old_contents, '', $start, $len);
						$copy = copy( $htaccess, $htaccess . '_backup_edited' );

						if ( $copy ) {
							$htaccess_new_contents = $htaccess_old_contents;

							// Gzip Block
							$gzip_block            = '';
							$gzip_block            .= "\n";
							$gzip_block            .= "#WPC-allow-origin";
							$gzip_block            .= "\n";
							$gzip_block            .= "<IfModule mod_headers.c>" . "\n";
							$gzip_block            .= '<FilesMatch "\.(ttf|ttc|otf|eot|woff|woff2|font.css|css|js)$">' . "\n";
							$gzip_block            .= 'Header set Access-Control-Allow-Origin "*"' . "\n";
							$gzip_block            .= "</FilesMatch>" . "\n";
							$gzip_block            .= "</IfModule>" . "\n";
							$gzip_block            .= "\n";
							$gzip_block            .= "#WPC-allow-origin-end";
							$htaccess_new_contents .= $gzip_block;

							$write = file_put_contents( $htaccess, $htaccess_new_contents );
							update_option( 'ic_htaccess_allow_origin', $htaccess_version );
						}
					}

					flush_rewrite_rules();
				}
			}
		}
	}


	public static function is_htaccess_writeable() {
		$htaccess = ABSPATH . '.htaccess';

		return ( ! file_exists( $htaccess ) && @fopen( $htaccess, 'w' ) ) || ( file_exists( $htaccess ) && is_writable( $htaccess ) );
	}


}


function wps_ic_format_bytes( $bytes, $force_unit = null, $format = null, $si = false ) {
	// Format string
	$format = ( $format === null ) ? '%01.2f %s' : (string) $format;

	// IEC prefixes (binary)
	if ( $si == false or strpos( $force_unit, 'i' ) !== false ) {
		$units = array( 'B', 'kB', 'MB', 'GB', 'TB', 'PB' );
		$mod   = 1024;
	} // SI prefixes (decimal)
	else {
		$units = array( 'B', 'kB', 'MB', 'GB', 'TB', 'PB' );
		$mod   = 1024;
	}
	// Determine unit to use
	if ( ( $power = array_search( (string) $force_unit, $units ) ) === false ) {
		$power = ( $bytes > 0 ) ? floor( log( $bytes, $mod ) ) : 0;
	}

	return sprintf( $format, $bytes / pow( $mod, $power ), $units[ $power ] );
}

function wps_ic_size_format( $bytes, $decimals ) {
	$quant = array(
		'TB' => 1000 * 1000 * 1000 * 1000,
		'GB' => 1000 * 1000 * 1000,
		'MB' => 1000 * 1000,
		'KB' => 1000,
		'B'  => 1,
	);

	if ( $bytes == 0 ) {
		return '0 MB';
	}

	if ( 0 === $bytes ) {
		return number_format_i18n( 0, $decimals ) . ' B';
	}

	foreach ( $quant as $unit => $mag ) {
		if ( doubleval( $bytes ) >= $mag ) {
			return number_format_i18n( $bytes / $mag, $decimals ) . ' ' . $unit;
		}
	}

	return false;
}

// TODO: maybe set in if (lazy_enabled==1)
add_filter( 'wp_lazy_loading_enabled', '__return_false', 1 );

// TODO: Maybe it's required on some themes?
add_action( 'init', array( 'wps_ic', 'init' ) );
add_action( 'send_headers', array( 'wps_ic_cache', 'init' ) );
add_action( 'save_post', array( 'wps_ic_cache', 'update_css_hash' ), 10, 1 );

add_filter( 'upgrader_post_install', array( 'wps_ic_cache', 'update_css_hash' ), 1 );
add_action( 'activate_plugin', array( 'wps_ic_cache', 'update_css_hash' ), 1 );
add_action( 'upgrader_process_complete', array( 'wps_ic_cache', 'update_css_hash' ), 1 );

#add_filter('upgrader_post_install', array('wpc_upgrader', 'upgrade'), 1);
#add_action('activate_plugin', array('wpc_upgrader', 'upgrade'), 1);
#add_action('upgrader_process_complete', array('wpc_upgrader', 'upgrade'), 1);

add_action( 'activate_plugin', array( 'wps_ic', 'mu_activation' ), 10, 2 );
register_activation_hook( __FILE__, array( 'wps_ic', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'wps_ic', 'deactivation' ) );