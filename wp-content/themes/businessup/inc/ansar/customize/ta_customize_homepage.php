<?php require get_template_directory() . '/inc/ansar/customizer-repeater/functions.php'; ?>
<?php function businessup_homepage_setting( $wp_customize ) {


			$wp_customize->add_panel( 'homepage_setting', array(
                'priority' => 20,
                'capability' => 'edit_theme_options',
                'title' => __('Homepage Section Settings', 'businessup'),
            ) );
              

            function businessup_template_sanitize_text( $input ) {

			return wp_kses_post( force_balance_tags( $input ) );

			}
			
			function businessup_homepage_sanitize_checkbox( $input ) {
			// Boolean check 
			return ( ( isset( $input ) && true == $input ) ? true : false );
			}
	
			function businessup_template_sanitize_html( $input ) {

			return force_balance_tags( $input );
			}
			
			
			function businessup_sanitize_colors( $input ) {
			// Is this an rgba color or a hex?
			$mode = ( false === strpos( $input, 'rgba' ) ) ? 'hex' : 'rgba';

			if ( 'rgba' === $mode ) {
				return businessup_sanitize_rgba( $input );
			} else {
				return businessup_sanitize_colors( $input );
			}
			}
		
					/**
			 * Sanitize rgba color.
			 *
			 * @param string $value Color in rgba format.
			 *
			 * @return string
			 */
			function businessup_sanitize_rgba( $value ) {
				$red   = 'rgba(0,0,0,0)';
				$green = 'rgba(0,0,0,0)';
				$blue  = 'rgba(0,0,0,0)';
				$alpha = 'rgba(0,0,0,0)';   // If empty or an array return transparent
				if ( empty( $value ) || is_array( $value ) ) {
					return '';
				}

				// By now we know the string is formatted as an rgba color so we need to further sanitize it.
				$value = str_replace( ' ', '', $value );
				sscanf( $value, 'rgba(%d,%d,%d,%f)', $red, $green, $blue, $alpha );
				return 'rgba(' . $red . ',' . $green . ',' . $blue . ',' . $alpha . ')';
			}
			
			if ( ! function_exists( 'businessup_homepage_sanitize_textarea_content' ) ) :

			/**
			 * Sanitize textarea content.
			 *
			 * @since 1.0.0
			 *
			 * @param string               $input Content to be sanitized.
			 * @param WP_Customize_Setting $setting WP_Customize_Setting instance.
			 * @return string Sanitized content.
			 */
			function businessup_homepage_sanitize_textarea_content( $input, $setting ) {

				return ( stripslashes( wp_filter_post_kses( addslashes( $input ) ) ) );

			}
		endif;
		
		
		if ( ! function_exists( 'businessup_sanitize_image' ) ) :

	/**
	 * Sanitize image.
	 *
	 * @since 1.0.0
	 *
	 * @see wp_check_filetype() https://developer.wordpress.org/reference/functions/wp_check_filetype/
	 *
	 * @param string               $image Image filename.
	 * @param WP_Customize_Setting $setting WP_Customize_Setting instance.
	 * @return string The image filename if the extension is allowed; otherwise, the setting default.
	 */
	function businessup_sanitize_image( $image, $setting ) {

		/**
		 * Array of valid image file types.
		 *
		 * The array includes image mime types that are included in wp_get_mime_types().
		*/
		$mimes = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'gif'          => 'image/gif',
		'png'          => 'image/png',
		'bmp'          => 'image/bmp',
		'tif|tiff'     => 'image/tiff',
		'ico'          => 'image/x-icon',
		);

		// Return an array with file extension and mime_type.
		$file = wp_check_filetype( $image, $mimes );

		// If $image has a valid mime_type, return it; otherwise, return the default.
		return ( $file['ext'] ? $image : $setting->default );

	}

$wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
$wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
$selective_refresh = isset( $wp_customize->selective_refresh ) ? 'postMessage' : 'refresh';	

if ( isset( $wp_customize->selective_refresh ) ) {
	
	// site title
	$wp_customize->selective_refresh->add_partial(
		'blogname',
		array(
			'selector'        => '.site-title',
			'render_callback' => array( 'Businessup_Customizer_Partials', 'customize_partial_blogname' ),
		)
	);

    // site tagline
	$wp_customize->selective_refresh->add_partial(
		'blogdescription',
		array(
			'selector'        => '.site-description',
			'render_callback' => array( 'Businessup_Customizer_Partials', 'customize_partial_blogdescription' ),
		)
	);
}


endif;
			
			
}

add_action( 'customize_register', 'businessup_homepage_setting' );
?>