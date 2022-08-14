<?php


class wps_ic_mainwp {


	public function __construct() {
		add_action( 'send_headers', array( __CLASS__, 'admin_init_mainwp' ) );
		add_action( 'send_headers', array( __CLASS__, 'check_mainwp' ) );
	}


	public static function check_mainwp() {
		if ( ! empty( $_GET['check_mainwp'] ) ) {
			$options = get_option( WPS_IC_OPTIONS );
			if ( ! empty( $options['api_key'] ) && $options['api_key'] != '' ) {
				wp_send_json_success();
			} else {
				wp_send_json_error( '#21' );
			}
		}
	}


	public static function admin_init_mainwp() {

		if ( ! empty( $_GET['force_ic_connect'] ) ) {
			$apikey = sanitize_text_field( $_POST['apikey'] );

			// Setup URI
			$uri = WPS_IC_KEYSURL;
			$uri .= '?action=connect';
			$uri .= '&apikey=' . $apikey;
			$uri .= '&site=' . urlencode( site_url() );
			$uri .= '&hash=' . md5( time() );
			$uri .= '&time_hash=' . time();
			$uri .= '&override_check=true';

			// Verify API Key is our database and user has is confirmed getresponse
			$get = wp_remote_get( $uri, array( 'timeout' => 120, 'sslverify' => false ) );

			if ( wp_remote_retrieve_response_code( $get ) == 200 ) {
				$body = wp_remote_retrieve_body( $get );
				$body = json_decode( $body );

				if ( $body->success == true && ! empty( $body->data->apikey ) ) {
					$options                 = get_option( WPS_IC_OPTIONS );
					$options['api_key']      = $body->data->apikey;
					$options['response_key'] = $body->data->response_key;
					$options['orp']          = $body->data->orp;
					update_option( WPS_IC_OPTIONS, $options );

					// First check if CDN Zone already exists
					$call = wp_remote_get( WPS_IC_KEYSURL . '?action=cdn_exists&live=true&domain=' . site_url() . '&apikey=' . $body->data->api_key, array( 'timeout' => '130', 'sslverify' => false ) );


					if ( wp_remote_retrieve_response_code( $call ) == 200 ) {
						$cdn_body = wp_remote_retrieve_body( $call );
						$cdn_body = json_decode( $cdn_body );

						if ( $cdn_body->success == 'true' ) {
							// CDN Does exist or we just created it
							$zone_name = $cdn_body->data->zone_name;
							if ( ! empty( $zone_name ) ) {
								update_option( 'ic_cdn_zone_name', $zone_name );
							}
						} else {
							// CDN Does not exist,error
						}
					}

					$settings = get_option( WPS_IC_SETTINGS );
					$sizes    = get_intermediate_image_sizes();
					foreach ( $sizes as $key => $value ) {
						$settings['thumbnails'][ $value ] = 1;
					}

					$default_Settings = array(
						'js'               => '1',
						'css'              => '0',
						'css_image_urls'   => '0',
						'external-url'     => '0',
						'replace-all-link' => '0',
						'emoji-remove'     => '0',
						'on-upload'        => '0',
						'defer-js'         => '0',
						'serve_jpg'        => '1',
						'serve_png'        => '1',
						'serve_gif'        => '1',
						'serve_svg'        => '1',
						'search-through'   => 'html',
						'preserve-exif'    => '0',
						'minify-css'       => '0',
						'minify-js'        => '0'
					);

					$settings = array_merge( $settings, $default_Settings );

					$settings['live-cdn'] = '1';
					update_option( WPS_IC_SETTINGS, $settings );

					//delete_option('wps_ic_affiliate_code');
					wp_send_json_success();
				}

				wp_send_json_error( $body->data );
			}

			wp_send_json_error( '0' );
		}

	}


}