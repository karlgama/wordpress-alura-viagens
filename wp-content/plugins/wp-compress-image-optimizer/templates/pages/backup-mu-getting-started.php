<?php
global $wps_ic, $wpdb;
?>
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
      </div>
      <div class="clearfix"></div>
    </div>

    <div class="wp-compress-pre-wrapper">
      <div class="wp-compress-pre-subheader">
        <div class="col-6">
          <ul>
            <li>
              <h3>Multisite Connect</h3>
            </li>
          </ul>
        </div>
        <div class="col-6 last">
        </div>
      </div>

      <div class="wp-compress-chart" style="height: 400px;">

        <div class="wpmu-loading" style="display: none;">
          <div class="wps-ic-loading-logo">
            <img src="<?php echo WPS_IC_URI; ?>assets/images/logo/blue-icon.svg" class="loading-logo"/>
            <img src="<?php echo WPS_IC_URI; ?>assets/preparing.svg" class="loading-circle"/>
          </div>
        </div>

				<?php
				$connected_to_api = false;
				$settings         = get_option(WPS_IC_MU_SETTINGS);

				if ( ! empty($settings['token'])) {
					$connected_to_api = true;
				}

				if ( ! $connected_to_api) {
					?>
          <form method="post" action="#" class="wpc-mu-api-connect-form">
            <label for="username">API Token:</label>
            <input type="text" name="api_token" placeholder="your api token"/>
            <input type="submit" value="submit"/>
          </form>
					<?php
				}
				else {
					?>
          <h3>List of Sites</h3>
          <table style="width: 100%;" class="wpc-mu-list-table">
            <thead>
            <tr>
              <th class="wpc-site-id" style="width:50px;">Site ID</th>
              <th style="width:250px;">Domain</th>
              <th style="width:150px;">Path</th>
              <th style="">Configuration</th>
              <th style="width:200px;">Status</th>
            </tr>
            </thead>
            <tbody>
						<?php
						$sites = get_sites();

						if ($sites) {
							foreach ($sites as $site) {
								switch_to_blog($site->blog_id);
								$options = get_option(WPS_IC_OPTIONS);

								$apikey  = $options['api_key'];
								?>
                <tr>
                  <td class="wpc-site-id"><?php echo $site->blog_id; ?></td>
                  <td class="wpc-domain"><?php echo $site->domain; ?></td>
                  <td class="wpc-path"><?php echo $site->path; ?></td>
                  <td>
										<?php
										if (!empty($apikey)) {

										} else {

										}
										?>
                    <ul>
                      <li>
                        <label for="webp">Live</label>
                        <select name="webp">
                          <option value="off">Off</option>
                          <option value="on" selected="selected">On</option>
                        </select>
                      </li>
                      <li>
                        <label for="webp">Quality</label>
                        <select name="webp">
                          <option value="off">Lossless</option>
                          <option value="on" selected="selected">Intelligent</option>
                          <option value="on">Ultra</option>
                        </select>
                      </li>
                      <li>
                        <label for="webp">WebP</label>
                        <select name="webp">
                          <option value="off">Off</option>
                          <option value="on">On</option>
                        </select>
                      </li>
                      <li>
                        <label for="webp">Adaptive</label>
                        <select name="webp">
                          <option value="off">Off</option>
                          <option value="on">On</option>
                        </select>
                      </li>
                      <li>
                        <label for="webp">Retina</label>
                        <select name="webp">
                          <option value="off">Off</option>
                          <option value="on">On</option>
                        </select>
                      </li>
                    </ul>
                  </td>
                  <td>
                    <?php
                    if (!empty($apikey)) {
											echo '<a href="#" class="wpc-mu-individual-disconnect" data-site-id="' . $site->blog_id . '">Connected - Click to Disconnect</a>';
										} else {
                      echo '<a href="#" class="wpc-mu-individual-connect" data-site-id="' . $site->blog_id . '">Not connected</a>';
										}
                    ?>
                  </td>
                </tr>
								<?php
							}
						}
						?>
            </tbody>
          </table>
					<?php

				}
				?>

      </div>

    </div>


  </div>