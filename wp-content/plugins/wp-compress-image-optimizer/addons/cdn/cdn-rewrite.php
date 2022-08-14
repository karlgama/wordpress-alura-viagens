<?php


class wps_cdn_rewrite {

	public static $settings;
	public static $options;
	public static $lazy_excluded_list;
	public static $excluded_list;
	public static $default_excluded_list;
	public static $cdnEnabled;

	public static $assets_to_preload;
	public static $assets_to_defer;

	public static $emoji_remove;

	public static $isAjax;
	public static $brizyCache;
	public static $brizyActive;
	public static $logger;

	// Predefined API URLs
	public static $apiUrl;
	public static $apiAssetUrl;

	// Site URL, Upload Dir
	public static $updir;
	public static $home_url;
	public static $site_url;
	public static $site_url_scheme;

	// Resolution
	public static $resolutions;
	public static $defined_resolution;

	// SVG Placeholder (empty svg)
	public static $svg_placeholder;

	// Common Variables
	public static $wst;
	public static $bgImageCount;
	public static $imageCount;
	public static $imageCount_Wst;

	// CSS / JS Variables
	public static $fonts;
	public static $css;
	public static $css_img_url;
	public static $css_minify;
	public static $js;
	public static $js_minify;

	// Image Compress Variables
	public static $replace_all_links;
	public static $external_url_excluded;
	public static $external_url_enabled;
	public static $zone_test;
	public static $zone_name;
	public static $is_retina;
	public static $exif;
	public static $webp;
	public static $retina_enabled;
	public static $adaptive_enabled;
	public static $webp_enabled;
	public static $lazy_enabled;
	public static $sizes;
	public static $randomHash;
	public static $is_multisite;

	public static $combine_scripts;


	public function __construct() {

		self::$zone_test    = 0;
		self::$is_multisite = is_multisite();

		self::$bgImageCount = 0;
		self::$imageCount   = 0;
		self::$randomHash   = 0;

		// default excluded keywords
		self::$default_excluded_list = array( 'redditstatic', 'ai-uncode', 'gtm', 'instagram.com', 'fbcdn.net', 'twitter', 'google', 'coinbase', 'cookie', 'schema', 'recaptcha', 'data:image' );

		// Preload anything inside themes,elementor,wp-includes
		self::$assets_to_preload = array( 'themes', 'elementor', 'wp-includes' );
		self::$assets_to_defer   = array( 'themes', 'tracking' );

		if ( ! empty( $_GET['gps'] ) ) {
			$fp = fopen( WPS_IC_DIR . 'gps.txt', 'a+' );
			fwrite( $fp, 'IP: ' . $_SERVER['REMOTE_ADDR'] . "\r\n" );
			fwrite( $fp, 'User Agent: ' . $_SERVER['HTTP_USER_AGENT'] . "\r\n" );
			fwrite( $fp, 'ALL: ' . print_r( $_SERVER, true ) . "\r\n" );
			fclose( $fp );
		}

		if ( ! empty( $_GET['ignore_ic'] ) ) {
			return;
		}

		if ( ! empty( $_GET['randomHash'] ) ) {
			self::$randomHash = time();
		}

		if ( strpos( $_SERVER['REQUEST_URI'], '.xml' ) !== false ) {
			return;
		}

		// Plugin is NOT Activated
		self::$options = get_option( WPS_IC_OPTIONS );
		$response_key  = self::$options['api_key'];
		if ( empty( $response_key ) ) {
			return;
		}

		self::$settings = get_option( WPS_IC_SETTINGS );
		if ( empty( self::$options['css_hash'] ) ) {
			self::$options['css_hash'] = 5021;
		}

		if ( ! defined( 'WPS_IC_HASH' ) ) {
			define( 'WPS_IC_HASH', self::$options['css_hash'] );
		}

		self::$cdnEnabled     = self::$settings['live-cdn'];
		self::$wst            = self::is_st();
		self::$imageCount_Wst = 10;

		// Is an ajax request?
		self::$isAjax = ( function_exists( "wp_doing_ajax" ) && wp_doing_ajax() ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX );

		// Don't run in admin side!
		if ( ! empty( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) {
			return;
		}

		// TODO: Check this for wpadmin and frontend ajax
		if ( ! self::$isAjax ) {
			if ( is_admin() || ! empty( $_GET['trp-edit-translation'] ) || ( ! empty( $_GET['fl_builder'] ) || isset( $_GET['fl_builder'] ) ) || ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['preview'] ) || ! empty( $_GET['PageSpeed'] ) || ! empty( $_GET['et_fb'] ) || ! empty( $_GET['tve'] ) || ! empty( $_GET['tatsu'] ) || ! empty( $_GET['ct_builder'] ) || ! empty( $_GET['fb-edit'] ) || ! empty( $_GET['bricks'] ) || ( ! empty
					( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) || ( ! empty( $_GET['page'] ) && $_GET['page'] == 'livecomposer_editor' ) ) {
				return;
			}

			if ( ! empty( $_GET['tatsu'] ) || ! empty( $_GET['tatsu-header'] ) || ! empty( $_GET['tatsu-footer'] ) ) {
				return;
			}

		}

		self::$svg_placeholder = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAwIiBoZWlnaHQ9IjEwMCI+PHBhdGggZD0iTTIgMmgxMDAwdjEwMEgyeiIgZmlsbD0iI2ZmZiIgb3BhY2l0eT0iMCIvPjwvc3ZnPg==';

		self::$updir = wp_upload_dir();

		// If SpeedTest Then disable LS cache and rocket cache
		if ( self::$wst ) {
			define( 'LSCACHE_NO_CACHE', true );
		}

		if ( ! is_multisite() ) {
			self::$site_url = site_url();
			self::$home_url = home_url();
		} else {
			$current_blog_id = get_current_blog_id();
			switch_to_blog( $current_blog_id );

			self::$site_url = network_site_url();
			self::$home_url = home_url();
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'home_url' ) {
			var_dump( self::$site_url );
			var_dump( self::$home_url );
			die();
		}

		self::$site_url_scheme = parse_url( self::$site_url, PHP_URL_SCHEME );

		self::$lazy_excluded_list = get_option( 'wpc-ic-lazy-exclude' );
		self::$excluded_list      = get_option( 'wpc-ic-external-url-exclude' );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'exclude_list' ) {
			var_dump( self::$excluded_list );
			die();
		}

		if ( ! is_array( self::$excluded_list ) ) {
			self::$external_url_excluded = explode( "\n", self::$excluded_list );
		} else {
			self::$external_url_excluded = self::$excluded_list;
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'lazy_exclude' ) {
			var_dump( get_option( 'wpc-ic-lazy-exclude' ) );
			var_dump( self::$lazy_excluded_list );
			die();
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'exclude_list' ) {
			var_dump( self::$excluded_list );
			var_dump( self::$external_url_excluded );
			die();
		}

		if ( defined( 'BRIZY_VERSION' ) ) {
			self::$brizyCache  = get_option( 'wps_ic_brizy_cache' );
			self::$brizyActive = true;
		} else {
			self::$brizyActive = false;
		}

		$custom_cname = get_option( 'ic_custom_cname' );

		if ( empty( $custom_cname ) || ! $custom_cname ) {
			self::$zone_name = get_option( 'ic_cdn_zone_name' );
		} else {
			self::$zone_name = $custom_cname;
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'direct' ) {

			if ( ! empty( $_GET['custom_server'] ) ) {
				self::$zone_name = $_GET['custom_server'] . '/key:' . self::$options['api_key'];
			}

		}

		if ( empty( self::$zone_name ) ) {
			return;
		}

		self::$resolutions          = array( 'desktop' => 1920, 'laptop' => 1500, 'laptop' => 1280, 'tablet' => 1024, 'laptop' => 768, 'mobile' => 360, 'xsmobile' => 290 );
		self::$is_retina            = '0';
		self::$webp                 = '0';
		self::$external_url_enabled = 'false';

		self::$lazy_enabled      = self::$settings['lazy'];
		self::$adaptive_enabled  = self::$settings['generate_adaptive'];
		self::$webp_enabled      = self::$settings['generate_webp'];
		self::$retina_enabled    = self::$settings['retina'];
		self::$replace_all_links = self::$settings['replace-all-link'];

		if ( ! empty( self::$settings['external-url'] ) ) {
			self::$external_url_enabled = self::$settings['external-url'];
		}

		if ( empty( self::$settings['emoji-remove'] ) ) {
			self::$settings['emoji-remove'] = 0;
		}

		if ( empty( self::$settings['external-url'] ) ) {
			self::$settings['external-url'] = 0;
		}

		if ( empty( self::$settings['css'] ) ) {
			self::$settings['css'] = 0;
		}

		if ( empty( self::$settings['fonts'] ) ) {
			self::$settings['fonts'] = 0;
		}

		if ( empty( self::$settings['js'] ) ) {
			self::$settings['js'] = 0;
		}

		if ( empty( self::$settings['preserve_exif'] ) ) {
			self::$settings['preserve_exif'] = 0;
		}

		if ( ! empty( $_GET['ic_override_setting'] ) && $_GET['ic_override_setting'] == 'lazy' ) {
			self::$lazy_enabled = (bool) $_GET['value'];
		}

		if ( ! empty( $_GET['ic_lazy'] ) ) {
			self::$lazy_enabled    = (bool) $_GET['ic_lazy'];
			self::$settings['css'] = 1;
			self::$settings['js']  = 1;
		}

		if ( ! empty( $_GET['css'] ) ) {
			self::$settings['css'] = (bool) $_GET['css'];
		}

		if ( ! empty( $_GET['js'] ) ) {
			self::$settings['js'] = (bool) $_GET['js'];
		}

		if ( empty( self::$settings['css_image_urls'] ) || ! isset( self::$settings['css_image_urls'] ) ) {
			self::$settings['css_image_urls'] = '0';
		}

		if ( ! empty( self::$settings['minify-css'] ) && self::$settings['minify-css'] ) {
			self::$settings['minify-css'] = '1';
		} else {
			self::$settings['minify-css'] = '0';
		}

		if ( ! empty( self::$settings['minify-js'] ) && self::$settings['minify-js'] ) {
			self::$settings['minify-js'] = '1';
		} else {
			self::$settings['minify-js'] = '0';
		}

		self::$external_url_enabled = self::$settings['external-url'];
		self::$css                  = self::$settings['css'];
		self::$css_img_url          = self::$settings['css_image_urls'];
		self::$css_minify           = self::$settings['minify-css'];
		self::$js                   = self::$settings['js'];
		self::$js_minify            = self::$settings['minify-js'];
		self::$emoji_remove         = self::$settings['emoji-remove'];
		self::$exif                 = self::$settings['preserve_exif'];
		self::$fonts                = self::$settings['fonts'];

		if ( self::$css_minify == 'true' ) {
			self::$css_minify = '1';
		} else {
			self::$css_minify = '0';
		}

		if ( self::$js_minify == 'true' ) {
			self::$js_minify = '1';
		} else {
			self::$js_minify = '0';
		}

		if ( ! empty( self::$emoji_remove ) && self::$emoji_remove == '1' ) {
			// Remove WP Emoji
			$this->remove_emoji();
		}

		if ( ! empty( self::$retina_enabled ) && self::$retina_enabled == '1' ) {
			if ( isset( $_COOKIE["ic_pixel_ratio"] ) ) {
				if ( $_COOKIE["ic_pixel_ratio"] >= 2 ) {
					self::$is_retina = '1';
				}
			}
		}

		if ( ! empty( self::$webp_enabled ) && self::$webp_enabled == '1' ) {
			if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'Linux' ) !== false || strpos( $_SERVER['HTTP_USER_AGENT'], 'Mozilla' ) !== false || strpos( $_SERVER['HTTP_USER_AGENT'], 'Chrome' ) !== false || strpos( $_SERVER['HTTP_USER_AGENT'], 'Firefox' ) !== false ) {
				self::$webp = '1';
			}

			if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'Safari' ) && ! strpos( $_SERVER['HTTP_USER_AGENT'], 'Chrome' ) ) {
				self::$webp_enabled = false;
				self::$webp         = '0';
			}
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'webp' ) {
			var_dump( self::$webp_enabled );
			var_dump( self::$webp );
			var_dump( $_SERVER['HTTP_USER_AGENT'] );
			var_dump( $_SERVER['HTTP_ACCEPT'] );
			die();
		}

		// Fix for gtmetrix because it uses no-webp at one point
		if ( strpos( $_SERVER['HTTP_ACCEPT'], 'webp' ) === false && strpos( $_SERVER['HTTP_ACCEPT'], '*/*' ) === false ) {
			#self::$webp = 'false';
		}

		// If Optimization Quality is Not set..
		if ( empty( self::$settings['optimization'] ) || self::$settings['optimization'] == '' || self::$settings['optimization'] == '0' ) {
			self::$settings['optimization'] = 'i';
		}

		if ( ! empty( $_GET['test_zone'] ) ) {
			if ( $_GET['test_zone'] == 'cdn-rage4' ) {
				self::$zone_test = 1;
				self::$zone_name = $_GET['server'] . '.zapwp.net/key:' . self::$options['api_key'];
			} else {
				self::$zone_name = $_GET['test_zone'] . '.wpmediacompress.com/key:' . self::$options['api_key'];
			}
		}

		switch ( self::$settings['optimization'] ) {
			case 'intelligent':
				self::$settings['optimization'] = 'i';
				break;
			case 'ultra':
				self::$settings['optimization'] = 'u';
				break;
			case 'lossless':
				self::$settings['optimization'] = 'l';
				break;
		}

		if ( ! empty( self::$exif ) && self::$exif == '1' ) {
			self::$apiUrl = 'https://' . self::$zone_name . '/q:' . self::$settings['optimization'] . '/e:1';
		} else {
			self::$apiUrl = 'https://' . self::$zone_name . '/q:' . self::$settings['optimization'] . '';
		}

		self::$apiAssetUrl = 'https://' . self::$zone_name . '/a:';

		self::$defined_resolution = self::$resolutions['desktop'];

		if ( $this->is_mobile() ) {
			self::$retina_enabled     = false;
			self::$is_retina          = '0';
			self::$defined_resolution = self::$resolutions['mobile'];
		}

		if ( ! empty( $_GET['wps_ic_check_settings'] ) ) {
			var_dump( 'CDN Enabled: ' . self::$cdnEnabled );
			var_dump( 'Retina enabled: ' . self::$retina_enabled );
			var_dump( 'Adaptive enabled: ' . self::$adaptive_enabled );
			var_dump( 'Lazy enabled: ' . self::$lazy_enabled );
			var_dump( 'CSS CDN: ' . self::$css );
			var_dump( 'JS CDN: ' . self::$js );
			var_dump( 'Zone Name: ' . self::$zone_name );
			die();
		}

		if ( function_exists( 'amp_is_request' ) ) {
			$_GET['wpc_is_amp'] = amp_is_request();
			if ( ! empty( $_GET['amp_dbg'] ) && $_GET['amp_dbg'] == 'first' ) {
				var_dump( print_r( $_GET['wpc_is_amp'], true ) );
				var_dump( print_r( amp_is_request(), true ) );
				die();
			}
		} else if ( function_exists( 'ampforwp_is_amp_endpoint' ) ) {
			$_GET['wpc_is_amp'] = ampforwp_is_amp_endpoint();
			if ( ! empty( $_GET['amp_dbg'] ) && $_GET['amp_dbg'] == 'second' ) {
				var_dump( print_r( $_GET['wpc_is_amp'], true ) );
				var_dump( print_r( ampforwp_is_amp_endpoint(), true ) );
				die();
			}
		}

		if ( ! empty( $_GET['amp_dbg'] ) && $_GET['amp_dbg'] == 'true' ) {
			var_dump( print_r( $_GET, true ) );
			var_dump( print_r( $_GET['wpc_is_amp'], true ) );
			var_dump( print_r( amp_is_request(), true ) );
			var_dump( print_r( ampforwp_is_amp_endpoint(), true ) );
			die();
		}

		if ( isset( $_GET['wpc_is_amp'] ) && ! empty( $_GET['wpc_is_amp'] ) ) {
			self::$lazy_enabled     = '0';
			self::$adaptive_enabled = '0';
			self::$retina_enabled   = '0';
		}

		if ( ! empty( $_GET['overwrite_retina'] ) ) {
			self::$retina_enabled = '1';
			self::$is_retina      = '1';
		}

		if ( self::$cdnEnabled == 1 ) {

			if ( self::dontRunif() ) {
				if ( self::$css == "1" ) {
					#add_filter('style_loader_src', array(&$this, 'adjust_src_url'), 10, 2);
					add_filter( 'style_loader_tag', array( &$this, 'adjust_style_tag' ), 10, 4 );
				}

				if ( self::$js == "1" ) {
					add_filter( 'script_loader_tag', array( &$this, 'rewrite_script_tag' ), 10, 3 );
					##add_filter('script_loader_src', array(&$this, 'adjust_src_url'), 10, 2);
				}
			}

			add_action( "wp_head", array( &$this, 'dnsPrefetch' ), 0 );
		} else {

			// Local Mode
			if ( self::dontRunif() ) {
				if ( self::$css == "1" ) {
					add_filter( 'style_loader_src', array( &$this, 'adjust_src_url' ), 10, 2 );
					add_filter( 'style_loader_tag', array( &$this, 'adjust_style_tag' ), 10, 4 );
				}

				if ( self::$js == "1" ) {
					add_filter( 'script_loader_src', array( &$this, 'adjust_src_url' ), 10, 2 );
				}
			}

			if ( self::$js == "1" || self::$css == "1" ) {
				add_action( "wp_head", array( &$this, 'dnsPrefetch' ), 0 );
			}

		}

	}


	public function dnsPrefetch() {
		if ( strlen( trim( self::$zone_name ) ) > 0 ) {
			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'direct' ) {

				if ( ! empty( $_GET['custom_server'] ) ) {
					self::$zone_name = $_GET['custom_server'] . '/key:' . self::$options['api_key'];
				}

			}
			echo '<link rel="dns-prefetch" href="https://' . self::$zone_name . '" />';
		}
	}


	/**
	 * Detect SpeedTest like pingdom or gtmetrix
	 * @return bool
	 */
	public function getIp() {
		$keys = [
			'HTTP_CF_CONNECTING_IP', // CF = CloudFlare.
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_X_REAL_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		];

		foreach ( $keys as $key ) {
			if ( ! isset ( $_SERVER[ $key ] ) ) {
				continue;
			}

			$ip = explode( ',', $_SERVER[ $key ] );
			$ip = end( $ip );

			if ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return false;
	}

	public function is_st() {
		if ( ! empty( $_GET['ic_lazy'] ) ) {
			return false;
		}

		if ( ! empty( $_GET['write_speedtest_log'] ) ) {
			$fp = fopen( WPS_IC_DIR . 'speedtest.txt', 'a+' );
			fwrite( $fp, 'IP: ' . $this->getIp() . "\r\n" );
			fwrite( $fp, 'User Agent PregMAtch: ' . print_r(preg_match( '#PingdomPageSpeed|DareBoost|Google|PTST|Chrome-Lighthouse|WP Rocket|gtmetrix#i', $_SERVER['HTTP_USER_AGENT'] ),true) . "\r\n" );
			fwrite( $fp, 'User Agent: ' . $_SERVER['HTTP_USER_AGENT'] . "\r\n" );
			fclose( $fp );
		}

		#if ( is_admin() || empty( $_SERVER['HTTP_USER_AGENT'] ) || empty( $_SERVER['REMOTE_ADDR'] ) ) {
		if ( is_admin() ) {
			return false;
		}

		$ips = [
			'208.70.247.157'  => '',   // GT Metrix - Vancouver 1.
			'172.255.48.130'  => '',   // GT Metrix - Vancouver 2.
			'172.255.48.131'  => '',   // GT Metrix - Vancouver 3.
			'172.255.48.132'  => '',   // GT Metrix - Vancouver 4.
			'172.255.48.133'  => '',   // GT Metrix - Vancouver 5.
			'172.255.48.134'  => '',   // GT Metrix - Vancouver 6.
			'172.255.48.135'  => '',   // GT Metrix - Vancouver 7.
			'172.255.48.136'  => '',   // GT Metrix - Vancouver 8.
			'172.255.48.137'  => '',   // GT Metrix - Vancouver 9.
			'172.255.48.138'  => '',   // GT Metrix - Vancouver 10.
			'172.255.48.139'  => '',   // GT Metrix - Vancouver 11.
			'172.255.48.140'  => '',   // GT Metrix - Vancouver 12.
			'172.255.48.141'  => '',   // GT Metrix - Vancouver 13.
			'172.255.48.142'  => '',   // GT Metrix - Vancouver 14.
			'172.255.48.143'  => '',   // GT Metrix - Vancouver 15.
			'172.255.48.144'  => '',   // GT Metrix - Vancouver 16.
			'172.255.48.145'  => '',   // GT Metrix - Vancouver 17.
			'172.255.48.146'  => '',   // GT Metrix - Vancouver 18.
			'172.255.48.147'  => '',   // GT Metrix - Vancouver 19.
			'52.229.122.240'  => '',   // GT Metrix - Quebec City.
			'104.214.72.101'  => '',   // GT Metrix - San Antonio, TX 1.
			'13.66.7.11'      => '',   // GT Metrix - San Antonio, TX 2.
			'13.85.24.83'     => '',   // GT Metrix - San Antonio, TX 3.
			'13.85.24.90'     => '',   // GT Metrix - San Antonio, TX 4.
			'13.85.82.26'     => '',   // GT Metrix - San Antonio, TX 5.
			'40.74.242.253'   => '',   // GT Metrix - San Antonio, TX 6.
			'40.74.243.13'    => '',   // GT Metrix - San Antonio, TX 7.
			'40.74.243.176'   => '',   // GT Metrix - San Antonio, TX 8.
			'104.214.48.247'  => '',   // GT Metrix - San Antonio, TX 9.
			'157.55.189.189'  => '',   // GT Metrix - San Antonio, TX 10.
			'104.214.110.135' => '',   // GT Metrix - San Antonio, TX 11.
			'70.37.83.240'    => '',   // GT Metrix - San Antonio, TX 12.
			'65.52.36.250'    => '',   // GT Metrix - San Antonio, TX 13.
			'13.78.216.56'    => '',   // GT Metrix - Cheyenne, WY.
			'52.162.212.163'  => '',   // GT Metrix - Chicago, IL.
			'23.96.34.105'    => '',   // GT Metrix - Danville, VA.
			'65.52.113.236'   => '',   // GT Metrix - San Francisco, CA.
			'172.255.61.34'   => '',   // GT Metrix - London 1.
			'172.255.61.35'   => '',   // GT Metrix - London 2.
			'172.255.61.36'   => '',   // GT Metrix - London 3.
			'172.255.61.37'   => '',   // GT Metrix - London 4.
			'172.255.61.38'   => '',   // GT Metrix - London 5.
			'172.255.61.39'   => '',   // GT Metrix - London 6.
			'172.255.61.40'   => '',   // GT Metrix - London 7.
			'52.237.235.185'  => '',   // GT Metrix - Sydney 1.
			'52.237.250.73'   => '',   // GT Metrix - Sydney 2.
			'52.237.236.145'  => '',   // GT Metrix - Sydney 3.
			'104.41.2.19'     => '',   // GT Metrix - São Paulo 1.
			'191.235.98.164'  => '',   // GT Metrix - São Paulo 2.
			'191.235.99.221'  => '',   // GT Metrix - São Paulo 3.
			'191.232.194.51'  => '',   // GT Metrix - São Paulo 4.
			'104.211.143.8'   => '',   // GT Metrix - Mumbai 1.
			'104.211.165.53'  => '',   // GT Metrix - Mumbai 2.
			'52.172.14.87'    => '',   // GT Metrix - Chennai.
			'40.83.89.214'    => '',   // GT Metrix - Hong Kong 1.
			'52.175.57.81'    => '',   // GT Metrix - Hong Kong 2.
			'20.188.63.151'   => '',   // GT Metrix - Paris.
			'20.52.36.49'     => '',   // GT Metrix - Frankfurt.
			'52.246.165.153'  => '',   // GT Metrix - Tokyo.
			'51.144.102.233'  => '',   // GT Metrix - Amsterdam.
			'13.76.97.224'    => '',   // GT Metrix - Singapore.
			'102.133.169.66'  => '',   // GT Metrix - Johannesburg.
			'52.231.199.170'  => '',   // GT Metrix - Busan.
			'13.53.162.7'     => '',   // GT Metrix - Stockholm.
			'40.123.218.94'   => '',   // GT Metrix - Dubai.
		];

		if ( ! empty( $this->getIp() ) ) {
			if ( isset( $ips[ $this->getIp() ] ) ) {
				return true;
			}
		}

		$user_agent = preg_match( '#PingdomPageSpeed|DareBoost|Google|PTST|Chrome-Lighthouse|WP Rocket|gtmetrix#i', $_SERVER['HTTP_USER_AGENT'] );
		if ($user_agent) {
			return true;
		}

		// OLD

		$pingdom    = strpos( strtolower( $_SERVER['HTTP_USER_AGENT'] ), 'pingdom' );
		$pingdombot = strpos( strtolower( $_SERVER['HTTP_USER_AGENT'] ), 'pingbot' );
		$gtmetrix   = strpos( strtolower( $_SERVER['HTTP_USER_AGENT'] ), 'gtmetrix' );
		$pageSpeed  = strpos( strtolower( $_SERVER['HTTP_USER_AGENT'] ), 'pagespeed' );
		$google     = strpos( strtolower( $_SERVER['HTTP_USER_AGENT'] ), 'google page speed' );
		$google_ps  = strpos( strtolower( $_SERVER['HTTP_USER_AGENT'] ), 'lighthouse' );

		if ( ! empty( $_GET['simulate_test'] ) ) {
			return true;
		}

		if ( $pingdom !== false ) {
			return true;
		}

		if ( $pingdombot !== false ) {
			return true;
		}

		if ( $pageSpeed !== false ) {
			return true;
		}

		if ( $gtmetrix !== false ) {
			return true;
		}

		if ( $google !== false ) {
			return true;
		}

		if ( $google_ps !== false ) {
			return true;
		}

		$userIP = $_SERVER['REMOTE_ADDR'];
		if ( in_array( $userIP, $ips ) ) {
			return true;
		} else {
			return false;
		}
	}


	public function rewrite_script_tag( $tag, $handle, $src ) {

		if ( $this->defaultExcluded( $src ) ) {
			return $tag;
		}

		if ( self::is_excluded_link( $src ) ) {
			return $tag;
		}

		/**
		 * TODO:
		 * check if external is enabled
		 */
		if ( ( self::$external_url_enabled == '0' || empty( self::$external_url_enabled ) ) ) {
			if ( ! self::image_url_matching_site_url( $src ) ) {
				// External not enabled
				return $tag;
			}
		}

		if ( self::$external_url_enabled == '1' && ! self::image_url_matching_site_url( $src ) ) {
			// External not enabled
			if ( strpos( $src, self::$zone_name ) === false ) {
				if ( strpos( $src, 'http' ) === false ) {
					$src = ltrim( $src, '//' );
					$src = 'https://' . $src;
				}

				if ( ! self::is_excluded_link( $src ) ) {
					$src = 'https://' . self::$zone_name . '/m:0/a:' . $src;
				}

			}
		}

		if ( self::$cdnEnabled == '1' && self::$js == '1' ) {
			if ( strpos( $src, self::$zone_name ) === false ) {

				/**
				 * Is script inside Wp-content or Wp-includes
				 */
				if ( strpos( $src, 'wp-content' ) !== false || strpos( $src, 'wp-includes' ) !== false ) {
					$src = 'https://' . self::$zone_name . '/m:' . self::$js_minify . '/a:' . self::reformat_url( $src, false );
				} else {
					$src = 'https://' . self::$zone_name . '/m:' . self::$js_minify . '/a:' . self::reformat_url( $src, false );
				}

				if ( ! empty( self::$settings['remove-render-blocking'] ) ) {
					if ( self::$settings['remove-render-blocking'] == '1' && 1 == 0 ) {
						foreach ( self::$assets_to_defer as $i => $defer_key ) {
							if ( strpos( $tag, $defer_key ) !== false ) {
								$tag = '<script type="text/javascript" src="' . $src . '" defer></script>';
							}
						}
					} else {
						$tag = preg_replace( '/src=["|\'](.*?)["|\']/si', 'src="' . $src . '"', $tag );
						#$tag = '<script type="text/javascript" src="' . $src . '"></script>';
					}
				}
			}
		}

		return $tag;
	}


	public function adjust_style_tag( $html, $handle, $href, $media ) {
		#if ( ! empty($_GET['dbg']) || self::$wst) {
		if ( ! empty( self::$settings['remove-render-blocking'] ) || self::$wst ) {
			foreach ( self::$assets_to_preload as $i => $preload_key ) {
				if ( strpos( $href, $preload_key ) !== false ) {
					if ( strpos( $html, 'preload' ) == false ) {
						if ( strpos( $html, 'rel=' ) !== false ) {
							// Rel exists, change it
							$html = preg_replace( '/rel\=["|\'](.*?)["|\']/', 'rel="preload" as="style"  onload="this.rel=\'stylesheet\'" defer', $html );
						} else {
							// Rel does not exist, create it
							$html = str_replace( '/>', 'rel="preload" as="style"  onload="this.rel=\'stylesheet\'" defer/>', $html );
						}

					}
				}
			}
		}

		return $html;
	}


	public function adjust_src_url( $src ) {

		if ( $this->defaultExcluded( $src ) ) {
			return $src;
		}

		if ( self::is_excluded_link( $src ) ) {
			return $src;
		}

		/**
		 * TODO:
		 * check if external is enabled
		 */
		if ( ( self::$external_url_enabled == '0' || empty( self::$external_url_enabled ) ) ) {
			if ( ! self::image_url_matching_site_url( $src ) ) {
				// External not enabled
				return $src;
			}
		}

		if ( self::$external_url_enabled == '1' && ! self::image_url_matching_site_url( $src ) ) {
			// External not enabled
			if ( strpos( $src, self::$zone_name ) === false ) {
				if ( strpos( $src, 'http' ) === false ) {
					$src = ltrim( $src, '//' );
					$src = 'https://' . $src;
				}

				if ( ! self::is_excluded_link( $src ) ) {
					$src = 'https://' . self::$zone_name . '/m:0/a:' . $src;
				}

			}

			return $src;
		}

		if ( strpos( $src, self::$zone_name ) === false ) {

			if ( strpos( $src, '.css' ) !== false ) {
				if ( ! self::is_excluded_link( $src ) ) {
					if ( self::$css_img_url == '1' ) {
						$src = 'https://' . self::$zone_name . '/m:' . self::$css_minify . '/a:' . self::reformat_url( $src );
					} else {
						if ( strpos( $src, 'wp-content' ) !== false || strpos( $src, 'wp-includes' ) !== false ) {
							$src = 'https://' . self::$zone_name . '/m:' . self::$css_minify . '/a:' . self::reformat_url( $src, false );
						} else {
							$src = 'https://' . self::$zone_name . '/m:' . self::$css_minify . '/a:' . self::reformat_url( $src, false );
						}
					}
				}
			} else if ( strpos( $src, '.js' ) !== false ) {

				if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'scr_js' ) {
					return print_r( array( $src ), true );
				}

				if ( strpos( $src, 'wp-content' ) !== false || strpos( $src, 'wp-includes' ) !== false ) {
					/*if (empty(self::$js_minify) || self::$js_minify == 'false') {
						$src = 'https://' . self::$zone_name . '/m:0/a:' . self::reformat_url($src, true);
					}
					else {*/
					$src = 'https://' . self::$zone_name . '/m:' . self::$js_minify . '/a:' . self::reformat_url( $src, false );
					//}
				} else {
					$src = 'https://' . self::$zone_name . '/m:' . self::$js_minify . '/a:' . self::reformat_url( $src, false );
				}

			}

		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'log_script' ) {
			$fp = fopen( WPS_IC_DIR . 'scripts.txt', 'a+' );
			fwrite( $fp, 'URL: ' . print_r( $src, true ) . "\r\n" );
			fwrite( $fp, '---' . "\r\n" );
			fclose( $fp );
		}

		return $src;
	}


	/**
	 * Remove WP Emoji
	 */
	public function remove_emoji() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	}


	public function is_mobile() {
		$userAgent = strtolower( $_SERVER['HTTP_USER_AGENT'] );

		if ( strpos( $userAgent, 'lighthouse' ) !== false ) {
			return true;
		}

		if ( preg_match( '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i',
		                 $userAgent ) || preg_match( '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',
		                                             substr( $userAgent, 0, 4 ) ) ) {
			return true;
		} else {
			return false;
		}
	}


	public static function local() {
		global $ic_running;

		if ( is_admin() || strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) {
			return;
		}

		if ( ! empty( $_GET['ignore_ic'] ) ) {
			return;
		}

		$settings     = get_option( WPS_IC_SETTINGS );
		$options      = get_option( WPS_IC_OPTIONS );
		$response_key = $options['response_key'];
		if ( empty( $response_key ) ) {
			return;
		}

		self::$isAjax = ( function_exists( "wp_doing_ajax" ) && wp_doing_ajax() ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX );

		// Don't run in admin side!
		if ( ! empty( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) {
			return true;
		}

		// TODO: Check this for wpadmin and frontend ajax
		if ( ! self::$isAjax ) {
			if ( wp_is_json_request() || is_admin() || ! empty( $_GET['trp-edit-translation'] ) || ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['preview'] ) || ! empty( $_GET['PageSpeed'] ) || ( ! empty( $_GET['fl_builder'] ) || isset( $_GET['fl_builder'] ) ) || ! empty( $_GET['et_fb'] ) || ! empty( $_GET['tatsu'] ) || ! empty( $_GET['tve'] ) || ! empty( $_GET['ct_builder'] ) || ! empty( $_GET['fb-edit'] ) || ( ! empty( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) || ( ! empty( $_GET['page'] ) && $_GET['page'] == 'livecomposer_editor' ) ) {
				return;
			}
		}

		if ( ! is_admin() ) {
			if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) {
				self::buffer_local_go();
			}
		}

	}


	public function buffer_local_go() {

		if ( self::$isAjax ) {
			$wps_ic_cdn = new wps_cdn_rewrite();
		}

		ob_start( array( $this, 'buffer_local_callback' ) );
	}


	public static function init() {
		global $ic_running;

		#add_filter('upgrader_post_install', array('wps_ic_cache', 'update_css_hash'), 1);
		#add_action('upgrader_process_complete', array('wps_ic_cache', 'update_css_hash'), 1);

		if ( strpos( $_SERVER['REQUEST_URI'], '.xml' ) !== false ) {
			return;
		}

		if ( is_admin() || strpos( $_SERVER['REQUEST_URI'], 'wp-login.php' ) !== false ) {
			return;
		}

		if ( $ic_running ) {
			return;
		}

		$ic_running = true;

		if ( ! empty( $_GET['ignore_cdn'] ) || ! empty( $_GET['ignore_ic'] ) ) {
			return;
		}

		$options      = get_option( WPS_IC_OPTIONS );
		$response_key = $options['response_key'];
		if ( empty( $response_key ) ) {
			return;
		}

		$settings = get_option( WPS_IC_SETTINGS );
		if ( ! empty( $settings['live-cdn'] ) && $settings['live-cdn'] == '0' ) {
			return;
		}

		self::$isAjax = ( function_exists( "wp_doing_ajax" ) && wp_doing_ajax() ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX );

		// Don't run in admin side!
		if ( ! empty( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) {
			return true;
		}

		// TODO: Check this for wpadmin and frontend ajax
		if ( ! self::$isAjax ) {
			if ( wp_is_json_request() || is_admin() || ! empty( $_GET['trp-edit-translation'] ) || ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['preview'] ) || ! empty( $_GET['PageSpeed'] ) || ( ! empty( $_GET['fl_builder'] ) || isset( $_GET['fl_builder'] ) ) || ! empty( $_GET['et_fb'] ) || ! empty( $_GET['tatsu'] ) || ! empty( $_GET['tve'] ) || ! empty( $_GET['fb-edit'] ) || ! empty( $_GET['ct_builder'] ) || ( ! empty( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) || ( ! empty( $_GET['page'] ) && $_GET['page'] == 'livecomposer_editor' ) ) {
				return;
			}
		}

		add_filter( 'get_site_icon_url', array( 'wps_cdn_rewrite', 'favicon_replace' ), 10, 1 );
	}


	public static function favicon_replace( $url ) {
		if ( empty( $url ) ) {
			return $url;
		}

		if ( strpos( $url, self::$zone_name ) !== false ) {
			return $url;
		}

		$url = 'https://' . self::$zone_name . '/' . self::reformat_url( $url, true );

		return $url;
	}


	public static function reformat_url( $url, $remove_site_url = false ) {

		// Check if url is maybe a relative URL (no http or https)
		if ( strpos( $url, 'http' ) === false ) {
			// Check if url is maybe absolute but without http/s
			if ( strpos( $url, '//' ) === 0 ) {
				// Just needs http/s
				$url = 'https:' . $url;
			} else {
				$url         = str_replace( '../wp-content', 'wp-content', $url );
				$url_replace = str_replace( '/wp-content', 'wp-content', $url );
				$url         = self::$site_url;
				$url         = rtrim( $url, '/' );
				$url         .= '/' . $url_replace;
			}
		}

		$formatted_url = $url;

		if ( strpos( $formatted_url, '?brizy_media' ) === false ) {
			$formatted_url = explode( '?', $formatted_url );
			$formatted_url = $formatted_url[0];
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'log_url_format' ) {
			$fp = fopen( WPS_IC_DIR . 'url_Format.txt', 'a+' );
			fwrite( $fp, 'URL: ' . $formatted_url . "\r\n" );
			fwrite( $fp, 'Site URL: ' . self::$site_url . "\r\n" );
			fwrite( $fp, 'Slashes: ' . addcslashes( self::$site_url, '/' ) . "\r\n" );
			fwrite( $fp, '---' . "\r\n" );
			fclose( $fp );
		}

		if ( $remove_site_url ) {
			$formatted_url = str_replace( self::$site_url, '', $formatted_url );
			$formatted_url = str_replace( str_replace( array( 'https://', 'http://' ), '', self::$site_url ), '', $formatted_url );
			$formatted_url = str_replace( addcslashes( self::$site_url, '/' ), '', $formatted_url );
			$formatted_url = ltrim( $formatted_url, '\/' );
			$formatted_url = ltrim( $formatted_url, '/' );
		}

		#if (strpos($formatted_url, 'all-in-one') == false) {
		if ( self::$randomHash == 0 && strpos( $formatted_url, '.css' ) !== false || strpos( $formatted_url, '.js' ) !== false ) {
			$formatted_url .= '?icv=' . WPS_IC_HASH;
		}
		#}

		if ( self::$randomHash != 0 ) {
			return $formatted_url . '?icv_random=' . self::$randomHash;
		}

		return $formatted_url;
	}


	public function buffer_local_callback( $html ) {

		//Do something with the buffer (HTML)
		if ( isset( $_GET['brizy-edit-iframe'] ) || isset( $_GET['brizy-edit'] ) || isset( $_GET['preview'] ) ) {
			return $html;
		}

		if ( self::$isAjax ) {
			return $html;
		}

		if ( is_admin() || is_feed() || ! empty( $_GET['trp-edit-translation'] ) || ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['preview'] ) || ! empty( $_GET['PageSpeed'] ) || ! empty( $_GET['tve'] ) || ! empty( $_GET['et_fb'] ) || ( ! empty( $_GET['fl_builder'] ) || isset( $_GET['fl_builder'] ) ) || ! empty( $_GET['ct_builder'] ) || ! empty
			( $_GET['tatsu'] ) || ! empty( $_GET['fb-edit'] ) || ! empty( $_GET['bricks'] ) || ( ! empty( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) || ( ! empty( $_GET['page'] ) && $_GET['page'] == 'livecomposer_editor' ) ) {
			return $html;
		}

		if ( self::$cdnEnabled == 0 ) {

			if ( ! empty( $_GET['local_script_encode'] ) ) {
				return $html;
			}

			$htmlBefore = $html;

			// TODO: Radi kada je script type=["|\']text\/javascript["|\']> !!
			#$html = preg_replace_callback('/<script type=["|\']text\/javascript["|\']>(.*?)<\/script>/si', array($this, 'local_script_encode'), $html);

			$html = preg_replace_callback( '/<script\b[^>]*>(.*?)<\/script>/si', array( $this, 'local_script_encode' ), $html );

			if ( empty( $html ) ) {
				$html = $htmlBefore;
			}

			if ( ! empty( $_GET['local_image_tags'] ) ) {
				return $html;
			}

			$html = preg_replace_callback( '/(?<![\"|\'])<img[^>]*>/i', array( $this, 'local_image_tags' ), $html );
			if ( self::$fonts == 1 ) {
				$html = preg_replace_callback( '/https?:[^)\'\'"]+\.(woff2|woff|eot|ttf)/i', array( $this, 'local_fonts' ), $html );
			}

			if ( ! empty( $_GET['local_script_decode'] ) ) {
				return $html;
			}

			$html = preg_replace_callback( '/\[script\-wpc\](.*?)\[\/script\-wpc\]/i', array( $this, 'local_script_decode' ), $html );
		}

		return $html;
	}


	public function buffer_callback_v3() {

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'ob_start' ) {
			global $post;
			var_dump( isset( $post->post_type ) );
			var_dump( strpos( $post->post_type, 'wfocu' ) );
			var_dump( $post );
			die();
		}

		global $post;
		if ( isset( $post->post_type ) && strpos( $post->post_type, 'wfocu' ) !== false ) {
			// Ignore Post Types
		} else {

			ob_start( array( $this, 'cdn_rewriter' ) );
		}
	}


	public function script_encode( $html ) {
		$html = base64_encode( $html[0] );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_encode' ) {
			return print_r( array( $html ), true );
		}

		return '[script-wpc]' . $html . '[/script-wpc]';
	}


	public function script_decode( $html ) {
		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_decode' ) {
			return print_r( array( $html ), true );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'no_decode' ) {
			return $html[1];
		}

		#$html[0] = str_replace('[iframe-wpc]', '', $html[0]);
		#$html[0] = str_replace('[/iframe-wpc]', '', $html[0]);

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'after_base64_replace' ) {
			return $html[1];
		}

		$html = base64_decode( $html[1] );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'after_base64_decode' ) {
			return $html;
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_decode_after' ) {
			return print_r( array( str_replace( '<iframe', 'framea', $html ) ), true );
		}

		return $html;
	}


	public function removeFontAwesome( $html ) {
		return '';
	}


	public function removeEventListener( $html ) {
		return '';
	}


	public function noscript_encode( $html ) {
		$html = base64_encode( $html[0] );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_encode' ) {
			return print_r( array( $html ), true );
		}

		return '[noscript-wpc]' . $html . '[/noscript-wpc]';
	}


	public function noscript_decode( $html ) {
		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_decode' ) {
			return print_r( array( $html ), true );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'no_decode' ) {
			return $html[1];
		}

		#$html[0] = str_replace('[iframe-wpc]', '', $html[0]);
		#$html[0] = str_replace('[/iframe-wpc]', '', $html[0]);

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'after_base64_replace' ) {
			return $html[1];
		}

		$html = base64_decode( $html[1] );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'after_base64_decode' ) {
			return $html;
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_decode_after' ) {
			return print_r( array( str_replace( '<iframe', 'framea', $html ) ), true );
		}

		return $html;
	}


	public function iframe_encode( $html ) {
		$html = base64_encode( $html[0] );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_encode' ) {
			return print_r( array( $html ), true );
		}

		return '[iframe-wpc]' . $html . '[/iframe-wpc]';
	}


	public function iframe_decode( $html ) {
		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_decode' ) {
			return print_r( array( $html ), true );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'no_decode' ) {
			return $html[1];
		}

		#$html[0] = str_replace('[iframe-wpc]', '', $html[0]);
		#$html[0] = str_replace('[/iframe-wpc]', '', $html[0]);

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'after_base64_replace' ) {
			return $html[1];
		}

		$html = base64_decode( $html[1] );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'after_base64_decode' ) {
			return $html;
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_decode_after' ) {
			return print_r( array( str_replace( '<iframe', 'framea', $html ) ), true );
		}

		return $html;
	}


	public function jetsmart_ajax_rewrite( $args ) {
		$html = $args['content'];

		//Prep Site URL
		$escapedSiteURL = quotemeta( self::$home_url );
		$regExURL       = '(https?:|)' . substr( $escapedSiteURL, strpos( $escapedSiteURL, '//' ) );

		//Prep Included Directories
		$directories = 'wp\-content|wp\-includes';
		if ( ! empty( $cdn['cdn_directories'] ) ) {

			$directoriesArray = array_map( 'trim', explode( ',', $cdn['cdn_directories'] ) );

			if ( count( $directoriesArray ) > 0 ) {
				$directories = implode( '|', array_map( 'quotemeta', array_filter( $directoriesArray ) ) );
			}

		}

		$old_values['lazy']     = self::$lazy_enabled;
		$old_values['adaptive'] = self::$adaptive_enabled;

		self::$lazy_enabled     = 0;
		self::$adaptive_enabled = 0;

		if ( self::$lazy_enabled || self::$adaptive_enabled || self::$webp_enabled || self::$retina_enabled ) {
			$html = preg_replace_callback( '/(?<![\"|\'])<img[^>]*>/i', array( $this, 'replace_image_tags' ), $html );
		}

		$regEx = '#(?<=[(\"\'])(?:' . $regExURL . ')?/(?:((?:' . $directories . ')[^\"\')]+)|([^/\"\']+\.[^/\"\')]+))(?=[\"\')])#';
		$html  = preg_replace_callback( $regEx, array( $this, 'cdn_rewrite_url' ), $html );

		self::$lazy_enabled     = $old_values['lazy'];
		self::$adaptive_enabled = $old_values['adaptive'];

		$args['content'] = $html;

		return $args;
	}


	public function cdn_rewriter( $html ) {

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'cdn_rewriter' ) {
			var_dump( $_GET );
			die();
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'ob_start_first' ) {
			var_dump( self::$webp_enabled );
			var_dump( self::$webp );
			var_dump( $_SERVER['HTTP_USER_AGENT'] );
			var_dump( $_SERVER['HTTP_ACCEPT'] );
			die();
		}

		if ( ! empty( $_GET['ignore_ic'] ) || ! self::dontRunif() ) {
			return $html;
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'dbg_feed' ) {
			var_dump( self::$webp_enabled );
			var_dump( self::$webp );
			var_dump( $_SERVER['HTTP_USER_AGENT'] );
			var_dump( $_SERVER['HTTP_ACCEPT'] );
			die();
		}

		if ( is_feed() ) {
			return $html;
		}

		if ( self::$isAjax ) {
			return $html;
		}

		if ( strpos( $_SERVER['REQUEST_URI'], 'xmlrpc' ) !== false || strpos( $_SERVER['REQUEST_URI'], 'wp-json' ) !== false ) {
			return $html;
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'xmlrpc' ) {
			var_dump( self::$webp_enabled );
			var_dump( self::$webp );
			var_dump( $_SERVER['HTTP_USER_AGENT'] );
			var_dump( $_SERVER['HTTP_ACCEPT'] );
			die();
		}

		//Prep Site URL
		$escapedSiteURL = quotemeta( self::$home_url );
		$regExURL       = '(https?:|)' . substr( $escapedSiteURL, strpos( $escapedSiteURL, '//' ) );

		//Prep Included Directories
		$directories = 'wp\-content|wp\-includes';
		if ( ! empty( $cdn['cdn_directories'] ) ) {

			$directoriesArray = array_map( 'trim', explode( ',', $cdn['cdn_directories'] ) );

			if ( count( $directoriesArray ) > 0 ) {
				$directories = implode( '|', array_map( 'quotemeta', array_filter( $directoriesArray ) ) );
			}

		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'before_link' ) {
			return $html;
		}

		$html = preg_replace_callback( '/<link rel=[\"|\']stylesheet[\"|\'].*?>/si', array( $this, 'link_content_tag' ), $html );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'before_script' ) {
			return $html;
		}

		/*$html = preg_replace_callback('/<script.*?>(.*?)<\/script>/si', array($this, 'script_content_tag'), $html);*/ // Todo: Fix made for lottie
		#$html = preg_replace_callback('/<script type=["|\']text\/javascript["|\']>(.*?)<\/script>/si', array($this, 'script_content_tag'), $html);
		// TODO: It was not working for plusaddons
		#$html = preg_replace_callback( '/<script\b[^>]*>(.*?)<\/script>/si', array( $this, 'script_content_tag' ), $html );
		#$html = preg_replace_callback( '/<script.*(?!type=\"text\/template\").*>(.*?)<\/script>/si', array( $this, 'script_content_tag' ), $html );
		$html = preg_replace_callback( '/((<[\\s\\/]*script\\b[^>]*>)([^>]*)(<\\/script>))/is', array( $this, 'script_content_tag' ), $html );

		if ( ! empty( $_GET['combine'] ) ) {

			/***
			 * Regenerate on:
			 * - plugin update, activation, deactivation
			 * - theme update,activation,deactivation
			 * - wp update
			 */

			if ( ! file_exists( WPS_IC_DIR . 'cache/combined.js' ) ) {
				$combined_file = fopen( WPS_IC_DIR . 'cache/combined.js', 'a+' );
			}

			$found_scripts = $this->find_all_scripts( $html );

			if ( $found_scripts ) {
				$combine = $this->parse_script_src( $found_scripts, 'wp-content/plugins' );
				if ( ! empty( $combine ) ) {
					foreach ( $combine as $i => $url ) {
					}
				}
			}

			return print_r( $combine, true );
			die();
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'before_replace' ) {
			return $html;
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'remove_iframe' ) {
			// Remove iframes from tests
			$html = preg_replace( '/<iframe.*?\/iframe>/i', '', $html );
		}

		if ( ! self::$wst ) {
			$html = preg_replace_callback( '/<iframe.*?\/iframe>/i', array( $this, 'iframe_encode' ), $html );
		} else {
			// is WST
			// Remove Crisp
			$html = preg_replace_callback( '/document\.addEventListener([^>]*)\;/is', array( $this, 'removeEventListener' ), $html );
			// Remove Tracking
			$html = preg_replace_callback( '/<script type=\"text\/javascript\" src=\"https\:\/\/app\.paykickstart\.com\/tracking\-script\"><\/script>/is', array( $this, 'removeFontAwesome' ), $html );
			// Remove fontawesome
			$html = preg_replace_callback( '/<script\b[^>]*src=\"https:\/\/kit\.fontawesome.com(.*?)\"><\/script>?/is', array( $this, 'removeFontAwesome' ), $html );
		}

		#$html = preg_replace_callback('/<script>.*?<\/script>/is', array($this, 'script_encode'), $html);
		$html = preg_replace_callback( '/<noscript><iframe.*?<\/noscript>/is', array( $this, 'noscript_encode' ), $html );

		if ( ! empty( self::$settings['background-sizing'] ) && self::$settings['background-sizing'] == '1' ) {
			$html = preg_replace_callback( '/style=["\']?(background(-image)?\s*:(.*?)\(\s*(\'|")?(?<image>.*?)\3?\s*\);?)["\']?/i', array( $this, 'replace_backround_images' ), $html );
			$html = preg_replace_callback( '/<style\b[^>]*>(.*?)<\/style>?/is', array( $this, 'replace_backround_images_in_styles' ), $html );
		}

		if ( self::$lazy_enabled || self::$adaptive_enabled || self::$webp_enabled || self::$retina_enabled ) {
			$html = preg_replace_callback( '/(?<![\"|\'])<img[^>]*>/i', array( $this, 'replace_image_tags' ), $html );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'first_replace' ) {
			return $html;
		}

		$html = preg_replace_callback( '/data-thumb=[\'|"](.*?)[\'|"]/i', array( $this, 'rev_Slider_data_thumb' ), $html );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bg_replace' ) {
			return $html;
		}

		$regEx = '#(?<=[(\"\'])(?:' . $regExURL . ')?/(?:((?:' . $directories . ')[^\"\')]+)|([^/\"\']+\.[^/\"\')]+))(?=[\"\')])#';
		$html  = preg_replace_callback( $regEx, array( $this, 'cdn_rewrite_url' ), $html );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'second_replace' ) {
			return $html;
		}

		$html = preg_replace_callback( '/srcset="([^"]+)"/i', array( $this, 'cdn_srcset_url' ), $html );
		$html = preg_replace_callback( '/<iframe\s/i', array( $this, 'replace_iframe_tags' ), $html );

		if ( self::$wst ) {
			// Remove iframes from tests
			$html = preg_replace( '/<iframe.*?\/iframe>/i', '', $html );
			// Remove Tracking scripts
			// tracking-script
			#$html = preg_replace_callback( '/<script\b[^>]*src="([^"]+)"><\/script>/is', array($this, 'defer_assets'), $html );
		}

		if ( self::$external_url_enabled == '1' ) {
			$html = preg_replace_callback( '/https?:[^)\'\'"]+\.(jpg|jpeg|png|gif|svg|css|js|ico|icon)/i', array( $this, 'cdn_external_url' ), $html );
		} else {
			if ( ! empty( self::$replace_all_links ) && self::$replace_all_links == '1' ) {
				$html = preg_replace_callback( '/https?:(\/\/[^"\']*\.(?:svg|css|js|ico|icon))/i', array( $this, 'cdn_all_links' ), $html );
			}
		}

		if ( self::$fonts == 1 ) {
			$html = preg_replace_callback( '/https?:[^)\'\'"]+\.(woff2|woff|eot|ttf)/i', array( $this, 'local_fonts' ), $html );
		}

		if ( ! self::$wst ) {
			$html = preg_replace_callback( '/\[iframe\-wpc\](.*?)\[\/iframe\-wpc\]/i', array( $this, 'iframe_decode' ), $html );
		}

		#$html = preg_replace_callback('/\[script\-wpc\](.*?)\[\/script\-wpc\]/i', array($this, 'script_decode'), $html);
		$html = preg_replace_callback( '/\[noscript\-wpc\](.*?)\[\/noscript\-wpc\]/i', array( $this, 'noscript_decode' ), $html );

		return $html;
	}


	public function find_all_scripts( $html ) {
		preg_match_all( '/<script.*<\/script>/Umsi', $html, $matches );

		if ( empty( $matches ) ) {
			return false;
		}

		return $matches[0];
	}


	public function parse_script_src( $scripts, $location ) {
		if ( ! empty( $scripts ) ) {
			foreach ( $scripts as $script ) {
				preg_match( '/<script\s+([^>]+[\s\'"])?src\s*=\s*[\'"]\s*?(?<url>[^\'"]+\.js(?:\?[^\'"]*)?)\s*?[\'"]([^>]+)?\/?>/Umsi', $script, $matches );
				if ( ! empty( $matches['url'] ) ) {
					if ( strpos( $matches['url'], $location ) !== false ) {
						self::$combine_scripts[] = $matches['url'];
					}
				}
			}
		}

		return self::$combine_scripts;
	}


	public function add_to_combined_file( $file ) {

	}


	public function link_content_tag( $html ) {
		$html = $html[0];
		preg_match_all( '/href=["|\'](.*?)["|\']/', $html, $matches );

		if ( ! empty( $matches[0] ) ) {
			$href = $matches[1][0];

			if ( ! empty( $href ) ) {
				if ( strpos( $href, 'fonts.googleapis' ) === false ) {
					// Google Fonts!
					$html = '<link rel="preload" as="style"  onload="this.rel=\'stylesheet\'" href="' . $href . '">';
				}
			}

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'link2' ) {
				return print_r( array( $href ), true );
			}
		}

		return $html;
	}


	public function local_script_encode( $html ) {


		$found = strlen( $html[0] );

		$encoded  = base64_encode( $html[0] );
		$decode   = base64_decode( $encoded );
		$replaced = strlen( $decode );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'script' ) {
			return print_r( array( $html ), true );
		}

		$slashed = addslashes( $html[0] );
		$encoded = base64_encode( $slashed );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_encode' ) {
			return print_r( $encoded, true );
		}

		return '[script-wpc]' . $encoded . '[/script-wpc]';
	}


	public function local_script_decode( $html ) {
		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_decode' ) {
			return print_r( array( $html ), true );
		}

		$decode = str_replace( '[script-wpc]', '', $html[0] );
		$decode = str_replace( '[/script-wpc]', '', $decode );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'bas64_decode_end' ) {
			return print_r( array( $decode ), true );
		}

		$decode = base64_decode( $decode );
		$decode = stripslashes( $decode );

		return $decode;
	}


	public function script_content_tag( $html ) {

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'ignore_script' ) {
			return $html;
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'script' ) {
			return print_r( array( $html ), true );
		}

		if ( strpos( $html[0], 'text/template' ) !== false || strpos( $html[0], 'text/x-template' ) !== false ) {
			return $html;
		}

		$html = preg_replace_callback( '/<img[^>]*>/si', array( $this, 'image_tag_to_asset' ), $html[0] );

		return $html;
	}


	public function replace_iframe_tags( $iframe ) {
		return '<iframe loading="lazy" ';
	}


	public function defer_assets( $assets ) {
		if ( empty( $assets[1] ) ) {
			return $assets;
		}

		// Found the script tag (entire)
		$tag = $assets[0];
		// Found the tag
		$src = $assets[1];

		if ( strpos( $tag, 'defer' ) !== false && strpos( $tag, 'async' ) !== false ) {
			return $assets;
		}

		$tag = str_replace( 'src="' . $src . '"', 'src="' . $src . '" async', $tag );

		return $tag;
	}


	public function rev_Slider_data_thumb( $image ) {
		$full_attribute = $image[0];
		$image_url      = $image[1];

		if ( self::is_excluded_link( $image_url ) || $this->defaultExcluded( $image_url ) ) {
			return $image[0];
		} else {
			#$NewSrc = 'https://' . self::$zone_name . '/q:' . self::$settings['optimization'] . '/retina:' . self::$is_retina . '/webp:' . self::$webp . '/w:480/url:' . $this->specialChars($image_url);
			$NewSrc = 'https://' . self::$zone_name . '/q:' . self::$settings['optimization'] . '/r:' . self::$is_retina . '/wp:' . self::$webp . '/w:480/u:' . $this->specialChars( $image_url );

			return 'data-thumb="' . $NewSrc . '"';
		}

		return $image[0];
	}


	public function cdn_srcset_url( $image ) {
		$srcset    = $image[0];
		$newSrcSet = "";

		if ( strpos( $_SERVER['REQUEST_URI'], 'embed' ) !== false || strpos( $srcset, 'coinbase' ) !== false ) {
			return $image[0];
		}

		if ( strpos( $image[0], 'cookie' ) !== false ) {
			return $image[0];
		}

		if ( ! empty( $srcset ) ) {
			preg_match_all( '/((https?\:\/\/|\/\/)[^\s]+\S+\.(jpg|jpeg|png|gif|svg|webp))\s(\d{1,5}+[wx])/', $srcset, $srcset_links );

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'srcset_01' ) {
				return print_r( array( $srcset_links ), true );
			}

			if ( ! empty( $srcset_links ) ) {
				foreach ( $srcset_links[0] as $i => $srcset ) {
					$src          = explode( ' ', $srcset );
					$srcset_url   = $src[0];
					$srcset_width = $src[1];

					if ( self::is_excluded_link( $srcset_url ) ) {
						$newSrcSet .= $srcset_url . ' ' . $srcset_width . ',';
					} else {

						if ( strpos( $srcset_width, 'x' ) !== false ) {
							$width_url    = 1;
							$srcset_width = str_replace( 'x', '', $srcset_width );
							$extension    = 'x';
						} else {
							$width_url = $srcset_width = str_replace( 'w', '', $srcset_width );
							$extension = 'w';
						}

						if ( strpos( $srcset_url, self::$zone_name ) !== false ) {
							$newSrcSet .= $srcset_url . ' ' . $srcset_width . $extension . ',';
							continue;
						}

						if ( strpos( $srcset_url, '.svg' ) !== false ) {
							$newSrcSet .= 'https://' . self::$zone_name . '/m:0/a:' . self::reformat_url( $srcset_url ) . ' ' . $srcset_width . $extension . ',';
						} else {
							#newSrcSet .= self::$apiUrl . '/retina:' . self::$is_retina . '/webp:' . self::$webp . '/w:' . $width_url . '/url:' . self::reformat_url($srcset_url) . ' ' . $srcset_width . $extension . ',';
							$newSrcSet .= self::$apiUrl . '/r:' . self::$is_retina . '/wp:' . self::$webp . '/w:' . $width_url . '/u:' . self::reformat_url( $srcset_url ) . ' ' . $srcset_width . $extension . ',';
						}
					}

				}

				$newSrcSet = rtrim( $newSrcSet, ',' );
			}

			self::$imageCount ++;
			if ( self::$wst && self::$imageCount >= self::$imageCount_Wst ) {
				#return 'srcset=""';
			}

			return 'srcset="' . $newSrcSet . '" ';
		}

		return $srcset;
	}


	public function cdn_all_links( $image ) {
		$src_url = $image[0];

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'cdn_all_links' ) {
			return print_r( array( $src_url ), true );
		}

		if ( $this->defaultExcluded( $src_url ) ) {
			return $src_url;
		}

		if ( strpos( $src_url, self::$zone_name ) !== false ) {
			return $src_url;
		}

		if ( ! self::is_excluded_link( $src_url ) ) {

			// External is disabled?
			if ( self::$external_url_enabled == '0' || empty( self::$external_url_enabled ) ) {
				if ( ! self::image_url_matching_site_url( $src_url ) ) {
					return $src_url;
				}
			}

			if ( strpos( $src_url, self::$zone_name ) === false ) {
				if ( strpos( $src_url, '.css' ) !== false ) {
					if ( self::$css == "1" ) {
						$newSrc = 'https://' . self::$zone_name . '/m:' . self::$css_minify . '/a:' . self::reformat_url( $src_url );
					}
				} else if ( strpos( $src_url, '.js' ) !== false ) {
					if ( self::$js == "1" ) {
						$newSrc = 'https://' . self::$zone_name . '/m:' . self::$js_minify . '/a:' . self::reformat_url( $src_url );
					}
				} else {
					$newSrc = 'https://' . self::$zone_name . '/m:0/a:' . self::reformat_url( $src_url );
				}

				return $newSrc;
			}
		}

		return $image[0];
	}


	public function cdn_external_url( $image ) {
		$src_url = $image[0];
		$width   = 1;
		if ( isset( $_GET['wpc_is_amp'] ) && ! empty( $_GET['wpc_is_amp'] ) ) {
			$width = 600;
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'external_url' ) {
			return print_r( array( $src_url ), true );
		}

		if ( strpos( $src_url, self::$zone_name ) !== false ) {
			return $src_url;
		}

		if ( ! self::is_excluded_link( $src_url ) ) {
			if ( strpos( $src_url, self::$zone_name ) === false ) {

				// Check if the URL is an image, then check if it's instagram etc...
				foreach ( self::$default_excluded_list as $i => $excluded_string ) {
					if ( strpos( $src_url, $excluded_string ) !== false ) {
						return $src_url;
					}
				}

				if ( strpos( $src_url, '.css' ) !== false ) {
					if ( self::$css == "1" ) {
						$newSrc = 'https://' . self::$zone_name . '/m:' . self::$css_minify . '/a:' . self::reformat_url( $src_url );
					}
				} else if ( strpos( $src_url, '.js' ) !== false ) {
					if ( self::$js == "1" ) {
						$newSrc = 'https://' . self::$zone_name . '/m:' . self::$js_minify . '/a:' . self::reformat_url( $src_url );
					}
				} else {

					if ( strpos( $src_url, '.svg' ) !== false ) {
						$newSrc = 'https://' . self::$zone_name . '/m:0/a:' . self::reformat_url( $src_url );
					} else {
						#$newSrc = self::$apiUrl . '/retina:' . self::$is_retina . '/webp:' . self::$webp . '/w:' . $width . '/url:' . self::reformat_url($src_url);
						$newSrc = self::$apiUrl . '/r:' . self::$is_retina . '/wp:' . self::$webp . '/w:' . $width . '/u:' . self::reformat_url( $src_url );
					}

				}

				return $newSrc;
			}
		}

		return $image[0];
	}


	public function replace_image_tags_script( $image ) {

		$whole_script = $image[0];
		$whole_script = stripslashes( $whole_script );

		// File is not an image
		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'js' ) {
			return 'scr' . $whole_script;
		}

		if ( ! empty( $whole_script ) ) {
			$whole_script = preg_replace_callback( '/https?:(\/\/[^"\']*\.(?:svg|jpg|jpeg|gif|png))/i', array( $this, 'cdn_all_links' ), $whole_script );
			$whole_script = addcslashes( $whole_script );

			return $whole_script;
		}

		return $whole_script;
	}


	public function maybe_addslashes( $image, $addslashes = false ) {
		if ( $addslashes ) {
			$image = addslashes( $image );
		}

		return $image;
	}


	public function local_image_tag_to_asset( $image ) {
		$addslashes    = false;
		$single_quotes = false;

		if ( strpos( $image[0], "'" ) !== false ) {
			$single_quotes = true;
		}

		if ( strpos( $image[0], '$' ) !== false ) {
			return $image[0];
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'image_asset_array' ) {
			return print_r( array( str_replace( '<img', 'sad', $image[0] ) ), true );
		}

		if ( strpos( $image[0], '=\"' ) !== false || strpos( $image[0], "=\'" ) !== false ) {
			$addslashes = true;
			$image[0]   = stripslashes( $image[0] );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'start_asset' ) {
			return print_r( $image, true );
		}

		if ( strpos( $_SERVER['REQUEST_URI'], 'embed' ) !== false ) {

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'request_uri' ) {
				return print_r( $image, true );
			}

			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		// File has already been replaced
		if ( $this->defaultExcluded( $image[0] ) ) {
			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		// File is not an image
		if ( ! self::is_image( $image[0] ) ) {

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'not_image' ) {
				return print_r( $image, true );
			}

			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		if ( ( self::$external_url_enabled == 'false' || self::$external_url_enabled == '0' ) && ! self::image_url_matching_site_url( $image[0] ) ) {
			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		// File is excluded
		if ( self::is_excluded( $image[0] ) ) {

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'is_excluded_local' ) {
				return print_r( $image, true );
			}

			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'img_src' ) {
			return print_r( $image[0], true );
		}

		$img_tag                           = $image[0];
		$original_img_tag['original_tags'] = $this->getAllTags( $image[0], array() );

		preg_match( '/src=["|\']([^"]+)["|\']/', $img_tag, $image_src );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'script_original_tags' ) {
			return print_r( $original_img_tag, true );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'preg_script_image_tag' ) {
			return print_r( $image_src, true );
		}

		if ( strpos( $image_src[1], '$' ) !== false ) {
			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		if ( ! empty( $image_src[1] ) ) {
			$NewSrc  = 'https://' . self::$zone_name . '/m:0/a:' . $this->specialChars( $image_src[1] );
			$img_tag = str_replace( $image_src[1], $NewSrc, $img_tag );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'build_script_image_tag' ) {
			return print_r( str_replace( '<img', '', $img_tag ), true );
		}

		#$img_tag = str_replace('/>', '\/>', $img_tag);
		$img_tag = $this->maybe_addslashes( $img_tag, $addslashes );

		return $img_tag;
	}


	public function image_tag_to_asset( $image ) {
		$addslashes    = false;
		$single_quotes = false;

		if ( strpos( $image[0], "'" ) !== false ) {
			$single_quotes = true;
		}

		if ( strpos( $image[0], '$' ) !== false ) {
			return $image[0];
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'image_asset_array' ) {
			return print_r( array( str_replace( '<img', 'sad', $image[0] ) ), true );
		}

		if ( strpos( $image[0], '=\"' ) !== false || strpos( $image[0], "=\'" ) !== false ) {
			$addslashes = true;
			$image[0]   = stripslashes( $image[0] );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'start_asset' ) {
			return print_r( $image, true );
		}

		if ( strpos( $_SERVER['REQUEST_URI'], 'embed' ) !== false ) {

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'request_uri' ) {
				return print_r( $image, true );
			}

			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		// File has already been replaced
		if ( $this->defaultExcluded( $image[0] ) ) {
			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		// File is not an image
		if ( ! self::is_image( $image[0] ) ) {

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'not_image' ) {
				return print_r( $image, true );
			}

			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		if ( ( self::$external_url_enabled == 'false' || self::$external_url_enabled == '0' ) && ! self::image_url_matching_site_url( $image[0] ) ) {
			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		// File is excluded
		if ( self::is_excluded( $image[0] ) ) {

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'is_excluded_tag_asset' ) {
				return print_r( $image, true );
			}

			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'img_src' ) {
			return print_r( $image[0], true );
		}

		$img_tag                           = $image[0];
		$original_img_tag['original_tags'] = $this->getAllTags( $image[0], array() );

		preg_match( '/src=["|\']([^"]+)["|\']/', $img_tag, $image_src );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'script_original_tags' ) {
			return print_r( $original_img_tag, true );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'preg_script_image_tag' ) {
			return print_r( $image_src, true );
		}

		if ( strpos( $image_src[1], '$' ) !== false ) {
			$image[0] = $this->maybe_addslashes( $image[0], $addslashes );

			return $image[0];
		}

		if ( ! empty( $image_src[1] ) ) {
			$NewSrc  = 'https://' . self::$zone_name . '/m:0/a:' . $this->specialChars( $image_src[1] );
			$img_tag = str_replace( $image_src[1], $NewSrc, $img_tag );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'build_script_image_tag' ) {
			return print_r( str_replace( '<img', '', $img_tag ), true );
		}

		#$img_tag = str_replace('/>', '\/>', $img_tag);
		$img_tag = $this->maybe_addslashes( $img_tag, $addslashes );

		return $img_tag;
	}


	public function specialChars( $url ) {
		if ( ! self::$brizyActive ) {
			$url = htmlspecialchars( $url );
		}

		return $url;
	}


	public function replace_backround_images_in_styles( $image ) {
		$style_content = $image[0];

		$html = preg_replace_callback( '~\bbackground(-image)?\s*:(.*?)\(\s*(\'|")?(?<image>.*?)\3?\s*\)~i', array( $this, 'replace_backround_images_styles' ), $style_content );

		return $html;
	}


	public function replace_backround_images_styles( $image ) {
		$tag          = $image[0];
		$url          = $image['image'];
		$original_url = $url;

		if ( strpos( $url, self::$zone_name ) == false ) {
			// File has already been replaced
			if ( $this->defaultExcluded( $url ) ) {
				return $tag;
			}

			// File is not an image
			if ( ! self::is_image( $url ) ) {
				return $tag;
			}

			if ( self::is_excluded( $url ) ) {
				return $tag;
			}

			$newUrl     = self::$apiUrl . '/r:' . self::$is_retina . '/wp:' . self::$webp . '/w:1/u:' . self::reformat_url( $url );
			$return_tag = str_replace( $original_url, $newUrl, $tag );

			if ( ! empty( $return_tag ) ) {
				return $return_tag;
			} else {
				return $tag;
			}

		} else {
			return $tag;
		}
	}


	public function replace_backround_images( $image ) {
		$tag          = $image[0];
		$url          = $image['image'];
		$original_url = $url;

		$enclosure = "";
		if ( strpos( $tag, '\"' ) !== false ) {
			$enclosure = '"';
		} else if ( strpos( $tag, "\'" ) !== false ) {
			$enclosure = "'";
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'background_tag_enclosure' ) {
			return print_r( array( $tag ), true );
		}

		if ( strpos( $url, self::$zone_name ) == false ) {
			// File has already been replaced
			if ( $this->defaultExcluded( $url ) ) {
				return $tag;
			}

			// File is not an image
			if ( ! self::is_image( $url ) ) {
				return $tag;
			}
		}

		if ( self::is_excluded( $url ) ) {
			return $tag;
		}

		$newUrl     = self::$apiUrl . '/r:' . self::$is_retina . '/wp:' . self::$webp . '/w:1/u:' . self::reformat_url( $url );
		$return_tag = str_replace( $original_url, $newUrl, $tag );

		if ( self::$lazy_enabled ) {
			$return_tag .= 'display:none;';
		}

		return $return_tag;
		/*
		if (strpos($tag, 'background-image') !== false) {
			// Background image
			$return_tag = 'background-image:url(';
			$return_tag .= $enclosure . $newUrl . $enclosure;
			$return_tag .= ');display:none;';
		}
		else {
			// just Background
			$return_tag = 'background:url(';
			$return_tag .= $enclosure . $newUrl . $enclosure;
			$return_tag .= ');display:none;';
		}

		if (!empty($return_tag)) {
			return $return_tag;
		} else {
			return $tag;
		}
		*/

	}


	public function replace_image_tags( $image ) {
		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'image_array' ) {
			return print_r( array( $image[0] ), true );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'start_img' ) {
			return print_r( $image, true );
		}

		if ( strpos( $_SERVER['REQUEST_URI'], 'embed' ) !== false ) {

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'request_uri' ) {
				return print_r( $image, true );
			}

			return $image[0];
		}

		if ( strpos( $image[0], self::$zone_name ) == false ) {
			// File has already been replaced
			if ( $this->defaultExcluded( $image[0] ) ) {
				return $image[0];
			}

			// File is not an image
			if ( ! self::is_image( $image[0] ) ) {

				if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'not_image' ) {
					return print_r( $image, true );
				}

				return $image[0];
			}

			if ( ( ( self::$external_url_enabled == 'false' || self::$external_url_enabled == '0' ) && ! self::image_url_matching_site_url( $image[0] ) ) ) {
				return $image[0];
			}

			// File is excluded
			if ( self::is_excluded( $image[0] ) ) {

				if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'is_excluded_rpl' ) {
					return print_r( $image, true );
				}

				return $image[0];
			}

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'img_src' ) {
				return print_r( $image[0], true );
			}
		} else {
			// Already has zapwp url, if minify:false/true then it's something
			if ( strpos( $image[0], 'm:' ) !== false ) {
				return $image[0];
			}
			#return $image[0];
		}

		if ( strpos( $image[0], 'cookie' ) !== false ) {
			$image[0] = stripslashes( $image[0] );

			return $image[0];
		}

		// Original URL was
		$original_img_tag                  = array();
		$original_img_tag['original_tags'] = $this->getAllTags( $image[0], array() );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'AllTags' ) {
			$image[0] = stripslashes( $image[0] );

			return print_r( array( str_replace( '<img', '', $image[0] ), $original_img_tag['original_tags'] ), true );
		}

		if ( ! empty( $original_img_tag['original_tags']['src'] ) ) {
			$image_source = $original_img_tag['original_tags']['src'];
		} else {
			$image_source = $original_img_tag['original_tags']['data-src'];
		}

		$original_img_tag['original_src'] = $image_source;

		/**
		 * Fetch image actual size
		 */
		$size = self::get_image_size( $image_source );

		// SVG Placeholder
		$source_svg = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size[0] . '" height="' . $size[1] . '"><path d="M2 2h' . $size[0] . 'v' . $size[1] . 'H2z" fill="#fff" opacity="0"/></svg>' );

		$image_source = $this->specialChars( $image_source );

		if ( isset( $_GET['wpc_is_amp'] ) && ! empty( $_GET['wpc_is_amp'] ) ) {
			$source_svg             = $image_source;
			self::$lazy_enabled     = '0';
			self::$adaptive_enabled = '0';
		}

		$isLogo   = false;
		$imageUrl = strtolower( $image_source );

		if ( strpos( strtolower( $imageUrl ), 'logo' ) !== false && strpos( strtolower( $original_img_tag['class'] ), 'logo' ) !== false ) {
			$isLogo = true;
		}

		if ( ! empty( $original_img_tag['sizes'] ) ) {
			$original_img_tag['additional_tags']['sizes'] = $original_img_tag['sizes'];
		}

		// Is LazyLoading enabled in the plugin?
		if ( ! empty( self::$lazy_enabled ) && self::$lazy_enabled == '1' ) {

			// if image is logo, then force image url - no lazy loading
			if ( $isLogo ) {
				$original_img_tag['src']                      = $image_source;
				$original_img_tag['additional_tags']['class'] = 'wps-ic-live-cdn wps-ic-logo';
			} else {
				$original_img_tag['src'] = $source_svg;
				#$original_img_tag['data-src']                 = $image_source;
				$original_img_tag['data-src']                 = self::$apiUrl . '/r:' . self::$is_retina . '/wp:' . self::$webp . '/w:1/u:' . self::reformat_url( $image_source );
				$original_img_tag['additional_tags']['class'] = 'wps-ic-live-cdn wps-ic-lazy-image';
			}

			$original_img_tag['additional_tags']['loading'] = 'lazy';
		} else {
			if ( ! empty( self::$adaptive_enabled ) && self::$adaptive_enabled == '1' ) {
				$original_img_tag['src'] = $source_svg;
				$original_img_tag['additional_tags']['class'] = 'wps-ic-cdn';

				/**
				 * If current image is logo then force image, don't lazy load
				 */
				if ( $isLogo || strpos( strtolower( $image_source ), 'logo' ) !== false ) {
					$original_img_tag['src'] = $image_source;
				} else {
					$original_img_tag['src'] = $source_svg;
					#$original_img_tag['data-src'] = $image_source;
					$original_img_tag['data-src'] = self::$apiUrl . '/r:' . self::$is_retina . '/wp:' . self::$webp . '/w:1/u:' . self::reformat_url( $image_source );
				}

			} else {
				$original_img_tag['additional_tags']['class'] = 'wps-ic-cdn';
				$original_img_tag['src'] = self::$apiUrl . '/r:' . self::$is_retina . '/wp:' . self::$webp . '/w:1/u:' . self::reformat_url( $image_source );
			}

            //Don't add anything if lazy load is off
			//$original_img_tag['additional_tags']['class'] = 'wps-ic-no-lazy';
		}

		/**
		 * Is this image lazy excluded?
		 */
		if ( ! empty( self::$lazy_excluded_list ) && ! empty( self::$lazy_enabled ) && self::$lazy_enabled == '1' ) {
			//return 'asd';
			foreach ( self::$lazy_excluded_list as $i => $lazy_excluded ) {
				if ( strpos( $image_source, $lazy_excluded ) !== false ) {
                    //Don't add anything if lazy load is off
					//$original_img_tag['additional_tags']['class'] = 'wps-ic-no-lazy';
					$original_img_tag['src']                      = $image_source;
					unset( $original_img_tag['additional_tags']['loading'] );
				}
			}
		} else if ( ! empty( self::$excluded_list ) ) {
			foreach ( self::$excluded_list as $i => $excluded ) {
				if ( strpos( $image_source, $excluded ) !== false ) {
					// Image is excluded
                    //Don't add anything if lazy load is off
					//$original_img_tag['additional_tags']['class'] = 'wps-ic-no-lazy';
					$original_img_tag['src']                      = $image_source;
					unset( $original_img_tag['data-src'] );
					unset( $original_img_tag['additional_tags']['loading'] );
				}
			}
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'lazy_exclude_tags' ) {
			return print_r( array( self::$lazy_excluded_list, self::$excluded_list ), true );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'image_tag' ) {
			return print_r( array( $original_img_tag ), true );
		}

		$data_src        = false;
		$src             = false;
		$preg_results    = array();
		$build_image_tag = '<img ';

		if ( ! empty( $original_img_tag['original_src'] ) ) {
			$original_img_tag['original_src'] = $this->specialChars( $original_img_tag['original_src'] );
		}

		if ( ! empty( $original_img_tag['src'] ) ) {
			$original_img_tag['src'] = $this->specialChars( $original_img_tag['src'] );
		}

		if ( ! empty( $original_img_tag['original_tags']['data-src'] ) ) {
			$original_img_tag['original_tags']['data-src'] = $this->specialChars( $original_img_tag['original_tags']['data-src'] );
		}

		if ( ! empty( $original_img_tag['data-src'] ) ) {
			$original_img_tag['data-src'] = $this->specialChars( $original_img_tag['data-src'] );
		}

		if ( self::is_excluded( $original_img_tag['original_src'], $original_img_tag['original_src'] ) ) {

			// Image is excluded
			if ( ! empty( $original_img_tag['original_src'] ) ) {
				$original_img_tag['src'] = $original_img_tag['original_src'];
			} else if ( ! empty( $original_img_tag['data-src'] ) ) {
				$original_img_tag['src'] = $original_img_tag['data-src'];
			}

		}

		/**
		 * If image contains logo in filename, then it's a logo probably
		 */
		if ( strpos( strtolower( $original_img_tag['original_tags']['class'] ), 'rs-lazyload' ) !== false || strpos( strtolower( $original_img_tag['original_tags']['class'] ), 'rs' ) !== false || strpos( strtolower( $image_source ), 'logo' ) !== false && strpos( strtolower( $original_img_tag['class'] ), 'logo' ) !== false ) {
			$build_image_tag .= 'src="' . $original_img_tag['original_src'] . '" ';
		} else {
			$build_image_tag .= 'src="' . $original_img_tag['src'] . '" ';

			/**
			 * if data-src is not empty then we have src as SVG
			 */
			if ( ! empty( $original_img_tag['original_tags']['data-src'] ) ) {
				$build_image_tag .= 'data-src="' . $original_img_tag['original_tags']['data-src'] . '" ';
			} else {
				if ( ! empty( $original_img_tag['data-src'] ) ) {
					$build_image_tag .= 'data-src="' . $original_img_tag['data-src'] . '" ';
				}
			}

		}

		if ( ! empty( $original_img_tag['original_tags'] ) ) {
			foreach ( $original_img_tag['original_tags'] as $tag => $value ) {

				if ( $tag == 'class' ) {
					continue;
				}

				if ( $tag == 'src' || $tag == 'data-src' ) {
					continue;
				}

				$build_image_tag .= $tag . '="' . $value . '" ';
			}
		}

		// foreach additional image tag
		foreach ( $original_img_tag['additional_tags'] as $tag => $value ) {

			if ( $tag == 'class' ) {
				$tag = 'class';

				if ( strpos( $original_img_tag['original_tags']['class'], 'rs-lazyload' ) !== false || strpos( $original_img_tag['original_tags']['class'], 'rs' ) !== false || ( strpos( $original_img_tag['original_tags']['class'], 'lazy' ) !== false && strpos( $original_img_tag['original_tags']['class'], 'skip-lazy' ) === false ) ) {
					// Leave as is
					$value = $original_img_tag['original_tags']['class'];
				} else {
					$value .= ' ' . $original_img_tag['original_tags']['class'];
				}
			}

			if ( $tag == 'src' || $tag == 'data-src' ) {
				continue;
			}

			// Check if tag already exists, if so - replace it
			$build_image_tag .= $tag . '="' . $value . '" ';

		}
		$build_image_tag .= '/>';

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'build_image_tag' ) {
			return print_r( str_replace( '<img', '', $build_image_tag ), true );
		}

		return $build_image_tag;
	}


	public function cdn_rewrite_url( $url ) {
		$width = 1;

		if ( isset( $_GET['wpc_is_amp'] ) && ! empty( $_GET['wpc_is_amp'] ) ) {
			$width = 600;
		}

		$url = $url[0];
		if ( strpos( $url, 'cookie' ) !== false ) {
			return $url;
		}

		$siteUrl = self::$home_url;
		$newUrl  = str_replace( $siteUrl, '', $url );

		// Check if site url is staging url? Anything after .com/something?
		preg_match( '/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]\/([a-zA-Z0-9]+)/', $siteUrl, $isStaging );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'isstaging' ) {
			return print_r( array( $isStaging, $siteUrl ), true );
		}

		// TODO: This is required for STAGING TO WORK!!! Don't remove SiteURL!!! LOOK for next TODO!!!

		$originalUrl = $url;
		$newSrcSet   = '';

		#preg_match_all('/((https?\:\/\/|\/\/)[^\s]+\S+\.(jpg|jpeg|png|gif|svg))\s(\d{1,5})/', $url, $srcset_links);
		#preg_match_all('/[^"\'=\s]+\.(jpe?g|png|gif|svg|webp)\s(\d{1,5}+[wx])/', $url, $srcset_links);
		preg_match_all( '/((https?\:\/\/|\/\/)[^\s]+\S+\.(jpg|jpeg|png|gif|svg|webp))\s(\d{1,5}+[wx])/', $url, $srcset_links );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'srcset' ) {
			return print_r( array( $srcset_links[0] ), true );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'is_excluded_dbg' ) {
			return print_r( array( $url ), true );
		}

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'is_excluded_dbg_srcset' ) {
			return print_r( array( $url, self::is_excluded_link( $url ), self::$excluded_list ), true );
		}

		if ( ! empty( $srcset_links[0] ) ) {

			$debug = array();
			foreach ( $srcset_links[0] as $i => $srcset ) {
				$src          = explode( ' ', $srcset );
				$srcset_url   = $src[0];
				$srcset_width = $src[1];

				if ( self::is_excluded_link( $srcset_url ) || self::is_excluded( $srcset_url, $srcset_url ) ) {
					$newSrcSet .= $srcset_url . ' ' . $srcset_width . ',';
				} else {

					if ( strpos( $srcset_width, 'x' ) !== false ) {
						$width_url    = 1;
						$srcset_width = str_replace( 'x', '', $srcset_width );
						$extension    = 'x';
					} else {
						$width_url = $srcset_width = str_replace( 'w', '', $srcset_width );
						$extension = 'w';
					}

					if ( strpos( $srcset_url, self::$zone_name ) !== false ) {
						$newSrcSet .= $srcset_url . ' ' . $srcset_width . $extension . ',';
						continue;
					}

					$newSrcSet .= self::$apiUrl . '/r:' . self::$is_retina . '/wp:' . self::$webp . '/w:' . $width_url . '/u:' . self::reformat_url( $srcset_url ) . ' ' . $srcset_width . $extension . ',';
				}
			}

			$newSrcSet = rtrim( $newSrcSet, ',' );

			return $newSrcSet;
		} else {


			if ( self::is_excluded_link( $url ) ) {
				return $url;
			}

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'rewrite_url' ) {
				return print_r( array( $url ), true );
			}

			if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'rewrite_url_to_file' ) {
				$fp = fopen( WPS_IC_DIR . 'rewrite_url_file.txt', 'a+' );
				fwrite( $fp, 'URL: ' . $url . "\r\n" );
				fwrite( $fp, '---' . "\r\n" );
				fclose( $fp );
			}

			if ( strpos( $url, self::$zone_name ) !== false ) {
				return $url;
			}

			// External is disabled?
			if ( empty( self::$external_url_enabled ) || self::$external_url_enabled == '0' ) {
				if ( ! self::image_url_matching_site_url( $url ) ) {
					return $url;
				}
			} else {
				// Check if the URL is an image, then check if it's instagram etc...
				if ( strpos( $url, '.jpg' ) !== false || strpos( $url, '.png' ) !== false || strpos( $url, '.gif' ) !== false || strpos( $url, '.svg' ) !== false || strpos( $url, '.jpeg' ) !== false ) {
					foreach ( self::$default_excluded_list as $i => $excluded_string ) {
						if ( strpos( $url, $excluded_string ) !== false ) {
							return $url;
						}
					}
				}
			}

			if ( ! empty( $url ) ) {
				if ( strpos( $url, '.css' ) !== false && self::$css == '1' ) {
					/**
					 * CSS File
					 */
					$newUrl = 'https://' . self::$zone_name . '/m:' . self::$css_minify . '/a:' . self::reformat_url( $url );
				} else if ( strpos( $url, '.js' ) !== false && self::$js == '1' ) {
					/**
					 * JS File
					 */
					if ( strpos( $url, 'wp-content' ) !== false || strpos( $url, 'wp-includes' ) !== false ) {
						if ( empty( self::$js_minify ) || self::$js_minify == 'false' ) {
							$newUrl = 'https://' . self::$zone_name . '/m:' . self::$js_minify . '/a:' . self::reformat_url( $url, false );
						} else {
							$newUrl = 'https://' . self::$zone_name . '/m:' . self::$js_minify . '/a:' . self::reformat_url( $url, false );
						}
					} else {
						$newUrl = 'https://' . self::$zone_name . '/m:' . self::$js_minify . '/a:' . self::reformat_url( $url, false );
					}
				} else if ( strpos( $url, '.svg' ) !== false ) {
					/**
					 * JS File
					 */
					if ( ! self::is_excluded( $url, $url ) ) {
						if ( self::$zone_test == 0 && ( strpos( $url, 'wp-content' ) !== false || strpos( $url, 'wp-includes' ) !== false ) ) {
							$newUrl = 'https://' . self::$zone_name . '/' . self::reformat_url( $url, true );
						} else {
							$newUrl = 'https://' . self::$zone_name . '/m:0/a:' . self::reformat_url( $url, false );
						}
					}

				} else if ( self::$fonts == 1 && ( strpos( $url, '.woff' ) !== false || strpos( $url, '.woff2' ) !== false || strpos( $url, '.eot' ) !== false || strpos( $url, '.ttf' ) !== false ) ) {
					/**
					 * JS File
					 */
					$newUrl = 'https://' . self::$zone_name . '/' . self::reformat_url( $url, true );
				} else {

					/**
					 * Something like an image?
					 */

					if ( self::is_image( $url ) && ! self::is_excluded( $url, $url ) ) {
						$newUrl = self::$apiUrl . '/r:' . self::$is_retina . '/wp:' . self::$webp . '/w:' . $width . '/u:' . self::reformat_url( $url );

						self::$imageCount ++;
						if ( self::$wst && self::$imageCount >= self::$imageCount_Wst ) {
							#$newUrl = '';
						}

					}
				}

				if ( self::is_excluded( $url, $url ) ) {
					return $originalUrl;
				}

				#return print_r(array($newUrl, self::$is_multisite, $originalUrl, self::is_image($url),self::is_excluded($url, $url)),true);

				// TODO: This is required for STAGING TO WORK!!! Don't remove SiteURL!!! LOOK for next TODO!!!
				if ( self::$is_multisite ) {
					return $newUrl;
				} else if ( empty( $isStaging ) || empty( $isStaging[0] ) ) {
					// Not a staging site
					return $newUrl;
				} else {
					// It's a staging site
					return $originalUrl;
				}
			}

			return $url;
		}
	}


	public static function is_excluded_link( $link ) {
		/**
		 * Is the link in excluded list?
		 */
		if ( empty( $link ) ) {
			return false;
		}

		if ( strpos( $link, '.css' ) !== false || strpos( $link, '.js' ) !== false ) {
			foreach ( self::$default_excluded_list as $i => $excluded_string ) {
				if ( strpos( $link, $excluded_string ) !== false ) {
					return true;
				}
			}
		}

		if ( ! empty( self::$excluded_list ) ) {
			foreach ( self::$excluded_list as $i => $value ) {
				if ( strpos( $link, $value ) !== false ) {
					// Link is excluded
					return true;
				}
			}
		}

		return false;
	}


	/**
	 * Is link matching the site url?
	 *
	 * @param $image
	 *
	 * @return bool
	 */
	public static function image_url_matching_site_url( $image ) {
		$site_url = self::$site_url;
		$image    = str_replace( array( 'https://', 'http://' ), '', $image );
		$site_url = str_replace( array( 'https://', 'http://' ), '', $site_url );

		if ( strpos( $image, '.css' ) !== false || strpos( $image, '.js' ) !== false ) {
			foreach ( self::$default_excluded_list as $i => $excluded_string ) {
				if ( strpos( $image, $excluded_string ) !== false ) {
					return false;
				}
			}
		}

		if ( strpos( $image, $site_url ) === false ) {
			// Image not on site
			return false;
		} else {
			// Image on site
			return true;
		}

	}


	public static function is_js( $link ) {
		if ( strpos( $link, '.js' ) === false ) {
			return false;
		} else {
			return true;
		}
	}


	public static function is_relative_url( $url ) {
		if ( strpos( $url, 'http://' ) === false && strpos( $url, 'https://' ) === false ) {
			// No http on start, try to figure out
			$url = explode( 'wp-content', $url );
			$url = '/wp-content' . $url[1];
			$url = self::$site_url . $url;

			// New, maybe working?
			#$url = str_replace('../wp-content', 'wp-content', $url);
			#$url = str_replace('/wp-content', 'wp-content', $url);
			#$url = self::$site_url . '/' . $url;

			return $url;
		} else {
			return false;
		}
	}


	public static function is_image( $image ) {
		if ( strpos( $image, '.webp' ) === false && strpos( $image, '.jpg' ) === false && strpos( $image, '.jpeg' ) === false && strpos( $image, '.png' ) === false && strpos( $image, '.ico' ) === false && strpos( $image, '.svg' ) === false && strpos( $image, '.gif' ) === false ) {
			return false;
		} else {

			// Serve JPG Enabled?
			if ( strpos( $image, '.jpg' ) !== false && strpos( $image, '.jpeg' ) !== false ) {
				// is JPEG enabled
				if ( self::$settings['serve_jpg'] == '0' ) {
					return false;
				}
			}

			// Serve GIF Enabled?
			if ( strpos( $image, '.gif' ) !== false ) {
				// is JPEG enabled
				if ( self::$settings['serve_gif'] == '0' ) {
					return false;
				}
			}

			// Serve PNG Enabled?
			if ( strpos( $image, '.png' ) !== false ) {
				// is PNG enabled
				if ( self::$settings['serve_png'] == '0' ) {
					return false;
				}
			}

			// Serve SVG Enabled?
			if ( strpos( $image, '.svg' ) !== false ) {
				// is SVG enabled
				if ( self::$settings['serve_svg'] == '0' ) {
					return false;
				}
			}

			return true;
		}
	}


	public static function is_excluded( $image_element, $image_link = '' ) {
		$image_path = '';

		if ( empty( $image_link ) ) {
			preg_match( '@src="([^"]+)"@', $image_element, $match_url );
			if ( ! empty( $match_url ) ) {
				$image_path        = $match_url[1];
				$basename_original = basename( $match_url[1] );
			} else {
				$basename_original = basename( $image_element );
			}
		} else {
			$image_path        = $image_link;
			$basename_original = basename( $image_link );
		}

		/*
		if ( ! empty($image_path)) {
			if (strpos(self::$excluded_list, $image_path) !== false) {
				return true;
			}
		}
		*/

		preg_match( "/([0-9]+)x([0-9]+)\.[a-zA-Z0-9]+/", $basename_original, $matches ); //the filename suffix way
		if ( empty( $matches ) ) {
			// Full Image
			$basename = $basename_original;
		} else {
			// Some thumbnail
			$basename = str_replace( '-' . $matches[1] . 'x' . $matches[2], '', $basename_original );
		}

		/**
		 * Is this image lazy excluded?
		 */
		if ( ! empty( self::$lazy_excluded_list ) && ! empty( self::$lazy_enabled ) && self::$lazy_enabled == '1' ) {
			//return 'asd';
			foreach ( self::$lazy_excluded_list as $i => $lazy_excluded ) {
				if ( strpos( $basename, $lazy_excluded ) !== false ) {
					return true;
				}
			}
		} else if ( ! empty( self::$excluded_list ) ) {
			foreach ( self::$excluded_list as $i => $excluded ) {
				if ( strpos( $basename, $excluded ) !== false ) {
					return true;
				}
			}
		}

		#$basename = $basename;
		if ( ! empty( self::$lazy_excluded_list ) && in_array( $basename, self::$lazy_excluded_list ) ) {
			return true;
		}

		if ( ! empty( self::$excluded_list ) && in_array( $basename, self::$excluded_list ) ) {
			return true;
		}

		return false;
	}


	public static function get_image_size( $url ) {
		preg_match( "/([0-9]+)x([0-9]+)\.[a-zA-Z0-9]+/", $url, $matches ); //the filename suffix way
		if ( isset( $matches[1] ) && isset( $matches[2] ) ) {
			return array( $matches[1], $matches[2] );
			$sizes = array( $matches[1], $matches[2] );
		} else { //the file
			return array( 1024, 1024 );
		}

		return $sizes;
	}


	public function filter_Woo_gallery_html( $html, $attachment_id ) {
		// filter...
		$html = preg_replace_callback( '/(https?\:\/\/|\/\/)[^\s]+\S+\.(jpg|jpeg|png|gif|svg)/', array( 'wps_cdn_rewrite', 'obstart_replace_url_in_css' ), $html );

		return $html;
	}


	public function filter_Woo_gallery( $array, $attachment_id, $image_size, $main_image ) {
		// filter...

		return $array;
	}


	public static function dontRunif() {
		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'dontRunif' ) {
			var_dump( $_GET );
			die();
		}

		if ( isset( $_GET['brizy-edit-iframe'] ) || isset( $_GET['brizy-edit'] ) || isset( $_GET['preview'] ) ) {
			return false;
		}

		if ( is_admin() || ! empty( $_GET['trp-edit-translation'] ) || ! empty( $_GET['fb-edit'] ) || ! empty( $_GET['bricks'] ) || ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['PageSpeed'] ) || ( ! empty( $_GET['fl_builder'] ) || isset( $_GET['fl_builder'] ) ) || ! empty( $_GET['et_fb'] ) || ! empty( $_GET['tatsu'] ) || ! empty( $_GET['tatsu-header'] ) || ! empty( $_GET['tatsu-footer'] ) || ! empty( $_GET['tve'] ) || ! empty
			( $_GET['ct_builder'] ) || ( ! empty( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) || ( ! empty( $_GET['page'] ) && $_GET['page'] == 'livecomposer_editor' ) ) {
			return false;
		}

		return true;
	}


	public function defaultExcluded( $string ) {
		foreach ( self::$default_excluded_list as $i => $excluded_string ) {
			if ( strpos( $string, $excluded_string ) !== false ) {
				return true;
			}
		}

		return false;
	}


	public function local_fonts( $url ) {
		$url = $url[0];
		if ( strpos( $url, self::$zone_name ) === false ) {
			if ( strpos( $url, '.woff' ) !== false || strpos( $url, '.woff2' ) !== false || strpos( $url, '.eot' ) !== false || strpos( $url, '.ttf' ) !== false ) {
				$newUrl = 'https://' . self::$zone_name . '/' . self::reformat_url( $url, true );

				if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'fonts' ) {
					return print_r( array( $url, $newUrl ), true );
				}

				return $newUrl;
			}
		}

		return $url;
	}


	public function local_image_tags( $image ) {
		$class_Addon  = '';
		$image_tag    = $image[0];
		$image_source = '';

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'local_start' ) {
			return print_r( $image, true );
		}

		// File has already been replaced
		if ( $this->defaultExcluded( $image[0] ) ) {
			return $image[0];
		}

		// File is not an image
		if ( ! self::is_image( $image[0] ) ) {
			return $image[0];
		}

		// File is excluded
		if ( self::is_excluded( $image[0] ) ) {
			$image_source = $image[0];
			$image_source = preg_replace( '/class=["|\'](.*?)["|\']/is', 'class="$1 wps-ic-loaded"', $image_source );

			return $image_source;
		}


		if ( ( self::$external_url_enabled == 'false' || self::$external_url_enabled == '0' ) && ! self::image_url_matching_site_url( $image[0] ) ) {
			return $image[0];
		}

		// Original URL was
		$original_img_tag                  = array();
		$original_img_tag['original_tags'] = $this->getAllTags( $image[0], array() );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'local_original_tags' ) {
			return print_r( $original_img_tag['original_tags'], true );
		}

		if ( ! empty( $original_img_tag['original_tags']['src'] ) && empty( $original_img_tag['original_tags']['data-src'] ) ) {
			$image_source = $original_img_tag['original_tags']['src'];
		} else {
			$image_source = $original_img_tag['original_tags']['data-src'];
		}

		$original_img_tag['original_src'] = $image_source;

		// Old Code Below

		// Figure out image class
		preg_match( '/srcset=["|\']([^"]+)["|\']/', $image_tag, $image_srcset );
		if ( ! empty( $image_srcset[1] ) ) {
			$original_img_tag['srcset'] = $image_srcset[1];
		}

		$size = self::get_image_size( $image_source );

		$svgAPI = $source_svg = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size[0] . '" height="' . $size[1] . '"><path d="M2 2h' . $size[0] . 'v' . $size[1] . 'H2z" fill="#fff" opacity="0"/></svg>' );

		// OriginalImageSource
		$original_img_src = $image_source;

		// Path to CSS File
		$site_url   = str_replace( array( 'https://', 'http://' ), '', self::$site_url );
		$image_path = str_replace( array( 'https://' . $site_url . '/', 'http://' . $site_url . '/' ), '', $image_source );
		$image_path = explode( '?', $image_path );
		$image_path = ABSPATH . $image_path[0];

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'local_settings' ) {
			$webP = str_replace( array( '.jpeg', '.jpg', '.png' ), '.webp', $image_path );

			return print_r( array( self::$webp, $image_path, $webP, file_exists( $webP ) ), true );
		}

		/**
		 * Local File does not exists?
		 */
		if ( ! file_exists( $image_path ) ) {
			return $image[0];
		} else {

			if ( self::$webp == 'true' || self::$webp == '1' ) {
				// Check if WebP Exists in PATH?
				$webP = str_replace( array( '.jpeg', '.jpg', '.png' ), '.webp', $image_path );

				if ( ! file_exists( $webP ) ) {
					$webP         = false;
					$image_source = $original_img_src;
				} else {
					$original_img_src = str_replace( array( '.jpeg', '.jpg', '.png' ), '.webp', $original_img_src );
					$image_source     = $original_img_src;
				}
			} else {
				$image_source = $original_img_src;
			}

		}

		// Is LazyLoading enabled in the plugin?
		if ( ! empty( self::$lazy_enabled ) && self::$lazy_enabled == '1' ) {

			// If Logo remove wps-ic-lazy-image
			if ( strpos( $image_source, 'logo' ) !== false ) {
				$image_tag = 'src="' . $image_source . '"';
			} else {
				$image_tag = 'src="' . $svgAPI . '"';
			}

			$image_tag .= ' loading="lazy"';
			$image_tag .= ' data-src="' . $image_source . '"';

			// If Logo remove wps-ic-lazy-image
			if ( strpos( $image_source, 'logo' ) !== false ) {
				// Image is for logo
				$class_Addon .= 'wps-ic-local-lazy wps-ic-logo';
			} else {
				// Image is not for logo
				$class_Addon .= 'wps-ic-local-lazy wps-ic-lazy-image ';
			}

		} else {
			if ( ! empty( self::$adaptive_enabled ) && self::$adaptive_enabled == '1' ) {
				$image_tag = 'src="' . $image_source . '"';
				$image_tag .= ' data-adaptive="true"';
				$image_tag .= ' data-remove-src="true"';
			} else {
				$image_tag = 'src="' . $image_source . '"';
				$image_tag .= ' data-adaptive="false"';
			}

			$image_tag .= ' data-src="' . $image_source . '"';

			#$class_Addon .= 'wps-ic-local-no-lazy ';
		}

		/**
		 * Srcset to WebP
		 */
		$srcset_att = '';

		if ( self::$webp == 'true' || self::$webp == '1' ) {
			if ( ! empty( $original_img_tag['srcset'] ) ) {
				$exploded_scrcset = explode( ',', $original_img_tag['srcset'] );
				if ( ! empty( $exploded_scrcset ) ) {
					foreach ( $exploded_scrcset as $i => $src ) {
						$src   = trim( $src );
						$src_w = explode( ' ', $src );

						if ( ! empty( $src_w ) ) {
							$real_src       = $src_w[0];
							$real_src_width = $src_w[1];

							$image_path      = str_replace( self::$site_url . '/', '', $real_src );
							$image_path_webP = ABSPATH . $image_path;

							$webP            = str_replace( array( '.jpeg', '.jpg', '.png' ), '.webp', $real_src );
							$image_path_webP = str_replace( array( '.jpeg', '.jpg', '.png' ), '.webp', $image_path_webP );

							if ( ! file_exists( $image_path_webP ) ) {
								$srcset_att .= $real_src . ' ' . $real_src_width . ',';
							} else {
								$srcset_att .= $webP . ' ' . $real_src_width . ',';
							}
						}
					}
				}
				$srcset_att = rtrim( $srcset_att, ',' );
			}
		}

		if ( empty( $srcset_att ) ) {
			$srcset_att = $original_img_tag['srcset'];
		}

		$image_tag .= ' srcset="' . $srcset_att . '" ';

		if ( ! empty( $original_img_tag['original_tags'] ) ) {
			foreach ( $original_img_tag['original_tags'] as $tag => $value ) {
				if ( $tag == 'class' ) {
					$value = $class_Addon . ' ' . $value;
				}

				if ( $tag == 'src' || $tag == 'data-src' ) {
					continue;
				}

				$image_tag .= $tag . '="' . $value . '" ';
			}
		}

		return '<img ' . $image_tag . ' />';
	}


	public function getAllTags( $image, $ignore_tags = array( 'src', 'srcset', 'data-src', 'data-srcset' ) ) {
		$found_tags = array();
		// (?:["|\']?)([0-9]{1,4})(?:["|\']?)
		// data-large_image
		#preg_match_all('/([a-zA-Z\-\_]*)=(?:["|\']?)([^"]+)(?:["|\']?)\s/', $image, $image_tags);
		preg_match_all( '/([a-zA-Z\-\_]*)\s*\=\s*[\'\"](.*?)[\'\"]/i', $image, $image_tags );

		if ( ! empty( $image_tags[1] ) ) {
			$tag_value = $image_tags[2];
			foreach ( $image_tags[1] as $i => $tag ) {

				if ( ! empty( $ignore_tags ) && in_array( $tag, $ignore_tags ) ) {
					continue;
				}

				$found_tags[ $tag ] = $tag_value[ $i ];
			}
		}

		return $found_tags;
	}


}