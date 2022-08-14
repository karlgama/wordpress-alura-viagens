<?php


class wps_ic_comms {


	public function __construct() {
		if ( ! is_admin() ) {
			if ( ! empty( $_GET['apikey'] ) && ! empty( $_GET['comms_action'] ) ) {
				add_action( 'send_headers', array( $this, 'start_comms' ) );
			}
		}
	}


	public static function change_setting() {
		$settings = get_option( WPS_IC_SETTINGS );

		$setting_key   = sanitize_text_field( $_GET['setting'] );
		$setting_value = sanitize_text_field( $_GET['value'] );

		if ( $setting_key == 'cdn' ) {
			// First check if CDN Zone already exists
			$options = get_option( WPS_IC_OPTIONS );

			$request_params           = array();
			$request_params['apiv3']  = 'true';
			$request_params['apikey'] = $options['api_key'];
			$request_params['action'] = 'cdn_check';
			$request_params['url']    = site_url();

			$params = array(
				'method'      => 'POST',
				'timeout'     => 30,
				'redirection' => 3,
				'sslverify'   => false,
				'httpversion' => '1.0',
				'blocking'    => true, // TODO: Mozda true?
				'headers'     => array(),
				'body'        => $request_params,
				'cookies'     => array()
			);

			// Send call to API
			$call = wp_remote_post( WPS_IC_APIURL, $params );
		}

		$settings[ $setting_key ] = $setting_value;
		update_option( WPS_IC_SETTINGS, $settings );

		wp_send_json_success();
	}


	public static function deactivate() {
		$settings                  = get_option( WPS_IC_SETTINGS );
		$settings['hide_compress'] = 0;
		update_option( WPS_IC_SETTINGS, $settings );

		$options                 = get_option( WPS_IC_OPTIONS );
		$options['api_key']      = '';
		$options['response_key'] = '';
		update_option( WPS_IC_OPTIONS, $options );

		wp_send_json_success();
	}


	public static function test_connection() {
		global $wpdb;

		if ( ! function_exists( 'download_url' ) ) {
			require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
			require_once( ABSPATH . "wp-admin" . '/includes/file.php' );
			require_once( ABSPATH . "wp-admin" . '/includes/media.php' );
		}

		// Get attachment
		$attachments = $wpdb->get_results( "SELECT ID FROM " . $wpdb->prefix . "posts WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type='image/jpeg' ORDER BY post_date DESC LIMIT 1" );

		if ( $attachments ) {

			$attachment_Path = get_attached_file( $attachments[0]->ID );
			$attachment_URL  = wp_get_attachment_image_src( $attachments[0]->ID, 'full' );

			if ( ! empty( $attachment_Path ) && ! empty( $attachment_URL ) ) {
				global $wps_ic;

				$original_filesize = filesize( $attachment_Path );

				$compressed = get_post_meta( $attachments[0]->ID, 'wps_ic_compressed', true );
				if ( $compressed == 'true' ) {
					// Restore first
					#$wps_ic->compress->restore($attachments[0]->ID, $_POST['ic_hash'], 'none');

					$file_name = basename( $attachment_Path );
					$file_path = str_replace( $file_name, '', $attachment_Path );

					// Find image source on site
					$image     = wp_get_attachment_image_src( $attachments[0]->ID, 'full' );
					$file_name = basename( $image[0] );

					$call = wp_remote_get( WPS_IC_APIURL . '?get_restore=true&site=' . site_url( '/' ) . '&attachment_id=' . $attachments[0]->ID . '&file_name=' . $file_name, array( 'timeout' => 25, 'sslverify' => false ) );

					$original_image = wp_remote_retrieve_body( $call );
					$original_image = json_decode( $original_image, true );
					$original_image = $original_image['data'];

					if ( wp_remote_retrieve_response_code( $call ) == 200 ) {
						$body = wp_remote_retrieve_body( $call );
						$body = json_decode( $body );

						if ( $body->success == 'true' ) {

							$temp = download_url( $body->data );

							if ( ! is_wp_error( $temp ) && filesize( $temp ) > 0 ) {
								clearstatcache();

								// Remove file
								unlink( $file_path . $file_name );

								// New file
								$fp = fopen( $file_path . $file_name, 'w+' );
								fclose( $fp );

								copy( $temp, $file_path . $file_name );

								$query = $wpdb->prepare( "UPDATE " . $wpdb->prefix . "ic_compressed SET restored='1' WHERE attachment_ID='" . $attachments[0]->ID . "'" );
								$wpdb->query( $query );

								$attach_data = wp_generate_attachment_metadata( $attachments[0]->ID, $attachment_Path );
								wp_update_attachment_metadata( $attachments[0]->ID, $attach_data );

								// Delete compress data
								delete_post_meta( $attachments[0]->ID, 'wps_ic_started' );
								delete_post_meta( $attachments[0]->ID, 'wps_ic_reset' );
								delete_post_meta( $attachments[0]->ID, 'wps_ic_times' );
								delete_post_meta( $attachments[0]->ID, 'wps_ic_compressed' );
								delete_post_meta( $attachments[0]->ID, 'wps_ic_data' );
								delete_post_meta( $attachments[0]->ID, 'wps_ic_cdn' );
								delete_post_meta( $attachments[0]->ID, 'wps_ic_in_bulk' );
								delete_post_meta( $attachments[0]->ID, 'wps_ic_compressing' );
								delete_post_meta( $attachments[0]->ID, 'wps_ic_restoring' );

							}
						}

					}

					// Set compressing
					delete_post_meta( $attachments[0]->ID, 'wps_ic_reset' );
					delete_post_meta( $attachments[0]->ID, 'wps_ic_started' );
					delete_post_meta( $attachments[0]->ID, 'wps_ic_restoring' );
					delete_post_meta( $attachments[0]->ID, 'wps_ic_in_bulk' );

					$original_filesize = filesize( $attachment_Path );

				}

				$wps_ic->compress->single_bulk_v2( array( 'attachment_id' => $attachments[0]->ID ) );

				$compressed_filesize = filesize( $attachment_Path );

				wp_send_json_success( array( 'original_size' => $original_filesize, 'compressed_size' => $compressed_filesize ) );
			}
		} else {
			wp_send_json_error( 'no-images' );
		}
	}


	public static function get_stats() {
		global $wpdb;

		if ( empty( $_GET['range'] ) ) {
			$_GET['range'] = 'current_month';
		}

		$range = $_GET['range'];

		if ( $range == 'current_month' ) {

			$month_start = strtotime( 'first day of this month', time() );
			$month_end   = strtotime( 'last day of this month', time() );

			$stats = $wpdb->get_results( "SELECT COUNT(ID) as count, created, original, compressed, saved FROM " . $wpdb->prefix . "ic_stats WHERE created>='" . date( 'Y-m-d', $month_start ) . "' AND created<='" . date( 'Y-m-d', $month_end ) . "' GROUP BY attachment_ID ORDER BY created DESC" );
		} else if ( $range == 'last_month' ) {

			$month_start = strtotime( 'first day of last month', time() );
			$month_end   = strtotime( 'last day of last month', time() );

			$stats = $wpdb->get_results( "SELECT COUNT(ID) as count, created, original, compressed, saved FROM " . $wpdb->prefix . "ic_stats WHERE created>='" . date( 'Y-m-d', $month_start ) . "' AND created<='" . date( 'Y-m-d', $month_end ) . "' GROUP BY attachment_ID ORDER BY created DESC" );

		} else if ( $range == 'get_month' ) {

			$month       = sanitize_text_field( $_GET['month'] );
			$month_start = strtotime( 'first day of this month', strtotime( $month ) );
			$month_end   = strtotime( 'last day of this month', strtotime( $month ) );

			$stats = $wpdb->get_results( "SELECT COUNT(ID) as count, created, original, compressed, saved FROM " . $wpdb->prefix . "ic_stats WHERE created>='" . date( 'Y-m-d', $month_start ) . "' AND created<='" . date( 'Y-m-d', $month_end ) . "' GROUP BY attachment_ID ORDER BY created DESC" );

		} else {
			$stats = $wpdb->get_results( "SELECT COUNT(ID) as count, created, original, compressed, saved FROM " . $wpdb->prefix . "ic_stats GROUP BY attachment_ID ORDER BY created DESC" );
		}

		$output = array();

		if ( $stats ) {
			foreach ( $stats as $stat ) {
				$output[ $stat->created ]['count']      += $stat->count;
				$output[ $stat->created ]['original']   += $stat->original;
				$output[ $stat->created ]['compressed'] += $stat->compressed;
				$output[ $stat->created ]['saved']      += $stat->saved;
			}
		} else {
			$output[ date( 'Y-m-d', $month_start ) ]['count']      = 0;
			$output[ date( 'Y-m-d', $month_start ) ]['original']   = 0;
			$output[ date( 'Y-m-d', $month_start ) ]['compressed'] = 0;
			$output[ date( 'Y-m-d', $month_start ) ]['saved']      = 0;
		}

		wp_send_json_success( $output );
	}


	public static function fetch_plugin_settings() {
		global $wpdb, $wps_ic;

		$wps_ic = new wps_ic();
		$output = array();

		if ( is_multisite() ) {
			$current_blog_id = get_current_blog_id();
			switch_to_blog( $current_blog_id );
			$settings = get_option( WPS_IC_SETTINGS );
		} else {
			$settings = get_option( WPS_IC_SETTINGS );
		}

		if ( ! empty( $settings ) ) {
			foreach ( $settings as $key => $value ) {
				$output['settings'][ $key ] = $value;
			}
		}

		$output['settings']['version'] = $wps_ic::$version;

		wp_send_json_success( $output );
	}


	public static function media_compressed() {
		$imageID   = sanitize_text_field( $_GET['imageID'] );
		$imageHash = sanitize_text_field( $_GET['imageHash'] );

	}


	public function remote_status() {
		$remote_action = get_option( 'ic_remote_action' );
		if ( ! $remote_action || empty( $remote_action ) ) {
			wp_send_json_success( 'empty' );
		} else {
			wp_send_json_success( $remote_action );
		}
	}


	public function restore_all() {
		global $wps_ic;
		update_option( 'ic_remote_action', 'restoring' );
		#wp_schedule_single_event(time(), 'wps_ic_remote_restore_all');

		if ( ! defined( 'ABSPATH' ) ) {
			/** Set up WordPress environment */
			require_once( dirname( __FILE__ ) . '/wp-load.php' );
		}

		if ( ! function_exists( 'update_option' ) ) {
			require_once( ABSPATH . "wp-includes" . '/option.php' );
		}

		$wps_ic->ajax->wps_ic_restore_bulk_prep_background_hidden( 'none' );
		$wps_ic->ajax->wps_ic_restore_hidden_bulk();

		wp_remote_get( site_url( '?ic_restore_queue_ping=true' ), array( 'timeout' => 10, 'sslverify' => false ) );
		wp_send_json_success( 'scheduled-restore-all' );
	}


	public function compress_all() {
		global $wps_ic;

		if ( ! defined( 'ABSPATH' ) ) {
			/** Set up WordPress environment */
			require_once( dirname( __FILE__ ) . '/wp-load.php' );
		}

		if ( ! function_exists( 'update_option' ) ) {
			require_once( ABSPATH . "wp-includes" . '/option.php' );
		}

		update_option( 'ic_remote_action', 'compressing' );
		$wps_ic->ajax->wps_ic_compress_bulk_prep_hidden_background( 'none' );
		#$wps_ic->ajax->wps_ic_compress_hidden_bulk();
		$queue = $wps_ic->queue->get_hidden_compress_bulk_queue();

		$queued_array = array();

		if ( $queue ) {
			foreach ( $queue as $attachment_ID => $attachment ) {

				$queued_array[] = $attachment_ID;

				// Mark attachment as compressing
				update_post_meta( $attachment_ID, 'wps_ic_in_bulk', 'true' );

				// Add the attachment to queue
				$wps_ic->queue->remove_queue( $attachment_ID );
				$queue = $wps_ic->queue->add_queue( $attachment_ID, 'hidden_compress_bulk' );

				if ( count( $queued_array ) >= 3 ) {
					#wp_schedule_single_event(time(), 'wps_ic_compress_single', array('attachments' => $queued_array));
					$queued_array = array();
				}

			}

			wp_remote_get( site_url( '?ic_queue_ping=true' ), array( 'timeout' => 10, 'sslverify' => false ) );
		}

		wp_send_json_success( 'scheduled-compress-all' );
	}


	public function start_comms() {
		global $wps_ic;
		$apikey = sanitize_text_field( $_GET['apikey'] );
		$action = sanitize_text_field( $_GET['comms_action'] );

		$options = get_option( WPS_IC_OPTIONS );
		if ( empty( $options ) ) {
			wp_send_json_error( 'Hacking?' );
		}

		if ( $apikey != $options['api_key'] ) {
			wp_send_json_error( 'Hacking?' );
		}

		if ( ! method_exists( $this, $action ) ) {
			wp_send_json_error( 'Function does not exist' );
		}

		self::$action();

		wp_send_json_error( '#155' );
	}


}