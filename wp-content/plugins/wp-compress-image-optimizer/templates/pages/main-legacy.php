<?php
global $wps_ic, $wpdb;

/**
 * Fix for stalling bulk image compression
 */
if ( ! empty($_GET['reset'])) {
	delete_option('wps_ic_hidden_bulk_running');
	delete_option('wps_ic_compress_info');
}

/**
 * Check CDN Bandwidth every few minutes and disable CDN
 * if user has used all of his bandwidth.
 * TODO: Show popup message, test
 */
$wps_ic->cdn->check_cdn_status();

// Reset Cache
$cache_cleared = get_transient('ic_cdn_reset_cache');
if ( ! $cache_cleared) {
	$cache_cleared = false;
} else {
	$cache_cleared = true;
	delete_transient('ic_cdn_reset_cache');
}

/**
 * Sets the recommended settings
 * - after plugin activation & connection, if user clicks the button "set recommended options"
 */
if ( ! empty($_GET['set_recommended'])) {
	$wps_ic->options->set_recommended_options();
}

/**
 * Fetch settings, or if save is triggered save them.
 * - If no settings are saved (bug, deleted options..) regenerate recommended
 */
$settings = $wps_ic->options->get_settings();

/**
 * Fetch stats from API Side
 */
$api_stats_query = $wps_ic->curl->get_stats();

$api_stats_sum = array();
$api_stats     = array();

if ( ! empty($api_stats_query->data->sum)) {
	$api_stats_sum = $api_stats_query->data->sum;
}

if ( ! empty($api_stats_query->data->stats)) {
	$api_stats = $api_stats_query->data->stats;
}

/**
 * Search if any new thumbnail sizes are in array
 * - maybe some theme or plugin added some after we did the settings
 */
$sizes = get_intermediate_image_sizes();
if ( ! empty($sizes)) {
	foreach ($sizes as $key => $value) {
		if (empty($settings['thumbnails'][ $value ])) {
			$settings['thumbnails'][ $value ] = 0;
		}
	}
}

/**
 * Quick fix for PHP undefined notices
 */
$wps_ic_active_settings['optimization']['normal']   = '';
$wps_ic_active_settings['optimization']['maximum']  = '';
$wps_ic_active_settings['optimization']['advanced'] = '';
$wps_ic_active_settings['optimization']['ultra']    = '';
$wps_ic_active_settings['backup']['cloud']          = '';
$wps_ic_active_settings['backup']['local']          = '';

/**
 * Decides which setting is active
 */
if ( ! empty($settings['optimization'])) {
	if ($settings['optimization'] == 'normal') {
		$wps_ic_active_settings['optimization']['normal'] = 'class="current"';
	} else if ($settings['optimization'] == 'maximum') {
		$wps_ic_active_settings['optimization']['maximum'] = 'class="current"';
	} else if ($settings['optimization'] == 'advanced') {
		$wps_ic_active_settings['optimization']['advanced'] = 'class="current"';
	} else {
		$wps_ic_active_settings['optimization']['ultra'] = 'class="current"';
	}
} else {
	$wps_ic_active_settings['optimization']['maximum'] = 'class="current"';
}

/**
 * Decides which setting is active
 */
if ( ! empty($settings['backup-location'])) {
	if ($settings['backup-location'] == 'cloud') {
		$wps_ic_active_settings['backup']['cloud'] = 'class="current"';
	} else if ($settings['backup-location'] == 'local') {
		$wps_ic_active_settings['backup']['local'] = 'class="current"';
	}
} else {
	$wps_ic_active_settings['backup']['cloud'] = 'class="current"';
}

/**
 * AutoPilot Default Settings
 */
$wps_otto_active_settings['otto']['automated'] = '';
$wps_otto_active_settings['otto']['on-upload'] = '';
$wps_otto_active_settings['otto']['off']       = '';

/**
 * Decides which setting is active for AutoPilot
 */
if ( ! empty($settings['otto'])) {
	if ($settings['otto'] == 'automated') {
		$wps_otto_active_settings['otto']['automated'] = 'class="current"';
	} else if ($settings['otto'] == 'on-upload') {
		$wps_otto_active_settings['otto']['on-upload'] = 'class="current"';
	} else {
		$wps_otto_active_settings['otto']['off'] = 'class="current"';
	}
} else {
	$wps_otto_active_settings['otto']['off'] = 'class="current"';
}

/**
 * Get Package/Plan details from API Key Verification
 * - Shows Shared or X Credits left information
 */
if ($verify_apikey->credits_sharing == '1') {
	$credits = 'Shared Plan';
} else if ($verify_apikey->credits >= 0) {
	$credits = $verify_apikey->credits . ' Zaps Left';
} else {
	$credits = 'Unknown';
}

if (empty($_GET['view'])) { ?>
  <div class="wrap">
    <div class="wps_ic_wrap wps_ic_settings_page">

      <div class="wp-compress-header">
        <div class="wp-ic-logo-container">
          <div class="wp-compress-logo">
            <img src="<?php echo WPS_IC_URI; ?>assets/images/logo/blue-icon.svg"/>
            <div class="wp-ic-logo-inner">
              <h3 style="color: #333;">WP Compress <span class="small"><?php echo $wps_ic::$version; ?></span></h3>
            </div>
          </div>
        </div>
        <div class="wp-ic-header-buttons-container">
          <ul>
            <?php
            if (!get_option('wps_ic_live') || get_option('wps_ic_live') == 'false') {
              ?>
            <li><a href="<?php echo admin_url('admin.php?page=' . $wps_ic::$slug . '&enable_live=true') ;?>" class="button pulsate-button">Enable Live</a></li>
            <?php
            } else {
							?>
              <li><a href="<?php echo admin_url('admin.php?page=' . $wps_ic::$slug . '&enable_live=false') ;?>" class="button button-primary">Enable Legacy</a></li>
							<?php
            }
            ?>
						<?php
						if (get_option('wps_ic_hidden_bulk_running') == 'true' && get_option('wps_ic_compress_info')) {
							?>
              <li>
                <a href="#" class="button button-primary" id="ic-background-action-info">
                  <span class="wps-ic-btn-text">Background action running... </span>
                  <span class="wps-ic-loading-white">
                  <img src="<?php echo WPS_IC_URI; ?>assets/images/white-loading.svg"/>
                </span>
                </a>
              </li>
						<?php } else { ?>
              <li><a href="#" class="wps-ic-compress-bulk-hidden button button-primary">Compress All Images</a></li>
              <li><a href="#" class="wps-ic-restore-bulk-hidden button button-primary">Restore All Images</a></li>
              <li><a href="https://app.wpcompress.com/login" target="_blank" class="button button-primary">Access The Portal</a></li>
						<?php } ?>
          </ul>
        </div>
        <div class="clearfix"></div>
      </div>

			<?php if ($cache_cleared) { ?>
        <div class="wp-compress-success-message">
          <h3 style="text-align: center;">We have cleared your CDN Cache!</h3>
          <div class="clearfix"></div>
        </div>
			<?php } ?>

      <div class="wp-compress-pre-wrapper">
        <div class="wp-compress-pre-subheader">
          <div class="col-6">
            <ul>
              <li><h3>Compression Report</h3></li>
            </ul>
          </div>
          <div class="col-6 last">
            <ul>
              <li><span class="legend-original"></span>Before</li>
              <li><span class="legend-after"></span>After optimization</li>
            </ul>
          </div>
        </div>

        <div class="wp-compress-chart" style="height: 400px;">
					<?php if ( ! empty($api_stats)) { ?>
            <canvas id="canvas"></canvas>
					<?php } else { ?>
            <div style="text-align: center;">
              <h3 style="text-align: center;padding: 80px 0px 40px 0px;    font-size: 1.3em;margin: 1em 0;">Sorry, you don't have any data yet.</h3>
              <a href="#" class="wps-ic-compress-bulk-hidden button button-primary" style="margin: 0 auto;">Start Compressing</a>
            </div>
					<?php } ?>
        </div>

      </div>
      <div class="wp-compres-settings">

        <div class="wp-compress-settings-row-blank">

          <div class="col-9 text-left white-box margin-right">
            <div class="pre-inner">
              <div class="inner">

								<?php

								/**
								 * Is it a multisite installation
								 * TODO: Do we still need this? Test on multisite
								 */
								if (is_multisite()) {
									$blogID = get_current_blog_id();
								} else {
									$blogID = 1;
								}

								/**
								 * Quick fix if there aren't any stats to show.
								 */
								if (empty($api_stats)) {
									$images = (object)array('percent_saved' => '0', 'count' => '0', 'original' => '0', 'compressed' => '0');
									$saved  = 0;
								} else {
									$saved = ($api_stats_sum->compressed / $api_stats_sum->original) * 100;
									$saved = 100 - $saved;
									$saved = round($saved, 1);
								}

								if ( ! empty($api_stats)) {
									?>

                  <div class="left-side-box">
                    <div class="circle-chart" style="width: 160px;height: 160px;display: inline-block;">
                      <div class="circle-inner"></div>
                      <div class="circle-text">
                        <strong><?php echo $saved; ?>%</strong>
                        <p>Savings</p>
                      </div>
                    </div>
                  </div>

                  <div class="right-side-box">
                    <div class="youve-saved">
											<?php
											if ($api_stats_sum->count > 0) {

												/**
												 * Savings calculation in MB/KB/GB...
												 * Total Original Size - Total Compressed Size
												 */
												$saved_before = $api_stats_sum->original - $api_stats_sum->compressed;

												?>
                        <h3>You've Saved</h3>
                        <h4><?php echo size_format($saved_before, 1); ?></h4>
												<?php
											} else {
												?>
                        <h3>No savings yet</h3>
                        <a href="#" class="button-primary button" style="margin-left: 10px;margin-right:10px;">Start</a>
											<?php } ?>
                      <div class="image-credits-remaining">
                        <a href="https://wpcompress.com/pricing" target="_blank" class="button button-primary">
                          <h5><?php echo $credits; ?></h5>
                        </a>
                      </div>
                    </div>

                    <div class="stats-boxes">

                      <div class="stats-box-single">
                        <h3><?php echo $api_stats_sum->count; ?></h3>
                        <h5>Total Images</h5>
                      </div>

                      <div class="stats-box-single">
                        <h3><?php echo size_format($api_stats_sum->original, 1); ?></h3>
                        <h5>Original</h5>
                      </div>

                      <div class="stats-box-single">
                        <h3><?php echo size_format($api_stats_sum->compressed, 1); ?></h3>
                        <h5>Compressed</h5>
                      </div>

                      <div class="stats-box-single">
                        <span>FREE</span>
                        <h3><?php echo $api_stats_sum->thumbs; ?></h3>
                        <h5>Thumbnails</h5>
                      </div>

                    </div>
                  </div>
								<?php } else { ?>
                  <div style="width: 47%;display:inline-block;vertical-align: top;">
                    <div style="text-align: center;padding: 42px 0px;">
                      <h3>Sorry, you don't have any data yet.</h3>
                      <a href="#" class="wps-ic-compress-bulk-hidden button-primary button" style="margin-left: 10px;margin-right:10px;">Start Compressing</a>
                    </div>
                  </div>
                  <div style="width: 49%;display:inline-block;vertical-align: top;padding: 66px 0px;text-align: center;">
                    <h3 style="display: inline-block;padding-top: 8px;padding-right:15px;margin: 0;vertical-align: top;">You have </h3>
                    <div class="image-credits-remaining" style="display: inline-block;vertical-align: bottom;">
                      <a href="https://wpcompress.com/pricing" target="_blank" class="button button-primary">
                        <h5 style="margin: 0;"><?php echo $credits; ?></h5>
                      </a>
                    </div>
                  </div>
								<?php } ?>
              </div>
            </div>
          </div>

          <div class="col-3">

            <div class="pre-inner">
              <div class="inner">

                <div class="autopilot-box">

                  <h3>Autopilot Mode <span class="ic-tooltip" title="We’ll optimize your images without you ever having to lift a finger again."><i class="fa fa-question-circle"></i></span></h3>

                  <div class="checkbox-container-v2 whole-checkbox autopilot-checkbox" style="padding-top: 0px;">
                    <input type="checkbox" id="wp-ic-setting[autopilot]" value="1"
                           name="wp-ic-setting[autopilot]" data-setting_name="autopilot" data-setting_value="1" <?php echo checked($settings['autopilot'], '1'); ?>/>
                    <div>
                      <label for="wp-ic-setting[autopilot]" class=""></label>
											<?php if ($settings['autopilot'] == '1') { ?>
                        <span>ON</span>
											<?php } else { ?>
                        <span>OFF</span>
											<?php } ?>
                    </div>
                  </div>
                  <div class="spacer"></div>
                  <div class="autopilot-options">
										<?php
										if (empty($settings['autopilot']) || $settings['autopilot'] == '0') {
											$enabled_status = 'style="display:none;"';
										} else {
											$enabled_status = 'style="display:block;"';
										}

										?>
                    <div class="wp-ic-select-box enabled" <?php echo $enabled_status; ?>>
                      <input type="hidden" name="wp-ic-setting[otto]" id="wp-ic-setting-otto" value="<?php echo $settings['otto'] ?>"/>
                      <ul>
                        <li <?php echo $wps_otto_active_settings['otto']['automated']; ?>><a href="#" class="tooltip wps-ic-change-otto" data-otto="automated"
                                                                                             title="Advanced checks for new or existing unoptimized images every hour and compresses during off peak hours to save server resources.">Advanced</a></li>
                        <li <?php echo $wps_otto_active_settings['otto']['on-upload']; ?>><a href="#" class="tooltip wps-ic-change-otto" data-otto="on-upload" title="On-Upload takes care of optimization new images right away while uploading.">On
                            Upload</a>
                        </li>
                      </ul>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>

          <div class="clearfix"></div>
        </div>

        <div class="wp-compress-settings-row">

          <div class="col-2 bigger text-center">
            <div class="inner">
              <h3>Optimization Level <span class="ic-tooltip" title="Select your preferred balance of speed and quality. Over-Compression Prevention™ is included in all modes."><i class="fa fa-question-circle"></i></span></h3>

              <div class="wp-ic-select-box">
                <input type="hidden" name="wp-ic-setting[optimization]" id="wp-ic-setting-optimization" value="<?php echo $settings['optimization']; ?>"/>
                <ul>
                  <li <?php echo $wps_ic_active_settings['optimization']['normal']; ?>><a href="#" class="wps-ic-change-optimization" data-optimization_level="normal">Lossless</a></li>
                  <li <?php echo $wps_ic_active_settings['optimization']['maximum']; ?>><a href="#" class="wps-ic-change-optimization" data-optimization_level="maximum">Intelligent</a></li>
                  <li <?php echo $wps_ic_active_settings['optimization']['ultra']; ?>><a href="#" class="wps-ic-change-optimization" data-optimization_level="ultra">Ultra</a></li>
                </ul>
              </div>
            </div>
          </div>

          <div class="col-2 text-center">

            <div class="inner">
              <h3>Resize Images <span class="ic-tooltip" title="Most images are way too big for the web, so we recommend resizing upon compression. The number you enter will become the longer dimension."><i class="fa fa-question-circle"></i></span></h3>

              <div class="checkbox-container-v2 text-input">
                <input type="checkbox" id="wp-ic-setting[resize_larger_images]" value="1"
                       name="wp-ic-setting[resize_larger_images]" data-setting_name="resize_larger_images" data-setting_value="1" <?php echo checked($settings['resize_larger_images'], '1'); ?>/>
                <div>
                  <label for="wp-ic-setting[resize_larger_images]" class="wp-ic-ajax-checkbox-v2"></label>
                  <input class="wp-ic-ajax-input" type="text" name="test-size" maxlength="4" value="<?php echo $settings['resize_larger_images_width']; ?>" data-setting_name="resize_larger_images_width"/>
                  <span class="no-change">PX</span>
                </div>
              </div>

            </div>

          </div>

          <div class="col-2 text-center">

            <div class="inner">
              <h3>Thumbnail Options <span class="ic-tooltip" title="Customize which thumbnail sizes you want to compress or skip."><i class="fa fa-question-circle"></i></span></h3>

              <a href="#" class="button button-primary thumbnails-popup">Configure</a>

            </div>

          </div>

          <div class="col-2 text-center">

            <div class="inner">
              <h3>Preserve Exif <span class="ic-tooltip" title="Keep the data such as date and time when the image was taken and other information stored in the image file."><i class="fa fa-question-circle"></i></span></h3>

              <div class="checkbox-container-v2 simple">
                <input type="checkbox" id="preserve-exif-toggle" value="1" name="wp-ic-setting[preserve_exif]" data-setting_name="preserve_exif" data-setting_value="1" <?php echo checked($settings['preserve_exif'], '1'); ?>/>
                <div>
                  <label for="preserve-exif-toggle" class="wp-ic-ajax-checkbox-v2"></label>
                </div>
              </div>

            </div>

          </div>

          <div class="col-2 text-center">

            <div class="inner">
              <h3>Hide WP Compress <span class="ic-tooltip" title="Hides WP Compress from the media library, for those who want a “natural” WordPress look and feel."><i class="fa fa-question-circle"></i></span></h3>

							<?php if (get_option('ic_whitelabel') == 'true') { ?>
                <div class="checkbox-container-v2 simple">
                  <input type="checkbox" id="hide-compress-toggle" value="1" name="wp-ic-setting[hide_compress]" data-setting_name="hide_compress" data-setting_value="1" <?php echo checked($settings['hide_compress'], '1'); ?>/>
                  <div>
                    <label for="hide-compress-toggle" class="wp-ic-ajax-checkbox-v2"></label>
                  </div>
                </div>
							<?php } else { ?>
                <span class="locked-option ic-tooltip" title="Please upgrade your plan to enable this feature."><i class="icon icon-lock"></i></span>
							<?php } ?>

            </div>

          </div>

          <div class="clearfix"></div>

        </div>

        <div class="wp-compress-settings-row-blank">

          <div class="col-9 text-left white-box margin-right">

            <div class="pre-inner">
              <div class="inner flex">

                <div class="fourth-width text-center">
                  <span class="new-badge">New</span>
                  <h3>Live API <span class="ic-tooltip" title="Intelligently show images based on device size, which can DRASTICALLY reduce load times on mobile devices."><i class="fa fa-question-circle"></i></span>
                  </h3>

                  <div class="checkbox-container-v2 informative-input whole-checkbox">
                    <input type="checkbox" id="live-api-toggle" value="1" name="wp-ic-setting[live_api]" data-setting_name="live_api" data-setting_value="1" <?php echo checked($settings['live_api'], '1'); ?>/>
                    <div>
                      <label for="live-api-toggle" class="live-api-toggle"></label>
											<?php if ($settings['live_api'] == '1') { ?>
                        <span>ON</span>
											<?php } else { ?>
                        <span>OFF</span>
											<?php } ?>
                    </div>
                  </div>
                </div>

								<?php
								$live_opacity = 'opacity:0.3;position:relative;';
								$disable_overlay_opacity = 'display:block;opacity:1;visibility:visible;';
								if ($settings['live_api'] == '1') {
									$live_opacity = 'opacity:1;';
									$disable_overlay_opacity = '';
								}
								?>

                <div class="fourth-width text-center live-option-row" style="<?php echo $live_opacity; ?>">
                  <div class="ic-settings-disable-overlay ic-live-overlay" style="<?php echo $disable_overlay_opacity; ?>"></div>
                  <span class="new-badge">New</span>
                  <h3>Live WebP <span class="ic-tooltip" title="Serve next-gen image format WebP to supported browsers for additional file-size savings."><i class="fa fa-question-circle"></i></span></h3>

                  <div class="checkbox-container-v2 informative-input whole-checkbox">
                    <input type="checkbox" id="generate-webp-toggle" value="1"
                           name="wp-ic-setting[generate_webp]" data-setting_name="generate_webp" data-setting_value="1" <?php echo checked($settings['generate_webp'], '1'); ?>/>
                    <div>
                      <label for="generate_webp" class="webp-toggle"></label>
											<?php if ($settings['generate_webp'] == '1') { ?>
                        <span>ON</span>
											<?php } else { ?>
                        <span>OFF</span>
											<?php } ?>
                    </div>
                  </div>
                </div>

                <div class="fourth-width text-center live-option-row" style="<?php echo $live_opacity; ?>">
                  <div class="ic-settings-disable-overlay ic-live-overlay" style="<?php echo $disable_overlay_opacity; ?>"></div>
                  <span class="new-badge">New</span>
                  <h3>Live Lazy <span class="ic-tooltip" title="Load your images only when they are in viewport."><i class="fa fa-question-circle"></i></span>
                  </h3>

                  <div class="checkbox-container-v2 informative-input whole-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="cdn-toggle" value="1" name="wp-ic-setting[lazy]" data-setting_name="lazy" data-setting_value="1" <?php echo checked($settings['lazy'], '1'); ?>/>
                    <div>
                      <label for="lazy-toggle" class="lazy-toggle"></label>
											<?php if ($settings['lazy'] == '1') { ?>
                        <span>ON</span>
											<?php } else { ?>
                        <span>OFF</span>
											<?php } ?>
                    </div>
                  </div>
                </div>

                <div class="fourth-width text-center live-option-row" style="<?php echo $live_opacity; ?>">
                  <div class="ic-settings-disable-overlay ic-live-overlay" style="<?php echo $disable_overlay_opacity; ?>"></div>
                  <span class="new-badge">New</span>
                  <h3>Live AI <span class="ic-tooltip" title="Speed up your image delivery across the globe with our lightning fast global network."><i class="fa fa-question-circle"></i></span>
                  </h3>

                  <div class="checkbox-container-v2 informative-input whole-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="cdn-toggle" value="1" name="wp-ic-setting[cdn]" data-setting_name="cdn" data-setting_value="1" <?php echo checked($settings['cdn'], '1'); ?>/>
                    <div>
                      <label for="cdn-toggle" class="cdn-toggle"></label>
											<?php
											if ($settings['cdn'] == '1') {
												?>
                        <span>ON</span>
											<?php } else { ?>
                        <span>OFF</span>
											<?php } ?>
                    </div>
                  </div>
                </div>

              </div>
            </div>

          </div>

          <div class="col-3 box-white">
            <div class="pre-inner">
              <div class="inner flex">

                <div class="full-width text-center">
                  <span class="new-badge">New</span>
                  <h3>Live CDN</h3>

                  <h3>You have 5,489 views remaining.</h3>

                </div>
              </div>
            </div>
          </div>

          <div class="clearfix"></div>

        </div>

        <?php
        $disable_overlay_opacity = '';
        $legacy_opacity = 'opacity:1;';
        if ($settings['live_api'] == '1') {
          $legacy_opacity = 'opacity:0.3;position:relative;';
					$disable_overlay_opacity = 'display:block;opacity:1;visibility:visible;';
				}
        ?>
        <div class="wp-compress-settings-row-blank" id="ic-legacy-row" style="<?php echo $legacy_opacity; ?>">
          <div class="ic-settings-disable-overlay" id="ic-legacy-overlay" style="<?php echo $disable_overlay_opacity; ?>"></div>
          <div class="col-9 text-left white-box margin-right">

            <div class="pre-inner">
              <div class="inner flex">

                <div class="fourth-width text-center">
                  <span class="new-badge">New</span>
                  <h3>Adaptive Images <span class="ic-tooltip" title="Intelligently show images based on device size, which can DRASTICALLY reduce load times on mobile devices."><i class="fa fa-question-circle"></i></span>
                  </h3>

                  <div class="checkbox-container-v2 informative-input whole-checkbox">
                    <input type="checkbox" id="adaptive-images-toggle" value="1" name="wp-ic-setting[adaptive_images]" data-setting_name="adaptive_images" data-setting_value="1" <?php echo checked($settings['adaptive_images'], '1'); ?>/>
                    <div>
                      <label for="adaptive-images-toggle" class="adaptive-toggle"></label>
											<?php if ($settings['adaptive_images'] == '1') { ?>
                        <span>ON</span>
											<?php } else { ?>
                        <span>OFF</span>
											<?php } ?>
                    </div>
                  </div>
                </div>

                <div class="fourth-width text-center">
                  <span class="new-badge">New</span>
                  <h3>WebP <span class="ic-tooltip" title="Serve next-gen image format WebP to supported browsers for additional file-size savings."><i class="fa fa-question-circle"></i></span></h3>

                  <div class="checkbox-container-v2 informative-input whole-checkbox">
                    <input type="checkbox" id="generate-webp-toggle" value="1"
                           name="wp-ic-setting[generate_webp]" data-setting_name="generate_webp" data-setting_value="1" <?php echo checked($settings['generate_webp'], '1'); ?>/>
                    <div>
                      <label for="generate_webp" class="webp-toggle"></label>
											<?php if ($settings['generate_webp'] == '1') { ?>
                        <span>ON</span>
											<?php } else { ?>
                        <span>OFF</span>
											<?php } ?>
                    </div>
                  </div>
                </div>


                <div class="fourth-width text-center">
                  <span class="new-badge">New</span>
                  <h3>Lazy Load <span class="ic-tooltip" title="Load your images only when they are in viewport."><i class="fa fa-question-circle"></i></span>
                  </h3>

                  <div class="checkbox-container-v2 informative-input whole-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="cdn-toggle" value="1" name="wp-ic-setting[lazy]" data-setting_name="lazy" data-setting_value="1" <?php echo checked($settings['lazy'], '1'); ?>/>
                    <div>
                      <label for="lazy-toggle" class="lazy-toggle"></label>
											<?php if ($settings['lazy'] == '1') { ?>
                        <span>ON</span>
											<?php } else { ?>
                        <span>OFF</span>
											<?php } ?>
                    </div>
                  </div>
                </div>

                <div class="fourth-width text-center">
                  <span class="new-badge">New</span>
                  <h3>Deliver From CDN <span class="ic-tooltip" title="Speed up your image delivery across the globe with our lightning fast global network."><i class="fa fa-question-circle"></i></span>
                  </h3>

                  <div class="checkbox-container-v2 informative-input whole-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="cdn-toggle" value="1" name="wp-ic-setting[cdn]" data-setting_name="cdn" data-setting_value="1" <?php echo checked($settings['cdn'], '1'); ?>/>
                    <div>
                      <label for="cdn-toggle" class="cdn-toggle"></label>
											<?php
											if ($settings['cdn'] == '1') {
												?>
                        <span>ON</span>
											<?php } else { ?>
                        <span>OFF</span>
											<?php } ?>
                    </div>
                  </div>
                </div>

              </div>
            </div>

          </div>

          <div class="col-3 box-white">
            <div class="pre-inner">
              <div class="inner flex">

                <div class="full-width text-center">
                  <span class="new-badge">New</span>
                  <h3>Backup Location</h3>

                  <div class="wp-ic-select-box">
                    <input type="hidden" name="wp-ic-setting[backup-location]" id="wp-ic-backup-location" value="<?php echo $settings['backup-location']; ?>"/>
                    <ul>
                      <li <?php echo $wps_ic_active_settings['backup']['cloud']; ?>><a href="#" class="wps-ic-change-backup-location" data-backup-location="cloud">Cloud</a></li>
                      <li <?php echo $wps_ic_active_settings['backup']['local']; ?>><a href="#" class="wps-ic-change-backup-location" data-backup-location="local">Local</a></li>
                    </ul>
                  </div>

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
              <a href="https://wpcompress.com/getting-started/">Getting Started Guide</a>
            </li>
            <!--<li>
            <a href="<?php echo admin_url('admin.php?page=wps_ic&set_pagination=true'); ?>">Set Media Paginations</a>
          </li>-->
            <li>
              <a href="https://go.crisp.chat/chat/embed/?website_id=afb69c89-31ce-4a64-abc8-6b11e22e3a10">Chat with Support</a>
            </li>
            <li>
              <a href="<?php echo admin_url('admin.php?page=' . $wps_ic::$slug . '&view=debug_tool'); ?>">Debug Tool</a>
            </li>
            <li>
              <a class="ic-reset-cache" href="<?php echo admin_url('admin.php?page=' . $wps_ic::$slug . ''); ?>">Reset CDN Cache</a>
            </li>
            <li>
              <a class="ic-reset-stats" href="<?php echo admin_url('admin.php?page=' . $wps_ic::$slug . ''); ?>">Reset Stats</a>
            </li>
            <li><a href="#" class="wps-ic-regen-thumbnails">Regenerate Thumbnails</a></li>
          </ul>
        </div>

        <div class="wp-compress-settings-footer partners">
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

    <div id="cdn-popup" style="display: none;">
      <div id="cdn-popup-inner">

        <h3>We are scanning your Media Library</h3>
        <p>We need to verify that all of your images have been pushed to the CDN.</p>

      </div>
    </div>
    <div id="leave-popup" style="display: none;">
      <div id="cdn-popup-inner">

        <div class="wps-ic-inner wps-ic-spinner">
          <img src="<?php echo WPS_IC_URI; ?>assets/images/spinner.svg"/>
        </div>

        <h3>We are working on your Media Library</h3>
        <p>If you refresh or leave this tab our worker will stop until tab is open again.</p>

      </div>
    </div>
    <div id="cdn-reset-popup" style="display: none;">
      <div id="cdn-popup-inner">

        <div class="wps-ic-inner wps-ic-spinner">
          <img src="<?php echo WPS_IC_URI; ?>assets/images/spinner.svg"/>
        </div>

        <h3>We are purging our CDN Cache</h3>
        <p>This might take up to 30 seconds...</p>

      </div>
    </div>
    <div id="stats-reset-popup" style="display: none;">
      <div id="cdn-popup-inner">

        <div class="wps-ic-inner wps-ic-spinner">
          <img src="<?php echo WPS_IC_URI; ?>assets/images/spinner.svg"/>
        </div>

        <h3>We are purging our Stats Database</h3>
        <p>This might take up to 30 seconds...</p>

      </div>
    </div>

  </div>

  <script type="text/javascript">
		<?php
		$labels = array();
		$in_traffic = '';
		$out_traffic = '';
		$images_charts = '';

		if (is_multisite()) {
			$blogID = get_current_blog_id();
		} else {
			$blogID = 1;
		}

		function format_KB($value) {
			$value = $value / 1024;
			$value = $value / 1024;

			return $value;
		}

		$labels_dates = array();
		//  WHERE created='2018-10-09'

		if ( ! empty($api_stats)) {
			$limit = 10;

			// Calculate offset
			$total_items = count((array)$api_stats);
			$offset      = $total_items - ($limit - 1);
			$item        = 0;

			foreach ($api_stats as $date => $data) {
				$item ++;
				if ($item < $offset) {
					continue;
				}

				if ($limit == 0) {
					break;
				}

				if (empty($data->original)) {
					continue;
				}

				$index                            = date('m-d-Y', strtotime($date));
				$labels[ $index ]['date']         = date('m/d/Y', strtotime($date));
				$labels[ $index ]['images']       = $data->count;
				$labels[ $index ]['total_input']  = $data->original;
				$labels[ $index ]['total_output'] = $data->compressed;

				$limit --;
			}
		}

		$count_labels = count($labels);
		if ($count_labels == 4) {
			$catpercentage = 0.20;
		} else if ($count_labels == 3) {
			$catpercentage = 0.12;
		} else if ($count_labels <= 2) {
			$catpercentage = 0.05;
		} else if ($count_labels >= 5 && $count_labels <= 8) {
			$catpercentage = 0.2;
		} else if ($count_labels >= 8 && $count_labels <= 10) {
			$catpercentage = 0.4;
		} else {
			$catpercentage = 0.55;
		}

		// Parse to javascript
		$labels_js = '';
		$biggestY = 0;
		if ($labels) {

			foreach ($labels as $date => $data) {
				$labels_js     .= '"' . date('m/d/Y', strtotime($data['date'])) . '",';
				$images_charts .= $data['images'] . ',';
				$in_traffic    .= format_KB($data['total_input'] - $data['total_output']) . ',';
				$out_traffic   .= format_KB($data['total_output']) . ',';

				$kbIN  = format_KB($data['total_input']);
				$kbOUT = format_KB($data['total_output']);

				if ($kbIN > $kbOUT) {
					if ($biggestY < $kbIN) {
						$biggestY = $kbIN;
					}
				} else {
					if ($biggestY < $kbOUT) {
						$biggestY = $kbOUT;
					}
				}

			}

		}

		$biggestY = ceil($biggestY);


		// Calculate Max

		$fig = (int)str_pad('1', 2, '0');
		$maxY = (ceil($biggestY * $fig) / $fig);

		$stepSize = $maxY / 10;

		if ($maxY <= 10) {
			$stepSize = 1;
		} else if ($maxY <= 100) {
			$stepSize = 10;
		} else if ($maxY <= 500) {
			$stepSize = 25;
		} else if ($maxY <= 1000) {
			$stepSize = 100;
		} else if ($maxY <= 2000) {
			$stepSize = 200;
		}

		if (! empty($labels) && ! empty($api_stats)) {

		$out_traffic = rtrim($out_traffic, ',');
		$in_traffic = rtrim($in_traffic, ',');
		$images_charts = rtrim($images_charts, ',');


		$labels_js = rtrim($labels_js, ',');
		?>

    function tooltipValue(bytes) {
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        if (bytes == 0) return '0 Byte';
        bytes = bytes * 1024 * 1024;
        var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + sizes[i];
    }


    var config = {
        type: 'bar',
        data: {
            labels: [<?php echo $labels_js; ?>],
            datasets: [{
                label: "After Optimization",
                fill: false,
                backgroundColor: '#3c4cdf',
                borderColor: '#3c4cdf',
                data: [
									<?php echo $out_traffic; ?>
                ],
                fill: false,
            }, {
                label: "Original",
                backgroundColor: '#09a8fc',
                borderColor: '#09a8fc',
                data: [
									<?php echo $in_traffic; ?>
                ],
                fill: false,
            }]
        },
        options: {
            legend: false,
            responsive: true,
            title: {
                display: false,
                text: ''
            },
            tooltips: {
                mode: 'index',
                intersect: false,
                itemSort: function (a, b) {
                    return b.datasetIndex - a.datasetIndex
                },
                callbacks: {
                    label: function (tooltipItems, data) {
                        var tooltip = tooltipValue(tooltipItems.yLabel);

                        // Index of the column
                        var indexColumn = tooltipItems.index;
                        var after = data.datasets[0].data[indexColumn];
                        var before = data.datasets[1].data[indexColumn];

                        if (tooltipItems.datasetIndex == 0) {
                            // Original
                            var prefix = 'After ';
                        } else {
                            // Compressed
                            var prefix = 'Before ';
                        }

                        if (tooltipItems.datasetIndex == 0) {
                            return prefix + tooltip;
                        } else {
                            return prefix + tooltipValue(after + before);
                            //return prefix + tooltip;
                        }
                    }
                }
            },
            hover: {
                mode: 'nearest',
                intersect: true
            },
            elements: {
                line: {
                    tension: 0.00000001
                }
            },
            maintainAspectRatio: false,
            scales: {
                xAxes: [{
                    categoryPercentage: <?php echo $catpercentage; ?>,
                    barThickness: 20,
                    stacked: true,
                    display: true,
                    scaleLabel: {
                        display: false,
                        labelString: 'Month'
                    }
                }],
                yAxes: [{
                    ticks: {
                        max: <?php echo $maxY; ?>,
                        beginAtZero: true,
                        stepSize:<?php echo $stepSize; ?>,
                        callback: function (value, index, values) {
                            return Math.ceil(value) + ' MB';
                        }
                    },
                    stacked: true,
                    display: true,
                    scaleLabel: {
                        display: false,
                        labelString: 'MB'
                    }
                }]
            }
        }
    };

		<?php } ?>

  </script>
  <script type="text/javascript">
      jQuery(document).ready(function ($) {
				<?php if (! empty($labels) && ! empty($api_stats)) { ?>
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
	switch ($_GET['view']) {
		case 'debug_tool':
			include 'debug_tool.php';
			break;
	}
}