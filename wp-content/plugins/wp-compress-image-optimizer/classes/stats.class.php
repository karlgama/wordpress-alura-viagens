<?php


/**
 * Class - Stats
 */
class wps_ic_stats {

  public static $api_key;
  public static $options;


  public function __construct() {

    if (is_admin()) {
      $options = new wps_ic_options();
      $options       = $options->get_option();
      self::$api_key = $options['api_key'];
    }

  }


  public function fetch_local_stats() {
    delete_transient('wps_ic_live_stats_v2');
    $transient = get_transient('wps_ic_live_stats_v2');

		if (!$transient || empty($transient)) {
			if (!empty(self::$api_key)) {
				$uri  = WPS_IC_KEYSURL . '?action=get_chart_local_stats&apikey=' . self::$api_key;
				$call = wp_remote_get($uri, array('sslverify' => false, 'timeout' => '50'));
				$body = wp_remote_retrieve_body($call);
				if (wp_remote_retrieve_response_code($call) == 200) {

					$body = json_decode($body);

					if ( ! empty($body) && $body->success == 'true') {
						set_transient('wps_ic_local_stats', $body);
						return $body;
					}
				}

			}

		}
  }


  public function fetch_sample_stats() {
    set_transient('ic_sample_data_live', 'true');
    $sample = wp_remote_get(WPS_IC_URI . 'sample-data-live.json');
    $body = wp_remote_retrieve_body($sample);
    $body = json_decode($body);
    return $body;
  }


  public function fetch_live_stats() {
    delete_transient('wps_ic_live_stats');
    $transient = get_transient('wps_ic_live_stats');

    if (!$transient || empty($transient)) {
      if (!empty(self::$api_key)) {
        $uri  = WPS_IC_KEYSURL . '?action=get_chart_live_stats&apikey=' . self::$api_key;
        $call = wp_remote_get($uri, array('sslverify' => false, 'timeout' => '50'));
        $body = wp_remote_retrieve_body($call);
        if (wp_remote_retrieve_response_code($call) == 200) {

          $body = json_decode($body);

          if ( ! empty($body) && $body->success == 'true') {
            set_transient('wps_ic_live_stats', $body);
            return $body;
          }
        }

      }

    }

    return false;
  }


}