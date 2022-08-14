<?php

/**
 * The idea:
 * 1. scan theme for css/js items
 * 2. scan active plugins for css/js items
 * 3. save all found items into an option
 * 4. loop through option and send each item to edge storage
 * 5. mark files which are saved to edge storage and replace in front
 */

/**
 * TODO:
 * - scan only plugins which are active
 * - figure out dynamic css upload
 */

#ini_set('display_errors', 1);
#error_reporting(E_ALL);

class wpc_edge {


	public function __construct() {
		$this->themeDir      = get_template_directory();
		$this->pluginDir     = WP_PLUGIN_DIR . '/';
		$this->themeFoundCSS = array();
		$this->themeFoundJS  = array();
		$this->pluginFoundJS  = array();
		$this->pluginFoundCSS  = array();
	}


	public function scanDir( $path_to_dir, $search = '*.css' ) {
		$found_files = array();
		foreach ( scandir( $path_to_dir ) as $filename ) {
			if ( $filename[0] === '.' ) {
				continue;
			}
			$filePath = $path_to_dir . '/' . $filename;

			if ( is_dir( $filePath ) ) {
				foreach ( $this->scanDir( $filePath ) as $childFilename ) {

					if ( $this->isFileType( $filename . '/' . $childFilename, 'css' ) ) {
						$found_files[] = $filename . '/' . $childFilename;
					} else {
						// Nothing
					}
				}
			} else {
				if ( $this->isFileType( $filename, 'css' ) ) {
					$found_files[] = $filename;
				} else {
					// Nothing
				}
			}
		}

		return $found_files;
	}


	public function isFileType( $filename, $what = 'css' ) {
		if ( strpos( $filename, '.' . $what ) !== false ) {
			$explodedFilename = explode( '.', $filename );
			$fileExtension    = end( $explodedFilename );
			if ( $fileExtension == $what ) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}


	public function scanThemeCSS() {
		$this->themeFoundCSS = $this->scanDir( $this->themeDir, '*.css' );
	}


	public function scanThemeJS() {
		$this->themeFoundJS = $this->scanDir( $this->themeDir, '*.js' );
	}


	public function scanPluginCSS() {
		$this->pluginFoundCSS = $this->scanDir( $this->pluginDir, '*.css' );
	}


	public function scanPluginJS() {
		$this->pluginFoundJS = $this->scanDir( $this->pluginDir, '*.js' );
	}


	public function getFoundItems() {
		var_dump( $this->themeFoundCSS );
		var_dump( $this->themeFoundJS );
		var_dump( $this->pluginFoundCSS );
		var_dump( $this->pluginFoundJS );
	}


}