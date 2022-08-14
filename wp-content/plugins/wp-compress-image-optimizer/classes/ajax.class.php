<?php


/**
 * Class - Ajax
 */
class wps_ic_ajax extends wps_ic {

	public static $local;
	public static $options;
	public static $settings;
	public static $accountStatus;

	public static $logo_compressed;
	public static $logo_uncompressed;
	public static $logo_excluded;
	public static $allowed_types;
	public static $count_thumbs;


	public function __construct() {
		if (is_admin()) {

			self::$settings = get_option(WPS_IC_SETTINGS);
			self::$count_thumbs = count(get_intermediate_image_sizes());
			self::$local             = parent::$local;
			self::$logo_compressed   = WPS_IC_URI . 'assets/images/legacy/logo-compressed.svg';
			self::$logo_uncompressed = WPS_IC_URI . 'assets/images/legacy/logo-not-compressed.svg';
			self::$logo_excluded     = WPS_IC_URI . 'assets/images/legacy/logo-excluded.svg';

			if ( ! empty(parent::$response_key)) {

				// GeoLocation for Popups
				$this->add_ajax('wps_ic_cname_add');
				$this->add_ajax('wps_ic_cname_retry');
				$this->add_ajax('wps_ic_remove_cname');
				$this->add_ajax('wps_ic_exclude_list');
				$this->add_ajax('wps_ic_geolocation');
				$this->add_ajax('wps_ic_geolocation_force');

				// Bulk Actions
				$this->add_ajax('wps_ic_compress_details');
				$this->add_ajax('wps_ic_media_library_bulk_restore_start');
				$this->add_ajax('wps_ic_media_library_bulk_compress_start');
				$this->add_ajax('wps_ic_media_library_bulk_heartbeat');
				$this->add_ajax('wps_ic_getBulkStats');
				$this->add_ajax('wps_ic_doBulkCompress');
				$this->add_ajax('wps_ic_doBulkRestore');
				$this->add_ajax('wps_ic_RestoreFinished');

				$this->add_ajax('wps_ic_media_library_heartbeat');
				$this->add_ajax('wps_ic_compress_live');
				$this->add_ajax('wps_ic_restore_live');
				$this->add_ajax('wps_ic_exclude_live');
				$this->add_ajax('wps_ic_get_default_settings');

				$this->add_ajax('wps_ic_save_all_settings');
				$this->add_ajax('wps_ic_ajax_checkbox');

				$this->add_ajax('wps_ic_hide_pro_notice');
				$this->add_ajax('wps_ic_purge_cdn');
				$this->add_ajax('wps_ic_preload_page');

				// Dev Testing
				$this->add_ajax('wps_ic_compress_all');
				$this->add_ajax('wps_ic_compress_all_ping');
				$this->add_ajax('wps_ic_compress_prepare');
				// Restore
				$this->add_ajax('wps_ic_restore_count');
				$this->add_ajax('wps_ic_restore_prepare');
				$this->add_ajax('wps_ic_restore_all_ping');

				// Live Start

				// First Run Variable
				$this->add_ajax('wps_ic_count_uncompressed_images');

				// Change Setting
				$this->add_ajax('wps_ic_settings_change');

				// Exclude Image from Compress
				$this->add_ajax('wps_ic_simple_exclude_image');
			}
			else {
				// Connect
				$this->add_ajax('wps_ic_live_connect');
			}
		}
		else {
			$this->add_ajax('wps_ic_purge_cdn');
			$this->add_ajax('wps_ic_preload_page');
		}
	}


	public function add_ajax($hook) {
		add_action('wp_ajax_' . $hook, array($this, $hook));
	}


	public function wps_ic_ajax_checkbox() {
		$setting_name    = sanitize_text_field($_POST['setting_name']);
		$setting_value   = sanitize_text_field($_POST['value']);
		$setting_checked = sanitize_text_field($_POST['checked']);

		$settings = get_option(WPS_IC_SETTINGS);

		// If it was checked then set to false as it's unchecked then
		if ($setting_checked == 'false') {
			$settings[ $setting_name ] = '0';
		}
		else {
			$settings[ $setting_name ] = '1';
		}

		if ($settings['live-cdn'] == '0') {
			$settings['js'] = '0';
			$settings['css'] = '0';
		}


		update_option(WPS_IC_SETTINGS, $settings);

		self::purgeBreeze();
		self::purge_cache_files();

		// Clear cache.
		if (function_exists('rocket_clean_domain')) {
			rocket_clean_domain();
		}

		// Lite Speed
		if (defined('LSCWP_V')) {
			do_action('litespeed_purge_all');
		}

		// HummingBird
		if (defined('WPHB_VERSION')) {
			do_action('wphb_clear_page_cache');
		}

		wp_send_json_success(array('new_value' => $settings[ $setting_name ], 'setting_name' => $setting_name, 'value' => $setting_value));
	}


	public static function purgeBreeze() {
		if (defined('BREEZE_VERSION')) {
			global $wp_filesystem;
			require_once(ABSPATH . 'wp-admin/includes/file.php');

			WP_Filesystem();

			$cache_path = breeze_get_cache_base_path(is_network_admin(), true);
			$wp_filesystem->rmdir(untrailingslashit($cache_path), true);

			if (function_exists('wp_cache_flush')) {
				wp_cache_flush();
			}
		}
	}


	public static function purge_cache_files() {
		$cache_dir = WPS_IC_CACHE;

		self::removeDirectory($cache_dir);

		return true;
	}


	public static function removeDirectory($path) {
		$files = glob($path . '/*');
		foreach ($files as $file) {
			is_dir($file) ? self::removeDirectory($file) : unlink($file);
		}

		return;
	}


	public function wps_ic_save_all_settings() {
		$settings = sanitize_text_field($_POST['settings']);
		parse_str($settings, $parsed_settings);
		$settings = get_option(WPS_IC_SETTINGS);
		foreach ($parsed_settings as $key => $value) {
			$settings[ $key ] = $value;
		}
		update_option(WPS_IC_SETTINGS, $settings);

		if ( ! empty($parsed_settings['css']) || ! empty($parsed_settings['js'])) {
			$this->purge_cdn_assets();
			wp_send_json_success('Purged CDN');
		}

		wp_send_json_success('Settings Saved');
	}


	public function purge_cdn_assets() {
		$options = get_option(WPS_IC_OPTIONS);

		$call = wp_remote_get(WPS_IC_KEYSURL . '?action=cdn_purge&domain=' . site_url() . '&apikey=' . $options['api_key'], array('timeout' => '30', 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'));

		if (wp_remote_retrieve_response_code($call) == 200) {
			$body = wp_remote_retrieve_body($call);
			$body = json_decode($body);
			if ($body->success == 'true') {
				return true;
			}
			else {
				return false;
			}
		}
		else {
			return false;
		}
	}


	public function wps_ic_preload_page() {
		$options = get_option(WPS_IC_OPTIONS);

		if (empty($options['api_key'])) {
			wp_send_json_error('API Key empty!');
		}

		$url = 'https://vancouver.zapwp.net/?action=preload&site_url=' . urlencode($_SERVER['HTTP_REFERER']);

		$call = wp_remote_get($url, array('timeout'    => 10,
																			'sslverify'  => 'false',
																			'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'));

		sleep(3);

		wp_send_json_success();
	}


	public function wps_ic_purge_cdn() {
		$options = get_option(WPS_IC_OPTIONS);

		if (empty($options['api_key'])) {
			wp_send_json_error('API Key empty!');
		}

		$hash                = time();
		$options['css_hash'] = $hash;
		update_option(WPS_IC_OPTIONS, $options);

		delete_transient('wps_ic_css_cache');
		delete_option('wps_ic_modified_css_cache');
		delete_option('wps_ic_css_combined_cache');

		set_transient('wps_ic_purging_cdn', 'true', 30);
		$url = WPS_IC_KEYSURL . '?action=cdn_purge&apikey=' . $options['api_key'];

		$call = wp_remote_get($url, array('timeout'    => 10,
																			'sslverify'  => 'false',
																			'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'));

		// Clear cache.
		if (function_exists('rocket_clean_domain')) {
			rocket_clean_domain();
		}

		// Lite Speed
		if (defined('LSCWP_V')) {
			do_action('litespeed_purge_all');
		}

		// HummingBird
		if (defined('WPHB_VERSION')) {
			do_action('wphb_clear_page_cache');
		}

		sleep(3);
		delete_transient('wps_ic_purging_cdn');
		wp_send_json_success();

		// Ignore this below, we just do a trigger
		if (wp_remote_retrieve_response_code($call) == 200) {
			$body = wp_remote_retrieve_body($call);
			$body = json_decode($body);
			if ($body->success == 'true') {
				delete_transient('wps_ic_purging_cdn');
				wp_send_json_success();
			}
		}

		wp_send_json_error('Could not call purge action!');
	}


	public function wps_ic_hide_pro_notice() {
		update_option('hide_upgrade_notice', '1');
	}


	public function wps_ic_restore_count() {

		$args = array('post_type'      => 'attachment',
									'post_status'    => 'inherit',
									'posts_per_page' => - 1,
									'meta_query'     => array('relation' => 'AND',
										array('key'     => 'wps_ic_data',
													'compare' => 'EXISTS'),
										array('key'     => 'wps_ic_data',
													'value'   => 'excluded',
													'compare' => '!=')));

		$compressed_attachments = new WP_Query($args);
		wp_send_json_success(array('compressed_images' => $compressed_attachments->post_count));
	}


	public function wps_ic_restore_prepare() {
		global $wpdb;

		$args = array('post_type'      => 'attachment',
									'post_status'    => 'inherit',
									'posts_per_page' => - 1,
									'meta_query'     => array('relation' => 'AND',
										array('key'     => 'wps_ic_data',
													'compare' => 'EXISTS'),
										array('key'     => 'wps_ic_data',
													'value'   => 'excluded',
													'compare' => '!=')));

		$compressed_attachments = new WP_Query($args);
		update_option('wps_ic_bg_stats', array('total' => $compressed_attachments->post_count));
		wp_send_json_success(array('total_images' => $compressed_attachments->post_count));
	}


	public function wps_ic_compress_prepare() {
		global $wpdb;

		$args = array('post_type'      => 'attachment',
									'post_status'    => 'inherit',
									'posts_per_page' => - 1,
									'meta_query'     => array('relation' => 'AND',
										array('key'     => 'wps_ic_data',
													'compare' => 'NOT EXISTS'),
										array('key'     => 'wps_ic_data',
													'value'   => 'excluded',
													'compare' => '!=')));

		$uncompressed_attachments = new WP_Query($args);
		update_option('wps_ic_bg_stats', array('total' => $uncompressed_attachments->post_count));

		wp_send_json_success(array('total_images' => $uncompressed_attachments->post_count));
	}


	public function get_processing_images() {
		global $wpdb;
		// TODO
		$processing_attachments = $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} attachment
WHERE EXISTS (
SELECT posts.ID FROM {$wpdb->posts} posts
LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=posts.ID
WHERE pm.meta_key='wps_ic_processing' AND posts.ID=attachment.ID
GROUP BY posts.ID
    )
AND attachment.post_type='attachment' AND attachment.post_status='inherit' AND attachment.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif', 'image/jpg')
ORDER BY `attachment`.`ID` DESC LIMIT 100");

		return $processing_attachments;

	}


	public function wps_ic_restore_all_ping() {
		ini_set('memory_limit', '2024M');
		ini_set('max_execution_time', '180');

		$args = array('post_type'      => 'attachment',
									'post_status'    => 'inherit',
									'posts_per_page' => 1,
									'meta_query'     => array(array('key'     => 'wps_ic_data',
																									'value'   => 'working',
																									'compare' => '!=')));

		$compressed_attachments = new WP_Query($args);
		if ($compressed_attachments->have_posts()) {
			$options = get_option(WPS_IC_OPTIONS);
			$sent    = array();

			while ($compressed_attachments->have_posts()) {
				$compressed_attachments->the_post();
				$attID = get_the_ID();

				if (get_post_meta($attID, 'wps_ic_state', true) == 'restoring') {
					wp_send_json_error('already-queued');
				}

				update_post_meta($attID, 'wps_ic_state', 'restoring');

				update_post_meta($attID, 'wps_ic_data', 'working');
				$this->send_restore_ping($attID, $options['api_key']);
				$sent[] = $attID;
			}

			wp_send_json_success(array('sent_pings' => count($sent), 'ids' => $sent));
		}

		wp_send_json_error('no-att');
	}


	/**
	 * @since 4.50.70
	 */
	public function send_restore_ping($attachmentID, $apikey = '') {
		if ( ! function_exists('download_url')) {
			require_once(ABSPATH . "wp-admin" . '/includes/image.php');
			require_once(ABSPATH . "wp-admin" . '/includes/file.php');
			require_once(ABSPATH . "wp-admin" . '/includes/media.php');
		}

		if ( ! function_exists('update_option')) {
			require_once(ABSPATH . "wp-includes" . '/option.php');
		}

		update_post_meta($attachmentID, 'wps_ic_restoring', 'true');
		update_post_meta($attachmentID, 'wps_ic_in_bulk', 'true');

		$call = wp_remote_get(WPS_IC_APIURL . '?restore_client=true&apikey=' . $apikey . '&attID=' . $attachmentID . '&hash=' . time(), array('timeout' => 120, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'));

		if (wp_remote_retrieve_response_code($call) == 200) {
			$body = wp_remote_retrieve_body($call);
			$body = json_decode($body, true);

			if ($body['success'] == false) {
				// Cannot restore
				clearstatcache();
				$file_data = get_attached_file($attachmentID);
				update_post_meta($attachmentID, 'wps_ic_noncompressed_size', filesize($file_data));
				delete_post_meta($attachmentID, 'wps_ic_compressed_size');
				delete_post_meta($attachmentID, 'wps_ic_data');
				delete_post_meta($attachmentID, 'wps_ic_dimmensions');
				delete_post_meta($attachmentID, 'wps_ic_restoring');
				delete_post_meta($attachmentID, 'wps_ic_in_bulk');
				delete_post_meta($attachmentID, 'wps_ic_state');

			}
			else {

				$image_list = $body['data'];
				delete_post_meta($attachmentID, 'wps_ic_state');

				if ($image_list) {
					foreach ($image_list as $size => $uri) {
						$temp_file = download_url($uri, 120);

						if ( ! is_wp_error($temp_file) && $temp_file) {

							$file_data = get_attached_file($attachmentID);

							if ($size !== 'full') {
								$fullsizepath           = get_attached_file($attachmentID);
								$path_to_thumb          = str_replace(basename($fullsizepath), '', $fullsizepath);
								$current_thumbnail      = basename($uri);
								$current_thumbnail_path = $path_to_thumb . $current_thumbnail;
								$file_data              = $current_thumbnail_path;
							}

							if (copy($temp_file, $file_data)) {
								if ($size == 'full') {
									clearstatcache();
									$file_data = get_attached_file($attachmentID);
									update_post_meta($attachmentID, 'wps_ic_noncompressed_size', filesize($file_data));
									delete_post_meta($attachmentID, 'wps_ic_compressed_size');
									delete_post_meta($attachmentID, 'wps_ic_data');
									delete_post_meta($attachmentID, 'wps_ic_dimmensions');
									delete_post_meta($attachmentID, 'wps_ic_restoring');
									delete_post_meta($attachmentID, 'wps_ic_in_bulk');

									#wp_update_attachment_metadata((int)$attachment->ID, wp_generate_attachment_metadata((int)$attachment->ID, $file_data));
								}

								unlink($temp_file);
							}
						}
					}
				}

			}

		}

	}


	public function wps_ic_compress_all_ping() {
		global $wpdb;

		ini_set('memory_limit', '2024M');
		ini_set('max_execution_time', '180');

		if ( ! $wpdb) {
			wp_send_json_error();
		}

		$args                     = array('post_type'      => 'attachment',
																			'post_status'    => 'inherit',
																			'posts_per_page' => 1,
																			'meta_query'     => array(array('key'     => 'wps_ic_data',
																																			'compare' => 'NOT EXISTS')));
		$uncompressed_attachments = new WP_Query($args);
		if ($uncompressed_attachments->have_posts()) {
			$options = get_option(WPS_IC_OPTIONS);
			$sent    = array();

			while ($uncompressed_attachments->have_posts()) {
				$uncompressed_attachments->the_post();
				$attID = get_the_ID();

				if (get_post_meta($attID, 'wps_ic_state', true) == 'compressing') {
					wp_send_json_error('already-queued');
				}

				update_post_meta($attID, 'wps_ic_state', 'compressing');
				update_post_meta($attID, 'wps_ic_data', 'working');
				$this->send_ping($attID, $options['api_key']);
				$sent[] = $attID;
			}

			wp_send_json_success(array('sent_pings' => count($sent), 'ids' => print_r($sent, true)));
		}

		wp_send_json_error('no-att');
	}


	/**
	 * @since 4.50.70
	 */
	public function send_ping($attachmentID, $apikey = '') {
		$settings = get_option(WPS_IC_SETTINGS);

		$ch = curl_init(WPS_IC_APIURL . '?pull_from_client=true&apikey=' . $apikey . '&attID=' . $attachmentID . '&settings[quality]=' . $settings['optimization'] . '&settings[resize]=' . $settings['resize_larger_images'] . '&settings[resize_width]=' . $settings['resize_larger_images_width']);

		// No Body return
		$agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0';
		curl_setopt($ch, CURLOPT_USERAGENT, $agent);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_exec($ch);
		curl_close($ch);
	}


	public function wps_ic_compress_all() {
		global $wpdb, $wps_ic;
		$options = get_option(WPS_IC_OPTIONS);

		$uncompressed_attachments = $wpdb->get_results("SELECT * FROM {$wpdb->posts} attachment
WHERE NOT EXISTS (
SELECT posts.ID FROM {$wpdb->posts} posts
LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=posts.ID
WHERE pm.meta_key='wps_ic_data' AND posts.ID=attachment.ID
GROUP BY posts.ID
    )  
    AND
    NOT EXISTS (
SELECT posts.ID FROM {$wpdb->posts} posts
LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=posts.ID
WHERE pm.meta_key='wps_ic_exclude' AND posts.ID=attachment.ID
GROUP BY posts.ID
    ) 
AND attachment.post_type='attachment' AND attachment.post_status='inherit' AND attachment.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif', 'image/jpg')
ORDER BY `attachment`.`ID` DESC LIMIT 1");

		$compress_queue = array();
		if ($uncompressed_attachments) {
			foreach ($uncompressed_attachments as $attachment) {
				$compress_queue[] = $attachment->ID;
				update_post_meta($attachment->ID, 'wps_ic_data', 'working');
			}

			if ($compress_queue) {
				$wps_ic->compress->bulk(array('attachments' => $compress_queue), $options['api_key']);
			}
			wp_send_json_success();
		}

		wp_send_json_error();
	}


	/**
	 * Legacy New Compress All
	 * @since 4.50.65
	 */
	public function wps_ic_legacy_process_status() {
		global $wpdb;

		$stopping = get_option('wps_ic_bg_stopping');

		if ($stopping == 'true') {
			wp_send_json_success('stopping');
		}

		$bg_process = get_option('wps_ic_bg_process');

		if ( ! $bg_process) {
			$bg_process_done = get_option('wps_ic_bg_process_done');
			delete_option('wps_ic_bg_process_done');
			wp_send_json_success(array('process' => 'done', 'type' => $bg_process_done));
		}

		$name          = $bg_process['type'];
		$process_count = $bg_process['count'];
		$total         = $bg_process['total'];
		$progress      = round(($process_count / $total) * 100, 0);

		if ($process_count == $total) {
			update_option('wps_ic_bg_process_done', $bg_process['type']);
			delete_option('wps_ic_bg_process');
		}

		wp_send_json_success(array('name' => $name, 'count' => $process_count, 'total' => $total, 'progress' => $progress));
	}


	/**
	 * Legacy New Restore All
	 * @since 4.50.65
	 */
	public function wps_ic_legacy_restore_all() {
		$stop = get_option('wps_ic_bg_process_stop');

		if ($stop == 'true') {
			delete_option('wps_ic_bg_stopping');
			delete_option('wps_ic_bg_process_stats');
			delete_option('wps_ic_bg_process_stop');
			update_option('ic_error', 'error on stop 962');
			wp_send_json_success('Stopped');
		}

		delete_option('wps_ic_bg_process_stop');
		delete_option('wps_ic_bg_process');
		delete_option('wps_ic_bg_process_done');

		$bg_process          = array();
		$bg_process['type']  = 'restore';
		$bg_process['count'] = 0;

		$args = array('post_type'      => 'attachment',
									'post_status'    => 'inherit',
									'posts_per_page' => - 1,
									'meta_query'     => array('relation' => 'AND',
										array('key'     => 'wps_ic_data',
													'compare' => 'EXISTS'),
										array('key'     => 'wps_ic_exclude',
													'compare' => 'NOT EXISTS')));

		$attachments         = new WP_Query($args);
		$bg_process['total'] = $attachments->post_count;
		update_option('wps_ic_bg_process', $bg_process);

		if ($attachments->have_posts()) {
			while ($attachments->have_posts()) {
				$attachments->the_post();
				$attID = get_the_ID();
				update_post_meta($attID, 'wps_ic_locked', 'true');
			}
		}

		$options = get_option(WPS_IC_OPTIONS);
		$ch      = curl_init(site_url('?run_restore=true&hash_time=' . md5(time()) . '&time=' . time() . '&apikey=' . $options['api_key']));

		// No Body return
		$agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0';
		curl_setopt($ch, CURLOPT_USERAGENT, $agent);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
		curl_exec($ch);
		curl_close($ch);

		wp_send_json_success($bg_process);

	}


	public function add_ajax_frontend($hook) {
		add_action('wp_ajax_nopriv_' . $hook, array($this, $hook));
	}


	public function wps_ic_get_default_settings() {
		$area         = sanitize_text_field($_POST['area']);
		$settings_opt = get_option(WPS_IC_SETTINGS);

		if ($area == 'serve-cdn') {
			$default_Settings = array('js' => '0', 'css' => '0', 'css_image_urls' => '0', 'serve_jpg' => '1', 'serve_png' => '1', 'serve_gif' => '1', 'serve_svg' => '1');
			$merge            = array_merge($settings_opt, $default_Settings);
			update_option(WPS_IC_SETTINGS, $merge);
		}
		else if ($area == 'additional-settings') {
			$default_Settings = array('preserve-exif' => '1', 'minify-css' => '0', 'minify-js' => '0', 'emoji-remove' => '0', 'on-upload' => '0', 'defer-js' => '0', 'external-url' => '0');
			$merge            = array_merge($settings_opt, $default_Settings);
			update_option(WPS_IC_SETTINGS, $merge);
		}

		wp_send_json_success($merge);
	}


	/**
	 * Exclude the image
	 * @since 4.0.0
	 */
	public function wps_ic_exclude_live() {
		global $wps_ic;

		$output        = '';
		$action        = sanitize_text_field($_POST['do_action']);
		$attachment_id = sanitize_text_field($_POST['attachment_id']);
		$filedata      = get_attached_file($attachment_id);
		$basename      = sanitize_title(basename($filedata));
		$exclude_list  = get_option('wps_ic_exclude_list');

		if ( ! $exclude_list) {
			$exclude_list = array();
		}

		$exclude = get_post_meta($attachment_id, 'wps_ic_exclude_live', true);

		if ($action == 'exclude') {
			$exclude_list[ $attachment_id ] = $basename;
			update_post_meta($attachment_id, 'wps_ic_exclude_live', 'true');

			$filesize = parent::get_wp_filesize($attachment_id);
			$output   .= '<div class="wps-ic-compressed-logo">';
			$output   .= '<img src="' . self::$logo_excluded . '" />';
			$output   .= '</div>';

			$output .= '<div class="wps-ic-compressed-info">';

			$output .= '<div class="wpc-info-box">';
			$output .= '<h5>Excluded</h5>';
			$output .= '</div>';

			$output .= '<div>';
			$output .= '<ul class="wpc-inline-list">';

			$output .= '<li><div class="wpc-savings-tag">' . $filesize . '</div></li>';

			$output .= '<li>';
			$output .= '<a class="wpc-dropdown-btn wps-ic-include-live ic-tooltip" title="Include" data-action="include" data-attachment_id="' . $attachment_id . '"></a>';
			$output .= '</li>';

			$output .= '</ul>';
			$output .= '</div>';

			$output .= '</div>';

		}
		else {
			unset($exclude_list[ $attachment_id ]);
			delete_post_meta($attachment_id, 'wps_ic_exclude_live');

			$filesize = parent::get_wp_filesize($attachment_id);
			$output   .= '<div class="wps-ic-compressed-logo">';
			$output   .= '<img src="' . self::$logo_uncompressed . '" />';
			$output   .= '</div>';

			$output .= '<div class="wps-ic-compressed-info">';

			$output .= '<div class="wpc-info-box">';
			$output .= '<h5>Not Compressed</h5>';
			$output .= '</div>';

			$output .= '<div>';
			$output .= '<ul class="wpc-inline-list">';

			$output .= '<li><div class="wpc-savings-tag">' . $filesize . '</div></li>';

			$output .= '<li>';
			$output .= '<a class="wpc-dropdown-btn wps-ic-compress-live ic-tooltip" title="Compress" data-attachment_id="' . $attachment_id . '"></a>';
			$output .= '</li>';
			$output .= '<li>';
			$output .= '<a class="wpc-dropdown-btn wps-ic-exclude-live ic-tooltip" title="Exclude" data-action="exclude" data-attachment_id="' . $attachment_id . '"></a>';
			$output .= '</li>';

			$output .= '</ul>';
			$output .= '</div>';

			$output .= '</div>';
		}

		update_option('wps_ic_exclude_list', $exclude_list);
		wp_send_json_success(array('html' => $output));
	}


	/**
	 * Exclude the image
	 * @since 4.0.0
	 */
	public function wps_ic_simple_exclude_image() {
		global $wps_ic;
		$wps_ic = new wps_ic_compress();

		#do_action('ic_debug', $_POST['attachment_id'], 'Triggered Exclude');
		$wps_ic->simple_exclude($_POST, 'html');
	}


	/**
	 * Connect Multsites With API
	 */
	public function wps_ic_api_mu_connect() {
		global $wps_ic;

		// Is localhost?
		$whitelist = array('127.0.0.1', '::1');
		if (in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
			#wp_send_json_error(array('msg' => 'Sorry, localhost installs are not supported.'));
		}

		$sites = get_sites();

		// API Key
		$apikey         = sanitize_text_field($_POST['apikey']);
		$affiliate_code = get_option('wps_ic_affiliate_code');

		if ($sites && is_multisite()) {
			$error = false;

			foreach ($sites as $key => $site) {

				// Setup URI
				$uri = WPS_IC_KEYSURL;
				$uri .= '?action=connect';
				$uri .= '&apikey=' . $apikey;
				$uri .= '&site=' . urlencode($site->domain . $site->path);
				$uri .= '&affiliate_code=' . $affiliate_code;

				// Verify API Key is our database and user has is confirmed getresponse
				$get = wp_remote_get($uri, array('timeout' => 120, 'sslverify' => false));

				if (wp_remote_retrieve_response_code($get) == 200) {
					$body = wp_remote_retrieve_body($get);
					$body = json_decode($body);

					if ($body->success == true && $body->data->api_key != '' && $body->data->response_key != '') {
						$options = new wps_ic_options();
						$options->set_option('api_key', $body->data->api_key);
						$options->set_option('response_key', $body->data->response_key);
						$options->set_option('orp', $body->data->orp);

						$settings = get_option(WPS_IC_SETTINGS);

						$sizes = get_intermediate_image_sizes();
						foreach ($sizes as $key => $value) {
							$settings['thumbnails'][ $value ] = 1;
						}

						update_option(WPS_IC_SETTINGS, $settings);

						//delete_option('wps_ic_affiliate_code');
					}
				}
				else {
					$error = true;
				}

			}

			if ($error == true) {
				wp_send_json_error($body->data);
			}
			else {
				wp_send_json_success();
			}

		}

		wp_send_json_error('0');
	}


	/**
	 * Connect With API
	 */
	public function wps_ic_live_connect() {
		global $wps_ic;

		delete_option('wps_ic_test');
		update_option('wps_ic_first_run', 'true');


		// API Key
		$apikey  = sanitize_text_field($_POST['apikey']);
		$siteurl = urlencode(site_url());

		// Setup URI
		$uri = WPS_IC_KEYSURL . '?action=connect_v5&apikey=' . $apikey . '&domain=' . $siteurl . '&hash=' . md5(time()) . '&time_hash=' . time();

		// Verify API Key is our database and user has is confirmed getresponse
		$get = wp_remote_get($uri, array('timeout' => 45, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'));

		if (wp_remote_retrieve_response_code($get) == 200) {
			$body = wp_remote_retrieve_body($get);
			$body = json_decode($body);

			if ( ! empty($body->data->code) && $body->data->code == 'site-user-different') {
				// Popup Site Already Connected
				wp_send_json_error('site-already-connected');
			}

			if ($body->success == true && $body->data->apikey != '' && $body->data->response_key != '') {
				$options = new wps_ic_options();
				$options->set_option('api_key', $body->data->apikey);
				$options->set_option('response_key', $body->data->response_key);

				/*$url = 'http://fra-01.wpeasymonitor.com/preload_do:true/url:' . site_url();
				$call = wp_remote_get($url, array('timeout'    => 10,
																					'sslverify'  => 'false',
																					'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'));*/

				// CDN Does exist or we just created it
				$zone_name = $body->data->zone_name;

				if ( ! empty($zone_name)) {
					update_option('ic_cdn_zone_name', $zone_name);
				}

				$settings = get_option(WPS_IC_SETTINGS);
				$sizes    = get_intermediate_image_sizes();
				foreach ($sizes as $key => $value) {
					$settings['thumbnails'][ $value ] = 1;
				}

				$default_Settings = array('js'               => '1',
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
																	'minify-js'        => '0');

				$settings = array_merge($settings, $default_Settings);

				$settings['live-cdn'] = '1';
				update_option(WPS_IC_SETTINGS, $settings);
				wp_send_json_success();
			}

			wp_send_json_error(array('uri' => $uri, 'body' => wp_remote_retrieve_body($get), 'code' => wp_remote_retrieve_response_code($get), 'get' => $get));
		}
		else {
			wp_send_json_error(array('Cannot Call API', $uri, wp_remote_retrieve_body($get), wp_remote_retrieve_response_code($get), wp_remote_retrieve_response_message($get)));
		}

		wp_send_json_error('0');
	}


	/**
	 * Change Settings Value
	 */
	public function wps_ic_settings_change() {
		global $wps_ic;

		$what     = sanitize_text_field($_POST['what']);
		$value    = sanitize_text_field($_POST['value']);
		$checked  = sanitize_text_field($_POST['checked']);
		$checkbox = sanitize_text_field($_POST['checkbox']);

		if ($what == 'apiv4') {
			$wps_ic->enable_apiv4($value);
		}

		$options  = new wps_ic_options();
		$settings = $options->get_settings();

		if ($what == 'thumbnails') {

			if ( ! isset($value) || empty($value)) {
				$settings['thumbnails'] = array();
			}
			else {
				$settings['thumbnails'] = array();
				$value                  = rtrim($value, ',');
				$value                  = explode(',', $value);
				foreach ($value as $i => $thumb_size) {
					$settings['thumbnails'][ $thumb_size ] = 1;
				}

			}

		}
		else {

			if ($what == 'autopilot') {
				if ($checked == 'checked') {

				}
				else {
					$settings['otto'] = 'automated';
				}
			}

			if ($checkbox == 'true') {
				if ($checked === 'false') {
					$settings[ $what ] = 0;
				}
				else {
					$settings[ $what ] = 1;
				}
			}
			else {
				$settings[ $what ] = $value;
			}
		}

		if ($what == 'live_autopilot') {
			if ($value == '1') {
				// Enabline Live, clear local queue
				$args = array('post_type'      => 'attachment',
											'post_status'    => 'inherit',
											'posts_per_page' => - 1,
											'meta_query'     => array(array('key'     => 'wps_ic_locked',
																											'compare' => 'EXISTS')));

				$attachments = new WP_Query($args);

				if ($attachments->have_posts()) {
					while ($attachments->have_posts()) {
						$attachments->the_post();
						$attID = get_the_ID();
						delete_post_meta($attID, 'wps_ic_locked');
					}
				}

				delete_option('wps_ic_bg_stop');
				delete_option('wps_ic_bg_process_stop');
				delete_option('wps_ic_bg_stopping');
				delete_option('wps_ic_bg_process');
				delete_option('wps_ic_bg_process_done');
				delete_option('wps_ic_bg_process_running');
				delete_option('wps_ic_bg_process_stats');
				delete_option('wps_ic_bg_last_run_compress');
				delete_option('wps_ic_bg_last_run_restore');

			}
		}
		else if ($what == 'css' || $what == 'js') {
			// Purge CSS/JS Cache
			$this->purge_cdn_assets();
		}

		update_option(WPS_IC_SETTINGS, $settings);

		wp_send_json_success();
	}


	/**
	 * @since 4.50.1
	 */
	public function count_attachments() {
		global $wpdb;
		$attachments = $wpdb->get_results("SELECT posts.ID FROM {$wpdb->posts} posts
			WHERE posts.post_type='attachment' AND posts.post_status='inherit' AND posts.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif', 'image/jpg')
			ORDER BY posts.ID DESC");

		if ($attachments) {
			$unique_attachments = array();
			foreach ($attachments as $attachment) {
				// Post which has got this meta is a clone
				$wpml = get_post_meta($attachment->ID, 'wpml_media_processed', true);
				if ( ! $wpml) {
					// Parent
					$unique_attachments[] = (object)array('ID' => $attachment->ID);
				}
			}
		}

		return $unique_attachments;
	}


	/**
	 * Deauthorize site with remote api
	 */
	public function wps_ic_deauthorize_api() {
		global $wps_ic;

		// Vars
		$site    = site_url();
		$options = new wps_ic_options();
		$apikey  = $options->get_option('api_key');

		// Setup URI
		$uri = WPS_IC_KEYSURL . '?action=disconnect&apikey=' . $apikey . '&site=' . urlencode($site);

		// Verify API Key is our database and user has is confirmed getresponse
		$get = wp_remote_get($uri, array('timeout' => 30, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'));

		$options->set_option('api_key', '');
		$options->set_option('response_key', '');
		$options->set_option('orp', '');
	}


	/**
	 * Heartbeat
	 */
	public function wps_ic_media_library_heartbeat() {
		global $wpdb, $wps_ic;

		$heartbeat_query = $wpdb->get_results("SELECT * FROM " . $wpdb->options . " WHERE option_name LIKE '_transient_wps_ic_compress_%' OR option_name LIKE '_transient_wps_ic_restore_%'");

		$html = array();
		if ($heartbeat_query) {
			foreach ($heartbeat_query as $heartbeat_item) {
				$value = unserialize(untrailingslashit($heartbeat_item->option_value));

				if ($value['status'] == 'compressed' || $value['status'] == 'restored') {
					$html[ $value['imageID'] ] = $wps_ic->media_library->compress_details($value['imageID']);
					delete_transient('wps_ic_compress_' . $value['imageID']);
					delete_transient('wps_ic_restore_' . $value['imageID']);
				}
			}

			wp_send_json_success($html);
		}

		wp_send_json_error();
	}


	public function wps_ic_media_library_bulk_restore_start() {
		global $wps_ic;
		$this->prepareBulkVariables();
		$queue = $wps_ic->media_library->prepare_restore();
	}


	public function prepareBulkVariables() {
		delete_option('wps_ic_bulk_stats');
		delete_option('wps_ic_restore_queue');
		delete_option('wps_ic_compress_queue');
	}


	public function wps_ic_media_library_bulk_compress_start() {
		global $wps_ic;
		$this->prepareBulkVariables();
		$queue = $wps_ic->media_library->prepare_compress();
	}


	public function wps_ic_getBulkStats() {
		$output = '';
		$output .= '<div class="wps-ic-bulk-html-wrapper">';
		$output .= '<div class="wps-ic-bulk-header">';
		$output .= '<div class="wps-ic-bulk-logo">';

		if ($_POST['type'] == 'compress') {
			#$output .= '<h2>We have compressed all images!</h2>';
		}
		else {
			$output .= '<h2>We have successfully restored all of your images!</h2>';
		}

		$output .= '<div class="logo-holder">';
		$output .= '<img src="' . WPS_IC_URI . 'assets/images/bulk/compress-complete.svg' . '">';
		$output .= '</div>';

		if ($_POST['type'] == 'compress') {
			$output .= '<div class="wps-ic-percent-savings">';
			$output .= '<h2>Image Compression Complete!</h2>';
			$output .= '</div>';
		}
		else {
			$output .= '<div class="wps-ic-percent-savings">';
			$output .= '<h2>Image Restore Complete</h2>';
			$output .= '</div>';
		}

		$output .= '</div>';
		$output .= '</div>';
		$output .= '</div>';

		wp_send_json_success(array('html' => $output));
	}


	public function wps_ic_remove_cname() {

		$cname     = get_option('ic_custom_cname');
		$zone_name = get_option('ic_cdn_zone_name');
		$options   = get_option(WPS_IC_OPTIONS);
		$apikey    = $options['api_key'];

		$url = WPS_IC_KEYSURL . '?action=cdn_removecname&apikey=' . $apikey . '&cname=' . $cname . '&zone_name=' . $zone_name . '&time=' . time() . '&no_cache=' . md5(mt_rand(999, 9999));

		$agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0';
		$call  = wp_remote_get($url, array('timeout' => 60, 'sslverify' => false, 'user-agent' => $agent));
		$call  = wp_remote_get(WPS_IC_KEYSURL . '?action=cdn_purge&domain=' . site_url() . '&apikey=' . $options['api_key'], array('timeout' => '10', 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'));

		delete_option('ic_custom_cname');

		$settings = get_option(WPS_IC_SETTINGS);
		$settings['cname'] = '';
		$settings['fonts'] = '';
		update_option(WPS_IC_SETTINGS, $settings);

		// Clear cache.
		if (function_exists('rocket_clean_domain')) {
			rocket_clean_domain();
		}

		// Lite Speed
		if (defined('LSCWP_V')) {
			do_action('litespeed_purge_all');
		}

		// HummingBird
		if (defined('WPHB_VERSION')) {
			do_action('wphb_clear_page_cache');
		}

		if (defined('BREEZE_VERSION')) {
			global $wp_filesystem;
			require_once(ABSPATH . 'wp-admin/includes/file.php');

			WP_Filesystem();

			$cache_path = breeze_get_cache_base_path(is_network_admin(), true);
			$wp_filesystem->rmdir(untrailingslashit($cache_path), true);

			if (function_exists('wp_cache_flush')) {
				wp_cache_flush();
			}
		}

		wp_send_json_success();
	}


	public function wps_ic_cname_retry() {
		$cname = get_option('ic_custom_cname');
		$retry_count = get_option('ic_cname_retry_count');

		if (!$retry_count) {
			update_option('ic_cname_retry_count', 1);
		} else {
			update_option('ic_cname_retry_count', $retry_count+1);
		}

		if ($retry_count>=3) {
			wp_send_json_error();
		}

		// Wait for SSL?
		sleep(10);

		wp_send_json_success(array('image' => 'https://' . $cname . '/' . WPS_IC_IMAGES . '/fireworks.svg', 'configured' => '<strong>Connected Domain: </strong><br/>' . $cname . '<br/>'));
	}

	public function wps_ic_cname_add() {
		$zone_name = get_option('ic_cdn_zone_name');
		$options   = get_option(WPS_IC_OPTIONS);
		$apikey    = $options['api_key'];

		if ( ! empty($_POST['cname'])) {
			$error   = '';
			$options = get_option(WPS_IC_OPTIONS);
			$apikey  = $options['api_key'];

			// TODO is cname valid?
			$cname = sanitize_text_field($_POST['cname']);
			$cname = str_replace(array('http://', 'https://'), '', $cname);
			$cname = rtrim($cname, '/');

			if ($zone_name == $cname) {
				$error = 'This domain is invalid, please link a new domain...';
				wp_send_json_error('invalid-domain');
			}

			if (strpos($cname, 'zapwp.com') !== false || strpos($cname, 'zapwp.net') !== false) {
				$error = 'This domain is invalid, please link a new domain...';
				wp_send_json_error('invalid-domain');
			}

			if (empty($error)) {

				if ( ! preg_match('/^([a-zA-z0-9\_\-]+)\.([a-zA-z0-9\_\-]+)\.([a-zA-z0-9\_\-]+)$/', $cname, $matches) && ! preg_match('/^([a-zA-z0-9\_\-]+)\.([a-zA-z0-9\_\-]+)\.([a-zA-z0-9\_\-]+)\.([a-zA-z0-9\_\-]+)$/', $cname, $matches)) {
					// Subdomain is not valid
					$error = 'This domain is invalid, please link a new domain...';
					delete_option('ic_custom_cname');
					$settings = get_option(WPS_IC_SETTINGS);
					unset($settings['cname']);
					update_option(WPS_IC_SETTINGS, $settings);
					wp_send_json_error('invalid-domain');
				}
				else {

					// Verify CNAME DNS
					$agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0';
					$verify_cname_dns = 'https://frankfurt.zapwp.net/?dnsCheck=true&host=' . $cname . '&zoneName=' . $zone_name . '&random=' . microtime(true);

					$call  = wp_remote_get($verify_cname_dns, array('timeout' => 60, 'sslverify' => false, 'user-agent' => $agent));
					if (wp_remote_retrieve_response_code($call) == 200) {

						$body = wp_remote_retrieve_body($call);
						$body = json_decode($body, true);
						$data = $body['data'];
						$host = $data['host'];
						$recordsType = $data['records']['type'];
						$recordsTarget = $data['records']['target'];

						if ($recordsType == 'CNAME') {
							if ($recordsTarget == $zone_name) {
								update_option('ic_custom_cname', sanitize_text_field($cname));

								$url = WPS_IC_KEYSURL . '?action=cdn_setcname&apikey=' . $apikey . '&cname=' . $cname . '&zone_name=' . $zone_name . '&time=' . time() . '&no_cache=' . md5(mt_rand(999, 9999));

								$call  = wp_remote_get($url, array('timeout' => 60, 'sslverify' => false, 'user-agent' => $agent));
								sleep(5);

								$call  = wp_remote_get(WPS_IC_KEYSURL . '?action=cdn_purge&domain=' . site_url() . '&apikey=' . $options['api_key'], array('timeout' => '10', 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'));

								// Wait for SSL?
								sleep(6);

								wp_send_json_success(array('image' => 'https://' . $cname . '/' . WPS_IC_IMAGES . '/fireworks.svg', 'configured' => '<strong>Connected Domain: </strong><br/>' . $cname . '<br/>'));
							}
						}

						wp_send_json_error('invalid-dns-prop');
					} else {
						wp_send_json_error('dns-api-not-working');
					}
				}
			}

			$custom_cname = get_option('ic_custom_cname');
			if ( ! $custom_cname) {
				$custom_cname = '';
			}

			wp_send_json_success($custom_cname);
		}
		else {
			$custom_cname = delete_option('ic_custom_cname');

			wp_send_json_success();
		}

	}


	public function wps_ic_exclude_list() {
		$excludeList = $_POST['excludeList'];
		$lazyExcludeList = $_POST['lazyExcludeList'];

		if ( ! empty($excludeList)) {
			$excludeList = rtrim($excludeList, "\n");
			$excludeList = explode("\n", $excludeList);
			update_option('wpc-ic-external-url-exclude', $excludeList);
		}
		else {
			delete_option('wpc-ic-external-url-exclude');
		}

		if ( ! empty($lazyExcludeList)) {
			$lazyExcludeList = rtrim($lazyExcludeList, "\n");
			$lazyExcludeList = explode("\n", $lazyExcludeList);
			update_option('wpc-ic-lazy-exclude', $lazyExcludeList);
		}
		else {
			delete_option('wpc-ic-lazy-exclude');
		}

		wp_send_json_success();
	}


	public function wps_ic_geolocation_force() {
		global $wps_ic;

		$post = $_POST['location'];

		if ($post == 'Automatic') {
			$geolocation = $this->geoLocateAjax();
			wp_send_json_success($geolocation);
		}

		$location_data = array('server' => 'frankfurt.zapwp.net', 'continent' => 'EU', 'continent_name' => 'Europe', 'country' => 'DE', 'country_name' => 'Germany');

		switch ($post) {
			case 'EU':
				break;
			case 'US':
				$location_data = array('server' => 'us.zapwp.net', 'continent' => 'US', 'continent_name' => 'United States', 'country' => 'US', 'country_name' => 'United States');
				break;
			case 'OC':
				$location_data = array('server' => 'sydney.zapwp.net', 'continent' => 'OC', 'continent_name' => 'Oceania', 'country' => 'AU', 'country_name' => 'Australia');
				break;
			case 'AS':
				$location_data = array('server' => 'singapore.zapwp.net', 'continent' => 'AS', 'continent_name' => 'Asia', 'country' => 'Singapore', 'country_name' => 'Singapore');
				break;
		}

		update_option('wpc-ic-force-location', $location_data);
		update_option('wps_ic_geo_locate', $location_data);

		wp_send_json_success($location_data);
	}


	public function wps_ic_geolocation() {
		global $wps_ic;
		$geolocation = $this->geoLocateAjax();
		wp_send_json_success($geolocation);
	}


	public function wps_ic_compress_details() {
		global $wps_ic;
		$attID = sanitize_text_field($_POST['attachment_id']);
		$html  = $wps_ic->media_library->compress_details_popup($attID);
		wp_send_json_success(array('html' => $html));
	}


	public function wps_ic_RestoreFinished() {
		global $wps_ic;

		$count  = $_POST['count'] . ' of ' . $_POST['count'];
		$output = '';

		$output .= '<div class="wps-ic-bulk-html-wrapper">';

		$output .= '<div class="bulk-restore-container">';

		$output .= '<div class="bulk-restore-preview-container">';
		$output .= '<div class="bulk-restore-preview-inner">';
		$output .= '<div class="bulk-restore-preview-image-holder">';
		$output .= '<img src="' . WPS_IC_URI . 'assets/images/bulk/restore-completed-image.png' . '">';
		$output .= '</div>';
		$output .= '</div>';
		$output .= '</div>';

		$output .= '<div class="bulk-restore-info">';

		$output .= '<div class="bulk-restore-status-top-left">';
		$output .= '<img src="' . WPS_IC_URI . 'assets/images/shield.svg' . '">';
		$output .= '<span class="badge">';
		$output .= '<i class="icon-check"></i> Restored';
		$output .= '</span>';
		$output .= '</div>';

		$output .= '<div class="bulk-restore-status-top-right">';
		$output .= '<h3>' . $count . '</h3>';
		$output .= '<h5>Images Restored</h5>';
		$output .= '</div>';

		$output .= '<div class="bulk-restore-status-container">';
		$output .= '<h4>Image Restore Complete!</h4>';
		$output .= '<span>We have successfully restored all of your images.</span>';
		$output .= '<div class="bulk-status-progress-bar">
              <div class="progress-bar-outer">
                <div class="progress-bar-inner" style="width: 100%;"></div>
              </div>
            </div>';
		$output .= '</div>';

		$output .= '</div>';

		$output .= '</div>';

		wp_send_json_success(array('html' => $output));
	}


	public function wps_ic_doBulkRestore() {
		global $wps_ic;

		$lastProgress            = $_POST['lastProgress'];
		$bulkStats               = get_option('wps_ic_bulk_stats');
		$compressed_images_queue = get_option('wps_ic_restore_queue');

		if (empty($bulkStats['images_restored'])) {
			$bulkStats['images_restored'] = 0;
		}

		if ($compressed_images_queue['queue']) {

			$attID = $compressed_images_queue['queue'][0];

			// First Image
			set_transient('wps_ic_restore_' . $attID, array('imageID' => $attID, 'status' => 'restoring'), 0);

			// do the restore
			self::$local->restore($attID);

			set_transient('wps_ic_restore_' . $attID, array('imageID' => $attID, 'status' => 'restored'), 0);

			unset($compressed_images_queue['queue'][0]);
			$compressed_images_queue['queue'] = array_values($compressed_images_queue['queue']);

			// Sleep so that it takes longer
			sleep(2);

			/**
			 * Calculate Progress
			 */
			$leftover_images  = count($compressed_images_queue['queue']);
			$total_images     = $compressed_images_queue['total_images'];
			$done_images      = $total_images - $leftover_images;
			$progress_percent = round(($done_images / $total_images) * 100);

			// Bulk Stats
			$bulkStats['images_restored'] += 1;

			update_option('wps_ic_bulk_stats', $bulkStats);
			update_option('wps_ic_restore_queue', $compressed_images_queue);

			wp_send_json_success(array('done'     => $attID,
																 'progress' => $progress_percent,
																 'finished' => $done_images,
																 'leftover' => $leftover_images,
																 'total'    => $total_images,
																 'todo'     => $compressed_images_queue,
																 'html'     => $this->bulkRestoreHtml($attID, $lastProgress)));
		}

		wp_send_json_error();
	}


	public function bulkRestoreHtml($imageID, $lastProgress) {
		$output = '';

		$thumbnail = wp_get_attachment_image_src($imageID, 'full');
		$full      = wp_get_attachment_image_src($imageID, 'full');

		$image_full_filename = basename($full[0]);
		$filedata            = get_attached_file($imageID);
		$original_filesize   = filesize($filedata);

		$output .= '<div class="wps-ic-bulk-html-wrapper">';

		$output .= '<div class="bulk-restore-container">';

		$output .= '<div class="bulk-restore-preview-container">';
		$output .= '<div class="bulk-restore-preview-inner">';
		$output .= '<div class="bulk-restore-preview-image-holder">';
		$output .= '<div class="image-holder-inner">';
		#$output .= '<img src="' . $thumbnail[0] . '" style="opacity:0;width:100%;"/>';
		$output .= '<div style="background-image:url(' . $thumbnail[0] . ');" class="image-holder-bg"></div>';
		$output .= '</div>';
		$output .= '</div>';
		$output .= '</div>';
		$output .= '</div>';

		$output .= '<div class="bulk-restore-info">';

		$output .= '<div class="bulk-restore-status-top-left">';
		$output .= '<span class="badge"><i class="icon icon-check"></i> Restored</span>';
		$output .= '</div>';

		$output .= '<div class="bulk-restore-status-top-right">';
		$output .= '<h3>16 / 16</h3>';
		$output .= '<h5>Images Restored</h5>';
		$output .= '</div>';

		$output .= '<div class="bulk-restore-status-container">';
		$output .= '<h4>' . $image_full_filename . '</h4>';
		$output .= '<span><i class="restore-bullet"></i> ' . wps_ic_format_bytes($original_filesize, null, null, false) . '</span>';
		$output .= '<div class="bulk-status-progress-bar">
              <div class="progress-bar-outer">
                <div class="progress-bar-inner" style="width: ' . $lastProgress . '%;"></div>
              </div>
            </div>';
		$output .= '</div>';

		$output .= '</div>';

		$output .= '</div>';

		$output .= '</div>';

		return $output;
	}


	public function wps_ic_doBulkCompress() {
		global $wps_ic;

		$uncompressed_images_queue = get_option('wps_ic_compress_queue');

		if ($uncompressed_images_queue['queue']) {

			$attID = $uncompressed_images_queue['queue'][0];

			// First Image
			set_transient('wps_ic_compress_' . $attID, array('imageID' => $attID, 'status' => 'compressing'), 30);

			self::$local->backup_image($attID);
			$bulkStats = self::$local->compress_image($attID);

			set_transient('wps_ic_compress_' . $attID, array('imageID' => $attID, 'status' => 'compressed'), 30);

			unset($uncompressed_images_queue['queue'][0]);
			$uncompressed_images_queue['queue'] = array_values($uncompressed_images_queue['queue']);

			/**
			 * Calculate Progress
			 */
			$leftover_images  = count($uncompressed_images_queue['queue']);
			$total_images     = $uncompressed_images_queue['total_images'];
			$done_images      = $total_images - $leftover_images;
			$progress_percent = round(($done_images / $total_images) * 100);

			/*
			 * Bulk Stats
			 */
			$bulkSavings      = wps_ic_format_bytes($bulkStats['total']['original'] - $bulkStats['total']['compressed'], null, null, false);
			$bulkThumbSavings = wps_ic_format_bytes($bulkStats['thumbs']['original'] - $bulkStats['thumbs']['compressed'], null, null, false);

			$avgReduction = (1 - (($bulkStats['total']['compressed'] / $bulkStats['images_compressed']) / ($bulkStats['total']['original'] / $bulkStats['images_compressed']))) * 100;
			$avgReduction = number_format($avgReduction, 1);

			// Progress HTML
			$CompressedImagesHTML = '<h3>' . $bulkStats['images_compressed'] . '/' . $total_images . '</h3><h5>Original Images</h5>';
			$CompressedThumbsHTML = '<h3>' . $bulkStats['thumbs_compressed'] . '</h3><h5>Total Images</h5>';
			$TotalSavingsHtml     = '<h3>' . $bulkSavings . '</h3><h5>Total Savings</h5>';
			//$ThumbsSavingsHtml    = '<h3>' . $bulkThumbSavings . '</h3><h5>Thumbs Savings</h5>';
			$TotalAvgReduction = '<h3>' . $avgReduction . '%</h3><h5>Average Savings</h5>';

			$full                = wp_get_attachment_image_src($attID, 'full');
			$image_full_filename = basename($full[0]);

			update_option('wps_ic_bulk_stats', $bulkStats);
			update_option('wps_ic_compress_queue', $uncompressed_images_queue);

			$stats               = get_post_meta($attID, 'ic_stats', true);
			$original_filesize   = wps_ic_format_bytes($stats['full']['original']['size'], null, null, false);
			$compressed_filesize = wps_ic_format_bytes($stats['full']['compressed']['size'], null, null, false);
			$savings_kb          = wps_ic_format_bytes($stats['full']['original']['size'] - $stats['full']['compressed']['size'], null, null, false);

			$savings = 1 - ($stats['full']['compressed']['size'] / $stats['full']['original']['size']);
			$savings = round($savings * 100, 1);

			$status = '';
			$status .= '<ul class="wps-icon-list">';
			$status .= '<li><i class="wps-icon saved"></i> ' . $savings_kb . ' Saved</li>';
			$status .= '<li><i class="wps-icon quality"></i> ' . ucfirst(self::$settings['optimization']) . ' Mode</li>';
			if (self::$settings['generate_webp'] == '1') {
				$status .= '<li><i class="wps-icon webp"></i> WebP Generated</li>';
			}
			$status .= '</ul>';

			wp_send_json_success(array('totalCompressed'          => $bulkStats['total']['compressed'],
																 'totalOriginal'            => $bulkStats['total']['original'],
																 'count'                    => $bulkStats['images_compressed'],
																 'done'                     => $attID,
																 'progress'                 => $progress_percent,
																 'finished'                 => $done_images,
																 'leftover'                 => $leftover_images,
																 'total'                    => $total_images,
																 'todo'                     => $uncompressed_images_queue,
																 'progressCompressedImages' => $CompressedImagesHTML,
																 'progressCompressedThumbs' => $CompressedThumbsHTML,
																 'progressTotalSavings'     => $TotalSavingsHtml,
																 'progressThumbsSavings'    => $ThumbsSavingsHtml,
																 'progressAvgReduction'     => $TotalAvgReduction,
																 'lastFileName'             => $image_full_filename,
																 'html'                     => $this->bulkCompressHtml($attID),
																 'status'                   => $status));
		}

		wp_send_json_error();
	}


	public function bulkCompressHtml($imageID) {
		$output = '';

		$thumbnail = wp_get_attachment_image_src($imageID, 'large');
		$full      = wp_get_attachment_image_src($imageID, 'full');

		$backup_images = get_post_meta($imageID, 'ic_backup_images', true);
		$stats         = get_post_meta($imageID, 'ic_stats', true);

		$image_filename      = basename($thumbnail[0]);
		$image_full_filename = basename($full[0]);

		// Does the backup exist, if not replace with original
		if ( ! empty($backup_images['full']) && ! file_exists($backup_images['full'])) {
			$original_image = $thumbnail[0];
		}
		else {
			$original_image = $full[0];
		}

		$original_filesize   = wps_ic_format_bytes($stats['full']['original']['size'], null, null, false);
		$compressed_filesize = wps_ic_format_bytes($stats['full']['compressed']['size'], null, null, false);
		$savings_kb          = wps_ic_format_bytes($stats['full']['original']['size'] - $stats['full']['compressed']['size'], null, null, false);

		$savings = 1 - ($stats['full']['compressed']['size'] / $stats['full']['original']['size']);
		$savings = round($savings * 100, 1);

		$output .= '<div class="wps-ic-bulk-html-wrapper">';

		$output .= '<div class="wps-ic-bulk-header">';
		$output .= '<div class="wps-ic-bulk-before">';
		$output .= '<div class="image-holder">';

		$output .= '<div class="image-holder-inner">';
		#$output .= '<img src="' . $original_image . '" style="opacity:0;width:100%;"/>';
		$output .= '<div style="background-image:url(' . $original_image . ');" class="image-holder-bg"></div>';
		$output .= '</div>';

		$output .= '<div class="image-info-holder">';
		$output .= '<h4>Before</h4>';
		$output .= '<h3>' . $original_filesize . '</h3>';
		$output .= '</div>';

		$output .= '</div>';
		$output .= '</div>';
		$output .= '<div class="wps-ic-bulk-logo">';
		$output .= '<div class="logo-holder">';
		#$output .= '<img src="' . WPS_IC_URI . 'assets/images/shield.svg' . '">';
		$output .= '<div class="wps-ic-bulk-preparing-logo-container">
        <div class="wps-ic-bulk-preparing-logo">
          <img src="' . WPS_IC_URI . 'assets/images/logo/blue-icon.svg" class="bulk-logo-prepare"/>
          <img src="' . WPS_IC_URI . 'assets/preparing.svg" class="bulk-preparing"/>
        </div>
      </div>';
		$output .= '</div>';
		$output .= '<div class="wps-ic-percent-savings">';
		$output .= '<h3>' . $savings . '% Savings</h3>';
		#$output .= '<h5>' . $savings_kb . ' saved</h5>';
		$output .= '</div>';
		$output .= '<div class="wps-ic-bulk-loading">';
		$output .= '';
		$output .= '</div>';
		$output .= '</div>';
		$output .= '<div class="wps-ic-bulk-after">';
		$output .= '<div class="image-holder">';

		$output .= '<div class="image-holder-inner">';
		#$output .= '<img src="' . $thumbnail[0] . '" style="opacity:0;width:100%;"/>';
		$output .= '<div style="background-image:url(' . $thumbnail[0] . ');" class="image-holder-bg"></div>';
		$output .= '</div>';

		$output .= '<div class="image-info-holder">';
		$output .= '<h4>After</h4>';
		$output .= '<h3>' . $compressed_filesize . '</h3>';
		$output .= '</div>';

		$output .= '</div>';
		$output .= '</div>';
		$output .= '</div>';

		$output .= '</div>';

		return $output;
	}


	public function wps_ic_media_library_bulk_heartbeat() {
		global $wpdb, $wps_ic;
		$heartbeat_query = $wpdb->get_results("SELECT * FROM " . $wpdb->options . " WHERE option_name LIKE '_transient_wps_ic_compress_%' OR option_name LIKE '_transient_wps_ic_restore_%'");

		$html = array();
		if ($heartbeat_query) {
			foreach ($heartbeat_query as $heartbeat_item) {
				$value = unserialize(untrailingslashit($heartbeat_item->option_value));

				if ($value['status'] == 'compressed' || $value['status'] == 'restored') {
					$html[ $value['imageID'] ] = $wps_ic->media_library->compress_details($value['imageID']);
					delete_transient('wps_ic_compress_' . $value['imageID']);
					delete_transient('wps_ic_restore_' . $value['imageID']);
				}
			}

			wp_send_json_success($html);
		}

		wp_send_json_error();
	}


	public function do_restore($arg) {
		set_transient('wps_ic_restore_' . $arg, array('imageID' => $arg, 'status' => 'restoring'), 0);

		// do the restore
		self::$local->restore($arg);

		set_transient('wps_ic_restore_' . $arg, array('imageID' => $arg, 'status' => 'restored'), 0);
		die();
	}


	/**
	 * Live Compress
	 */
	public function wps_ic_restore_live() {
		global $wps_ic;
		call_user_func_array(array($this, 'do_restore'), array($_POST['attachment_id']));
		sleep(1);
		wp_send_json_success();
	}


	public function do_compress($arg) {
		/*
		 * Check if it's a single file clicked or bulk compress button
		 */
		set_transient('wps_ic_compress_' . $arg, array('imageID' => $arg, 'status' => 'compressing'), 30);

		$settings = get_option(WPS_IC_SETTINGS);

		self::$local->backup_image($arg);
		self::$local->compress_image($arg, false, $settings['retina'], $settings['generate_webp']);
		#self::$local->regenerate_thumbnails($arg, false, $settings['retina'], $settings['generate_webp']);

		set_transient('wps_ic_compress_' . $arg, array('imageID' => $arg, 'status' => 'compressed'), 30);
	}


	public function wps_ic_compress_live() {
		self::$accountStatus = parent::getAccountStatusMemory();


		if (self::$accountStatus->bytes->package_local_leftover <= 0) {
			wp_send_json_error('no-credits');
		}

		if (self::$accountStatus->bytes->package_local_leftover <= self::$count_thumbs) {
			// Force refresh of credits
			delete_transient( 'wps_ic_account_status' );
		}

		call_user_func_array(array($this, 'do_compress'), array($_POST['attachment_id']));
		sleep(1);
		wp_send_json_success();
	}


	/**
	 * Count Uncompressed Images
	 */
	public function wps_ic_count_uncompressed_images() {
		global $wpdb;

		$args = array('post_type'      => 'attachment',
									'post_status'    => 'inherit',
									'posts_per_page' => - 1,
									'meta_query'     => array('relation' => 'AND',
										array('key'     => 'wps_ic_data',
													'compare' => 'NOT EXISTS'),
										array('key'     => 'wps_ic_exclude',
													'compare' => 'NOT EXISTS')));

		$uncompressed_attachments = new WP_Query($args);
		$total_file_size          = 0;
		if ($uncompressed_attachments->have_posts()) {

			while ($uncompressed_attachments->have_posts()) {
				$uncompressed_attachments->the_post();
				$postID = get_the_ID();

				$filesize        = filesize(get_attached_file($postID));
				$total_file_size += $filesize;
			}
		}

		wp_send_json_success(array('uncompressed' => $total_file_size, 'unit' => 'Bytes'));
	}


}