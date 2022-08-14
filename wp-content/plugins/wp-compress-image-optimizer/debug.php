<?php

if (empty($_GET['write_debug_log'])) {
	define('WPS_IC_DEBUG_LOG', false);
}
else {
	define('WPS_IC_DEBUG_LOG', true);
}

if ( ! empty($_GET['ic_debug'])) {
	if ($_GET['ic_debug'] == 'true') {
		update_option('wps_ic_debug', 'true');
	}
	else if ($_GET['ic_debug'] == 'log') {
		update_option('wps_ic_debug', 'log');
	}
	else {
		delete_option('wps_ic_debug');
	}
}

if (get_option('wps_ic_debug') == 'true') {
	ini_set('display_errors', 1);
	error_reporting(E_ALL);
	define('WPS_IC_DEBUG', 'true');
}
else {
	ini_set('display_errors', 0);
	error_reporting(0);
	define('WPS_IC_DEBUG', 'false');
}

if (get_option('wps_ic_debug') == 'log') {
	define('WPS_IC_DEBUG', 'true');
}