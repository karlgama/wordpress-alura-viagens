<?php

define('WPS_IC_QUEUE_EXECUTION_TIME', 360);
if (empty($_GET['min_debug'])) {
	define('WPS_IC_MIN', '.min'); // .min => script.min.js
} else {
	define('WPS_IC_MIN', ''); // .min => script.min.js
}

define('WPS_IC_GB', 1000000000);

define('WPS_IC_APIURL', 'https://legacy-eu.wpcompress.com/');
define('WPS_IC_KEYSURL', 'https://keys.wpmediacompress.com/');

define('WPS_IC_MU_SETTINGS', 'wps_ic_mu_settings');
define('WPS_IC_SETTINGS', 'wps_ic_settings');
define('WPS_IC_OPTIONS', 'wps_ic');

define('WPS_IC_BULK', 'wps_ic_bulk');

$plugin_dir = str_replace(site_url('/', 'https'), '', WP_PLUGIN_URL);
$plugin_dir = str_replace(site_url('/', 'http'), '', $plugin_dir);

define('WPS_IC_URI', plugin_dir_url(__FILE__));
define('WPS_IC_DIR', plugin_dir_path(__FILE__));
define('WPS_IC_IMAGES', $plugin_dir . '/wp-compress-image-optimizer/assets/images');
define('WPS_IC_TEMPLATES', plugin_dir_path(__FILE__) . 'templates/');

define('WPS_IC_UPLOADS_DIR', WP_CONTENT_DIR . '/uploads');
define('WPS_IC_CACHE', WP_CONTENT_DIR . '/cache/wp-cio/');
define('WPS_IC_CACHE_URL', WP_CONTENT_URL . '/cache/wp-cio/');
define('WPS_IC_LOG', WPS_IC_DIR . 'logs/');
define('WPS_IC_LOG_URL', WPS_IC_URI . 'logs/');

if ( ! file_exists(WP_CONTENT_DIR . '/cache')) {
	mkdir(WP_CONTENT_DIR . '/cache');
}

if ( ! file_exists(WPS_IC_CACHE)) {
	mkdir(rtrim(WPS_IC_CACHE, '/'));
}

// Stats v2
define('WPS_IC_STATS_BULK_FILES', 'wps_ic_stats_bulk_files');
define('WPS_IC_STATS_BULK_TOTAL_FILES', 'wps_ic_stats_bulk_total_files');
define('WPS_IC_STATS_BULK_SAVINGS', 'wps_ic_stats_bulk_savings');
define('WPS_IC_STATS_BULK_AVG', 'wps_ic_stats_bulk_avg');
define('WPS_IC_STATS_FILES', 'wps_ic_files_processed');
define('WPS_IC_STATS_BYTES', 'wps_ic_bytes_saved');
define('WPS_IC_STATS_AVG_REDUCTION', 'wps_ic_avg_reduction');