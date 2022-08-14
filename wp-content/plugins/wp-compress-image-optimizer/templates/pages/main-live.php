<?php

global $wps_ic, $wpdb;
$no_credits_popup = false;

$reconnect_msg = '';
if ( ! empty( $_GET['reconnect'] ) ) {
	// API Key
	$options = get_option( WPS_IC_OPTIONS );
	$apikey  = sanitize_text_field( $options['api_key'] );
	$siteurl = urlencode( site_url() );

	// Setup URI
	$uri = WPS_IC_KEYSURL . '?action=connect_v5&apikey=' . $apikey . '&domain=' . $siteurl . '&hash=' . md5( time() ) . '&time_hash=' . time();

	// Verify API Key is our database and user has is confirmed getresponse
	$get = wp_remote_get( $uri, array( 'timeout' => 30, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0' ) );

	if ( wp_remote_retrieve_response_code( $get ) == 200 ) {
		$body = wp_remote_retrieve_body( $get );
		$body = json_decode( $body );

		if ( ! empty( $body->data->code ) && $body->data->code == 'site-user-different' ) {
			// Popup Site Already Connected
			$reconnect_msg = 'invalid-api-key';
		}

		if ( $body->success == true && $body->data->apikey != '' && $body->data->response_key != '' ) {
			$options = new wps_ic_options();
			$options->set_option( 'api_key', $body->data->apikey );
			$options->set_option( 'response_key', $body->data->response_key );

			// CDN Does exist or we just created it
			$zone_name = $body->data->zone_name;

			if ( ! empty( $zone_name ) ) {
				update_option( 'ic_cdn_zone_name', $zone_name );
			}

			$settings = get_option( WPS_IC_SETTINGS );

			$sizes = get_intermediate_image_sizes();
			foreach ( $sizes as $key => $value ) {
				$settings['thumbnails'][ $value ] = 1;
			}

			update_option( WPS_IC_SETTINGS, $settings );
			$reconnect_msg = 'ok';
		} else {
			$reconnect_msg = 'api-error';
		}
	} else {
		$reconnect_msg = 'api-error';
	}
}

// Reset Cache
$cache_cleared = get_transient( 'ic_cdn_reset_cache' );
if ( ! $cache_cleared ) {
	$cache_cleared = false;
} else {
	$cache_cleared = true;
	delete_transient( 'ic_cdn_reset_cache' );
}

/**
 * Get Credits
 */
$user_credits = $wps_ic->getAccountStatusMemory( 'true' );
$allow_local  = $user_credits->account->allow_local;
$allow_cname  = $user_credits->account->allow_cname;
$quota_type   = $user_credits->account->type;

if ( ! empty( $_GET['test_lock'] ) ) {
	$allow_cname = 0;
	delete_transient( 'ic_allow_cname' );
}

/**
 * Account Status Overrride
 */
if ( ! empty( $_GET['acc_status_dbg'] ) ) {
	$options = get_option( WPS_IC_OPTIONS );
	$apikey  = sanitize_text_field( $options['api_key'] );
	$call    = wp_remote_get( WPS_IC_KEYSURL . '?action=get_account_status&version=2&apikey=' . $options['api_key'] . '&range=month&hash=' . md5( mt_rand( 999, 9999 ) ), array( 'timeout' => 10, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0' ) );
	var_dump( WPS_IC_KEYSURL . '?action=get_account_status&version=2&apikey=' . $options['api_key'] . '&range=month&hash=' . md5( mt_rand( 999, 9999 ) ) );
	var_dump( $call );
}

/**
 * Fetch settings, or if save is triggered save them.
 * - If no settings are saved (bug, deleted options..) regenerate recommended
 */
$settings = get_option( WPS_IC_SETTINGS );

if ( ! empty( $_POST['set_optimization'] ) ) {
	if ( $_POST['set_optimization'] == 'live' ) {
		$settings['live-cdn'] = '1';
	} else {
		$settings['live-cdn'] = '0';
	}

	update_option( WPS_IC_SETTINGS, $settings );
}

/**
 * Must have settings
 */
$defaults_saved = false;
$defaults       = array(
	'js'                     => '0',
	'css'                    => '0',
	'css_image_urls'         => '0',
	'external-url'           => '0',
	'replace-all-link'       => '0',
	'emoji-remove'           => '0',
	'on-upload'              => '0',
	'defer-js'               => '0',
	'serve_jpg'              => '0',
	'serve_png'              => '0',
	'serve_gif'              => '0',
	'serve_svg'              => '0',
	'search-through'         => 'html',
	'preserve-exif'          => '0',
	'optimization'           => 'lossless',
	'generate_webp'          => '0',
	'generate_adaptive'      => '0',
	'lazy'                   => '0',
	'retina'                 => '0',
	'background-sizing'      => '0',
	'remove-render-blocking' => '0',
	'minify-css'             => '0',
	'minify-js'              => '0'
);;
foreach ( $defaults as $key => $value ) {
	if ( ! isset( $settings[ $key ] ) ) {
		$settings[ $key ] = $value;
		$defaults_saved   = true;
	}
}

if ( $defaults_saved ) {
	update_option( WPS_IC_SETTINGS, $settings );
}

/**
 * Is the plugin paused? Default: No
 */
$statsclass = new wps_ic_stats();

if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) {
	// Local
	$live_cdn    = false;
	$stats_local = $statsclass->fetch_local_stats();
    if ( isset ( $stats_local ) ) {
        $stats_local = $stats_local->data;
    }
} else {
	// Live
	$live_cdn   = true;
	$stats_live = $statsclass->fetch_live_stats();
    if ( isset ( $stats_live ) ) {
        $stats_live = $stats_live->data;
    }
}

if ( ! empty( $_GET['fresh'] ) ) {
	$stats_live  = '';
	$stats_local = '';
}

/**
 * Quick fix for PHP undefined notices
 */
$wps_ic_active_settings['optimization']['lossless']    = '';
$wps_ic_active_settings['optimization']['intelligent'] = '';
$wps_ic_active_settings['optimization']['ultra']       = '';

/**
 * Decides which setting is active
 */
if ( ! empty( $settings['optimization'] ) ) {
	if ( $settings['optimization'] == 'lossless' ) {
		$wps_ic_active_settings['optimization']['lossless'] = 'class="current"';
	} else if ( $settings['optimization'] == 'intelligent' ) {
		$wps_ic_active_settings['optimization']['intelligent'] = 'class="current"';
	} else {
		$wps_ic_active_settings['optimization']['ultra'] = 'class="current"';
	}
} else {
	$wps_ic_active_settings['optimization']['intelligent'] = 'class="current"';
}

/**
 * Get Package/Plan details from API Key Verification
 * - Shows Shared or X Credits left information
 */
$is_unlimited = 0;

// Account Type
if ( $quota_type == 'shared' ) {

	// Shared
    if ( isset ( $user_credits->credits ) ) {
        $credits = wps_ic_size_format($user_credits->credits, 2) . ' Left';
    }
	$local_credits = $credits_type = 'Shared Plan';

}
else if ( $quota_type == 'unlimited' ) {

	// Unlimited
	$credits       = 'Unlimited';
	$local_credits = false;
	$credits_type  = 'Unlimited';
	$allow_local   = 0;
	$is_unlimited  = 1;

}
else if ( $quota_type == 'requests-combined' ) {

	if (!empty($_GET['dbg_credits'])) {
		var_dump($quota_type);
		var_dump('Leftover: ' . $user_credits->bytes->leftover);
	}

	if ( $user_credits->bytes->package_local_type === 'shared' ) {
		$local_credits = 'Shared Credits';
	} else {

		if ( $user_credits->bytes->package_local_leftover > 0 ) {
			$local_credits = number_format( $user_credits->bytes->package_local_leftover, 0 ) . ' Images Left';
		} else {
			if ( $user_credits->bytes->local_one_time_leftover > 0 ) {
				$local_credits = $user_credits->bytes->local_one_time_leftover . ' Images Left';
			} else {
				$local_credits = '0 Images Left';
			}
		}

	}

	// Quota
	if ( $user_credits->bytes->package_requests_type === 'shared' ) {
		$credits = 'Shared Credits';
	} else {
		if ( $user_credits->bytes->leftover > 0 ) {
			$credits = number_format( $user_credits->bytes->leftover, 0 ) . ' Requests Left';
		} else {
			if ( $user_credits->bytes->requests_one_time_leftover > 0 ) {
				$credits = $user_credits->bytes->requests_one_time_leftover . ' Requests Left';
			} else {
				$credits = '0 Requests Left';
			}
		}
	}

	if (!empty($_GET['dbg_credits'])) {
		var_dump($user_credits->bytes->package_requests_type);
		var_dump('Leftover: ' . $user_credits->bytes->leftover);
		var_dump('Credits: ' . $credits);
	}

}
else if ( $quota_type == 'requests' ) {

	if ( $user_credits->bytes->package_local_type == 'shared' ) {
		$local_credits = 'Shared Credits';
	} else {

		if ( $user_credits->bytes->local_leftover > 0 ) {
			$local_credits = number_format( $user_credits->bytes->local_leftover, 0 ) . ' Images Left';
		} else {
			if ( $user_credits->bytes->local_one_time_leftover > 0 ) {
				$local_credits = $user_credits->bytes->local_one_time_leftover . ' Images Left';
			} else {
				$local_credits = '0 Images Left';
			}
		}

	}

	// Quota
	if ( $user_credits->bytes->package_requests_type == 'shared' ) {
		$credits = 'Shared Credits';
	} else {
		if ( $user_credits->bytes->leftover > 0 ) {
			$credits = number_format( $user_credits->bytes->leftover, 0 ) . ' Requests Left';
		} else {
			if ( $user_credits->bytes->requests_one_time_leftover > 0 ) {
				$credits = $user_credits->bytes->requests_one_time_leftover . ' Requests Left';
			} else {
				$credits = '0 Requests Left';
			}
		}
	}
}
else if ( $quota_type == 'shared_requests' ) {

	// Quota
	if ( $user_credits->bytes->leftover > 0 ) {
		$credits = 'Shared Credits';
	} else {
		$credits = '0 Requests Left';
	}
}
else {

	// Bandwidth
	$local_credits = 0;
	$allow_live    = true;

	if ( $user_credits->account->allow_live === 'false' || $user_credits->account->allow_live == false ) {
		$allow_live = false;
	} else {
		$allow_live = true;
	}

	$allow_live    = true;
	// Quota
	if ( $user_credits->account->plan == 'free' ) {
	  $credits = 'Free Plan';
  } else {

	  if ( $user_credits->account->type == '' || $user_credits->account->type == 'quota' ) {

		  $credits = $user_credits->formatted->package_without_extra . ' Left';

		  $credits          = $user_credits->formatted->package_requests_leftover;
		  $credits_one_time = $user_credits->formatted->package_requests_one_time;
		  $local            = $user_credits->bytes->package_local_leftover;
		  $local_one_time   = $user_credits->bytes->package_local_one_time;

		  if ( ! $credits ) {
			  $credits = 0;
		  }
		  if ( ! $credits_one_time ) {
			  $credits_one_time = 0;
		  }
		  if ( ! $local ) {
			  $local = 0;
		  }
		  if ( ! $local_one_time ) {
			  $local_one_time = 0;
		  }

		  if ( $credits == 0 ) {
			  $credits = $credits_one_time . ' Left';
		  } else {
			  $credits = $credits . ' Left';
		  }

		  if ( $local == 0 ) {
			  $local_credits = $local_one_time . ' Images Left';
		  } else {
			  $local_credits = $local . ' Images Left';
		  }

		  if ( $credits == 0 && $credits_one_time == 0 ) {
			  $no_credits_popup = true;
		  }

	  } else {
		  $credits = 'Free Plan';
	  }
  }

}

$in_local = true;
if ( $allow_local == 1 ) {
	if ( ! empty( $settings['live-cdn'] ) && $settings['live-cdn'] == '1' ) {
		$in_local = false;
	}
}


if (!empty($_GET['dbg_credits'])) {
	var_dump($quota_type);
	var_dump('Leftover: ' . $user_credits->bytes->leftover);
	var_dump('Credits: ' . $credits);
}

$bg_process = get_option( 'wps_ic_bg_process' );
$cnd_Purged = '';

if ( empty( $_GET['view'] ) ) { ?>
  <div class="wrap">
  <div class="wps_ic_wrap wps_ic_settings_page wps_ic_live">

    <div class="wp-compress-header">
      <div class="wp-ic-logo-container">
        <div class="wp-compress-logo">
          <img src="<?php echo WPS_IC_URI; ?>assets/images/main-logo.svg"/>
          <div class="wp-ic-logo-inner">
            <h3 style="color: #333;"><span class="small">v<?php echo $wps_ic::$version; ?></span></h3>
          </div>
        </div>
      </div>
      <div class="wp-ic-header-buttons-container">
        <ul>

			<?php if ( ! empty( $live_cdn ) && $live_cdn == '1' ) { ?>
              <li><a href="#" class="wps-ic-service-status active">Live Optimization Active</a></li>
			<?php } else { ?>
              <li><a href="#" class="wps-ic-service-status paused">Live Optimization Paused</a></li>
			<?php } ?>

          <li class="bulk-button" style="<?php if ( ! empty( $live_cdn ) && $live_cdn == '1' ) {
			  echo 'display:none;';
		  } ?>"><a href="<?php echo admin_url( 'options-general.php?page=' . $wps_ic::$slug . '&view=bulk' ); ?>" class="button button-primary">Bulk Optimization</a></li>

          <li><a href="<?php echo admin_url( 'options-general.php?page=' . $wps_ic::$slug . '&view=advanced_settings_v2' ); ?>" class="button button-primary">PRO Settings</a></li>
        </ul>
      </div>
      <div class="clearfix"></div>
    </div>

	  <?php if ( $reconnect_msg ) { ?>
        <div class="wp-compress-success-message">
			<?php
			if ( $reconnect_msg == 'invalid-api-key' ) {
				?>
              <h3 style="text-align: center;">Invalid API Key!</h3>
			<?php } else if ( $reconnect_msg == 'ok' ) { ?>
              <h3 style="text-align: center;">We have successfully reconnected your plugin!</h3>
			<?php } else { ?>
              <h3 style="text-align: center;">Please contact WP Compress Support!</h3>
			<?php } ?>
          <div class="clearfix"></div>
        </div>
	  <?php } ?>

	  <?php if ( $cache_cleared ) { ?>
        <div class="wp-compress-success-message">
          <h3 style="text-align: center;">We have cleared your CDN Cache!</h3>
          <div class="clearfix"></div>
        </div>
	  <?php } ?>

	  <?php if ( ! empty( $_GET['purge_cdn'] ) && $cnd_Purged == 'true' ) { ?>
        <div class="wp-compress-success-message">
          <h3 style="text-align: center;">We have cleared your CDN Cache!</h3>
          <div class="clearfix"></div>
        </div>
	  <?php } ?>

    <div class="wp-compress-pre-wrapper">
      <div class="wp-compress-pre-subheader">
        <div class="col-6">
          <ul>
            <li>
              <h3>Compression Report</h3>
				<?php
				if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) {
					// Local Stats

					if ( empty( $stats_local ) ) {
						echo '<li><span class="button-sample-data ic-tooltip" title="Chart will update when usage data is available.">Sample Data</span></li>';
					} else {
						echo '<li><span class="button-sample-data ic-tooltip" title="Chart data for Local Compression">Local</span></li>';
					}

				} else {
					// Live Stats
					if ( $user_credits->bytes->cdn_bandwidth == 0 && $user_credits->bytes->cdn_requests == 0 ) {
						echo '<li><span class="button-sample-data ic-tooltip" title="Chart will update when usage data is available.">Sample Data</span></li>';
					} else {
						echo '<li><span class="button-sample-data ic-tooltip" title="Chart data for Live Compression">Live</span></li>';
					}

				}
				?>
            </li>
          </ul>
        </div>
        <div class="col-6 last">
          <ul>
            <li><span class="legend-original"></span>Before</li>
            <li><span class="legend-after"></span>After Optimization</li>
          </ul>
        </div>
      </div>

      <div class="wp-compress-chart" style="height: 400px;">
		  <?php echo '<canvas id="canvas"></canvas>'; ?>
      </div>

    </div>
    <div class="wp-compres-settings">

      <div class="wp-compress-settings-row-blank">

        <div class="col-9 text-left white-box margin-right">
          <div class="pre-inner">
            <div class="inner inner-flex">

				<?php

				/**
				 * Is it a multisite installation
				 * TODO: Do we still need this? Test on multisite
				 */
				if ( is_multisite() ) {
					$blogID = get_current_blog_id();
				} else {
					$blogID = 1;
				}

				/**
				 * Is Live OFF?
				 */
				if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) {
				$donut_size = 1;
				$savings    = false;

				if ( isset ( $user_credits->bytes->local_savings ) && $user_credits->bytes->local_savings > 0 ) {
					$savings      = true;
					$donut_size   = 1 - ( $user_credits->bytes->local_optimized / $user_credits->bytes->local_original );
					$donut_size   = number_format( $donut_size, 2 );
					$user_savings = $donut_size * 100;
				} else {
					$savings      = true;
					$user_savings = 0;
				}

				if ( ! empty( $_GET['fresh'] ) ) {
					$user_savings = '0';
					$donut_size   = '100';
				}

				if ( empty( $user_savings ) || $user_savings == '' ) {
					$user_savings = '0';
				}

				?>

              <div class="left-side-box">
                <div class="user-account-circle">
                  <div id="circle-big" data-value="<?php echo $donut_size; ?>"></div>
                  <div class="dashboard-account-circle-text">
					  <?php if ( $savings ) { ?>
                        <h5><?php echo $user_savings . '%'; ?></h5>
                        <h4>Savings</h4>
					  <?php } ?>
                  </div>
                  <!-- -35s == 35% -->
                </div>
              </div>


              <div class="right-side-box">
                <div class="youve-saved">
					<?php
					/**
					 * Live Stats Exist OR  Stats Local Exists
					 */
					if ( ! empty( $stats_live ) || ! empty( $stats_local ) ) { ?>
						<?php
						if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) {
							?>
							<?php
							if ( isset ( $user_credits->bytes->local_savings ) && $user_credits->formatted->local_savings == '0' ) {
								echo '<h3>You\'ve Saved</h3>';
								echo '<h4>' . $user_credits->formatted->local_savings . '</h4>';
							} else {
								echo '<h3 style="padding-right:20px;">No Savings Yet!</h3>';
							}
							?>
						<?php } else { ?>
                          <h3>You've Saved</h3>
                          <h4><?php echo $user_credits->formatted->bandwidth_savings_bytes; ?></h4>
						<?php } ?>
                      <div class="image-credits-remaining">
						  <?php
						  $local_requests_left = '';
						  $requests_left       = '';
						  if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) {
							  $requests_left = 'display:none;';
						  } else {
							  $local_requests_left = 'display:none;';
						  }
						  ?>
                        <a href="https://wpcompress.com/pricing" target="_blank" class="button button-primary local-requests-left" style="<?php echo $local_requests_left; ?>">
                          <h5><?php echo $local_credits; ?></h5>
                        </a>
                        <a href="https://wpcompress.com/pricing" target="_blank" class="button button-primary requests-left" style="<?php echo $requests_left; ?>">
                          <h5><?php echo $credits; ?></h5>
                        </a>

                      </div>
						<?php
					} else {
						?>
                      <h3>You have not yet optimized any images!</h3>
                      <a href="<?php echo admin_url( 'options-general.php?page=' . $wps_ic::$slug . '&view=bulk' ); ?>" class="button-primary button btn-start-optimization" style="margin-left: 10px;margin-right:10px;"><i class="icon icon-cdn"></i> Start Optimization</a>
					<?php } ?>
                </div>

				  <?php
				  if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) {
				  ?>
                <div class="stats-boxes smaller">
					<?php } else { ?>
                  <div class="stats-boxes">
					  <?php } ?>
                    <div class="stats-box-single">
                      <div class="stats-box-icon-holder">
                        <img src="<?php echo WPS_IC_URI; ?>/assets/images/icon-total-images.svg"/>
                      </div>
                      <div class="stats-box-text-holder">
						  <?php
						  if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) {
							  $thumbs = get_intermediate_image_sizes();
							  ?>
                            <h3><?php
                                if ( isset ( $user_credits->bytes->local_requests ) ){
                                    echo $user_credits->bytes->local_requests * count( $thumbs );
                                } ?></h3>
                            <h5>Total Images</h5>
						  <?php } else { ?>
                            <h3><?php
                                if ( isset ( $user_credits->formatted->cdn_requests ) ) {
                                    echo $user_credits->formatted->cdn_requests;
                                }?></h3>
                            <h5>Total Images</h5>
						  <?php } ?>
                      </div>
                    </div>

                    <div class="stats-box-single">
                      <div class="stats-box-icon-holder">
                        <img src="<?php echo WPS_IC_URI; ?>/assets/images/icon-original-size.svg"/>
                      </div>
                      <div class="stats-box-text-holder">
						  <?php
						  if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) {
							  ?>
                            <h3>
								<?php
								if ( empty( $user_credits->formatted->local_original ) || $user_credits->formatted->local_original == '' ) {
									echo '0';
								} else {
									echo $user_credits->formatted->local_original;
								}
								?>
                            </h3>
                            <h5>Original Size</h5>
						  <?php } else { ?>
                            <h3>
								<?php
								if ( empty( $user_credits->formatted->original_bandwidth ) || $user_credits->formatted->original_bandwidth == '' ) {
									echo '0';
								} else {
									echo $user_credits->formatted->original_bandwidth;
								}
								?></h3>
                            <h5>Original</h5>
						  <?php } ?>
                      </div>
                    </div>

                    <div class="stats-box-single">
                      <div class="stats-box-icon-holder">
                        <img src="<?php echo WPS_IC_URI; ?>/assets/images/icon-after-optimization.svg"/>
                      </div>
                      <div class="stats-box-text-holder">
						  <?php
						  if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) {
							  ?>
                            <h3>
								<?php
								if ( empty( $user_credits->formatted->local_optimized ) || $user_credits->formatted->local_optimized == '' ) {
									echo '0';
								} else {
									echo $user_credits->formatted->local_optimized;
								}
								?>
                            </h3>
                            <h5>Optimized</h5>
						  <?php } else { ?>
                            <h3>
								<?php
								if ( empty( $user_credits->formatted->cdn_bandwidth ) || $user_credits->formatted->cdn_bandwidth == '' ) {
									echo '0';
								} else {
									echo $user_credits->formatted->cdn_bandwidth;
								}
								?></h3>
                            <h5>Optimized</h5>
						  <?php } ?>
                      </div>
                    </div>

                  </div>
                </div>
				  <?php
				  } else {

					  // Live is ON
					  if ( ! empty( $stats_live ) ) {
						  if ( $user_credits->bytes->bandwidth_savings > 0 ) {
							  $savings    = true;
							  $donut_size = $user_credits->bytes->bandwidth_savings / 100;
							  $donut_size = number_format( $donut_size, 2 );
							  $donut_text = $user_savings = $user_credits->formatted->bandwidth_savings;
						  } else {
							  $savings      = true;
							  $user_savings = 0;
						  }
					  } else {
						  $donut_size = 1;
						  $donut_text = 0;
					  }
					  ?>

                    <div class="left-side-box">
                      <div class="user-account-circle">
                        <div id="circle-big" data-value="<?php echo $donut_size; ?>"></div>
                        <div class="dashboard-account-circle-text">
                          <h5><?php echo $donut_text; ?>%</h5>
                          <h4>Savings</h4>
                        </div>
                        <!-- -35s == 35% -->
                      </div>
                    </div>

                    <div class="right-side-box">
                      <div class="youve-saved">
						  <?php if ( ! empty( $stats_live ) ) { ?>
                            <h3>You've Saved</h3>
                            <h4><?php echo $user_credits->formatted->bandwidth_savings_bytes; ?></h4>
						  <?php } else { ?>
                            <h3>You've Saved</h3>
                            <h4><?php echo 0 . ' MB'; ?></h4>
						  <?php } ?>
                        <div class="image-credits-remaining">

							<?php if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) { ?>
                              <a href="https://wpcompress.com/pricing" target="_blank" class="button button-primary local-requests-left">
                                <h5><?php echo $local_credits; ?></h5>
                              </a>
                              <a href="https://wpcompress.com/pricing" target="_blank" class="button button-primary requests-left" style="display: none;">
                                <h5><?php echo $credits; ?></h5>
                              </a>
							<?php } else { ?>
                              <a href="https://wpcompress.com/pricing" target="_blank" class="button button-primary local-requests-left" style="display: none;">
                                <h5><?php echo $local_credits; ?></h5>
                              </a>
                              <a href="https://wpcompress.com/pricing" target="_blank" class="button button-primary requests-left">
                                <h5><?php echo $credits; ?></h5>
                              </a>
							<?php } ?>
                        </div>
                      </div>

                      <div class="stats-boxes">


                        <div class="stats-box-single">
                          <div class="stats-box-icon-holder">
                            <img src="<?php echo WPS_IC_URI; ?>/assets/images/icon-total-images.svg"/>
                          </div>
                          <div class="stats-box-text-holder">
                            <h3><?php echo $user_credits->formatted->cdn_bandwidth; ?></h3>
                            <h5>Optimized</h5>
                          </div>
                        </div>

                        <div class="stats-box-single">
                          <div class="stats-box-icon-holder">
                            <img src="<?php echo WPS_IC_URI; ?>/assets/images/icon-original-size.svg"/>
                          </div>
                          <div class="stats-box-text-holder">
                            <h3><?php echo $user_credits->formatted->original_bandwidth; ?></h3>
                            <h5>Original</h5>
                          </div>
                        </div>

                        <div class="stats-box-single">
                          <div class="stats-box-icon-holder">
                            <img src="<?php echo WPS_IC_URI; ?>/assets/images/icon-after-optimization.svg"/>
                          </div>
                          <div class="stats-box-text-holder">
                            <h3><?php echo $user_credits->formatted->cdn_requests; ?></h3>
                            <h5>Assets Served</h5>
                          </div>
                        </div>


                      </div>
                    </div>
				  <?php } ?>
              </div>
            </div>
          </div>

          <div class="col-3 col-autopilot">

            <div class="pre-inner">
              <div class="inner">

                <div class="autopilot-box">
                  <h3>Optimization Mode
					  <?php /*<span class="tooltip" title="You may switch between local compression and live image optimization using the Global CDN based on your needs."><i class="fa fa-question-circle"></i></span>*/ ?>
					  <?php /*<span class="tooltip" title="By disabling Live Mode you pause the WP Compress CDN service."><i class="fa fa-question-circle"></i></span>*/ ?>
                  </h3>

					<?php
					$cdn_popup_class = '';
					if ( $no_credits_popup ) {
						$cdn_popup_class = 'no-leftover-popup';
					}

					if ( isset ( $allow_live ) && ! $allow_live ) {
						$cdn_popup_class = 'dont-allow-local';
					}
					?>
                  <div class="checkbox-container-custom-pause wps-ic-ajax-checkbox-cdn ajax-change-span wps-ic-live-cdn-ajax <?php echo $cdn_popup_class; ?>" data-leftover="<?php echo $user_credits->bytes->leftover; ?>" style="padding-top: 0px;">
                    <input type="checkbox" id="wp-ic-setting[live-cdn]" data-on-text="Live CDN" data-off-text="Local" value="1"
                           name="wp-ic-setting[live-cdn]" data-setting_name="live-cdn" data-setting_value="1" <?php if ( $live_cdn ) {
						echo 'checked="checked"';
					} else {
					} ?>/>
                    <div>
                      <label for="wp-ic-setting[live-cdn]" class=""></label>
						<?php if ( isset ( $settings['live-cdn'] ) && $settings['live-cdn'] == '1' ) { ?>
                          <span>Live CDN</span>
						<?php } else { ?>
                          <span>Local</span>
						<?php } ?>
                    </div>
                  </div>


                </div>


              </div>
            </div>
          </div>

          <div class="clearfix"></div>
        </div>

        <div class="wp-compress-settings-row wps-ic-live-compress">
          <div class="col-2 bigger text-center">
            <div class="inner">
              <h3>Optimization Level</h3>
              <span class="ic-tooltip" title="Select your preferred balance of speed and quality. Over-Compression Prevention™ is included in all modes."><i class="fa fa-question-circle"></i></span>

              <div class="wp-ic-select-box">
                <input type="hidden" name="wp-ic-setting[optimization]" id="wp-ic-setting-optimization" value="<?php echo $settings['optimization']; ?>"/>
                <ul>
                  <li <?php echo $wps_ic_active_settings['optimization']['lossless']; ?>><a href="#" class="wps-ic-change-optimization" data-optimization_level="lossless">Lossless</a></li>
                  <li <?php echo $wps_ic_active_settings['optimization']['intelligent']; ?>><a href="#" class="wps-ic-change-optimization" data-optimization_level="intelligent">Intelligent</a></li>
                  <li <?php echo $wps_ic_active_settings['optimization']['ultra']; ?>><a href="#" class="wps-ic-change-optimization" data-optimization_level="ultra">Ultra</a></li>
                </ul>
              </div>
            </div>
          </div>

          <div class="col-2 text-center">

            <div class="inner">
              <h3>Retina</h3>
              <span class="ic-tooltip" title="Intelligently show retina enabled images based on device, which can improve the image quality on retina enabled devices."><i class="fa fa-question-circle"></i></span>

              <div class="checkbox-container-v2 informative-input whole-checkbox">
                <input type="checkbox" id="retina-toggle" value="1" name="wp-ic-setting[retina]" data-setting_name="retina" data-setting_value="1" <?php echo checked( $settings['retina'], '1' ); ?>/>
                <div>
                  <label for="retina-toggle" class="retina-toggle"></label>
					<?php if ( $settings['retina'] == '1' ) { ?>
                      <span>ON</span>
					<?php } else { ?>
                      <span>OFF</span>
					<?php } ?>
                </div>
              </div>

            </div>

          </div>

          <div class="col-2 text-center">

            <div class="inner">
              <h3>Adaptive</h3>
              <span class="ic-tooltip" title="Intelligently show images based on device size, which can DRASTICALLY reduce load times on mobile devices."><i class="fa fa-question-circle"></i></span>

              <div class="checkbox-container-v2 informative-input whole-checkbox">
                <input type="checkbox" id="adaptive-images-toggle" value="1" name="wp-ic-setting[generate_adaptive]" data-setting_name="generate_adaptive" data-setting_value="1" <?php echo checked( $settings['generate_adaptive'], '1' ); ?>/>
                <div>
                  <label for="adaptive-images-toggle" class="adaptive-toggle"></label>
					<?php if ( $settings['generate_adaptive'] == '1' ) { ?>
                      <span>ON</span>
					<?php } else { ?>
                      <span>OFF</span>
					<?php } ?>
                </div>
              </div>

            </div>

          </div>

          <div class="col-2 text-center">
            <div class="inner">
              <h3>WebP</h3>
              <span class="ic-tooltip" title="Serve next-gen image format WebP to supported browsers for additional file-size savings."><i class="fa fa-question-circle"></i></span>

              <div class="checkbox-container-v2 informative-input whole-checkbox">
                <input type="checkbox" id="generate-webp-toggle" value="1"
                       name="wp-ic-setting[generate_webp]" data-setting_name="generate_webp" data-setting_value="1" <?php echo checked( $settings['generate_webp'], '1' ); ?>/>
                <div>
                  <label for="generate_webp" class="webp-toggle"></label>
					<?php if ( $settings['generate_webp'] == '1' ) { ?>
                      <span>ON</span>
					<?php } else { ?>
                      <span>OFF</span>
					<?php } ?>
                </div>
              </div>
            </div>
          </div>

          <div class="col-2 text-center">
            <div class="inner">
              <h3>Lazy</h3>
              <span class="ic-tooltip" title="Load your images only when they are in viewport."><i class="fa fa-question-circle"></i></span>

              <div class="checkbox-container-v2 informative-input whole-checkbox" style="display: block;">
                <input type="checkbox" id="cdn-toggle" value="1" name="wp-ic-setting[lazy]" data-setting_name="lazy" data-setting_value="1" <?php echo checked( $settings['lazy'], '1' ); ?>/>
                <div>
                  <label for="lazy-toggle" class="lazy-toggle"></label>
					<?php if ( $settings['lazy'] == '1' ) { ?>
                      <span>ON</span>
					<?php } else { ?>
                      <span>OFF</span>
					<?php } ?>
                </div>
              </div>
            </div>
          </div>

          <div class="clearfix"></div>

        </div>

        <div class="wp-compress-settings-footer">
          <div class="wp-compress-separator"></div>
          <ul>
            <li>
              <a href="https://wpcompress.com/pricing/">Get More Credits</a>
            </li>
            <li>
              <a href="https://wpcompress.com/quick-start/">Getting Started Guide</a>
            </li>
            <li>
              <a href="https://go.crisp.chat/chat/embed/?website_id=afb69c89-31ce-4a64-abc8-6b11e22e3a10">Chat with Support</a>
            </li>
            <li>
              <a href="<?php echo admin_url( 'options-general.php?page=' . $wps_ic::$slug . '&view=debug_tool' ); ?>">Debug Tool</a>
            </li>
            <li>
              <a href="<?php echo admin_url( 'options-general.php?page=' . $wps_ic::$slug . '&check_account=true' ); ?>">Clear Account Cache</a>
            </li>
          </ul>
        </div>

        <div class="wp-compress-settings-footer partners" style="display: none;">
          <ul>
            <li>
              <a href="https://wpcompress.com/go/astra"><img src="<?php echo WPS_IC_URI; ?>assets/partners/astra.png" title="Astra"/></a>
            </li>
            <li>
              <a href="https://wp-pagebuilderframework.com/"><img src="<?php echo WPS_IC_URI; ?>assets/partners/page-builder-framework-logo.png" title="WP PageBuilder Framework"/></a>
            </li>
            <li>
              <a href="https://wpcompress.com/go/swift"><img src="<?php echo WPS_IC_URI; ?>assets/partners/swift.png" title="Swift"/></a>
            </li>
            <li>
              <a href="https://wpcompress.com/go/mainwp"><img src="<?php echo WPS_IC_URI; ?>assets/partners/mainwp.png" title="MainWP"/></a>
            </li>
            <li>
              <a href="https://wpcompress.com/go/cloudways"><img src="<?php echo WPS_IC_URI; ?>assets/partners/cw.png" title="CloudWays"/></a>
            </li>
          </ul>
        </div>

      </div>

    </div>

    <div id="lazy-compatibility-popup" style="display: none;">
      <div id="cdn-popup-inner" class="ic-compress-all-popup ic-lazy-compatibility-popup">

        <div class="cdn-popup-top">
          <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/compatibility.svg"/>
        </div>

        <div class="cdn-popup-content">
          <h3>Please Confirm Compatibility</h3>
          <p>Advanced features such as serving images with lazy load may conflict with your active themes, plugins or environment. If any issues occur after activating, you can simply toggle it off.</p>
        </div>

      </div>
    </div>

    <div id="generate_adaptive-compatibility-popup" style="display: none;">
      <div id="cdn-popup-inner" class="ic-compress-all-popup">

        <div class="cdn-popup-top">
          <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/compatibility.svg"/>
        </div>

        <div class="cdn-popup-content">
          <h3>Please Confirm Compatibility</h3>
          <p>Advanced features such as serving Adaptive Images may conflict with your active themes, plugins or environment. If any issues occur after activating, you can simply toggle it off.</p>
        </div>

      </div>
    </div>

    <div id="no-credits-popup" style="display: none;">
      <div id="cdn-popup-inner" class="ic-compress-all-popup no-credits-popup">

        <div class="cdn-popup-top">
          <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/compatibility.svg"/>
        </div>

        <div class="cdn-popup-content" style="padding-bottom: 25px;">
          <h3>Ooops, you have no quota left.</h3>
          <p>Your account has exhausted all credits and automatically reverted to “Local" Mode to prevent CDN issues.</p>
          <a href="https://www.wpcompress.com/pricing" target="_blank" class="button button-primary">Get Credits</a>
        </div>

      </div>
    </div>

    <div id="no-live-popup" style="display: none;">
      <div id="cdn-popup-inner" class="ic-compress-all-popup no-credits-popup">

        <div class="cdn-popup-top">
          <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/compatibility.svg"/>
        </div>

        <div class="cdn-popup-content" style="padding-bottom: 25px;">
          <h3>Live mode is not enabled.</h3>
          <p>Please contact your account administrator to enable your live mode.</p>
          <a href="https://www.wpcompress.com/pricing" target="_blank" class="button button-primary">Get Credits</a>
        </div>

      </div>
    </div>

  </div>
  <div class="wps-ic-connect-form" style="display: none;">
    <div class="wps-ic-connect-inner">
      <form method="post" action="<?php echo admin_url( 'options-general.php?page=' . $wps_ic::$slug . '&do=activate' ); ?>" id="wps-ic-connect-form">

        <div class="wps-ic-init-container wps-ic-popup-message-container">
          <img src="<?php echo WPS_IC_URI . 'assets/images/live/bolt-icon.png'; ?>" alt="WP Compress - Lightning Fast Images" class="wps-ic-popup-icon"/>

          <h1>Lightning Fast Load Times</h1>
          <h2>without lifting another finger past setup!</h2>
        </div>

        <div class="wps-ic-error-message-container wps-ic-popup-message-container" style="display: none;">
          <img src="<?php echo WPS_IC_URI . 'assets/images/live/error-v2.png'; ?>" alt="WP Compress - Connection Error" class="wps-ic-popup-icon"/>
        </div>

        <div class="wps-ic-success-message-container wps-ic-popup-message-container" style="display: none;">
          <div class="ic-popup ic-popup-v2">
            <img src="<?php echo WPS_IC_URI; ?>assets/images/fireworks.svg"/>
          </div>
        </div>

        <div class="wps-ic-loading-container wps-ic-popup-message-container" style="display:none;">
          <img src="<?php echo WPS_IC_URI; ?>assets/images/live/bars.svg"/>

          <h1>Confirming Your Access Key</h1>
          <h2>You're so close to faster load times for life...</h2>

        </div>

        <div class="wps-ic-error-message-container-text" style="display: none;">
          <h1>We have encountered an error</h1>
          <h2>Your Access Key seems to be invalid</h2>

          <a href="#" class="wps-ic-connect-retry">Retry</a>
        </div>

        <div class="wps-ic-error-already-connected" style="display: none;">
          <h1>We have encountered an error</h1>
          <h2>Your site is already connected to a different API Key</h2>

          <a href="#" class="wps-ic-connect-retry">Retry</a>
        </div>

        <div class="wps-ic-success-message-container-text" style="display: none;">
          <div class="wps-ic-success-message-container-text" style="display: block">
            <h1 class="ic-title">It’s Really That Simple...</h1>
            <h3 class="ic-text">It may take a few moments to start serving all assets, but you're all set up with lightning-fast live optimization!</h3>
            <a href="<?php echo admin_url( 'options-general.php?page=' . $wps_ic::$slug . '' ); ?>" class="wps-ic-dashboard-btn">Continue</a>
          </div>
        </div>

        <div class="wps-ic-success-message-choice-container-text" style="display: none;">
          <div class="wps-ic-success-message-choice-container-text" style="display: block">
            <h1 class="ic-title">Select Optimization Mode</h1>
            <h3 class="ic-text">You may switch back and forth at any time from the settings panel.</h3>
            <div class="flex-link-container">
              <a href="<?php echo admin_url( 'options-general.php?page=' . $wps_ic::$slug); ?>" class="wps-big-button-with-icon wpc-live-btn">
                <img src="<?php echo WPS_IC_URI; ?>assets/images/live-optimization-btn.svg"/>
                <span>Live Mode</span>
                <p>Optimize images and scripts in real-time based on the visitor's attributes.</p>
                <div class="btn btn-primary">Select</div>
              </a>
              <a href="<?php echo admin_url( 'options-general.php?page=' . $wps_ic::$slug); ?>" class="wps-big-button-with-icon wpc-local-btn">
                <img src="<?php echo WPS_IC_URI; ?>assets/images/local-optimization-btn.svg"/>
                <span>Local Mode</span>
                <p>Compress images in your local media library without the use of CDN.</p>
                <div class="btn btn-primary">Select</div>
              </a>
            </div>
          </div>
        </div>

        <div class="wps-ic-form-container">
          <div class="wps-ic-form-field">
            <label for="apikey">Enter Your Access Key</label>
            <input id="apikey" type="text" placeholder="u390jv0v28zquh8293uzfhc" name="apikey" value=""/>
          </div>
          <div class="wps-ic-submit-field">
            <input type="submit" name="submit" value="Start"/>
          </div>
          <div class="wps-ic-form-other-options">
            <a href="https://app.wpcompress.com/register" class="fadeIn noline" target="_blank">Create an Account</a>
            </br>
            <a href="https://app.wpcompress.com/" class="fadeIn noline" target="_blank" style="text-decoration: none;margin-top: 5px;display: inline-block;">Go to Portal</a>
          </div>
        </div>

      </form>


    </div>
  </div>
  <script type="text/javascript">
	  <?php
	  $labels = array();
	  $in_traffic_sum = '';
	  $in_traffic = '';
	  $out_traffic = '';
	  $images_charts = '';

	  function format_KB( $value ) {
		  $value = $value / 1000;
		  $value = $value / 1000;

		  return round( $value, 2 );
	  }

	  $labels_dates = array();
	  $limit = 10;

	  // Calculate offset
	  $item = 0;

	  if ( empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0' ) {
		  // Local Data

		  if ( empty( $stats_local ) ) {
			  $stats = $statsclass->fetch_sample_stats();
			  $stats = $stats->data;
		  } else {
			  #$stats = $statsclass->fetch_local_stats();
			  $stats = $stats_local;
			  unset( $stats->total );
		  }

	  } else {

		  // Live Data
		  if ( ! $stats_live ) {
			  // Sample data
			  $stats = $statsclass->fetch_sample_stats();
			  $stats = $stats->data;
		  } else {
			  $stats = $stats_live;
		  }
	  }


	  if ( $stats ) {
		  foreach ( $stats as $date => $value ) {
			  $index                            = date( 'd-m-Y', strtotime( $date ) );
			  $labels[ $index ]['date']         = date( 'm/d/Y', strtotime( $date ) );
			  $labels[ $index ]['total_input']  = $value->original;
			  $labels[ $index ]['total_output'] = $value->compressed;

			  if ( $labels[ $index ]['total_input'] < 0 ) {
				  $labels[ $index ]['total_input'] = 0;
			  }

			  if ( $labels[ $index ]['total_output'] < 0 ) {
				  $labels[ $index ]['total_output'] = 0;
			  }
		  }
	  }

	  asort( $labels );

	  $count_labels = count( $labels );
	  if ( $count_labels == 4 ) {
		  $catpercentage = 0.20;
	  } else if ( $count_labels == 3 ) {
		  $catpercentage = 0.12;
	  } else if ( $count_labels <= 2 ) {
		  $catpercentage = 0.05;
	  } else if ( $count_labels >= 5 && $count_labels <= 8 ) {
		  $catpercentage = 0.2;
	  } else if ( $count_labels >= 8 && $count_labels <= 10 ) {
		  $catpercentage = 0.4;
	  } else {
		  $catpercentage = 0.55;
	  }

	  // Parse to javascript
	  $labels_js = '';
	  $biggestY = 0;
	  if ( $labels ) {

		  foreach ( $labels as $date => $data ) {
			  $labels_js      .= '"' . date( 'm/d/Y', strtotime( $data['date'] ) ) . '",';
			  $in_traffic     .= format_KB( $data['total_input'] - $data['total_output'] ) . ',';
			  $out_traffic    .= format_KB( $data['total_output'] ) . ',';
			  $in_traffic_sum .= format_KB( $data['total_input'] ) . ',';

			  $kbIN  = format_KB( $data['total_input'] );
			  $kbOUT = format_KB( $data['total_output'] );

			  if ( $kbIN > $kbOUT ) {
				  if ( $biggestY < $kbIN ) {
					  $biggestY = $kbIN;
				  }
			  } else {
				  if ( $biggestY < $kbOUT ) {
					  $biggestY = $kbOUT;
				  }
			  }

		  }

	  }

	  // Calculate Max
	  $biggestY = ceil( $biggestY );
	  $fig = (int) str_pad( '1', 2, '0' );
	  $maxY = ceil( ( ceil( $biggestY * $fig ) / $fig ) );

	  $stepSize = $maxY / 10;

	  if ( $maxY <= 10 ) {
		  $stepSize = 1;
	  } else if ( $maxY <= 100 ) {
		  $stepSize = 10;
	  } else if ( $maxY <= 500 ) {
		  $stepSize = 25;
	  } else if ( $maxY <= 1000 ) {
		  $stepSize = 100;
	  } else if ( $maxY <= 2000 ) {
		  $stepSize = 200;
	  } else {
		  $stepSize = 500;
	  }

	  if (! empty( $labels ) && ! empty( $stats )) {


	  $out_traffic = rtrim( $out_traffic, ',' );
	  $in_traffic = rtrim( $in_traffic, ',' );
	  $images_charts = rtrim( $images_charts, ',' );


	  $labels_js = rtrim( $labels_js, ',' );

	  function roundUpToAny( $n, $x = 5 ) {
		  if ( $x < 5 ) {
			  $x = 2;
		  }

		  return ( ceil( $n ) % $x === 0 ) ? ceil( $n ) : round( ( $n + $x / 2 ) / $x ) * $x;
	  }

	  ?>

      function tooltipValue(bytes) {
          var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
          if (bytes == 0) return '0 Byte';
          bytes = bytes * 1000 * 1000;
          var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1000)));
          return (bytes / Math.pow(1000, i)).toFixed(1) + ' ' + sizes[i];
      }

      var var_barPercentage = 0.6;
      var var_barThickness = 10;
      var var_maxBarThickness = 12;
      var var_minBarLength = 2;
      var trafficSum = '<?php echo rtrim( $in_traffic_sum, ',' ); ?>';

      const footer = (tooltipItems) => {
          let sum = 0;

          var tooltip = tooltipValue(tooltipItems.yLabel);

          // Index of the column
          var index = tooltipItems[1].dataIndex;
          var before = tooltipItems[0].dataset.data[index];
          var after = tooltipItems[1].dataset.data[index];

          tooltipItems[0].dataset.data[index] = tooltipItems[0].dataset.data[index] + tooltipItems[1].dataset.data[index];

          if (tooltipItems.datasetIndex == 0) {
              // Original
              var prefix = 'After ';
          }
          else {
              // Compressed
              var prefix = 'Before ';
          }

          if (tooltipItems.datasetIndex == 0) {
              //return prefix + tooltip;
          }
          else {
              //return prefix + tooltipValue(before + after);
          }

          //console.log(tooltipItems);
          //tooltipItems.forEach(function(tooltipItem) {
          //    sum += tooltipItem.parsed.y;
          //});
          //return 'Sum: ' + sum;
      };

      var sumLabel = 0;

	  <?php
	  if (empty( $settings['live-cdn'] ) || $settings['live-cdn'] == '0') {
	  ?>
      var config = {
          type: 'bar', data: {
              labels: [<?php echo $labels_js; ?>], datasets: [{
                  label: "After Optimization", fill: false, backgroundColor: '#3c4cdf', borderColor: '#3c4cdf', barPercentage: var_barPercentage, barThickness: var_barThickness, maxBarThickness: var_maxBarThickness, minBarLength: var_minBarLength, data: [
					  <?php echo $out_traffic; ?>
                  ], fill: false,
              }, {
                  label: "Before", fill: false, backgroundColor: '#09a8fc', borderColor: '#09a8fc', borderRadius: 20, barPercentage: var_barPercentage, barThickness: var_barThickness, maxBarThickness: var_maxBarThickness, minBarLength: var_minBarLength, data: [
					  <?php echo $in_traffic; ?>
                  ], fill: false,
              }]
          }, options: {
              responsive: true, maintainAspectRatio: false, title: {
                  display: false, text: ''
              }, interaction: {
                  intersect: false, mode: 'index', itemSort: function (a, b) {
                      return b.datasetIndex - a.datasetIndex
                  },
              }, plugins: {
                  legend: {
                      display: false,
                  }, tooltip: {
                      callbacks: {
                          footer: footer, label: function (tooltipItems, context) {
                              if (tooltipItems.datasetIndex == '1') {
                                  var trafficTotal = trafficSum.split(',');
                                  return 'Before ' + trafficTotal[tooltipItems.dataIndex] + ' MB';
                              }

                              return tooltipItems.dataset.label + ' ' + tooltipItems.formattedValue + ' MB';
                          }
                      }
                  },

              }, elements: {
                  line: {
                      tension: 0.00000001
                  }
              }, scales: {
                  x: {
                      barThickness: 20, stacked: true, display: true, scaleLabel: {
                          display: false, labelString: 'Month'
                      }
                  }, y: {
                      stacked: true, display: true, scaleLabel: {
                          display: false, labelString: 'MB'
                      }
                  }
              }
          }

      };
	  <?php } else { ?>

      var config = {
          type: 'bar', data: {
              labels: [<?php echo $labels_js; ?>], datasets: [{
                  label: "After Optimization", fill: false, backgroundColor: '#3c4cdf', borderColor: '#3c4cdf', barPercentage: var_barPercentage, barThickness: var_barThickness, maxBarThickness: var_maxBarThickness, minBarLength: var_minBarLength, data: [
					  <?php echo $out_traffic; ?>
                  ], fill: false,
              }, {
                  label: "Before", fill: false, backgroundColor: '#09a8fc', borderColor: '#09a8fc', borderRadius: 20, barPercentage: var_barPercentage, barThickness: var_barThickness, maxBarThickness: var_maxBarThickness, minBarLength: var_minBarLength, data: [
					  <?php echo $in_traffic; ?>
                  ], fill: false,
              }]
          }, options: {
              responsive: true, maintainAspectRatio: false, title: {
                  display: false, text: ''
              }, interaction: {
                  intersect: false, mode: 'index', itemSort: function (a, b) {
                      return b.datasetIndex - a.datasetIndex
                  },
              }, plugins: {
                  legend: {
                      display: false,
                  }, tooltip: {
                      callbacks: {
                          footer: footer, label: function (tooltipItems, data) {
                              if (tooltipItems.datasetIndex == '1') {
                                  var trafficTotal = trafficSum.split(',');
                                  return 'Before ' + trafficTotal[tooltipItems.dataIndex] + ' MB';
                              }
                              return tooltipItems.dataset.label + ' ' + tooltipItems.formattedValue + ' MB';
                          }
                      }
                  },

              }, elements: {
                  line: {
                      tension: 0.00000001
                  }
              }, scales: {
                  x: {
                      barThickness: 20, stacked: true, display: true, scaleLabel: {
                          display: false, labelString: 'Month'
                      }
                  }, y: {
                      stacked: true, display: true, scaleLabel: {
                          display: false, labelString: 'MB'
                      }
                  }
              }
          }

      };
	  <?php } ?>

	  <?php } ?>
  </script>
  <script type="text/javascript">
      jQuery(document).ready(function ($) {

		  <?php if (! empty( $labels ) && ! empty( $stats )) { ?>
          setTimeout(function () {
              if ($('#canvas').length) {
                  var ctx = document.getElementById("canvas").getContext("2d");
                  window.myLine = new Chart(ctx, config);
              }
          }, 200);
		  <?php } ?>
      });
  </script>

<?php } else {
	switch ( $_GET['view'] ) {
		case 'debug_tool':
			include 'debug_tool.php';
			break;
		case 'advanced_settings':
			include 'advanced_settings.php';
			break;
		case 'advanced_settings_v2':
			include 'advanced_settings_v2.php';
			break;
	}
}