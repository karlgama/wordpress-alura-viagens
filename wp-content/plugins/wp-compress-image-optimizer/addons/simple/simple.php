<?php
/**
 * WP Compress - Extensions
 * Extension name: Simple Addon
 * Description: Create a simple infrastructure for PRO Users
 */

#ini_set('display_errors', 1);
#error_reporting(E_ALL);

class wpc_simple_cdn {



	public function __construct() {

		$this->prepare();
		$this->setupHomeUrl();

		$this->assetsToPreload = array( 'themes', 'elementor', 'wp-includes' );

		$this->isMultisite            = is_multisite();
		$this->directories_to_include = 'wp\-content|wp\-includes';
		$this->isAjax                 = ( function_exists( "wp_doing_ajax" ) && wp_doing_ajax() ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX );

		$this->lazyExcludedList      = get_option( 'wpc-ic-lazy-exclude' );
		$this->excludedList          = get_option( 'wpc-ic-external-url-exclude' );
		$this->settings              = get_option( WPS_IC_SETTINGS );
		$this->allowedFileExtensions = array( 'jpg', 'jpeg', 'webp', 'gif', 'png', 'ico', 'svg' );

		$this->customCname = get_option( 'ic_custom_cname' );

		if ( empty( $this->customCname ) || ! $this->customCname ) {
			$this->zoneName = get_option( 'ic_cdn_zone_name' );
		} else {
			$this->zoneName = $this->customCname;
		}

		// default excluded keywords
		$this->defaultExcludedList = array( 'redditstatic', 'ai-uncode', 'gtm', 'instagram.com', 'fbcdn.net', 'twitter', 'google', 'coinbase', 'cookie', 'schema', 'recaptcha', 'data:image' );
	}


	public function prepare() {
		add_action( "wp_head", array( $this, 'dnsPrefetch' ), 0 );
		add_filter( 'style_loader_tag', array( $this, 'rewriteStyleTag' ), 10, 4 );
		add_filter( 'script_loader_tag', array( $this, 'rewriteScriptTags' ), 10, 3 );
	}


	/**
	 * Prefetching DNS to make it connect/handshake faster
	 * @return void
	 */
	public function dnsPrefetch() {
		if ( strlen( trim( $this->zoneName ) ) > 0 ) {


			echo '<link rel="dns-prefetch" href="https://' . $this->zoneName . '" />';
		}
	}


	/**
	 * Set defer and preload for Styles
	 *
	 * @param $html
	 * @param $handle
	 * @param $href
	 * @param $media
	 *
	 * @return array|mixed|string|string[]|null
	 */
	public function rewriteStyleTag( $html, $handle, $href, $media ) {

		foreach ( $this->assetsToPreload as $i => $preload_key ) {
			if ( strpos( $href, $preload_key ) !== false ) {
				if ( strpos( $html, 'preload' ) == false ) {
					if ( strpos( $html, 'rel=' ) !== false ) {
						// Rel exists, change it
						$html = preg_replace( '/rel\=["|\'](.*?)["|\']/', 'rel="preload" as="style"  onload="this.rel=\'stylesheet\'" defer', $html );
					} else {
						// Rel does not exist, create it
						$html = str_replace( '/>', 'rel="preload" as="style"  onload="this.rel=\'stylesheet\'" defer/>', $html );
					}

				}
			}
		}

		return $html;
	}


	/**
	 * Rewrite Javascript tags and set to CDN
	 * @param $tag
	 * @param $handle
	 * @param $src
	 *
	 * @return mixed
	 */
	public function rewriteScriptTags( $tag, $handle, $src ) {

		if ( $this->isLinkExcluded( $src ) ) {
			return $tag;
		}

		/**
		 * TODO:
		 * check if external is enabled
		 */
		if ( ( $this->settings['external-url'] == '0' || empty( $this->settings['external-url'] ) ) ) {
			if ( ! $this->urlMatchingSiteUrl( $src ) ) {
				// External not enabled
				return $tag;
			}
		} else {
			// External not enabled
			if ( strpos( $src, $this->zoneName ) === false ) {
				if ( strpos( $src, 'http' ) === false ) {
					$src = ltrim( $src, '//' );
					$src = 'https://' . $src;
				}
			}
		}

		if ( strpos( $src, $this->zoneName ) === false ) {
			$src = 'https://' . $this->zoneName . '/' . $this->reformatUrl( $src, true );

			/*
			foreach ( self::$assets_to_defer as $i => $defer_key ) {
				if ( strpos( $tag, $defer_key ) !== false ) {
					$tag = '<script type="text/javascript" src="' . $src . '" defer></script>';
				}
			}*/
		}

		return $tag;
	}


	public function setupHomeUrl() {
		if ( ! is_multisite() ) {
			$this->siteUrl = site_url();
			$this->homeUrl = home_url();
		} else {
			$current_blog_id = get_current_blog_id();
			switch_to_blog( $current_blog_id );

			$this->siteUrl = network_site_url();
			$this->homeUrl = home_url();
		}

		if (strpos( $this->siteUrl,'https://') !== false) {
			// It has ssl!
			$this->siteUrlIsSsl = true;
		} else {
			$this->siteUrlIsSsl = false;
		}
	}


	public function rewriteHtml( $html ) {
		if ( $this->dontRunif() ) {
			return $html;
		}

		$html = $this->rewriteWpUrl( $html );

		return $html;
	}


	public function rewriteWpUrl( $html ) {
		//Prep Site URL
		$escapedSiteURL = quotemeta( $this->homeUrl );
		$regExURL       = '(https?:|)' . substr( $escapedSiteURL, strpos( $escapedSiteURL, '//' ) );

		// search for links
		$regEx = '#(?<=[(\"\'])(?:' . $regExURL . ')?/(?:((?:' . $this->directories_to_include . ')[^\"\')]+)|([^/\"\']+\.[^/\"\')]+))(?=[\"\')])#';
		$html  = preg_replace_callback( $regEx, array( $this, 'cdnRewriteUrl' ), $html );

		return $html;
	}


	public function cdnRewriteUrl( $url ) {
		$width = 1;

		if ( isset( $_GET['wpc_is_amp'] ) && ! empty( $_GET['wpc_is_amp'] ) ) {
			$width = 600;
		}

		$url = $url[0];
		if ( strpos( $url, 'cookie' ) !== false ) {
			return $url;
		}

		$siteUrl = $this->homeUrl;
		$newUrl  = str_replace( $siteUrl, '', $url );

		// Check if site url is staging url? Anything after .com/something?
		preg_match( '/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]\/([a-zA-Z0-9]+)/', $siteUrl, $isStaging );

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'isstaging' ) {
			return print_r( array( $isStaging, $siteUrl ), true );
		}

		// TODO: This is required for STAGING TO WORK!!! Don't remove SiteURL!!! LOOK for next TODO!!!
		$originalUrl = $url;
		$newSrcSet   = '';

		preg_match_all( '/((https?\:\/\/|\/\/)[^\s]+\S+\.(jpg|jpeg|png|gif|svg|webp))\s(\d{1,5}+[wx])/', $url, $srcset_links );

		/**
		 * We are searching for srcset first, if array is not empty srcset exists
		 */
		if ( ! empty( $srcset_links[0] ) ) {

			$debug = array();
			foreach ( $srcset_links[0] as $i => $srcset ) {
				$src          = explode( ' ', $srcset );
				$srcset_url   = $src[0];
				$srcset_width = $src[1];

				if ( $this->isLinkExcluded( $srcset_url ) || $this->isExcluded( $srcset_url, $srcset_url ) ) {
					$newSrcSet .= $srcset_url . ' ' . $srcset_width . ',';
				} else {

					if ( strpos( $srcset_width, 'x' ) !== false ) {
						$width_url    = 1;
						$srcset_width = str_replace( 'x', '', $srcset_width );
						$extension    = 'x';
					} else {
						$width_url = $srcset_width = str_replace( 'w', '', $srcset_width );
						$extension = 'w';
					}

					if ( strpos( $srcset_url, $this->zoneName ) !== false ) {
						$newSrcSet .= $srcset_url . ' ' . $srcset_width . $extension . ',';
						continue;
					}

					$newSrcSet .= 'https://' . $this->zoneName . '/' . $this->reformatUrl( $url, true ) . ' ' . $srcset_width . $extension . ',';
				}
			}

			$newSrcSet = rtrim( $newSrcSet, ',' );

			return $newSrcSet;
		} else {

			if ( strpos( $url, $this->zoneName ) !== false ) {
				return $url;
			}

			if ( $this->isLinkExcluded( $url ) ) {
				return $url;
			}

			// External is disabled?
			if ( empty( $this->settings['external-url'] ) || $this->settings['external-url'] == '0' ) {
				if ( ! $this->urlMatchingSiteUrl( $url ) ) {
					return $url;
				}
			}

			if ( ! empty( $url ) ) {
				$newUrl = 'https://' . $this->zoneName . '/' . $this->reformatUrl( $url, true );

				if ( $this->isExcluded( $url, $url ) ) {
					return $originalUrl;
				}

				// TODO: This is required for STAGING TO WORK!!! Don't remove SiteURL!!! LOOK for next TODO!!!
				if ( $this->isMultisite ) {
					return $newUrl;
				} else if ( empty( $isStaging ) || empty( $isStaging[0] ) ) {
					// Not a staging site
					return $newUrl;
				} else {
					// It's a staging site
					return $originalUrl;
				}
			}

			return $url;
		}
	}


	/**
	 * Format site URL for replacing
	 *
	 * @param $url
	 * @param $remove_site_url
	 *
	 * @return mixed|string
	 */
	public function reformatUrl( $url, $remove_site_url = false ) {

		// Check if url is maybe a relative URL (no http or https)
		if ( strpos( $url, 'http' ) === false ) {
			// Check if url is maybe absolute but without http/s
			if ( strpos( $url, '//' ) === 0 ) {
				// Just needs http/s
				$url = 'https:' . $url;
			} else {
				$url         = str_replace( '../wp-content', 'wp-content', $url );
				$url_replace = str_replace( '/wp-content', 'wp-content', $url );
				$url         = $this->siteUrl;
				$url         = rtrim( $url, '/' );
				$url         .= '/' . $url_replace;
			}
		}

		$formatted_url = $url;

		if ( strpos( $formatted_url, '?brizy_media' ) === false ) {
			$formatted_url = explode( '?', $formatted_url );
			$formatted_url = $formatted_url[0];
		}

		if ( $remove_site_url ) {

			if ($this->siteUrlIsSsl) {
				// site url is ssl
				$formatted_url = str_replace('http://', 'https://', $formatted_url);
			}

			$formatted_url = str_replace($this->siteUrl, '', $formatted_url );
			$formatted_url = str_replace( addcslashes( $this->siteUrl, '/' ), '', $formatted_url );
			$formatted_url = ltrim( $formatted_url, '\/' );
			$formatted_url = ltrim( $formatted_url, '/' );
		}

		/**
		 * If the file is a CSS/JS add query variable for cache clearing
		 */
		if ( strpos( $formatted_url, '.css' ) !== false || strpos( $formatted_url, '.js' ) !== false ) {
			$formatted_url .= '?icv=' . WPS_IC_HASH;
		}

		return $formatted_url;
	}


	/**
	 * Is link matching the site url?
	 *
	 * @param $image
	 *
	 * @return bool
	 */
	public function urlMatchingSiteUrl( $image ) {
		$image    = str_replace( array( 'https://', 'http://' ), '', $image );
		$site_url = str_replace( array( 'https://', 'http://' ), '', $this->siteUrl );

		if ( strpos( $image, '.css' ) !== false || strpos( $image, '.js' ) !== false ) {
			foreach ( $this->defaultExcludedList as $i => $excluded_string ) {
				if ( strpos( $image, $excluded_string ) !== false ) {
					return false;
				}
			}
		}

		if ( strpos( $image, $site_url ) === false ) {
			// Image not on site
			return false;
		} else {
			// Image on site
			return true;
		}

	}


	/**
	 * Check if the file is actually,an image - just by extension
	 *
	 * @param $image
	 *
	 * @return bool
	 */
	public function is_image( $image ) {

		foreach ( $this->allowedFileExtensions as $key => $extension ) {
			if ( strpos( $image, '.' . $extension ) !== false ) {
				// It's an image from allowed list
				return true;
			}
		}

		return false;

		/*
				if ( strpos( $image, '.webp' ) === false && strpos( $image, '.jpg' ) === false && strpos( $image, '.jpeg' ) === false && strpos( $image, '.png' ) === false && strpos( $image, '.ico' ) === false && strpos( $image, '.svg' ) === false && strpos( $image, '.gif' ) === false ) {
					return false;
				} else {
					return true;
				}
		*/
	}


	/**
	 * Check if the image src is excluded
	 *
	 * @param $image_element
	 * @param $image_link
	 *
	 * @return bool
	 */
	public function isExcluded( $image_element, $image_link = '' ) {
		$image_path = '';

		if ( empty( $image_link ) ) {
			preg_match( '@src="([^"]+)"@', $image_element, $match_url );
			if ( ! empty( $match_url ) ) {
				$image_path        = $match_url[1];
				$basename_original = basename( $match_url[1] );
			} else {
				$basename_original = basename( $image_element );
			}
		} else {
			$image_path        = $image_link;
			$basename_original = basename( $image_link );
		}

		preg_match( "/([0-9]+)x([0-9]+)\.[a-zA-Z0-9]+/", $basename_original, $matches ); //the filename suffix way
		if ( empty( $matches ) ) {
			// Full Image
			$basename = $basename_original;
		} else {
			// Some thumbnail
			$basename = str_replace( '-' . $matches[1] . 'x' . $matches[2], '', $basename_original );
		}

		/**
		 * Is this image lazy excluded?
		 */
		if ( ! empty( $this->lazyExcludedList ) && ( ! empty( $this->settings['lazy'] ) && $this->settings['lazy'] == '1' ) ) {
			//return 'asd';
			foreach ( $this->lazyExcludedList as $i => $lazy_excluded ) {
				if ( strpos( $basename, $lazy_excluded ) !== false ) {
					return true;
				}
			}
		} else if ( ! empty( $this->excludedList ) ) {
			foreach ( $this->excludedList as $i => $excluded ) {
				if ( strpos( $basename, $excluded ) !== false ) {
					return true;
				}
			}
		}

		#$basename = $basename;
		if ( ! empty( $this->lazyExcludedList ) && in_array( $basename, $this->lazyExcludedList ) ) {
			return true;
		}

		if ( ! empty( $this->excludedList ) && in_array( $basename, $this->excludedList ) ) {
			return true;
		}

		return false;
	}


	/**
	 * Check if the link is excluded
	 *
	 * @param $link
	 *
	 * @return bool
	 */
	public function isLinkExcluded( $link ) {
		/**
		 * Is the link in excluded list?
		 */
		if ( empty( $link ) ) {
			return false;
		}

		if ( strpos( $link, '.css' ) !== false || strpos( $link, '.js' ) !== false ) {
			foreach ( $this->defaultExcludedList as $i => $excluded_string ) {
				if ( strpos( $link, $excluded_string ) !== false ) {
					return true;
				}
			}
		}

		if ( ! empty( $this->excludedList ) ) {
			foreach ( $this->excludedList as $i => $value ) {
				if ( strpos( $link, $value ) !== false ) {
					// Link is excluded
					return true;
				}
			}
		}

		return false;
	}


	public function dontRunif() {
		global $post;

		if ( ! empty( $_GET['dbg'] ) && $_GET['dbg'] == 'dontRunif' ) {
			var_dump( $_GET );
			die();
		}

		if ( is_admin() || is_feed() || $this->isAjax || ( ! empty( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) || wp_is_json_request() ) {
			return true;
		}

		if ( strpos( $_SERVER['REQUEST_URI'], 'xmlrpc' ) !== false || strpos( $_SERVER['REQUEST_URI'], 'wp-json' ) !== false ) {
			return true;
		}

		if ( isset( $_GET['brizy-edit-iframe'] ) || isset( $_GET['brizy-edit'] ) || isset( $_GET['preview'] ) ) {
			return true;
		}

		if ( isset( $post->post_type ) && strpos( $post->post_type, 'wfocu' ) !== false ) {
			return true;
		}

		if ( ! empty( $_GET['trp-edit-translation'] ) || ! empty( $_GET['fb-edit'] ) || ! empty( $_GET['bricks'] ) || ! empty( $_GET['elementor-preview'] ) || ! empty( $_GET['PageSpeed'] ) || ! empty( $_GET['fl_builder'] ) || ! empty( $_GET['et_fb'] ) || ! empty( $_GET['tatsu'] ) || ! empty( $_GET['tatsu-header'] ) || ! empty( $_GET['tatsu-footer'] ) || ! empty( $_GET['tve'] ) || ! empty( $_GET['ct_builder'] ) || ( ! empty( $_SERVER['SCRIPT_URL'] ) && $_SERVER['SCRIPT_URL'] == "/wp-admin/customize.php" ) || ( ! empty( $_GET['page'] ) && $_GET['page'] == 'livecomposer_editor' ) ) {
			return true;
		}

		return false;
	}


	public function templateRedirect() {
		if ( empty( $_GET['ignore_ic'] ) ) {
			ob_start( array( $this, 'rewriteHtml' ) );
		}
	}


	public function run() {
		add_action( 'template_redirect', array( $this, 'templateRedirect' ) );
	}

}