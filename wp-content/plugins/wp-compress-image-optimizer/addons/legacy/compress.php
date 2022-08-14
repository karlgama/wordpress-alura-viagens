<?php
/**
 * Local Compress
 * @since 5.00.59
 */


class wps_local_compress {

	private static $allowed_types;
	private static $apiURL;
	private static $apiParams;
	private static $settings;
	private static $options;
	private static $zone_name;
	private static $backup_directory;
	public $webp_sizes;
	public $sizes;
	public $total_sizes;
	public $compressed_list;


	public function __construct() {
		global $wps_ic;
		global $wpc_filesystem;

		$this->get_filesystem();

		$this->total_sizes = count( get_intermediate_image_sizes() );
		$this->sizes       = array();
		$this->webp_sizes  = get_intermediate_image_sizes();
		$uploads_dir       = wp_upload_dir();

		self::$allowed_types    = array( 'jpg' => 'jpg', 'jpeg' => 'jpeg', 'gif' => 'gif', 'png' => 'png' );
		self::$backup_directory = $uploads_dir['basedir'] . '/wp-compress-backups';
		self::$settings         = get_option( WPS_IC_SETTINGS );
		self::$options          = get_option( WPS_IC_OPTIONS );

		/**
		 * If backup directories don't exist, create them
		 */
		if ( ! file_exists( self::$backup_directory ) ) {
			$made_dir = mkdir( self::$backup_directory, 0755 );
			if ( ! $made_dir ) {
				update_option( 'wpc_errors', array( 'unable-to-create-backup-dir' => self::$backup_directory ) );
			} else {
				delete_option( 'wpc_errors' );
			}
		}

		add_action( 'delete_attachment', array( $this, 'on_delete' ) );

		if ( ! empty( self::$settings['on-upload'] ) && self::$settings['on-upload'] == '1' ) {
			/*
			 * This works but uploads a full sized image to storage for every size variation
			 */

			add_filter( 'wp_update_attachment_metadata', array( $this, 'on_upload' ), PHP_INT_MAX, 2 );
			add_filter( 'wp_generate_attachment_metadata', array( $this, 'on_upload_gutenber' ), 15, 2 );

			#add_action( 'rest_insert_attachment', array( $this, 'on_upload_rest' ));
			#add_action( 'rest_after_insert_attachment', array( $this, 'on_upload_rest' ));
			#add_filter( 'intermediate_image_sizes_advanced', array( $this, 'on_upload_thumbs' ), PHP_INT_MAX, 3 );
			#add_action('add_attachment', array($this, 'on_upload_attachment'), PHP_INT_MAX, 1);
		}

		if ( empty( self::$settings['cname'] ) || ! self::$settings['cname'] ) {
			self::$zone_name = get_option( 'ic_cdn_zone_name' );
		} else {
			self::$zone_name = get_option( 'ic_custom_cname' );
		}

		$location = get_option( 'wps_ic_geo_locate' );
		if ( empty( $location ) ) {
			$location = $this->geoLocate();
		}

		if ( is_object( $location ) ) {
			$location = (array) $location;
		}

		$options = get_option( WPS_IC_OPTIONS );
		$apikey  = $options['api_key'];

		if ( isset( $location ) && ! empty( $location ) ) {
			if ( is_array( $location ) && ! empty( $location['server'] ) ) {
				if ( $location['country'] == 'AS' ) {
					self::$apiURL = 'https://singapore.zapwp.net/local/';
				} else if ( $location['country'] == 'EU' ) {
					self::$apiURL = 'https://frankfurt.zapwp.net/local/';
				} else if ( $location['country'] == 'OC' ) {
					self::$apiURL = 'https://sydney.zapwp.net/local/';
				} else if ( $location['country'] == 'US' ) {
					self::$apiURL = 'https://us.zapwp.net/local/';
				} else {
					self::$apiURL = 'https://frankfurt.zapwp.net/local/';
				}
			} else {
				self::$apiURL = 'https://' . $location->server . '/local/';
			}
		} else {
			self::$apiURL = 'https://frankfurt.zapwp.net/local/';
		}

		// Setup paraams for POST to API
		self::$apiParams            = array();
		self::$apiParams['apikey']  = $apikey;
		self::$apiParams['quality'] = self::$settings['optimization'];
		self::$apiParams['retina']  = 'false';
		self::$apiParams['webp']    = 'false';
		self::$apiParams['width']   = 'false';
		self::$apiParams['url']     = '';
	}


	public function geoLocate() {
		$force_location = get_option( 'wpc-ic-force-location' );
		if ( ! empty( $force_location ) ) {
			return $force_location;
		}

		$call = wp_remote_get( 'https://cdn.zapwp.net/?action=geo_locate&domain=' . urlencode( site_url() ), array( 'timeout' => 30, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0' ) );
		if ( wp_remote_retrieve_response_code( $call ) == 200 ) {
			$body = wp_remote_retrieve_body( $call );
			$body = json_decode( $body );

			if ( $body->success ) {
				update_option( 'wps_ic_geo_locate', $body->data );

				return $body->data;
			} else {
				update_option( 'wps_ic_geo_locate', array( 'country' => 'EU', 'server' => 'frankfurt.zapwp.net' ) );

				return array( 'country' => 'EU', 'server' => 'frankfurt.zapwp.net' );
			}

		} else {
			update_option( 'wps_ic_geo_locate', array( 'country' => 'EU', 'server' => 'frankfurt.zapwp.net' ) );

			return array( 'country' => 'EU', 'server' => 'frankfurt.zapwp.net' );
		}
	}


	public function on_upload_attachment( $post_ID ) {
		global $wpc_filesystem;
		wp_raise_memory_limit( 'image' );

		$image         = wp_get_attachment_image_src( $post_ID, 'full' );
		$file_path     = get_attached_file( $post_ID );
		$file_basename = basename( $image[0] );

		// Figure out image type
		$exif = exif_imagetype( $file_path );
		$mime = image_type_to_mime_type( $exif );

		// Fetch the image content
		$file_content = $wpc_filesystem->get_contents( $file_path );

		$post_fields = array(
			'filename' => $file_basename,
			'apikey'   => self::$apiParams['apikey'],
			'key'      => self::$apiParams['apikey'],
			'image'    => $image[0],
			'url'      => $image[0],
			'exif'     => $exif,
			'mime'     => $mime,
			'content'  => base64_encode( $file_content ),
			'quality'  => self::$apiParams['quality'],
			'retina'   => self::$apiParams['retina'],
			'webp'     => self::$apiParams['webp']
		);

		$tmp_location      = $file_path . '_tmp';
		$file_location     = $file_path;
		$original_filesize = filesize( $file_path );
		$response          = wp_remote_post( self::$apiURL, array(
			'timeout'   => 300,
			'method'    => 'POST',
			'sslverify' => false,
			'body'      => $post_fields
		) );

		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			$body = wp_remote_retrieve_body( $response );
			clearstatcache();
			if ( ! empty( $body ) ) {
				file_put_contents( $tmp_location, $body );
				unset( $body );

				// Check if compressed image is smaller than original image from backup
				$compressed_filesize = filesize( $tmp_location );
				if ( $compressed_filesize >= $original_filesize ) {
					// Do Nothing
				} else {
					unlink( $file_location );
					copy( $tmp_location, $file_location );
					unlink( $tmp_location );

					$stats['full']['original']['size']   = $original_filesize;
					$stats['full']['compressed']['size'] = $compressed_filesize;

					update_post_meta( $post_ID, 'ic_status', 'compressed' );
					update_post_meta( $post_ID, 'ic_stats', $stats );
				}

			}

		}
	}


	public function on_delete( $post_id ) {
		$compressed_images = get_post_meta( $post_id, 'ic_compressed_images', true );
		foreach ( $compressed_images as $index => $path ) {
			if ( strpos( $index, 'webp' ) !== false ) {
				if ( file_exists( $path ) ) {
					unlink( $path );
				}
			}
		}
	}


	public function on_upload_rest( $attachment, $request, $creating ) {
		global $wpc_filesystem;
		die( 'x' );

		wp_raise_memory_limit( 'image' );

		// Check for parameter on request that tells us not to create an attachment post.
		#if ( $request->get_param( 'flag' ) == true ) {
		$attachment_id = $attachment->ID;

		$this->sizes = array( 'full' );
		$this->backup_image( $attachment_id );
		#$this->compress_image($attachment_id, false, self::$settings['retina'], self::$settings['webp'], false);

		$image         = wp_get_attachment_image_src( $attachment_id, 'full' );
		$file_path     = get_attached_file( $attachment_id );
		$file_basename = basename( $image[0] );

		// Figure out image type
		$exif = exif_imagetype( $file_path );
		$mime = image_type_to_mime_type( $exif );

		// Fetch the image content
		$file_content = $wpc_filesystem->get_contents( $file_path );

		$post_fields = array(
			'filename'     => $file_basename,
			'apikey'       => self::$apiParams['apikey'],
			'key'          => self::$apiParams['apikey'],
			'image'        => $image[0],
			'url'          => $image[0],
			'exif'         => $exif,
			'mime'         => $mime,
			'content'      => base64_encode( $file_content ),
			'quality'      => self::$apiParams['quality'],
			'retina'       => self::$apiParams['retina'],
			'webp'         => self::$apiParams['webp'],
			'count_thumbs' => $this->total_sizes
		);

		$tmp_location      = $file_path . '_tmp';
		$file_location     = $file_path;
		$original_filesize = filesize( $file_path );
		$response          = wp_remote_post( self::$apiURL, array(
			'timeout'   => 300,
			'method'    => 'POST',
			'sslverify' => false,
			'body'      => $post_fields
		) );

		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			$body = wp_remote_retrieve_body( $response );
			clearstatcache();
			#fwrite($fopen, 'Inside IF 200: ' . $tmp_location . "\r\n");
			if ( ! empty( $body ) ) {
				file_put_contents( $tmp_location, $body );
				unset( $body );

				#fwrite($fopen, 'Inside not empty body: ' . $tmp_location . "\r\n");

				// Check if compressed image is smaller than original image from backup
				$compressed_filesize = filesize( $tmp_location );

				#fwrite($fopen, 'Size Original: ' . $original_filesize . "\r\n");
				#fwrite($fopen, 'Size Compressed: ' . $compressed_filesize . "\r\n");

				if ( $compressed_filesize >= $original_filesize ) {
					// Do Nothing
				} else {
					unlink( $file_location );
					copy( $tmp_location, $file_location );
					unlink( $tmp_location );

					$stats['full']['original']['size']   = $original_filesize;
					$stats['full']['compressed']['size'] = $compressed_filesize;

					update_post_meta( $attachment_id, 'ic_status', 'compressed' );
					update_post_meta( $attachment_id, 'ic_stats', $stats );
				}

			}

		}

		update_post_meta( $attachment_id, 'is_uploaded', 'true' );

		// Kill the request and send a 201 response.
		wp_die( '', '', 201 );
		#}
	}


	public function on_upload_gutenber( $meta, $id ) {
		global $wpc_filesystem;

		$attachment_id = $id;
		wp_raise_memory_limit( 'image' );

		$this->sizes = array( 'full' );
		$this->backup_image( $attachment_id );

		$image         = wp_get_attachment_image_src( $attachment_id, 'full' );
		$file_path     = get_attached_file( $attachment_id );
		$file_basename = basename( $image[0] );

		// Figure out image type
		$exif = exif_imagetype( $file_path );
		$mime = image_type_to_mime_type( $exif );

		// Fetch the image content
		$file_content = $wpc_filesystem->get_contents( $file_path );

		$post_fields = array(
			'filename'     => $file_basename,
			'apikey'       => self::$apiParams['apikey'],
			'key'          => self::$apiParams['apikey'],
			'image'        => $image[0],
			'url'          => $image[0],
			'exif'         => $exif,
			'mime'         => $mime,
			'content'      => base64_encode( $file_content ),
			'quality'      => self::$apiParams['quality'],
			'retina'       => self::$apiParams['retina'],
			'webp'         => self::$apiParams['webp'],
			'count_thumbs' => $this->total_sizes
		);

		$tmp_location      = $file_path . '_tmp';
		$file_location     = $file_path;
		$original_filesize = filesize( $file_path );
		$response          = wp_remote_post( self::$apiURL, array(
			'timeout'   => 300,
			'method'    => 'POST',
			'sslverify' => false,
			'body'      => $post_fields
		) );

		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			$body = wp_remote_retrieve_body( $response );
			clearstatcache();

			if ( ! empty( $body ) ) {
				file_put_contents( $tmp_location, $body );
				unset( $body );

				// Check if compressed image is smaller than original image from backup
				$compressed_filesize = filesize( $tmp_location );

				if ( $compressed_filesize >= $original_filesize ) {
					// Do Nothing
				} else {
					unlink( $file_location );
					copy( $tmp_location, $file_location );
					unlink( $tmp_location );

					$stats['full']['original']['size']   = $original_filesize;
					$stats['full']['compressed']['size'] = $compressed_filesize;

					update_post_meta( $attachment_id, 'ic_status', 'compressed' );
					update_post_meta( $attachment_id, 'ic_stats', $stats );
				}

			}

		}

		update_post_meta( $attachment_id, 'is_uploaded', 'true' );

		return $meta;
	}


	public function on_upload( $data, $attachment_id ) {
		global $wpc_filesystem;

		wp_raise_memory_limit( 'image' );

		if ( count( $data['sizes'] ) >= $this->total_sizes ) {
			$this->generate_webp( $attachment_id, 'on_upload' );

			return $data;
		}

		if ( get_post_meta( $attachment_id, 'is_uploaded', true ) == 'true' ) {
			return $data;
		}

		if ( ! $this->is_supported( $attachment_id ) ) {
			return $data;
		}

		$this->sizes = array( 'full' );
		$this->backup_image( $attachment_id );
		#$this->compress_image($attachment_id, false, self::$settings['retina'], self::$settings['webp'], false);

		$image         = wp_get_attachment_image_src( $attachment_id, 'full' );
		$file_path     = get_attached_file( $attachment_id );
		$file_basename = basename( $image[0] );

		// Figure out image type
		$exif = exif_imagetype( $file_path );
		$mime = image_type_to_mime_type( $exif );

		// Fetch the image content
		$file_content = $wpc_filesystem->get_contents( $file_path );

		$post_fields = array(
			'filename'     => $file_basename,
			'apikey'       => self::$apiParams['apikey'],
			'key'          => self::$apiParams['apikey'],
			'image'        => $image[0],
			'url'          => $image[0],
			'exif'         => $exif,
			'mime'         => $mime,
			'content'      => base64_encode( $file_content ),
			'quality'      => self::$apiParams['quality'],
			'retina'       => self::$apiParams['retina'],
			'webp'         => self::$apiParams['webp'],
			'count_thumbs' => $this->total_sizes
		);

		$tmp_location      = $file_path . '_tmp';
		$file_location     = $file_path;
		$original_filesize = filesize( $file_path );
		$response          = wp_remote_post( self::$apiURL, array(
			'timeout'   => 300,
			'method'    => 'POST',
			'sslverify' => false,
			'body'      => $post_fields
		) );

		#$fopen = fopen(WPS_IC_DIR . '/' . $attachment_id . '.txt', 'w+');
		if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
			$body = wp_remote_retrieve_body( $response );
			clearstatcache();
			#fwrite($fopen, 'Inside IF 200: ' . $tmp_location . "\r\n");
			if ( ! empty( $body ) ) {
				file_put_contents( $tmp_location, $body );
				unset( $body );

				#fwrite($fopen, 'Inside not empty body: ' . $tmp_location . "\r\n");

				// Check if compressed image is smaller than original image from backup
				$compressed_filesize = filesize( $tmp_location );

				#fwrite($fopen, 'Size Original: ' . $original_filesize . "\r\n");
				#fwrite($fopen, 'Size Compressed: ' . $compressed_filesize . "\r\n");

				if ( $compressed_filesize >= $original_filesize ) {
					// Do Nothing
				} else {
					unlink( $file_location );
					copy( $tmp_location, $file_location );
					unlink( $tmp_location );

					$stats['full']['original']['size']   = $original_filesize;
					$stats['full']['compressed']['size'] = $compressed_filesize;

					update_post_meta( $attachment_id, 'ic_status', 'compressed' );
					update_post_meta( $attachment_id, 'ic_stats', $stats );
				}

			}

		}

		#fclose($fopen);

		update_post_meta( $attachment_id, 'is_uploaded', 'true' );

		#$metadata_new = $this->regenerate_thumbnails( $attachment_id );

		// Compress Retina?
		/*
		if ( self::$options['retina'] == '1' ) {
			$return                = $this->generate_retina( $attachment_id );
			$stats                 = array_merge( $stats, $return['stats'] );
			$this->compressed_list = array_merge( $this->compressed_list, $return['compressed'] );
		}*/

		#if ( self::$options['generate_webp'] == '1' ) {
		#$return                = $this->generate_webp( $attachment_id, 'on_upload' );
		#$stats                 = array_merge( $stats, $return['stats'] );
		#$this->compressed_list = array_merge( $this->compressed_list, $return['compressed'] );
		#}

		if ( ! empty( $metadata_new ) ) {
			return $metadata_new;
		}

		// Remove corrupted meta
		#delete_post_meta( $attachment_id, '_wp_attachment_metadata' );

		// Generate new attachment meta
		#$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path);

		return $data;
	}


	public function on_upload_thumbs( $new_sizes, $image_meta, $attachment_id ) {
		set_transient( 'wps_ic_compress_' . $attachment_id, array( 'imageID' => $attachment_id, 'status' => 'compressing' ), 0 );
		#$this->backup_image( $attachment_id );
		#$this->compress_thumbs($attachment_id, false, self::$settings['retina'], self::$settings['webp'], true);
		$this->generate_webp( $attachment_id, 'on_upload' );
		set_transient( 'wps_ic_compress_' . $attachment_id, array( 'imageID' => $attachment_id, 'status' => 'compressed' ), 0 );

		return $new_sizes;
	}


	public function restore( $imageID ) {
		delete_post_meta( $imageID, 'ic_debug' );
		$backup_images = get_post_meta( $imageID, 'ic_backup_images', true );

		if ( ! empty( $backup_images ) && is_array( $backup_images ) ) {
			$compressed_images = get_post_meta( $imageID, 'ic_compressed_images', true );

			// Remove Generated Images
			if ( ! empty( $compressed_images ) ) {

				foreach ( $compressed_images as $index => $path ) {
					if ( strpos( $index, 'webp' ) !== false ) {
						if ( file_exists( $path ) ) {
							unlink( $path );
						}
					}
				}

			}

			$upload_dir = wp_get_upload_dir();
			$sizes      = get_intermediate_image_sizes();
			foreach ( $sizes as $i => $size ) {
                clearstatcache();
                $image = image_get_intermediate_size($imageID, $size);
                if ( $image['path'] ) {
                    $path = $upload_dir['basedir'] . '/' . $image['path'];
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
			}

			$path_to_image = get_attached_file( $imageID );

			// Restore only full
			$restore_image_path = $backup_images['full'];

			// If backup file exists
			if ( file_exists( $restore_image_path ) ) {
				unlink( $path_to_image );

				// Restore from local backups
				$copy = copy( $restore_image_path, $path_to_image );

				// Delete the backup
				unlink( $restore_image_path );
			}

			clearstatcache();
			/*
						$new_metadata = wp_generate_attachment_metadata($imageID, $path_to_image);
						if (is_wp_error($new_metadata) || empty($new_metadata)) {
						}
						else {
							$result['update'] = wp_update_attachment_metadata($imageID, $new_metadata);
							$metadata         = wp_get_attachment_metadata($imageID);
						}*/

			wp_update_attachment_metadata( $imageID, wp_generate_attachment_metadata( $imageID, $path_to_image ) );

			// Remove meta tags
			delete_post_meta( $imageID, 'ic_stats' );
			delete_post_meta( $imageID, 'ic_compressed_images' );
			delete_post_meta( $imageID, 'ic_compressed_thumbs' );
			delete_post_meta( $imageID, 'ic_backup_images' );
			update_post_meta( $imageID, 'ic_status', 'restored' );

			return true;
		} else {
			return false;
		}
	}


	public function backup_image( $imageID ) {
		wp_raise_memory_limit( 'image' );

		// Image Backup Exists
		if ( $this->backup_exists( $imageID ) ) {
			return true;
		}

		// Setup Image Stats
		$stats       = array();
		$backup_list = array();

		// Create backup directory
		$this->create_backup_directory();

		// Add full image size to the list
		$this->sizes[] = 'full';

		// Get filename
		$image              = wp_get_attachment_image_src( $imageID, 'full' );
		$image_url          = $image[0];
		$parsed_url         = parse_url( $image_url );
		$parsed_url['path'] = ltrim( $parsed_url['path'], '/' );
		$filename           = basename( $parsed_url['path'] );
		$backup_folders     = str_replace( $filename, '', $parsed_url['path'] );
		$backup_folders     = rtrim( $backup_folders, '/' );
		$backup_folders     = explode( '/', $backup_folders );

		$backup_dir = self::$backup_directory;
		if ( is_array( $backup_folders ) ) {
			foreach ( $backup_folders as $i => $folder ) {
				$backup_dir .= '/' . $folder;
				if ( ! file_exists( $backup_dir ) ) {
					$made_dir = mkdir( $backup_dir, 0755 );
				}
			}
		}

		foreach ( $this->sizes as $i => $size ) {

			if ( $size == 'full' ) {
				$image     = wp_get_attachment_image_src( $imageID, $size );
				$image_url = $image[0];
			} else {
				$image     = image_get_intermediate_size( $imageID, $size );
				$image_url = $image['url'];
			}

			if ( empty( $image ) || empty( $image_url ) ) {
				continue;
			}

			// Parse Image url and fetch it's Path
			$parsed_url         = parse_url( $image_url );
			$parsed_url['path'] = ltrim( $parsed_url['path'], '/' );

			// Define original / backup file paths
			$original_file_location = ABSPATH . $parsed_url['path'];

			// Where is backup saved?
			$backup_file_location = $backup_dir . '/' . $filename;

			// Stats
			#$stats[ $filename ]['original']['size'] = filesize($original_file_location);
			$stats[ $size ]['original']['size'] = filesize( $original_file_location );

			if ( $size == 'full' ) {
				copy( $original_file_location, $backup_file_location );
			}

			$backup_list[ $size ] = $backup_file_location;

			if ( ! file_exists( $backup_file_location ) ) {
				// TODO: What then
				//wp_send_json_error('failed-to-create-backup');
			}

		}

		update_post_meta( $imageID, 'ic_stats', $stats );
		update_post_meta( $imageID, 'ic_backup_images', $backup_list );
		update_post_meta( $imageID, 'ic_original_stats', $stats );
	}


	public function backup_exists( $imageID ) {
		$backup_exists = get_post_meta( $imageID, 'ic_backup_images', true );
		if ( ! empty( $backup_exists ) && is_array( $backup_exists ) ) {

			foreach ( $backup_exists as $filename => $backup_location ) {

				if ( ! empty( $backup_location ) ) {

					// If backup file exists
					if ( file_exists( $backup_location ) ) {
						return $backup_location;
					} else {
						return false;
					}
				}

			}

			return true;
		} else {
			return false;
		}
	}


	public function create_backup_directory() {
		if ( ! file_exists( self::$backup_directory ) ) {
			mkdir( self::$backup_directory, 0755 );
		}
	}


	public function parse_path( $file_path ) {
		$path_exploded = explode( '/', $file_path );

		return $path_exploded;
	}


	public function get_stats( $imageID ) {
		$stats = get_post_meta( $imageID, 'ic_stats', true );

		return $stats;
	}


	public function is_original_better( $imageID, $size, $im, $stats, $extension ) {
		$original_filesize = $stats[ $size ]['original']['size'];
		$file_location     = tmpfile();

		switch ( $extension ) {
			case 'jpg':
				imagejpeg( $im, $file_location );
				break;
			case 'jpeg':
				imagejpeg( $im, $file_location );
				break;
			case 'png':
				imagepng( $im, $file_location );
				break;
			case 'gif':
				imagegif( $im, $file_location );
				break;
		}

	}


	public function debug_msg( $attachmentID, $mesage ) {
		if ( defined( 'WPS_IC_DEBUG' ) && WPS_IC_DEBUG == 'true' ) {
			$debug_log = get_post_meta( $attachmentID, 'ic_debug', true );
			if ( ! $debug_log ) {
				$debug_log = array();
			}
			$debug_log[] = $mesage;
			update_post_meta( $attachmentID, 'ic_debug', $debug_log );
		}
	}


	public function compress_thumbs( $imageID, $bulk = true, $retina = true, $webp = true, $just_thumbs = false ) {

		// Is the image type supported
		if ( ! $this->is_supported( $imageID ) ) {
			return true;
		}

		// Is the image already Compressed
		if ( $this->is_already_compressed( $imageID ) && ! $just_thumbs ) {
			return true;
		}

		$bulkStats = get_option( 'wps_ic_bulk_stats' );

		$backup_images = get_post_meta( $imageID, 'ic_backup_images', true );
		$stats         = get_post_meta( $imageID, 'ic_stats', true );

		if ( empty( $stats ) || ! $stats ) {
			$stats = array();
		}

		if ( empty( $bulkStats ) || ! $bulk ) {
			$bulkStats                                = array();
			$bulkStats['images_compressed']           = 0;
			$bulkStats['thumbs_compressed']           = 0;
			$bulkStats['total']                       = array();
			$bulkStats['total']['original']           = 0;
			$bulkStats['total']['compressed']         = 0;
			$bulkStats['total']['thumbs']             = 0;
			$bulkStats['thumbs']['thumbs_compressed'] = 0;
			$bulkStats['thumbs']['original']          = 0;
			$bulkStats['thumbs']['compressed']        = 0;
			$bulkStats['thumbs']['thumbs']            = 0;
		}

		$bulkStats['images_compressed'] = $bulkStats['images_compressed'] + 1;

		foreach ( $this->sizes as $i => $size ) {

			if ( $size == 'full' ) {
				$image     = wp_get_attachment_image_src( $imageID, $size );
				$image_url = $image[0];
				$this->debug_msg( $imageID, 'Full IMG Url: ' . print_r( $image, true ) );
			} else {
				$bulkStats['thumbs_compressed'] = $bulkStats['thumbs_compressed'] + 1;
				$image                          = image_get_intermediate_size( $imageID, $size );
				$image_url                      = $image['url'];
				$this->debug_msg( $imageID, 'IMG Url: ' . print_r( $image, true ) );
			}

			if ( empty( $image_url ) ) {
				continue;
			}

			$parsed_url         = parse_url( $image_url );
			$parsed_url['path'] = ltrim( $parsed_url['path'], '/' );

			$tmp_location                       = ABSPATH . $parsed_url['path'] . '_tmp';
			$file_location                      = ABSPATH . $parsed_url['path'];
			$stats[ $size ]['original']['size'] = filesize( $file_location );

			$apiURL = self::$apiURL . $image_url;
			$this->debug_msg( $imageID, 'API Url: ' . print_r( $apiURL, true ) );

			// Compress Regular Thumbnail
			$call = wp_remote_get( $apiURL, array( 'timeout' => 60, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0' ) );
			if ( wp_remote_retrieve_response_code( $call ) == 200 ) {
				$body = wp_remote_retrieve_body( $call );
				clearstatcache();

				if ( ! empty( $body ) ) {
					file_put_contents( $tmp_location, $body );
					unset( $body );

					// Check if compressed image is smaller than original image from backup
					$original_filesize   = $stats[ $size ]['original']['size'];
					$compressed_filesize = filesize( $tmp_location );

					$this->debug_msg( $imageID, 'Compressed to ' . $compressed_filesize );

					if ( $compressed_filesize >= $original_filesize ) {
						$this->debug_msg( $imageID, 'Compressed bigger than original' );
						if ( file_exists( $backup_images[ $size ] ) ) {
							// Restore from backup
							unlink( $tmp_location );
							// Restore from local backups
							copy( $backup_images[ $size ], $file_location );
							// Reset stats for restored image
							$stats[ $size ]['compressed']['size'] = $original_filesize;
						} else {
							$stats[ $size ]['compressed']['size'] = $original_filesize;
						}
					} else {
						$this->debug_msg( $imageID, 'Compressed smaller than original' );

						unlink( $file_location );
						copy( $tmp_location, $file_location );
						unlink( $tmp_location );
						$stats[ $size ]['compressed']['size'] = $compressed_filesize;
					}

					$bulkStats['total']['original']   += $stats[ $size ]['original']['size'];
					$bulkStats['total']['compressed'] += $stats[ $size ]['compressed']['size'];

					if ( $size !== 'full' ) {
						$bulkStats['thumbs']['original']   += $stats[ $size ]['original']['size'];
						$bulkStats['thumbs']['compressed'] += $stats[ $size ]['compressed']['size'];
					}

					$this->compressed_list[ $size ] = $file_location;
				} else {
					$this->debug_msg( $imageID, 'API Call body Empty' );
				}

			} else {
				$this->debug_msg( $imageID, 'API Call not 200' );
			}

		}

		update_post_meta( $imageID, 'ic_compressed_images', $this->compressed_list );

		// Compress Retina?
		if ( $retina && 1 == 0 ) {
			$return                = $this->generate_retina( $imageID );
			$stats                 = array_merge( $stats, $return['stats'] );
			$this->compressed_list = array_merge( $this->compressed_list, $return['compressed'] );
		}

		if ( $webp && 1 == 0 ) {
			$return                = $this->generate_webp( $imageID );
			$stats                 = array_merge( $stats, $return['stats'] );
			$this->compressed_list = array_merge( $this->compressed_list, $return['compressed'] );
		}

		update_post_meta( $imageID, 'ic_status', 'compressed' );
		update_option( 'wps_ic_bulk_stats', $bulkStats );
		update_post_meta( $imageID, 'ic_stats', $stats );
		update_post_meta( $imageID, 'ic_compressed_images', $this->compressed_list );
	}


	public function regenerate_thumbnails( $imageID ) {
		wp_raise_memory_limit( 'image' );
		$thumbs                 = array();
		$thumbs['total']['old'] = 0;
		$thumbs['total']['new'] = 0;

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
			require_once( ABSPATH . "wp-admin" . '/includes/file.php' );
			require_once( ABSPATH . "wp-admin" . '/includes/media.php' );
		}

		// Get all thumb sizes
		$upload_dir = wp_get_upload_dir();
		$sizes      = get_intermediate_image_sizes();
		foreach ( $sizes as $i => $size ) {
			clearstatcache();
			$image                  = image_get_intermediate_size( $imageID, $size );
			$path                   = $upload_dir['basedir'] . '/' . $image['path'];
			$thumbs[ $size ]['old'] = filesize( $path );
			$thumbs['total']['old'] = $thumbs['total']['old'] + filesize( $path );
		}

		add_filter( 'jpeg_quality', function( $arg ) {
			return 70;
		} );

		// Add generate thumbnail to queue
		$file_data = get_attached_file( $imageID );
		#$attach_data = wp_generate_attachment_metadata($imageID, $file_data);
		#wp_update_attachment_metadata($imageID, $attach_data);

		foreach ( $sizes as $i => $size ) {
			clearstatcache();
			$image                  = image_get_intermediate_size( $imageID, $size );
			$path                   = $upload_dir['basedir'] . '/' . $image['path'];
			$thumbs[ $size ]['new'] = filesize( $path );
			$thumbs['total']['new'] = $thumbs['total']['new'] + filesize( $path );
		}

		update_post_meta( $imageID, 'ic_compressed_thumbs', $thumbs );

		return $attach_data;
	}


	public function get_filesystem() {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
		require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php' );
		global $wpc_filesystem;

		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) );
		}

		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) );
		}

		if ( ! isset( $wpc_filesystem ) || ! is_object( $wpc_filesystem ) ) {
			$wpc_filesystem = new WP_Filesystem_Direct( '' );
		}
	}


	public function compress_image( $imageID, $bulk = true, $retina = true, $webp = true, $just_thumbs = false, $regenerate = true ) {
		global $wpc_filesystem;
		wp_raise_memory_limit( 'image' );

		$bulkStats = get_option( 'wps_ic_bulk_stats' );

		// Is the image type supported
		if ( ! $this->is_supported( $imageID ) ) {

			if ( ! $bulk ) {
				wp_send_json_error( 'file-not-supported' );
			}

			return $bulkStats;
		}

		// Is the image already Compressed
		if ( $this->is_already_compressed( $imageID ) && ! $just_thumbs ) {

			if ( ! $bulk ) {
				wp_send_json_error( 'file-already-compressed' );
			}

			return $bulkStats;
		}

		$metadata      = array();
		$backup_images = get_post_meta( $imageID, 'ic_backup_images', true );
		$stats         = get_post_meta( $imageID, 'ic_stats', true );

		if ( empty( $stats ) || ! $stats ) {
			$stats = array();
		}

		if ( empty( $bulkStats ) || ! $bulk ) {
			$bulkStats                                = array();
			$bulkStats['images_compressed']           = 0;
			$bulkStats['thumbs_compressed']           = 0;
			$bulkStats['total']                       = array();
			$bulkStats['total']['original']           = 0;
			$bulkStats['total']['compressed']         = 0;
			$bulkStats['total']['thumbs']             = 0;
			$bulkStats['thumbs']['thumbs_compressed'] = 0;
			$bulkStats['thumbs']['original']          = 0;
			$bulkStats['thumbs']['compressed']        = 0;
			$bulkStats['thumbs']['thumbs']            = 0;
		}

		$bulkStats['images_compressed'] = $bulkStats['images_compressed'] + 1;

		foreach ( $this->sizes as $i => $size ) {

			if ( $size == 'full' ) {
				$image     = wp_get_attachment_image_src( $imageID, $size );
				$image_url = $image[0];
				$this->debug_msg( $imageID, 'Full IMG Url: ' . print_r( $image, true ) );
				$bulkStats['thumbs_compressed'] = $bulkStats['thumbs_compressed'] + $this->total_sizes + 1;
			} else {
				$bulkStats['thumbs_compressed'] = $bulkStats['thumbs_compressed'] + 1;
				$image                          = image_get_intermediate_size( $imageID, $size );
				$image_url                      = $image['url'];
				$this->debug_msg( $imageID, 'IMG Url: ' . print_r( $image, true ) );
			}

			if ( empty( $image_url ) ) {
				continue;
			}

			/**
			 * Figure out the actual file path
			 */
			$file_path     = get_attached_file( $imageID );
			$file_basename = basename( $image_url );

			// Figure out image type
			$exif = exif_imagetype( $file_path );
			if ( ! $exif ) {
				// Image is NOT valid
				continue;
			}

			$mime = image_type_to_mime_type( $exif );

			// Fetch the image content
			$file_content = $wpc_filesystem->get_contents( $file_path );

			$post_fields = array(
				'filename'     => $file_basename,
				'apikey'       => self::$apiParams['apikey'],
				'key'          => self::$apiParams['apikey'],
				'image'        => $image_url,
				'url'          => $image_url,
				'exif'         => $exif,
				'mime'         => $mime,
				'content'      => base64_encode( $file_content ),
				'quality'      => self::$apiParams['quality'],
				'retina'       => self::$apiParams['retina'],
				'webp'         => self::$apiParams['webp'],
				'count_thumbs' => $this->total_sizes,
			);

			$tmp_location                       = $file_path . '_tmp';
			$file_location                      = $file_path;
			$stats[ $size ]['original']['size'] = filesize( $file_location );

			$response = wp_remote_post( self::$apiURL, array(
				'timeout'   => 300,
				'method'    => 'POST',
				'sslverify' => false,
				'body'      => $post_fields
			) );

			#$body = wp_remote_retrieve_body( $response );
			#var_dump($body);
			#die();

			// Compress Regular Thumbnail
			if ( wp_remote_retrieve_response_code( $response ) == 200 ) {
				$body = wp_remote_retrieve_body( $response );
				clearstatcache();

				if ( ! empty( $body ) ) {
					file_put_contents( $tmp_location, $body );
					unset( $body );

					// Check if compressed image is smaller than original image from backup
					$original_filesize     = $stats[ $size ]['original']['size'];
					$compressed_filesize   = filesize( $tmp_location );
					$downloaded_image_exif = exif_imagetype( $tmp_location );

					// is the image broken?
					if ( ! $downloaded_image_exif ) {
						// Image is broken!
						$stats[ $size ]['compressed']['size'] = $original_filesize;
					} else {
						// Image is valid

						$this->debug_msg( $imageID, 'Compressed to ' . $compressed_filesize );

						if ( $compressed_filesize >= $original_filesize ) {
							$this->debug_msg( $imageID, 'Compressed bigger than original' );
							if ( file_exists( $backup_images[ $size ] ) ) {
								// Restore from backup
								unlink( $tmp_location );
								// Restore from local backups
								copy( $backup_images[ $size ], $file_location );
								// Reset stats for restored image
								$stats[ $size ]['compressed']['size'] = $original_filesize;
							} else {
								$stats[ $size ]['compressed']['size'] = $original_filesize;
							}
						} else {
							$this->debug_msg( $imageID, 'Compressed smaller than original' );

							unlink( $file_location );
							copy( $tmp_location, $file_location );
							unlink( $tmp_location );
							$stats[ $size ]['compressed']['size'] = $compressed_filesize;
						}

						$bulkStats['total']['original']   += $stats[ $size ]['original']['size'];
						$bulkStats['total']['compressed'] += $stats[ $size ]['compressed']['size'];

						if ( $size !== 'full' ) {
							$bulkStats['thumbs']['original']   += $stats[ $size ]['original']['size'];
							$bulkStats['thumbs']['compressed'] += $stats[ $size ]['compressed']['size'];
						}

						$this->compressed_list[ $size ] = $file_location;
					}
				} else {
					wp_send_json_error( array( 'msg' => 'api-call-empty', 'dbg_post' => $post_fields, 'dbg_api' => self::$apiURL ) );
					$this->debug_msg( $imageID, 'API Call body Empty' );
				}

			} else {
				wp_send_json_error( array( 'msg' => 'api-call-not-200', 'body' => wp_remote_retrieve_body( $response ), 'dbg_post' => $post_fields, 'dbg_api' => self::$apiURL ) );
				$this->debug_msg( $imageID, 'API Call not 200' );
			}

		}

		update_post_meta( $imageID, 'ic_compressed_images', $this->compressed_list );

		// Compress Retina?
		if ( $retina && $regenerate ) {
			$return                = $this->generate_retina( $imageID );
			$stats                 = array_merge( $stats, $return['stats'] );
			$this->compressed_list = array_merge( $this->compressed_list, $return['compressed'] );
		}

		if ( $webp && $regenerate ) {
			$return                = $this->generate_webp( $imageID );
			$stats                 = array_merge( $stats, $return['stats'] );
			$this->compressed_list = array_merge( $this->compressed_list, $return['compressed'] );
		}

		if ( $regenerate ) {
			$this->regenerate_thumbnails( $imageID );
		}

		update_post_meta( $imageID, 'ic_status', 'compressed' );
		update_option( 'wps_ic_bulk_stats', $bulkStats );
		update_post_meta( $imageID, 'ic_stats', $stats );
		update_post_meta( $imageID, 'ic_compressed_images', $this->compressed_list );

		return $bulkStats;
	}


	public function is_supported( $imageID ) {
		$file_data = get_attached_file( $imageID );
		$type      = wp_check_filetype( $file_data );

		// Is file extension allowed
		if ( ! in_array( strtolower( $type['ext'] ), self::$allowed_types ) ) {
			return false;
		} else {
			return true;
		}
	}


	public function is_already_compressed( $imageID ) {
		$backup_exists = get_post_meta( $imageID, 'ic_status', true );
		if ( ! empty( $backup_exists ) && $backup_exists == 'compressed' ) {
			return true;
		} else {
			return false;
		}
	}


	public function generate_retina( $arg ) {
		$imageID    = $arg;
		$return     = array();
		$compressed = array();
		$filename   = '';

		$image     = $image_url = wp_get_attachment_image_src( $imageID, 'full' );
		$image_url = $image_url[0];

		if ( $filename == '' ) {
			if ( strpos( $image_url, '.jpg' ) !== false ) {
				$extension = 'jpg';
			} else if ( strpos( $image_url, '.jpeg' ) !== false ) {
				$extension = 'jpeg';
			} else if ( strpos( $image_url, '.gif' ) !== false ) {
				$extension = 'gif';
			} else if ( strpos( $image_url, '.png' ) !== false ) {
				$extension = 'png';
			} else {
				return true;
			}
		}

		/**
		 * Figure out the actual file path
		 */
		$file_path     = get_attached_file( $imageID );
		$file_basename = basename( $image[0] );
		$file_path     = str_replace( $file_basename, '', $file_path );

		foreach ( $this->sizes as $i => $size ) {

			if ( empty( $image_url ) ) {
				continue;
			}

			$retinaAPIUrl = self::$apiURL . $image_url;

			if ( $size == 'full' ) {
				continue;
			} else {
				$image     = image_get_intermediate_size( $imageID, $size );
				$image_url = $image['url'];
			}

			if ( empty( $image['width'] ) || $image['width'] == '' ) {
				continue;
			}

			$file_location = $file_path . basename( $image_url );

			// Retina File Path
			$retina_file_location = str_replace( '.' . $extension, '-x2.' . $extension, $file_location );

			// Enable Retina
			$retinaAPIUrl = str_replace( 'r:0', 'r:1', $retinaAPIUrl );
			$retinaAPIUrl = str_replace( 'w:1', 'w:' . $image['width'], $retinaAPIUrl );

			$call = wp_remote_get( $retinaAPIUrl, array( 'timeout' => 60, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:20.0) Gecko/20100101 Firefox/20.0' ) );

			if ( wp_remote_retrieve_response_code( $call ) == 200 ) {
				$body = wp_remote_retrieve_body( $call );
				if ( ! empty( $body ) ) {

					file_put_contents( $retina_file_location, $body );
					clearstatcache();

					$stats[ $size . '-2x' ]['compressed']['size'] = filesize( $retina_file_location );
					$compressed[ $size . '-2x' ]                  = $retina_file_location;
				}
			}
		}

        if ( isset ( $stats ) ) {
            $return['stats'] = $stats;
        }
		$return['compressed'] = $compressed;

		$stats = get_post_meta( $imageID, 'ic_stats', true );

		if ( empty( $stats ) ) {
			$stats = array();
		}
		if ( empty( $return['stats'] ) ) {
			$return['stats'] = array();
		}

		$stats = array_merge( $stats, $return['stats'] );
		update_post_meta( $imageID, 'ic_stats', $stats );

		$compressed = get_post_meta( $imageID, 'ic_compressed_images', true );
		$compressed = array_merge( $compressed, $return['compressed'] );
		update_post_meta( $imageID, 'ic_compressed_images', $compressed );

		return $return;
	}


	public function generate_webp( $arg, $type = 'click' ) {
		global $wpc_filesystem;

		$imageID    = $arg;
		$return     = array();
		$compressed = array();
		$extension  = '';
		$stats      = array();

		$image_url_full = wp_get_attachment_image_src( $imageID, 'full' );
		$image_url_full = $image_url_full[0];
		$image_filename = basename( $image_url_full );

		if ( strpos( $image_filename, '.jpg' ) !== false ) {
			$extension = 'jpg';
		} else if ( strpos( $image_filename, '.jpeg' ) !== false ) {
			$extension = 'jpeg';
		} else if ( strpos( $image_filename, '.gif' ) !== false ) {
			$extension = 'gif';
		} else if ( strpos( $image_filename, '.png' ) !== false ) {
			$extension = 'png';
		}

		/**
		 * Figure out the actual file path
		 */
		$image         = wp_get_attachment_image_src( $imageID, 'full' );
		$file_path     = get_attached_file( $imageID );
		$file_basename = basename( $image[0] );

		// Setup POST Params
		$headers = array(
			'timeout'     => 300,
			'httpversion' => '1.0',
			'blocking'    => true,
		);

		// Figure out image type
		$exif = exif_imagetype( $file_path );
		$mime = image_type_to_mime_type( $exif );

		// Fetch the image content
		$file_content = $wpc_filesystem->get_contents( $file_path );

		$post_fields = array(
			'filename' => $file_basename,
			'apikey'   => self::$apiParams['apikey'],
			'key'      => self::$apiParams['apikey'],
			'image'    => $image[0],
			'url'      => $image[0],
			'exif'     => $exif,
			'mime'     => $mime,
			'content'  => base64_encode( $file_content ),
			'quality'  => self::$apiParams['quality'],
			'width'    => '1',
			'retina'   => 'false',
			'webp'     => 'true'
		);

		#$this->webp_sizes[] = 'full';
		$this->webp_sizes[] = get_intermediate_image_sizes();
		foreach ( $this->webp_sizes as $i => $size ) {

			if ( $size == 'full' ) {
				$image     = wp_get_attachment_image_src( $imageID, $size );
                if ( $image ) {
                    $image_url = $image[0];
                }
			} else {
				$image     = image_get_intermediate_size( $imageID, $size );
                if ( $image ) {
                    $image_url = $image['url'];
                }
			}

			if ( empty( $image_url ) ) {
				continue;
			}

			$file_location = WPS_IC_UPLOADS_DIR . '/' . $image['path'];

			if ( empty( $image['path'] ) ) {
				$image['path'] = get_attached_file( $imageID );
				$file_location = $image['path'];
			}

			if ( ! empty( $size ) ) {
				if ( $size == 'full' ) {
					$post_fields['width'] = '1';
				} else {
					if ( empty( $image['width'] ) ) {
						$post_fields['width'] = '1';
					} else {
						$post_fields['width'] = $image['width'];
					}
				}
			}

			// WebP File Path
			$webp_file_location = str_replace( '.' . $extension, '.webp', $file_location );
			$call               = wp_remote_post( self::$apiURL, array(
				'timeout'   => 300,
				'method'    => 'POST',
				'headers'   => $headers,
				'sslverify' => false,
				'body'      => $post_fields
			) );

			if ( wp_remote_retrieve_response_code( $call ) == 200 ) {
				$body = wp_remote_retrieve_body( $call );
				if ( ! empty( $body ) ) {

					file_put_contents( $webp_file_location, $body );
					clearstatcache();

					$stats[ $size . '-webp' ]['compressed']['size'] = filesize( $webp_file_location );
					$compressed[ $size . '-webp' ]                  = $webp_file_location;
				}
			}
		}

		$return['stats']      = $stats;
		$return['compressed'] = $compressed;

		$stats = get_post_meta( $imageID, 'ic_stats', true );
		$stats = array_merge( $stats, $return['stats'] );
		update_post_meta( $imageID, 'ic_stats', $stats );

		if ( $type == 'click' ) {
			$compressed = get_post_meta( $imageID, 'ic_compressed_images', true );
			$compressed = array_merge( $compressed, $return['compressed'] );
			update_post_meta( $imageID, 'ic_compressed_images', $compressed );
		}

		return $return;
	}

}