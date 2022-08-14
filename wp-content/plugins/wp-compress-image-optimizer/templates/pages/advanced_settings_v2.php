<?php global $wps_ic, $wpdb;

/**
 * GeoLocation Stuff
 */
$geolocation = get_option('wps_ic_geo_locate');
if (empty($geolocation)) {
	$geolocation = $this->geoLocate();
}
else {
	$geolocation = (object)$geolocation;
}

$geolocation_text = $geolocation->country_name . ' (' . $geolocation->continent_name . ')';

$default_Settings = array('js'                     => '1',
													'css'                    => '0',
													'fonts'                  => '0',
													'css_image_urls'         => '0',
													'external-url'           => '0',
													'replace-all-link'       => '0',
													'emoji-remove'           => '0',
													'on-upload'              => '0',
													'defer-js'               => '0',
													'serve_jpg'              => '1',
													'serve_png'              => '1',
													'serve_gif'              => '1',
													'serve_svg'              => '1',
													'search-through'         => 'html',
													'preserve-exif'          => '0',
													'background-sizing'      => '0',
													'remove-render-blocking' => '0',
													'minify-css'             => '0',
													'minify-js'              => '0');

$save = false;
if ( ! empty($_POST['submit_form'])) {
	if ( ! empty($_POST['wp-ic-setting'])) {
		// Previous Settings
		$settings = get_option(WPS_IC_SETTINGS);

		$new_settings = array();

		foreach ($default_Settings as $name => $value) {

			if (empty($_POST['wp-ic-setting'][ $name ]) || ! isset($_POST['wp-ic-setting'][ $name ])) {
				$new_settings[ $name ] = '0';
			}
			else {
				$new_settings[ $name ] = $_POST['wp-ic-setting'][ $name ];
			}

		}

		$save         = true;
		$new_settings = array_merge($settings, $new_settings);

		if ($_POST['wp-ic-setting']['search-through'] == 'html+css' || $_POST['wp-ic-setting']['search-through'] == 'all') {
			$new_settings['css_image_urls'] = '1';
		}
		else {
			$new_settings['css_image_urls'] = '0';
		}

		update_option(WPS_IC_SETTINGS, $new_settings);

        if ( isset ( $_POST['external-url-exclude'] ) && ! empty($_POST['external-url-exclude'])) {
            $post = $_POST['external-url-exclude'];
            $post = explode("\r\n", $post);
            update_option('wpc-ic-external-url-exclude', $post);
        }
        else if ( isset ( $_POST['external-url-exclude'] ) ) {
            update_option('wpc-ic-external-url-exclude', '');
        }

		$wps_ic->ajax->purgeBreeze();
		$wps_ic->ajax->purge_cache_files();

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
	}
}

$settings = get_option(WPS_IC_SETTINGS);

/**
 * Get Credits
 */
$user_credits = $wps_ic->getAccountStatusMemory();

// 1GB => WPS_IC_GB
$allow_cname = false;

if ( ! empty($user_credits->account->allow_cname)) {
	$allow_cname = $user_credits->account->allow_cname;
}

if ($allow_cname) {
	set_transient('ic_allow_cname', 'true', 15 * 60);
}
else {
	delete_transient('ic_allow_cname');
}

if ( ! empty($_GET['test_lock'])) {
	delete_transient('ic_allow_cname');
	delete_option('hide_upgrade_notice');
}

$allow_cname = get_transient('ic_allow_cname');
?>
<form method="POST" action="<?php echo admin_url('options-general.php?page=' . $wps_ic::$slug . '&view=advanced_settings_v2'); ?>">
  <div class="wrap">
    <div class="wps_ic_wrap wps_ic_settings_page ic-advanced-settings-v2">

      <div class="wp-compress-header">
        <div class="wp-ic-logo-container">
          <div class="wp-compress-logo">
            <img src="<?php echo WPS_IC_URI; ?>assets/images/main-logo.svg"/>
            <div class="wp-ic-logo-inner">
              <span class="small">v<?php echo $wps_ic::$version; ?></span>
            </div>
          </div>
        </div>
        <div class="wp-ic-header-buttons-container">
					<?php
					if ($save) {
						echo '<div class="changes-saved-container">';
						echo '<div class="inner">';
						echo '<div class="wps-ic-saved"><span class="ic-round-icon"><i class="icon-ok"></i></span><h3 style="color:#2fb940;">We have saved your changes!</h3></div>';
						echo '<div style="display:block;"></div>';
						echo '</div>';
						echo '</div>';
					}
					?>
          <ul>
            <li><a href="<?php echo admin_url('admin.php?page=' . $wps_ic::$slug . ''); ?>" class="button button-primary button-transparent">Return to Dashboard</a></li>
          </ul>
        </div>
        <div class="changes-detected-container" style="display:none;">
          <div class="inner" style="display:flex;">
            <h3>We have detected you have made some changes, please save your changes!</h3>
            <ul>
              <li><input type="submit" name="submit_form" class="button button-primary save-button-header" value="Save"/></li>
            </ul>
          </div>
        </div>
        <div class="clearfix"></div>
      </div>


			<?php
			$locked_css_class    = '';
			$locked_tooltip      = '';
			$hide_upgrade_notice = get_option('hide_upgrade_notice');
			#if (empty($allow_cname) || ! empty($_GET['test_lock'])) {
			if (1 == 0) {

				$settings      = get_option(WPS_IC_SETTINGS);
				$lock_settings = array('defer-js', 'external-images', 'emoji-remove', 'css_image_urls');
				foreach ($lock_settings as $k => $v) {
					if ( ! empty($settings[ $v ]) && $settings[ $v ] == '1') {
						$settings[ $v ] = '0';
					}
				}

				update_option(WPS_IC_SETTINGS, $settings);
				$locked_tooltip   = 'title="Advanced options are locked to accounts which have over 1GB of quota."';
				$locked_css_class = 'locked tooltip';
				if ( ! $hide_upgrade_notice || $hide_upgrade_notice == 0) {
					?>
          <div class="upgrade-to-pro-container">
            <div class="inner">
              <h3><img src="<?php echo WPS_IC_URI; ?>assets/images/star.svg" style="padding: 0px 20px;" alt="Upgrade to Pro"/> Upgrade to Pro to get all advanced features</h3>
              <a href="https://wpcompress.com/pricing/" target="_blank" class="upgrade-btn">Upgrade Now</a>
              <a href="#" class="close-pro-btn">
                <i class="fa fa-times"></i>
              </a>
            </div>
          </div>
				<?php } ?>
			<?php } ?>

      <div class="settings-container-flex-outer" style="margin-bottom: 25px">

        <div class="settings-container-half first">
          <div class="inner">
            <div class="setting-header">
              <div class="settings-header-title">
                CDN Delivery Options
                <span class="ic-tooltip" title="Allows your website to minify and serve JavaScript files via the CDN - be sure not to duplicate JavaScript settings with other solutions, however all toggles are temporary and can be toggled off at anytime to revert changes."><i class="fa fa-question-circle"></i></span>
              </div>
              <div class="settings-header-actions">
                <a href="#" class="wpc-small-pill wpc-default-settings" data-settings-area="serve-cdn"><i class="svg-icon icon-set-default"></i> Set Default</a>
              </div>
            </div>
            <div style="margin-left:15px;display:flex;width:100%;">
              <div class="setting-body-half settings-area-serve-cdn">
                <div class="setting-inner-header">
                  <h3>Serve Images</h3>
                </div>
								<?php
								$settings = get_option(WPS_IC_SETTINGS);
								if (empty($settings['live-cdn']) || $settings['live-cdn'] == '0') {
									?>
                  <div class="setting-option wpc-checkbox">
                    <input type="checkbox" id="serve_jpg" value="1" name="wp-ic-setting[serve_jpg]" data-setting_name="serve_jpg" data-setting_value="1" class="disabled-checkbox ic-tooltip" title="Switch to live optimization to serve images." <?php echo checked($settings['serve_jpg'], '1'); ?>/>
                    <label for="serve_jpg">JPG/JPEG</label>
                  </div>
                  <div class="setting-option wpc-checkbox">
                    <input type="checkbox" id="serve_png" value="1" name="wp-ic-setting[serve_png]" data-setting_name="serve_png" data-setting_value="1" class="disabled-checkbox ic-tooltip" title="Switch to live optimization to serve images." <?php echo checked($settings['serve_png'], '1'); ?>/>
                    <label for="serve_png">PNG</label>
                  </div>
                  <div class="setting-option wpc-checkbox">
                    <input type="checkbox" id="serve_gif" value="1" name="wp-ic-setting[serve_gif]" data-setting_name="serve_gif" data-setting_value="1" class="disabled-checkbox ic-tooltip" title="Switch to live optimization to serve images." <?php echo checked($settings['serve_gif'], '1'); ?>/>
                    <label for="serve_gif">GIF</label>
                  </div>
                  <div class="setting-option wpc-checkbox">
                    <input type="checkbox" id="serve_svg" value="1" name="wp-ic-setting[serve_svg]" data-setting_name="serve_svg" data-setting_value="1" class="disabled-checkbox ic-tooltip" title="Switch to live optimization to serve images." <?php echo checked($settings['serve_svg'], '1'); ?>/>
                    <label for="serve_svg">SVG</label>
                  </div>
								<?php } else { ?>
                  <div class="setting-option wpc-checkbox">
                    <input type="checkbox" id="serve_jpg" value="1" name="wp-ic-setting[serve_jpg]" data-setting_name="serve_jpg" data-setting_value="1" <?php echo checked($settings['serve_jpg'], '1'); ?>/>
                    <label for="serve_jpg">JPG/JPEG</label>
                  </div>
                  <div class="setting-option wpc-checkbox">
                    <input type="checkbox" id="serve_png" value="1" name="wp-ic-setting[serve_png]" data-setting_name="serve_png" data-setting_value="1" <?php echo checked($settings['serve_png'], '1'); ?>/>
                    <label for="serve_png">PNG</label>
                  </div>
                  <div class="setting-option wpc-checkbox">
                    <input type="checkbox" id="serve_gif" value="1" name="wp-ic-setting[serve_gif]" data-setting_name="serve_gif" data-setting_value="1" <?php echo checked($settings['serve_gif'], '1'); ?>/>
                    <label for="serve_gif">GIF</label>
                  </div>
                  <div class="setting-option wpc-checkbox">
                    <input type="checkbox" id="serve_svg" value="1" name="wp-ic-setting[serve_svg]" data-setting_name="serve_svg" data-setting_value="1" <?php echo checked($settings['serve_svg'], '1'); ?>/>
                    <label for="serve_svg">SVG</label>
                  </div>
								<?php } ?>
              </div>
              <div class="setting-body-half settings-area-serve-cdn">
                <div class="setting-inner-header">
                  <h3>Serve Assets</h3>
                </div>
                <div class="setting-option wpc-checkbox">
                  <input type="checkbox" id="js-toggle" value="1" name="wp-ic-setting[js]" data-setting_name="js" data-setting_value="1" <?php echo checked($settings['js'], '1'); ?>/>
                  <label for="js-toggle">JavaScript via CDN</label>
                </div>
                <div class="setting-option wpc-checkbox">
                  <input type="checkbox" id="css-toggle" value="1" name="wp-ic-setting[css]" data-setting_name="css" data-setting_value="1" <?php echo checked(@$settings['css'], '1'); ?>/>
                  <label for="css-toggle">CSS via CDN</label>
                </div>
                <div class="setting-option wpc-checkbox">
									<?php
									$zone_name = get_option('ic_custom_cname');
									if ( ! empty($zone_name)) {
										?>
                    <input type="checkbox" id="fonts-enabled" value="1" name="wp-ic-setting[fonts]" data-setting_name="fonts" data-setting_value="1" <?php echo checked(@$settings['fonts'], '1'); ?> class="cname-enabled"/>
                    <label for="fonts-enabled" class="label-enabled">Fonts</label>

                    <input type="checkbox" id="fonts" value="1" name="wp-ic-setting[fonts]" data-setting_name="fonts" data-setting_value="1" <?php echo checked(@$settings['fonts'], '1'); ?> class="disabled-checkbox ic-tooltip cname-disabled" title="To enable fonts option you need to setup your custom CDN." style="display: none;"/>
                    <label for="fonts" class="label-disabled ic-tooltip" title="To be able to serve fonts, you’ll first need to set up a custom CDN domain matching your root domain." style="display:none;">Fonts</label>
									<?php } else { ?>
                    <input type="checkbox" id="fonts-enabled" value="1" name="wp-ic-setting[fonts]" data-setting_name="fonts" data-setting_value="1" <?php echo checked(@$settings['fonts'], '1'); ?> style="display: none;" class="cname-enabled"/>
                    <label for="fonts-enabled" class="label-enabled" style="display:none;">Fonts</label>

                    <input type="checkbox" id="fonts" value="1" name="wp-ic-setting[fonts]" data-setting_name="fonts" data-setting_value="1" <?php echo checked(@$settings['fonts'], '1'); ?> class="disabled-checkbox ic-tooltip cname-disabled"
                           title="To be able to serve fonts, you’ll first need to set up a custom CDN domain matching your root domain."/>
                    <label for="fonts" class="label-disabled">Fonts</label>
									<?php } ?>
                </div>
              </div>
            </div>
            <div style="margin-left:15px;width:100%;">
              <div class="setting-body settings-area-search-through">
                <div class="setting-inner-header">
                  <h3>Search Through</h3>
                </div>
                <div class="setting-option wpc-checkbox" style="margin-top:20px;">
                  <div class="wp-ic-select-box full-width-box">
                    <input type="hidden" name="wp-ic-setting[search-through]" id="wp-ic-search-through" value="<?php echo $settings['search-through']; ?>">
                    <ul>
											<?php
											$options = array('html' => 'HTML Only', 'html+css' => 'HTML + CSS', 'all' => 'All URLs');
											foreach ($options as $key => $value) {
												if ($key == $settings['search-through']) {
													echo '<li class="current"><a href="#" class="wps-ic-search-through" data-value="' . $key . '">' . $value . '</a></li>';
												}
												else {
													echo '<li><a href="#" class="wps-ic-search-through" data-value="' . $key . '">' . $value . '</a></li>';
												}
											}
											?>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="settings-container-half">
          <div class="inner">
            <div class="setting-header">
              <div class="settings-header-title">
                Additional Settings
              </div>
              <div class="settings-header-actions">
                <a href="#" class="wpc-small-pill wpc-default-settings" data-settings-area="additional-settings"><i class="svg-icon icon-set-default"></i> Set Default</a>
              </div>
            </div>
            <div class="setting-body settings-area-additional-settings">

              <div class="setting-option">
                <div class="setting-label">Activate Background Sizing</div>
                <div class="setting-value ic-custom-tooltip" title="Automatically size backgrounds based on their container.">
                  <div class="checkbox-container-v3 wps-ic-ajax-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="background-sizing-toggle" value="1" name="wp-ic-setting[background-sizing]" data-setting_name="background-sizing" data-setting_value="1" <?php echo checked($settings['background-sizing'], '1'); ?>/>
                    <div>
                      <label for="background-sizing-toggle" class="background-sizing-toggle"></label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="setting-option">
                <div class="setting-label">Preserve EXIF Data</div>
                <div class="setting-value ic-custom-tooltip" title="Keep the image metadata, typically for photographers or SEO.">
                  <div class="checkbox-container-v3 wps-ic-ajax-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="preserve_exif-toggle" value="1" name="wp-ic-setting[preserve-exif]" data-setting_name="preserve-exif" data-setting_value="1" <?php echo checked($settings['preserve-exif'], '1'); ?>/>
                    <div>
                      <label for="preserve_exif-toggle" class="preserve_exif-toggle"></label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="setting-option">
                <div class="setting-label">Optimize on Upload</div>
                <div class="setting-value ic-custom-tooltip" title="Compress future image uploads with local mode settings.">
                  <div class="checkbox-container-v3 wps-ic-ajax-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="on-upload-toggle" value="1" name="wp-ic-setting[on-upload]" data-setting_name="on-upload" data-setting_value="1" <?php echo checked($settings['on-upload'], '1'); ?>/>
                    <div>
                      <label for="on-upload-toggle" class="on-upload-toggle"></label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="setting-option">
                <div class="setting-label">Disable WP Emoji Script</div>
                <div class="setting-value ic-custom-tooltip" title="You may remove the script if no emojis are used on your site.">
                  <div class="checkbox-container-v3 wps-ic-ajax-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="emoji-remove-toggle" value="1" name="wp-ic-setting[emoji-remove]" data-setting_name="emoji-remove" data-setting_value="1" <?php echo checked($settings['emoji-remove'], '1'); ?>/>
                    <div>
                      <label for="emoji-remove-toggle" class="emoji-remove-toggle"></label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="setting-option">
                <div class="setting-label">Minify CSS</div>
                <div class="setting-value ic-custom-tooltip" title="Compress CSS files and remove whitespace to reduce file-size.">
                  <div class="checkbox-container-v3 wps-ic-ajax-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="minify-css-toggle" value="1" name="wp-ic-setting[minify-css]" data-setting_name="minify-css" data-setting_value="1" <?php echo checked($settings['minify-css'], '1'); ?>/>
                    <div>
                      <label for="minify-css-toggle" class="minify-css-toggle"></label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="setting-option">
                <div class="setting-label">Minify JavaScript</div>
                <div class="setting-value ic-custom-tooltip" title="Compress JavaScript files and remove whitespace to reduce file-size.">
                  <div class="checkbox-container-v3 wps-ic-ajax-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="minify-js-toggle" value="1" name="wp-ic-setting[minify-js]" data-setting_name="minify-js" data-setting_value="1" <?php echo checked($settings['minify-js'], '1'); ?>/>
                    <div>
                      <label for="minify-js-toggle" class="minify-js-toggle"></label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="setting-option">
                <div class="setting-label">Defer JavaScript</div>
                <div class="setting-value ic-custom-tooltip" title="Delay the load priority of specified JavaScript files.">
                  <div class="checkbox-container-v3 wps-ic-ajax-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="defer-js-toggle" value="1" name="wp-ic-setting[defer-js]" data-setting_name="defer-js" data-setting_value="1" <?php echo checked($settings['defer-js'], '1'); ?>/>
                    <div>
                      <label for="defer-js-toggle" class="defer-js-toggle"></label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="setting-option">
                <div class="setting-label">External URLs</div>
                <div class="setting-value ic-custom-tooltip" title="Allows third party URLs to be live optimized for s3 offloading etc.">
                  <div class="checkbox-container-v3 wps-ic-ajax-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="external-url-toggle" value="1" name="wp-ic-setting[external-url]" data-setting_name="external-urls" data-setting_value="1" <?php echo checked($settings['external-url'], '1'); ?>/>
                    <div>
                      <label for="external-url-toggle" class="external-url-toggle"></label>
                    </div>
                  </div>
                </div>
              </div>

              <div class="setting-option">
                <div class="setting-label">Remove Render Blocking</div>
                <div class="setting-value ic-custom-tooltip" title="Remove render blocking on crucial assets.">
                  <div class="checkbox-container-v3 wps-ic-ajax-checkbox" style="display: inline-block;">
                    <input type="checkbox" id="remove-render-blocking-toggle" value="1" name="wp-ic-setting[remove-render-blocking]" data-setting_name="remove-render-blocking" data-setting_value="1" <?php echo checked($settings['remove-render-blocking'], '1'); ?>/>
                    <div>
                      <label for="remove-render-blocking-toggle" class="remove-render-blocking-toggle"></label>
                    </div>
                  </div>
                </div>
              </div>


            </div>
          </div>
        </div>

      </div>

      <div class="settings-container-flex-outer">
        <div class="settings-container-third" style="margin-bottom:25px;margin-right:10px;">
          <div class="inner">
            <div class="setting-group setting-group-center setting-group-narrow">
              <div class="setting-icon ic-tooltip" title="If you know your site location please select it from the option list below.">
                <img src="<?php echo WPS_IC_URI; ?>assets/images/icon-geolocation.svg"/>
              </div>
              <div class="setting-header" style="line-height: 30px;">
                <strong>Site GEO Location</strong>
              </div>

              <div class="setting-value">
                <p>We've detected your server is in <strong><?php echo $geolocation_text; ?></strong>.</p>
              </div>

              <a href="#" class="wps-ic-configure-popup" data-popup-width="600" data-popup="geo-location"><i class="svg-icon icon-configure"></i> Configure</a>

            </div>
          </div>
        </div>

        <div class="settings-container-third" style="margin-bottom:25px;margin-left:10px;margin-right:10px;">
          <div class="inner">
            <div class="setting-group setting-group-center setting-group-narrow">
              <div class="setting-icon ic-tooltip" title="If you know your site location please select it from the option list below.">
                <img src="<?php echo WPS_IC_URI; ?>assets/images/icon-cdn-custom.svg"/>
              </div>
              <div class="setting-header" style="line-height: 30px;">
                <strong>Exclude List</strong>
              </div>

              <div class="setting-value">
                <p>Specify excluded images, files or paths as desired.</p>
              </div>

              <a href="#" class="wps-ic-configure-popup" data-popup-width="900" data-popup="exclude-list"><i class="svg-icon icon-configure"></i> Configure</a>
            </div>
          </div>
        </div>

        <div class="settings-container-third cname-container" style="margin-bottom:25px;margin-left:10px;">
          <div class="inner">
            <div class="setting-group setting-group-center setting-group-narrow">
              <div class="setting-icon ic-tooltip" title="If you know your site location please select it from the option list below.">
                <img src="<?php echo WPS_IC_URI; ?>assets/images/icon-exclude-list.svg"/>
              </div>
              <div class="setting-header" style="line-height: 30px;">
                <strong>Custom CDN Domain</strong>
              </div>

            </div>

            <div class="setting-group setting-group-center setting-group-narrow">
							<?php
							$zone_name = get_option('ic_custom_cname');
							if ( ! empty($zone_name)) {
								?>
                <div class="setting-value setting-configured cname-configured">
                  <strong>Connected Domain: </strong><br/><?php echo $zone_name; ?><br/>
                </div>
                <div class="setting-value setting-configure" style="display: none;">
                  <p>Use <strong>any domain</strong> you own to serve images and assets.</p>
                </div>
								<?php
							}
							else {
								?>
                <div class="setting-value setting-configured cname-configured" style="display: none;">
                  <strong>Connected Domain: </strong><br/><?php echo $zone_name; ?><br/>
                </div>
                <div class="setting-value setting-configure">
                  <p>Use <strong>any domain</strong> you own to serve images and assets.</p>
                </div>
								<?php
							}
							?>

							<?php
							$zone_name = get_option('ic_custom_cname');
							if ( ! empty($zone_name)) {
								?>
                <a href="#" class="wps-ic-configure-popup setting-configured" data-popup="remove-custom-cdn"><i class="icon-trash"></i> Remove</a>
                <a href="#" class="wps-ic-configure-popup setting-configure" data-popup-width="600" data-popup="custom-cdn" style="display:none;"><i class="svg-icon icon-configure"></i> Configure</a>
								<?php
							}
							else {
								?>
                <a href="#" class="wps-ic-configure-popup setting-configured" data-popup="remove-custom-cdn" style="display: none;"><i class="icon-trash"></i> Remove</a>
                <a href="#" class="wps-ic-configure-popup setting-configure" data-popup-width="600" data-popup="custom-cdn"><i class="svg-icon icon-configure"></i> Configure</a>
								<?php
							}
							?>
            </div>

          </div>
        </div>

      </div>

    </div>


  </div>

</form>
<div id="geo-location" style="display: none;">
  <div id="cdn-popup-inner" class="ajax-settings-popup bottom-border geo-location-popup">

    <div class="cdn-popup-top">
      <h3>Site Geo Location</h3>
      <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/icon-geolocation-popup.svg"/>
    </div>

    <div class="cdn-popup-loading" style="display: none;">
      <div class="wps-ic-bulk-preparing-logo-container">
        <div class="wps-ic-bulk-preparing-logo">
          <img src="<?php echo WPS_IC_URI; ?>assets/images/logo/blue-icon.svg" class="bulk-logo-prepare"/>
          <img src="<?php echo WPS_IC_URI; ?>assets/preparing.svg" class="bulk-preparing"/>
        </div>
      </div>
    </div>

    <div class="cdn-popup-content">
      <p class="wpc-dynamic-text">We have detected that your server is located in <?php echo $geolocation_text; ?>, if that's not correct, please select the nearest region below.</p>
      <form method="post" action="#">
        <select name="location-select">
					<?php
					$location_select = array('Automatic' => 'Automatic', 'EU' => 'Europe', 'US' => 'United States', 'AS' => 'Asia', 'OC' => 'Oceania');

					foreach ($location_select as $k => $v) {
						if ($k == $geolocation->continent) {
							?>
              <option value="<?php echo $k; ?>" selected="selected"><?php echo $v; ?></option>
							<?php
						}
						else { ?>
              <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
							<?php
						}
					}
					?>
        </select>
        <div class="wps-empty-row">&nbsp;</div>
        <a href="#" class="btn btn-primary btn-active btn-save-location">Save Location</a>
      </form>
    </div>

    <div class="cdn-popup-bottom-border">&nbsp;</div>

  </div>
</div>
<div id="exclude-list" style="display: none;">
  <div id="cdn-popup-inner" class="ajax-settings-popup bottom-border exclude-list-popup">

    <div class="cdn-popup-loading" style="display: none;">
      <div class="wps-ic-bulk-preparing-logo-container">
        <div class="wps-ic-bulk-preparing-logo">
          <img src="<?php echo WPS_IC_URI; ?>assets/images/logo/blue-icon.svg" class="bulk-logo-prepare"/>
          <img src="<?php echo WPS_IC_URI; ?>assets/preparing.svg" class="bulk-preparing"/>
        </div>
      </div>
    </div>

    <div class="cdn-popup-top">
      <h3>Exclude List</h3>
      <p>Add excluded files or paths as desired as we use wildcard searching.</p>
    </div>

    <form method="post" action="#">
      <div class="cdn-popup-content-flex">
        <div class="cdn-popup-content">
          <div class="inline-heading-icon">
            <img src="<?php echo WPS_IC_URI; ?>assets/images/icon-exclude-from-cdn.svg"/>
            <h3>Exclude from CDN</h3>
          </div>
					<?php
					$external_url_exclude = get_option('wpc-ic-external-url-exclude');
					if ( ! empty($external_url_exclude)) {
						$external_url_exclude = implode("\n", $external_url_exclude);
					}
					?>
          <textarea name="exclude-list-textarea" class="exclude-list-textarea-value" placeholder="e.g. plugin-name/js/script.js, scripts.js, anyimage.jpg"><?php echo $external_url_exclude; ?></textarea>
          <div class="wps-empty-row">&nbsp;</div>

        </div>
        <div class="cdn-popup-content">
          <div class="inline-heading-icon">
            <img src="<?php echo WPS_IC_URI; ?>assets/images/exclude-lazy.svg"/>
            <h3>Exclude from Lazy</h3>
          </div>
					<?php
					$lazy_exclude = get_option('wpc-ic-lazy-exclude');
					if ( ! empty($lazy_exclude)) {
						$lazy_exclude = implode("\n", $lazy_exclude);
					}
					?>
          <textarea name="exclude-lazy-textarea" class="exclude-lazy-textarea-value" placeholder="e.g. wp-content/themes/folder/images, imagefilename.jpg"><?php echo $lazy_exclude; ?></textarea>
          <div class="wps-empty-row">&nbsp;</div>
        </div>
      </div>
      <div class="wps-example-list">
        <div>
          <h3>Examples:</h3>
          <div>
            <p>.svg would exclude all assets with that extension</p>
            <p>imagename would exclude any file with that name</p>
            <p>/myplugin/image.jpg would exclude that specific file</p>
            <p>/wp-content/myplugin/ would exclude everything using that path</p>
          </div>
        </div>
      </div>
      <a href="#" class="btn btn-primary btn-active btn-save btn-exclude-save">Save</a>
    </form>

    <div class="cdn-popup-bottom-border">&nbsp;</div>

  </div>
</div>
<div id="custom-cdn" style="display: none;">
  <div id="cdn-popup-inner" class="ajax-settings-popup bottom-border custom-cname-popup">

    <div class="cdn-popup-loading" style="display: none;">
      <div class="wps-ic-bulk-preparing-logo-container">
        <div class="wps-ic-bulk-preparing-logo">
          <img src="<?php echo WPS_IC_URI; ?>assets/images/logo/blue-icon.svg" class="bulk-logo-prepare"/>
          <img src="<?php echo WPS_IC_URI; ?>assets/preparing.svg" class="bulk-preparing"/>
        </div>
      </div>
      <h4 style="margin-top: 0px;margin-bottom: 46px;display:none;">We are setting up your DNS, this can take up to 30 seconds...</h4>
    </div>

    <div class="cdn-popup-content">
      <div class="custom-cdn-steps">
        <div class="custom-cdn-step-1">
          <div class="cdn-popup-top">
            <h3>Custom CDN Domain</h3>
            <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/icon-custom-cdn.svg"/>
          </div>
					<?php
					$zone_name = get_option('ic_cdn_zone_name');
					?>
          <ul>
            <li>1. Create a subdomain or domain that you wish to use.</li>
            <li>2. Edit the DNS records for the domain to create a new CNAME pointed at <strong><?php echo $zone_name; ?></strong></li>
            <li>3. Enter the url you've pointed below:</li>
          </ul>
          <form method="post" action="#" class="wpc-form-inline">
						<?php
						$custom_cname = get_option('ic_custom_cname');
						?>
            <input type="text" name="custom-cdn" placeholder="Example: cdn.mysite.com" value="<?php echo $custom_cname; ?>"/>
            <input type="submit" value="Save" name="save"/>
          </form>
          <div class="custom-cdn-error-message wpc-error-text" style="display: none;">
            &nbsp;
          </div>
        </div>
        <div class="custom-cdn-step-2" style="display: none;">
          <img src="" class="custom-cdn-step-2-img" onerror="this.src='<?php echo WPS_IC_URI; ?>assets/images/broken-placeholder.png';"/>
          <h3>Custom Domain Configuration</h3>
          <p>If you can see the celebration image above your custom domain is working!</p>
          <div class="wps-empty-row"></div>
          <a href="#" class="btn btn-primary btn-i-cant-see btn-cdn-config">I can't see the above Image</a>
          <a href="#" class="btn btn-primary btn-active btn-close btn-cdn-config">All Good to Go!</a>
        </div>
        <div class="custom-cdn-step-1-retry" style="display: none;">
          <div class="cdn-popup-top">
            <h3>Custom CDN Domain</h3>
            <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/icon-custom-cdn.svg"/>
          </div>
					<?php
					$zone_name = get_option('ic_cdn_zone_name');
					?>
          <ul>
            <li>1. Create a subdomain or domain that you wish to use.</li>
            <li>2. Edit the DNS records for the domain to create a new CNAME pointed at <strong><?php echo $zone_name; ?></strong></li>
            <li>3. Enter the url you've pointed below:</li>
          </ul>
          <form method="post" action="#" class="wpc-form-inline">
						<?php
						$custom_cname = get_option('ic_custom_cname');
						?>
            <input type="text" name="custom-cdn" placeholder="Example: cdn.mysite.com" value="<?php echo $custom_cname; ?>"/>
            <input type="submit" value="Save" name="save"/>
          </form>
          <p class="wpc-error-text"><span class="icon-container close-toggle"><i class="icon-cancel"></i></span> Seems like you have issues with your custom CDN configuration.</p>
          <div class="custom-cdn-error-message wpc-error-text" style="display: none;">
            &nbsp;
          </div>
        </div>
      </div>
    </div>

    <div class="cdn-popup-bottom-border">&nbsp;</div>

  </div>
</div>
<div id="remove-custom-cdn" style="display: none;">
  <div id="cdn-popup-inner" class="ajax-settings-popup bottom-border remove-cname-popup">

    <div class="cdn-popup-loading" style="display: none;">
      <div class="wps-ic-bulk-preparing-logo-container">
        <div class="wps-ic-bulk-preparing-logo">
          <img src="<?php echo WPS_IC_URI; ?>assets/images/logo/blue-icon.svg" class="bulk-logo-prepare"/>
          <img src="<?php echo WPS_IC_URI; ?>assets/preparing.svg" class="bulk-preparing"/>
        </div>
      </div>
    </div>

    <div class="cdn-popup-bottom-border">&nbsp;</div>

  </div>
</div>


<div id="settings-saved-popup" style="display: none;">
  <div id="cdn-popup-inner" class="ajax-settings-saved-popup">

    <div class="cdn-popup-content">
      <lottie-player
        src="<?php echo WPS_IC_URI; ?>assets/lottie-icons/done.json" background="transparent" speed="1" style="width: 200px; height: 200px;margin:0 auto;" autoplay>
      </lottie-player>

      <h3>Your settings have been saved!</h3>
    </div>

  </div>
</div>
<div id="css_combine-compatibility-popup" style="display: none;">
  <div id="cdn-popup-inner cdn-popup-smaller" class="">

    <div class="cdn-popup-top">
      <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/compatibility.svg"/>
    </div>

    <div class="cdn-popup-content">
      <h3>Please Confirm Compatibility</h3>
      <p>Advanced features such as Combine CSS Files may conflict with your active themes, plugins or environment. If any issues occur after activating, you can simply toggle it off.</p>
    </div>

  </div>
</div>
<div id="defer-js-compatibility-popup" style="display: none;">
  <div id="cdn-popup-inner cdn-popup-smaller" class="">

    <div class="cdn-popup-top">
      <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/compatibility.svg"/>
    </div>

    <div class="cdn-popup-content">
      <h3>Please Confirm Compatibility</h3>
      <p>Advanced features such as Defer JavaScript may conflict with your active themes, plugins or environment. If any issues occur after activating, you can simply toggle it off.</p>
    </div>

  </div>
</div>
<div id="js-compatibility-popup" style="display: none;">
  <div id="cdn-popup-inner cdn-popup-smaller" class="">

    <div class="cdn-popup-top">
      <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/compatibility.svg"/>
    </div>

    <div class="cdn-popup-content">
      <h3>Please Confirm Compatibility</h3>
      <p>Advanced features such as serving JavaScript from the CDN may conflict with your active themes, plugins or environment. If any issues occur after activating, you can simply toggle it off.</p>
    </div>

  </div>
</div>
<div id="css-compatibility-popup" style="display: none;">
  <div id="cdn-popup-inner cdn-popup-smaller" class="">

    <div class="cdn-popup-top">
      <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/compatibility.svg"/>
    </div>

    <div class="cdn-popup-content">
      <h3>Please Confirm Compatibility</h3>
      <p>Advanced features such as serving static CSS from the CDN may conflict with your active themes, plugins or environment. If any issues occur after activating, you can simply toggle it off.</p>
    </div>

  </div>
</div>
<div id="locked-popup" style="display: none;">
  <div id="cdn-popup-inner" class="locked-popup">

    <div class="cdn-popup-top">
      <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/rocket.png"/>
    </div>

    <div class="cdn-popup-content">
      <h3>Option Locked</h3>
      <p>Advanced options are locked to account which have over 1GB of quota.</p>
    </div>

  </div>
</div>
<div id="emoji-remove-minify-compatibility-popup" style="display: none;">
  <div id="cdn-popup-inner cdn-popup-smaller" class="">

    <div class="cdn-popup-top">
      <img class="popup-icon" src="<?php echo WPS_IC_URI; ?>assets/images/compatibility.svg"/>
    </div>

    <div class="cdn-popup-content">
      <h3>Please Confirm Compatibility</h3>
      <p>Advanced features such as removing WP Emoji may conflict with your active themes, plugins or environment. If any issues occur after activating, you can simply toggle it off.</p>
    </div>

  </div>
</div>
<div class="wps-ic-mu-popup-empty-cname" style="visibility: hidden;">
  <div id="cdn-popup-empty-sites-inner" class="ajax-settings-popup bottom-border custom-cname-popup-empty-sites">

    <div style="padding-bottom:30px;">
      <div class="wps-ic-mu-popup-select-sites">
        <img src="<?php echo WPS_IC_URI; ?>assets/images/projected-alert.svg" style="width:160px;"/>
      </div>
      <h3>You need to insert your CNAME!</h3>
    </div>

    <div class="cdn-popup-bottom-border">&nbsp;</div>

  </div>
</div>