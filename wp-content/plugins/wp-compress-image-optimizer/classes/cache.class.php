<?php


/**
 * Class - Cache
 * Handles CSS Caching
 */
class wps_ic_cache {

	public static $cache_option = 'wps_ic_modified_css_cache';
	public static $cache;
	public static $options;


	public function __construct() {
		self::$cache   = get_option(self::$cache_option);
		self::$options = get_option(WPS_IC_OPTIONS);
	}


	public static function purge_hooks() {
		add_action('save_post', array('wps_ic_cache', 'update_css_hash'), 10, 1);

		#add_action('pre_post_update', array('wps_ic_cache', 'purge_post_on_update'), 10, 1);
		#add_action('save_post', array('wps_ic_cache', 'purge_post_on_update'), 10, 1);
		#add_action('wp_trash_post', array('wps_ic_cache', 'purge_post_on_update'), 10, 1);
		#add_action('comment_post', array($this, 'purge_post_on_new_comment'), 10, 3);
		#add_action('wp_set_comment_status', array($this, 'purge_post_on_comment_status_change'), 10, 2);
	}


	public static function update_css_hash() {
		if ( ! function_exists('get_option')) {
			require_once ABSPATH . 'wp-admin/includes/option.php';
		}

		$hash = substr(md5(microtime(true)), 0, 6);

		if (is_multisite()) {
			$current_blog_id = get_current_blog_id();
			switch_to_blog($current_blog_id);
			$options             = get_option(WPS_IC_OPTIONS);
			#$hash                = time();
			$options['css_hash'] = $hash;
			update_option(WPS_IC_OPTIONS, $options);
			/* Was causing problems with feedzy!!
			$sites = get_sites();
			if ($sites) {
				$hash = time();
				foreach ($sites as $site) {
					switch_to_blog($site->blog_id);
					$options             = get_option(WPS_IC_OPTIONS);
					$options['css_hash'] = $hash;
					update_option(WPS_IC_OPTIONS, $options);
				}
			}*/

		}
		else {
			#$hash                = time();
			$options             = get_option(WPS_IC_OPTIONS);
			$options['css_hash'] = $hash;
			update_option(WPS_IC_OPTIONS, $options);

		}
	}


	public static function purge_post_on_update($post_id) {
		$post_type = get_post_type($post_id);
		$purging   = get_transient('wps_ic_purging_cdn');
		set_transient('wps_ic_purging_cdn', 5);
		if ( ! $post_id || ! $post_type) {
			return;
		}

		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || 'revision' === $post_type) {
			return;
		}
		elseif ( ! current_user_can('edit_post', $post_id) && ( ! defined('DOING_CRON') || ! DOING_CRON)) {
			return;
		}

		if ( ! $purging) {
			self::purgeCache();
		}

		return;
	}


	public static function purgeCache() {
		self::$options = get_option(WPS_IC_OPTIONS);
		set_transient('wps_ic_purging_cdn', 'true', 10);
		$url  = WPS_IC_KEYSURL . '?action=cdn_purge&apikey=' . self::$options['api_key'] . '&callback=' . site_url() . '&hash=' . md5(microtime());
		$call = wp_remote_get($url, array('timeout' => 4, 'blocking' => false, 'sslverify' => 'false', 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'));

		// Purge Cached Files
		$cache_dir = WPS_IC_CACHE;
		if (file_exists($cache_dir)) {
			self::removeDirectory($cache_dir);
		}
	}


	public static function removeDirectory($path) {
		$files = glob($path . '/*');
		foreach ($files as $file) {
			is_dir($file) ? self::removeDirectory($file) : unlink($file);
		}

		return;
	}


	public static function init() {
		self::$cache   = get_option(self::$cache_option);
		self::$options = get_option(WPS_IC_OPTIONS);

		if ( ! empty($_GET['wpc_action'])) {
			self::purge_actions();
		}
	}


	public static function purge_actions() {
		if ( ! empty($_GET['wpc_action']) && empty($_GET['apikey'])) {
			wp_send_json_error();
		}

		if ( ! empty($_GET['wpc_action'])) {
			$apikey = sanitize_text_field($_GET['apikey']);
			if ($apikey !== self::$options['api_key']) {
				wp_send_json_error('Bad API Key');
			}

			switch ($_GET['wpc_action']) {
				case 'purge_other_cache':

					$options = get_option(WPS_IC_OPTIONS);
					$options['css_hash'] = mt_rand(1000,9999);
					update_option(WPS_IC_OPTIONS, $options);

					self::purgeOtherCache();
					break;
			}
		}
	}


	public static function purgeOtherCache($json = true) {

		// Rocket - Clear cache
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

		// Breeze
		self::purgeBreeze();

		// Others
		self::purgeSuperCache();
		self::purgeFastestCache();
		self::purge_cache_files();

		if ($json) {
			wp_send_json_success( 'Purged Other Cache' );
		}
	}


	public static function purgeFastestCache() {
		if (defined('WPFC_WP_CONTENT_BASENAME')) {
			global $file_prefix;
			wp_cache_clean_cache($file_prefix, ! empty($params['all']));
		}
	}


	public static function purgeSuperCache() {
		if (defined('WPCACHEHOME')) {
			global $file_prefix;
			wp_cache_clean_cache($file_prefix, ! empty($params['all']));
		}
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


	public function is_cached($args = array()) {

		// Is page or post in our cache buffer?
		if (self::is_page_cached($args['ID']) || self::is_post_cached($args['ID'])) {

			// Fetch cache buffer
			$cache = self::get_cache($args['ID']);

		}
		else {

			return false;

		}

	}


	// Store cached file

	public function is_page_cached($pageID) {
		if (isset(self::$cache[ $pageID ]) && ! empty(self::$cache[ $pageID ])) {
			return true;
		}
		else {
			return false;
		}
	}


	// Get cached file

	public function is_post_cached($postID) {
		if (isset(self::$cache[ $postID ]) && ! empty(self::$cache[ $postID ])) {
			return true;
		}
		else {
			return false;
		}
	}


	// Save cache data

	public function get_cache($ID) {
		if (isset(self::$cache[ $ID ]) && ! empty(self::$cache[ $ID ])) {
			return self::$cache[ $ID ];
		}
		else {
			return array();
		}
	}


	// Retrieve cache data

	public function store_cached_file($handle, $data) {

	}


	public function get_cached_file($ID, $handle) {
		// Fetch cache buffer
		$cache = self::get_cache($ID);
		if ( ! empty($cache[ $ID ][ $handle ])) {
			return $cache[ $ID ][ $handle ];
		}
		else {
			return false;
		}
	}


	public function store_cache($ID, $data) {
		// Fetch cache buffer
		$cache = self::get_cache($ID);

		$cache[ $ID ] = $data;

		update_option(self::$cache_option, $cache);
	}

}