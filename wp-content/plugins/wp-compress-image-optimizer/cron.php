<?php


class wpc_compress_cron {

	function __construct() {
		#$this->setup_cron('update_stats', 'wps_ic_update_stats', 5*60); // 5 Minutes
	}


	public function setup_cron($hook, $transient_name, $interval = 60) {
		if (!empty($_GET['refresh_cron'])) {
			delete_transient($transient_name);
		}

		$transient = get_transient($transient_name);
		if (!$transient) {
			$this->$hook();
			set_transient($transient_name, 'true', $interval);
		}
	}


	public function update_stats() {
		$options = get_option(WPS_IC_OPTIONS);
		$get = wp_remote_get('https://cdn.zapwp.net/?get_stats=true&apikey=' . $options['api_key'] . '&hash=' . microtime() . '&rand=' . mt_rand(99,9999),
		array('sslverify' => false, 'timeout' => 10, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0'));
	}


}