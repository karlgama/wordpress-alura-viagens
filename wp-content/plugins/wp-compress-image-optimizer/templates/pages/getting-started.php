<?php
if ( ! empty($_GET['view'])) {
	switch ($_GET['view']) {
		case 'debug_tool':
			include 'debug_tool.php';
			break;
	}
} else {
?>
<div class="wrap">
  <div class="wps_ic_wrap wps_ic_subscribe_container">

    <div class="wp-compress-header" style="background: #383838;">
      <div class="wp-ic-logo-container">
        <div class="wp-compress-logo">
          <img src="<?php echo WPS_IC_URI; ?>assets/images/logo/blue-icon.svg"/>
          <div class="wp-ic-logo-inner">
            <h3 style="color: #fff;">WP Compress</h3>
          </div>
        </div>
      </div>
      <div class="clearfix"></div>
    </div>
		<?php
		if (empty($response_key) || ! $response_key) {
			?>
      <div class="wps_ic_form_centered">

        <h3 style="font-size: 1.3em;margin: 1em 0;">Connect Your Website to the Management Portal</h3>

        <div class="wps-ic-form-success-container" style="display:none;">
          <div class="wps-ic-form-subscribe" style="display: none;">
            <img src="<?php echo WPS_IC_URI; ?>assets/images/confirmed.svg" alt="Success"/>
            <h3>We have sent confirmation e-mail to your address.</h3>
          </div>

          <div class="wps-ic-form-connect" style="display: none;">
            <img src="<?php echo WPS_IC_URI; ?>assets/images/confirmed.svg" alt="Success"/>
            <h3>We have successfully connected with cloud!</h3>
          </div>

          <div class="wps-ic-form-error" style="display: none;">
            <img src="<?php echo WPS_IC_URI; ?>assets/images/error.svg" alt="Error"/>
            <h3></h3>
            <p>Form will reappear in 5 seconds.</p>
          </div>
          <div class="wps-ic-form-error-text" style="display: none;">
            <img src="<?php echo WPS_IC_URI; ?>assets/images/error.svg" alt="Error"/>
            <h3>We have encountered an error within our tests...</h3>
            <p class="text-holder">Form will reappear in 5 seconds.</p>
          </div>
        </div>

        <div id="wps-ic-test-error" style="display: none;">
					<?php
					echo '<div class="ic-popup ic-popup-v2" id="wps-ic-connection-tests-inner">';
					echo '<div class="ic-image"><img src="' . WPS_IC_URI . 'assets/tests/error_robot.png" /></div>';
					echo '<h3 class="ic-title">We have encountered an error</h3>';
					echo '<ul class="wps-ic-check-list" style="margin:0px !important;">';
					echo '<li></li>';
					echo '</ul>';
					echo '<h5 class="ic-error-msg" style="margin:15px 0px;">Error message</h5>';

					echo '<div class="ic-input-holder">';
					echo '<a class="button button-primary button-half wps-ic-swal-close" href="#">Retry</a>';
					echo '<a class="button button-primary button-half wps-ic-swal-close" target="_blank" href="https://wpcompress.com/support">Contact support</a>';
					echo '</div>';

					echo '</div>';
					?>
        </div>
        <div id="wps-ic-connection-tests" style="display: none;">
					<?php
					echo '<div class="ic-popup ic-popup-v2" id="wps-ic-connection-tests-inner">';
					echo '<div class="ic-image"><img src="' . WPS_IC_URI . 'assets/tests/robot.png" /></div>';
					echo '<h3 class="ic-title">We\'re running a few quick tests</h3>';
					echo '<h5 class="ic-subtitle" style="padding-bottom:10px;">It should only be a few moments...</h5>';
					echo '<ul class="wps-ic-check-list" style="margin:0px !important;">';
					echo '<li data-test="verify_api_key"><span class="fas fa-dot-circle running"></span> API Key Validation</li>';
					echo '<li data-test="communication_with_portal"><span class="fas fa-dot-circle running"></span> Portal Communication</li>';
					echo '<li data-test="image_compress"><span class="fas fa-dot-circle running"></span> Image Optimization</li>';
					echo '<li data-test="image_restore"><span class="fas fa-dot-circle running"></span> Backup & Restore</li>';
					echo '<li data-test="finalization"><span class="fas fa-dot-circle running"></span> Finalization</li>';
					echo '</ul>';
					echo '<div class="ic-input-holder">';
					echo '<a class="button button-primary wps-ic-swal-close">Cancel</a>';
					echo '</div>';
					echo '</div>';
					?>
        </div>
        <div id="wps-ic-connection-tests-done" style="display: none;">
					<?php
					echo '<div class="ic-popup ic-popup-v2" id="wps-ic-connection-tests-inner">';
					echo '<div class="ic-image"><img src="' . WPS_IC_URI . 'assets/tests/robot.png" /></div>';
					echo '<h3 class="ic-title">You\'re Ready to Optimize!</h3>';
					echo '<ul class="wps-ic-check-list" style="margin:0px 0px 0px 0px !important;padding-top:10px;">';
					echo '<li data-test="verify_api_key"><span class="fas fa-check success"></span> API Key Validation</li>';
					echo '<li data-test="communication_with_portal"><span class="fas fa-check success"></span> Portal Communication</li>';
					echo '<li data-test="image_compress"><span class="fas fa-check success"></span> Image Optimization</li>';
					echo '<li data-test="image_restore"><span class="fas fa-check success"></span> Backup & Restore</li>';
					echo '</ul>';
					echo '<div class="ic-input-holder">';
					echo '<a href="' . admin_url('admin.php?page=' . $wps_ic::$slug . '&set_recommended=true') . '" class="button button-primary">Use Recommended Settings</a>';
					echo '<a href="' . admin_url('admin.php?page=' . $wps_ic::$slug . '') . '" class="grey-link">No Thanks, I\'ll Configure Settings</a>';
					echo '</div>';
					echo '</div>';
					?>
        </div>

        <form method="post" action="<?php echo admin_url('admin.php?page=' . $wps_ic::$slug . '&do=activate'); ?>" id="wps_ic_activate_form">
          <div class="wps_ic_activate_form slideInUp animated">
            <div class="wps_ic_form_header">
              <h3 style="text-align: left;">Link Your Website</h3>
            </div>
            <div class="wps_ic_form_input">
              <label>API Key</label>
              <input type="text" name="apikey" placeholder="251c0fda3e2b4bff5f6d28cf28bf5452f70d32"/>
							<?php
							if ( ! empty($_GET['fail_test'])) {
								echo '<input type="hidden" name="fail_test" value="' . $_GET['fail_test'] . '"/>';
							}
							?>
            </div>
            <div class="wps_ic_form_input pull_right">
              <input type="submit" name="submit" value="Activate"/>
            </div>
          </div>
        </form>

        <div class="wps-ic-form-loading-container" style="display:none;">
          <div class="wps-ic-form-loading"><img src="<?php echo WPS_IC_URI; ?>assets/images/spinner.svg"/></div>
        </div>


        <div class="wps_ic_form_other_option">
          <a href="https://app.wpcompress.com/register" class="fadeIn noline" target="_blank">Go to Portal</a>
          <strong style="">OR</strong>
          <a href="https://wpcompress.com/getting-started" target="_blank" class="fadeIn noline" target="_blank">View Getting Started Guide</a>
        </div>


      </div>
		<?php } ?>
  </div>
</div>
<?php } ?>