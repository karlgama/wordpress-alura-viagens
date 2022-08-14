<?php


/**
 * Class - Options
 */
class wps_ic_options {

	public static $options;


	public function __construct() {
		return $this;
	}


	/**
	 * Save settings
	 */
	public function save_settings() {
		if ( ! empty($_POST)) {

			$options                           = get_option(WPS_IC_SETTINGS);
			$_POST['wp-ic-setting']['unlocks'] = $options['unlocks'];

			if (empty($_POST['wp-ic-setting']['optimization']) || $_POST['wp-ic-setting']['optimization'] == '0') {
				$_POST['wp-ic-setting']['optimization'] = 'maximum';
			}

			if (empty($_POST['wp-ic-setting']['optimize_upload'])) {
				$_POST['wp-ic-setting']['optimize_upload'] = '0';
			}

			if (empty($_POST['wp-ic-setting']['ignore_larger_images'])) {
				$_POST['wp-ic-setting']['ignore_larger_images'] = '0';
			}

			if (empty($_POST['wp-ic-setting']['resize_larger_images'])) {
				$_POST['wp-ic-setting']['resize_larger_images'] = '0';
			}

			if (empty($_POST['wp-ic-setting']['resize_larger_images_width'])) {
				$_POST['wp-ic-setting']['resize_larger_images_width'] = '2048';
			}

			if (empty($_POST['wp-ic-setting']['ignore_larger_images_width'])) {
				$_POST['wp-ic-setting']['ignore_larger_images_width'] = '2048';
			}

			if (empty($_POST['wps_no']['time'])) {
				$_POST['wp-ic-setting']['wps_no']['time'] = '';
			}

			if (empty($_POST['wp-ic-setting']['backup'])) {
				$_POST['wp-ic-setting']['backup'] = '0';
			}

			if (empty($_POST['wp-ic-setting']['hide_compress'])) {
				$_POST['wp-ic-setting']['hide_compress'] = '0';
			}

			if (empty($_POST['wp-ic-setting']['thumbnails_locally'])) {
				$_POST['wp-ic-setting']['thumbnails_locally'] = '0';
			}

			if (empty($_POST['wp-ic-setting']['debug'])) {
				$_POST['wp-ic-setting']['debug'] = '0';
			}

			if (empty($_POST['wp-ic-setting']['preserve_exif'])) {
				$_POST['wp-ic-setting']['preserve_exif'] = '0';
			}

			if (empty($_POST['wp-ic-setting']['night_owl'])) {
				$_POST['wp-ic-setting']['night_owl'] = '0';
			}

			if (empty($_POST['wp-ic-setting']['otto'])) {
				$_POST['wp-ic-setting']['otto'] = 'off';
			}

			if (empty($_POST['wp-ic-setting']['night_owl_upload'])) {
				$_POST['wp-ic-setting']['night_owl_upload'] = '0';
			}

			if ( ! empty($_POST['wp-ic-setting']['thumbnails'])) {
				foreach ($_POST['wp-ic-setting']['thumbnails'] as $key => $value) {
					$_POST['wp-ic-setting']['thumbnails'][ $key ] = 1;
				}
			}

			// Sanitize
			foreach ($_POST['wp-ic-setting'] as $key => $value) {
				$_POST['wp-ic-setting'][ $key ] = $value;
			}

			update_option(WPS_IC_SETTINGS, $_POST['wp-ic-setting']);
		}
	}


	/**
	 * Get compress stats (total images, total saved)
	 * @return mixed|void
	 */
	public function get_stats() {
		global $wpdb;

		$query = $wpdb->prepare("SELECT COUNT(ID) as images, SUM(saved) as saved FROM " . $wpdb->prefix . "ic_compressed ORDER by ID");
		$query = $wpdb->get_results($query);

		return array('images' => $query[0]->images, 'saved' => $query[0]->saved);
	}


	/**
	 * Update stats
	 */
	public function update_stats($attachment_ID = 1, $saved = '', $action = 'add') {
		global $wpdb;

		$attachment_ID = (int)$attachment_ID;
		$saved         = sanitize_text_field($saved);

		if ($action == 'add') {
			$query = $wpdb->prepare("INSERT INTO " . $wpdb->prefix . "ic_compressed (created, attachment_ID, saved, count) VALUES (%s, %s, %s, %s) ON DUPLICATE KEY UPDATE created=%s, count=count+1, restored=0", current_time('mysql'), $attachment_ID, $saved, current_time('mysql'), '1');
			$wpdb->query($query);
		}
		else {
			//
		}

	}


	/**
	 * Get various settings for WP Compress
	 * @return mixed|void
	 */
	public function get_settings() {
		$settings = get_option(WPS_IC_SETTINGS);

		if ( ! $settings) {
			$this->set_recommended_options();
			$settings = get_option(WPS_IC_SETTINGS);
		}

		return $settings;
	}


	/**
	 * Set recommended options
	 */
	public function set_recommended_options() {
		$settings_opt = get_option(WPS_IC_SETTINGS);

		if ( ! empty($settings['hide_compress'])) {
			$settings['hide_compress'] = '0';
		}

		if (empty($settings['replace-method']) || $settings['replace-method'] == 'dom') {
			$settings['replace-method'] = 'regexp';
			update_option(WPS_IC_SETTINGS, $settings);
		}

		if ( ! $settings_opt) {
			// Default settings
			$settings['replace-method']             = 'regexp';
			$settings['live-cdn']                   = '1';
			$settings['otto']                       = 'on-upload';
			$settings['optimization']               = 'intelligent';
			$settings['resize_larger_images']       = '1';
			$settings['resize_larger_images_width'] = '2048';
			$settings['lazyload_threshold']         = '300';
			$settings['hide_compress']              = '0';
			$settings['preserve_exif']              = '0';
			$settings['generate_adaptive']          = '1';
			$settings['generate_webp']              = '1';
			$settings['backup-location']            = 'cloud';
			$settings['lazy']                       = '0';
			$settings['retina']                     = '1';
			$settings['external-url']               = '0';
			$settings['remove-render-blocking']     = '0';
			$settings['background-sizing']     = '0';

			// Advanced Settings
			$settings['cname']          = '';
			$settings['js']             = '0';
			$settings['js-minify']      = '0';
			$settings['css']            = '0';
			$settings['css-minify']     = '0';
			$settings['defer-js']       = '0';
			$settings['css_image_urls'] = '0';
			$settings['emoji-remove']   = '0';

			// Serve Images
			$settings['serve_jpg']      = '1';
			$settings['serve_gif']      = '1';
			$settings['serve_png']      = '1';
			$settings['serve_svg']      = '1';
			$settings['search-through'] = 'html';

			// Debug
			$settings['url-method'] = 'cdn';
			$settings['cdn-lazy']   = '1';

			// Live API
			$settings['live_api'] = '1';

			// Find all thumbnail sizes and put them as "active"
			$sizes = get_intermediate_image_sizes();
			if ( ! empty($sizes)) {
				foreach ($sizes as $key => $value) {
					$settings['thumbnails'][ $value ] = 1;
				}
			}

			// Save the settings
			update_option(WPS_IC_SETTINGS, $settings);
		}
	}


	/**
	 * Set missing options
	 */
	public function set_missing_options() {
		$settings = array();

		$settings = get_option(WPS_IC_SETTINGS);

		if ( ! $settings) {
			$settings['live-cdn'] = '1';
		}

		// Save the settings
		update_option(WPS_IC_SETTINGS, $settings);
	}


	/**
	 * Fetch specific option or all options if key is empty
	 *
	 * @param null $key
	 *
	 * @return bool|mixed|void
	 */
	public function get_option($key = null) {
		$options = get_option(WPS_IC_OPTIONS);

		if ($key == null) {

			if (empty($options)) {
				return false;
			}

			return $options;
		}
		else {

			if (empty($options[ $key ])) {
				return false;
			}

			return $options[ $key ];
		}

	}


	/**
	 * Set option with key and value
	 *
	 * @param $key
	 * @param $value
	 */
	public function set_option($key, $value) {
		$options         = get_option(WPS_IC_OPTIONS);
		$options[ $key ] = $value;
		update_option(WPS_IC_OPTIONS, $options);
	}


	/**
	 * Setup default settings
	 */
	public function set_defaults() {
		$this->set_recommended_options();
	}

}