<?php

/**
 * AutoMin for Statamic
 * 
 * Original version for ExpressionEngine (https://github.com/bunchjesse/AutoMin/) by Jesse Bunch (http://getbunch.com/).
 *
 * @author    André Elvan
 * @link		  http://andreelvan.net/
 */


require_once('libraries/automin_caching_library.php');
require_once('models/automin_model.php');


class Plugin_automin extends Plugin {

  var $meta = array(
    'name'       => 'Automin',
    'version'    => '1.0.0',
    'author'     => 'André Elvan',
    'author_url' => 'http://vaersaagod.no/'
  );

	const MARKUP_TYPE_JS = 'js';
	const MARKUP_TYPE_CSS = 'css';
	const MARKUP_TYPE_LESS = 'less';
  
  var $automin_model;
  var $automin_caching;
  
  public function index() {
    $type = $this->fetch_param('type');
    $attr = $this->fetch_param('attr');

    $this->content = $this->parser->parse($this->content, Statamic_View::$_dataStore, 'Statamic_View::callback');

    $this->automin_caching = new Automin_caching_library();
    $this->automin_model = new Automin_model();

    if (!$type) {
      return $this->content;
    }
    
    return $this->_process_markup(
        $this->content,
        $type
      );    
      
    // Send the content back to Statamic's parser
    //return $this->content;
  }
    

	/**
	 * Main processing routine, to be used for all types
	 * @param $markup
	 * @param $markup_type One of the MARKUP_TYPE_X values
	 * @return string The new markup
	 * @author Jesse Bunch
	*/
	private function _process_markup($markup, $markup_type) {
		
		// AutoMin disabled? Go no further...
		if (!$this->automin_model->is_automin_enabled()) {
			return $markup;
		}

		// Gather information
		$filename_array = $this->_extract_filenames($markup, $markup_type);
		$filename_array = $this->_prep_filenames($filename_array);
		$last_modified = $this->_find_last_modified_timestamp($filename_array);

		// File Extension
		// LESS files should have a .css extension
		$extension = ($markup_type == self::MARKUP_TYPE_LESS) ? self::MARKUP_TYPE_CSS : $markup_type;
		$cache_key = $this->automin_caching->get_cache_key($markup, $extension);

		// Fetch and validate cache
		$cache_filename = $this->automin_caching->fetch_cache(
			$cache_key, 
			$markup, 
			$last_modified
		);
		
		// Output cache file, if valid
		if (FALSE !== $cache_filename) {
			$this->_write_log("Cache found and valid");
			return $this->_format_output($cache_filename, $last_modified, $markup_type);
		}

		// Combine files, parse @imports if appropriate
		$combined_file_data = $this->_combine_files(
			$filename_array,
			($markup_type == self::MARKUP_TYPE_CSS
				OR $markup_type == self::MARKUP_TYPE_LESS)
		);

		// If we couldn't read some files, return original tags
		if (FALSE === $combined_file_data) {
			$this->_write_log("ERROR: One or more of your files couldn't be read.");
			return $markup;
		}
		
		// Attempt compilation and compression
		$data_length_before = strlen($combined_file_data) / 1024;
		$combined_file_data = $this->_compile_and_compress(
			$combined_file_data, 
			$markup_type
		);
		$data_length_after = strlen($combined_file_data) / 1024;

		// Log the savings
		$data_savings_kb = $data_length_before - $data_length_after;
		$data_savings_percent = ($data_savings_kb / $data_length_before) * 100;
		$data_savings_message = sprintf(
			'(%s Compression) Before: %1.0fkb / After: %1.0fkb / Data reduced by %1.2fkb or %1.2f%%',
			strtoupper($markup_type),
			$data_length_before,
			$data_length_after,
			$data_savings_kb,
			$data_savings_percent
		);
		$this->_write_log($data_savings_message);

		// If compilation fails, return original tags
		if (FALSE === $combined_file_data) {
			$this->_write_log("ERROR: Compilation failed. Perhaps you have a syntax error?");
			return $markup;
		}

		// Cache output
		$cache_result = $this->automin_caching->write_cache($cache_key, $combined_file_data);

		// If caching failed, return original tags
		if (FALSE === $cache_result) {
			$this->_write_log("ERROR: Caching is disabled or we were unable to write to your cache directory.");
			return $markup;
		}
		
		// Return the markup output
		return $this->_format_output($cache_result, $last_modified, $markup_type);
	}

	/**
	 * Compress and compile (if necessary) the code.
	 * @param string $code
	 * @param string $markup_type One of the MARKUP_TYPE_X values
	 * @return mixed FALSE if failure, string if success
	 * @author Jesse Bunch
	*/
	private function _compile_and_compress($code, $markup_type) {

		@ini_set('memory_limit', '50M');
		@ini_set('memory_limit', '128M');
		@ini_set('memory_limit', '256M');
		@ini_set('memory_limit', '512M');
		@ini_set('memory_limit', '1024M');

		try {
			
			switch($markup_type) {

				case self::MARKUP_TYPE_LESS:

					// Compile with LESS
					require_once('libraries/lessphp/lessc.inc.php');
					$less_obj = new lessc();
					$code = $less_obj->parse($code);

					// Compress CSS
					require_once('libraries/class.minify_css_compressor.php');
					$code = Minify_CSS_Compressor::process($code);	

					break;

				case self::MARKUP_TYPE_CSS:

					// Compress CSS
					require_once('libraries/class.minify_css_compressor.php');
					$code = Minify_CSS_Compressor::process($code);	
						
					break;

				case self::MARKUP_TYPE_JS:
					
					// Compile JS
					require_once('libraries/class.jsmin.php');
					$code = JSMin::minify($code);

					// require_once('libraries/class.minify_js_closure.php');
					// $code = Minify_JS_ClosureCompiler::minify($code);
					
					break;

			}

		} catch (Exception $e) {
			exit($e->getMessage());
			$this->_write_log('Compilation Exception: ' . $e->getMessage());
			return FALSE;

		}

		return $code;

	}

	/**
	 * Formats the output into valid markup
	 * @param string $cache_filename The url path to the cache file.
	 * @param integer $last_modified Timestamp of the latest-modified file
	 * @param string $markup_type One of the MARKUP_TYPE_X values
	 * @return string
	 * @author Jesse Bunch
	*/
	private function _format_output($cache_filename, $last_modified, $markup_type) {

		$markup_output = '';
		
		// Append modified time to the filename
		$cache_filename = "$cache_filename?modified=$last_modified";

		// Format attributes
    $attributes_string = $this->fetch_param('attr');


		// Create tag
		switch($markup_type) {

			case self::MARKUP_TYPE_CSS:
			case self::MARKUP_TYPE_LESS:
				$markup_output = sprintf(
					'<link href="%s" %s>', 
					$cache_filename, 
					$attributes_string
				);
				break;

			case self::MARKUP_TYPE_JS:
				$markup_output = sprintf(
					'<script src="%s" %s></script>', 
					$cache_filename, 
					$attributes_string
				);
				break;

		}

		return $markup_output;

	}

	/**
	 * Returns a string of all the files combined. If a file cannot be read,
	 * this function will return FALSE.
	 * @param array $files_array Pass in the output of _prep_filenames
	 * @return mixed string or FALSE
	 * @author Jesse Bunch
	*/
	private function _combine_files($files_array, $should_parse_imports = FALSE) {
		
		$combined_output = '';
		foreach ($files_array as $file_array) {
			
			if (!file_exists($file_array['server_path'])
				OR !is_readable($file_array['server_path'])) {
				return FALSE;
			}

			// Get file contents
			$combined_output .= file_get_contents($file_array['server_path']);

			// Parse @imports
			if ($should_parse_imports) {
				$combined_output = $this->_parse_css_imports(
					$combined_output, 
					$file_array['url_path']
				);
			}

		}

		return $combined_output;

	}

	/**
	 * Returns the timestamp of the latest modified file
	 * @param array $files_array Pass in the output of _prep_filenames
	 * @return int
	 * @author Jesse Bunch
	*/
	private function _find_last_modified_timestamp($files_array) {
		
		$last_modified_timestamp = 0;
		foreach ($files_array as $file_array) {
			if ($file_array['last_modified']
				AND $file_array['last_modified'] > $last_modified_timestamp) {
				$last_modified_timestamp = $file_array['last_modified'];
			}
		}

		return $last_modified_timestamp;

	}

	/**
	 * Gathers information about each file and normalizes
	 * the filename and path.
	 * @param array $filenames_array
	 * @return array
	 * 	- url_path
	 * 	- server_path
	 * 	- last_modified
	 * @author Jesse Bunch
	*/
	private function _prep_filenames($filenames_array) {
		
		$information_array = array();

		foreach($filenames_array as $index => $filename) {

			// Path for URLs
			$information_array[$index]['url_path'] = $this->_normalize_file_path(
				$filename
			);

			// Path for reading
			$information_array[$index]['server_path'] = $this->_normalize_file_path(
				$filename, 
				'', 
				TRUE
			);

			// Last modified
			$information_array[$index]['last_modified'] = @filemtime(
				$information_array[$index]['server_path']
			);
			
		} 

		return $information_array;

	}

	/**
	 * Extracts the filenames from the markup based on the provided
	 * markup type.
	 * @param string $markup
	 * @param string $markup_type Use one of the constants MARKUP_TYPE_X
	 * @return array (of filenames)
	 * @author Jesse Bunch
	*/
	private function _extract_filenames($markup, $markup_type) {

		$matches_array;
		switch($markup_type) {
			case self::MARKUP_TYPE_CSS:
			case self::MARKUP_TYPE_LESS:
				preg_match_all(
					"/href\=\"([A-Za-z0-9\.\/\_\-\?\=\:]+.[css|less])\"/",
					$markup,
					$matches_array
				);
				break;
			case self::MARKUP_TYPE_JS:
				preg_match_all(
					"/src\=\"([A-Za-z0-9\.\/\_\-\?\=\:]+.js)\"/",
					$markup,
					$matches_array
				);
				break;
		}

		// Matches?
		if (count($matches_array) >= 2) {
			return $matches_array[1];
		}

		return FALSE;

	}

	/**
	 * File paths may be in different formats. This function will take
	 * any file path and normalize it to on of two formats depending on the
	 * parameters you pass.
	 * @param string $file_path The path to normalize.
	 * @param string $relative_path If $file_path is a relative path, we need
	 * the path to the relative file. If no path is supplied, the dirname of 
	 * the current URI is used.
	 * @param bool $include_root If TRUE, the full server path is returned. If
	 * FALSE, the path returned is relative to the document root.
	 * @return string
	 * @author Jesse Bunch
	*/
	private function _normalize_file_path($file_path, $relative_path='', $include_root = FALSE) {

		// If the path is a full URL, return it
		// We don't currently fetch remote files
		if (0 === stripos($file_path, 'http')
			OR 0 === stripos($file_path, '//')) {
			return $file_path;
		}

		// Get the relative path
		if (!$relative_path) {
			$relative_path = $_SERVER['REQUEST_URI'];
		}

		// Relative path should leave out the document root
		$relative_path = str_replace(dirname($_SERVER['SCRIPT_FILENAME']), '', $relative_path);

		// Parse the path
		$path_parts = pathinfo($relative_path);
		$dirname = $path_parts['dirname'].'/';

		// If not document-root relative, we must add the URI
		// of the calling page to make it document-root relative
		if (substr($file_path, 0, 1) != '/') {
			$file_path = $dirname.$file_path;
		}
	
		// Include full root path?
		if ($include_root) {
			$file_path = dirname($_SERVER['SCRIPT_FILENAME']) . $file_path;
		}

		return $this->remove_double_slashes($file_path);

	}
	
	/**
	 * Reads a file from the filesystem
	 * @param string $file_path Full server path to the file to read
	 * @return string
	 * @author Jesse Bunch
	*/
	private function _read_file($file_path) {
		
		return @file_get_contents($file_path);

	}

	/**
	 * Looks for and parses @imports in the provided string.
	 * @param string $string
	 * @param string $relative_path Passed to _normalize_file_path(). See
	 * that function's documentation for details on this param.
	 * @return string
	 * @author Jesse Bunch
	*/
	private function _parse_css_imports(&$string, $relative_path = '') {
		
		// Get all @imports
		$matches = array();
		preg_match_all('/\@import\s[url\(]?[\'\"]{1}([A-Za-z0-9\.\/\_\-]+)[\'\"]{1}[\)]?[;]?/', $string, $matches);
		$matched_lines = $matches[0];
		$matched_filenames = $matches[1];
		$count = 0;

		// Iterate and parse
		foreach($matched_filenames as $filename) {

			$filename = $this->_normalize_file_path($filename, $relative_path, TRUE);

			// Read the file
			$file_data = $this->_read_file($filename);

			// If we have data, replace the @import
			if ($file_data) {
				$string = str_replace($matched_lines[$count], $file_data, $string);
			}

			$count++;
		}

		return $string;
	}
  
	/**
	 * Removes double slashes from string
	 * @param string $str
	 * @return string
	 * @author André Elvan
	*/
  private function remove_double_slashes($str) {
		return preg_replace("#([^/:])/+#", "\\1/", $str);
	}

  

	/**
	 * Writes the message to the template log
	 * @param string $message
	 * @return void
	 * @author Jesse Bunch
	*/
	private function _write_log($message) {
		//$this->EE->TMPL->log_item("AutoMin Module: $message");
	}
  
  
}