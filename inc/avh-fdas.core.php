<?php
// Stop direct call
if ( preg_match( '#' . basename( __FILE__ ) . '#', $_SERVER['PHP_SELF'] ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class AVH_FDAS_Core {
	/**
	 * Version of AVH First Defense Against Spam
	 *
	 * @var string
	 */
	var $version;

	/**
	 * Comments used in HTML do identify the plugin
	 *
	 * @var string
	 */
	var $comment_general;
	var $comment_begin;
	var $comment_end;

	/**
	 * Paths and URI's of the WordPress information, 'home', 'siteurl', 'install_url', 'install_dir'
	 *
	 * @var array
	 */
	var $info;

	/**
	 * Options set for the plugin
	 *
	 * @var array
	 */
	var $options;

	/**
	 * Default options for the plugin
	 *
	 * @var array
	 */
	var $default_general_options;
	var $default_options;
	var $default_spam;
	var $default_nonces;

	var $data;
	var $default_data;
	var $default_spam_data;
	var $default_nonces_data;

	/**
	 * Name of the options field in the WordPress database options table.
	 *
	 * @var string
	 */
	var $db_options_core;
	var $db_options_data;
	var $db_options_nonces;

	/**
	* Endpoint of the stopforumspam.com API
	*
	* @var string
	*/
	var $stopforumspam_endpoint;

	/**
	 * PHP5 constructor
	 *
	 */
	function __construct ()
	{

		$this->version = "2.0-rc1";
		$this->comment_general = '<!-- AVH First Defense Against Spam version ' . $this->version . ' -->';
		$this->comment_begin = '<!-- AVH First Defense Against Spam version ' . $this->version . ' Begin -->';
		$this->comment_end = '<!-- AVH First Defense Against Spam version ' . $this->version . ' End -->';

		$this->db_options_core = 'avhfdas';
		$this->db_options_data = 'avhfdas_data';
		$this->db_options_nonces ='avhfdas_nonces';

		/**
		 * Default options - General Purpose
		 */
		$this->default_general_options = array (
				'version' => $this->version,
			);
		$this->default_spam = array (
				'whentoemail' => 1,
				'whentodie' => 3,
				'diewithmessage' => 1,
				'useblacklist' => 1,
				'blacklist' => '',
				'usewhitelist' => 1,
				'whitelist' => '',
				'emailsecuritycheck' => 1,
				'sfsapikey' => '',
			);
		$this->default_spam_data = array(
				'counter' => 0,
			);

		$this->default_nonces_data = 'default';

		/**
		 * Default Options - All as stored in the DB
		 */
		$this->default_options = array (
				'general' => $this->default_general_options,
				'spam' => $this->default_spam,
			);

		$this->default_data = array (
				'spam' => $this->default_spam_data,
			);
		$this->default_nonces = array (
				'default' => $this->default_nonces_data
			);

		/**
		 * Set the options for the program
		 *
		 */
		$this->options = $this->handleOptionsDB( $this->default_options, $this->db_options_core );
		$this->data = $this->handleOptionsDB( $this->default_data, $this->db_options_data );

		// Check if we have to do upgrades
		$this->checkForUpgrade();

		// Determine installation path & url
		//$info['home_path'] = get_home_path();
		$path = str_replace( '\\', '/', dirname( __FILE__ ) );
		$path = substr( $path, strpos( $path, 'plugins' ) + 8, strlen( $path ) );

		$info['siteurl'] = get_option( 'siteurl' );
		if ( $this->isMuPlugin() ) {
			$info['install_url'] = WPMU_PLUGIN_URL;
			$info['install_dir'] = WPMU_PLUGIN_DIR;

			if ( $path != 'mu-plugins' ) {
				$info['install_url'] .= '/' . $path;
				$info['install_dir'] .= '/' . $path;
			}
		} else {
			$info['install_url'] = WP_PLUGIN_URL;
			$info['install_dir'] = WP_PLUGIN_DIR;

			if ( $path != 'plugins' ) {
				$info['install_url'] .= '/' . $path;
				$info['install_dir'] .= '/' . $path;
			}
		}

		// Set class property for info
		$this->info = array (
				'home' => get_option ( 'home' ),
				'siteurl' => $info['siteurl'],
				'install_url' => $info['install_url'],
				'install_dir' => $info['install_dir'],
				'graphics_url' => $info['install_url'] . '/images',
				'home_path' => $info['home_path'],
				'wordpress_version' => $this->getWordpressVersion ()
		);

		$this->stopforumspam_endpoint = 'http://www.stopforumspam.com/api';

		/**
		 * TODO Localization
		 */
		// Localization.
		//$locale = get_locale();
		//if ( !empty( $locale ) ) {
		//	$mofile = $this->info['install_dir'].'/languages/avhamazon-'.$locale.'.mo';
		//	load_textdomain('avhfdas', $mofile);
		//}
		return;
	}

	/**
	 * PHP4 constructor - Initialize the Core
	 *
	 * @return
	 */
	function AVH_FDAS_Core ()
	{
		$this->__construct();
	}

	/**
	 * Test if local installation is mu-plugin or a classic plugin
	 *
	 * @return boolean
	 */
	function isMuPlugin ()
	{
		if ( strpos( dirname( __FILE__ ), 'mu-plugins' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Sets data according to the the DB
	 *
	 * @param array $default_data
	 * @param atring $optionsdb
	 * @return array
	 *
	 * @since 1.2.3
	 */
	function handleOptionsDB ( $default_data, $optionsdb )
	{

		// Get options from WP options
		$data_from_table = get_option( $optionsdb );

		if ( is_array( $data_from_table ) ) {
			// Update default options by getting not empty values from options table
			foreach ( $default_data as $section_key => $section_array ) {
				foreach ( array_keys( $section_array ) as $name ) {
					/**
					 * We only set the data if the following is all TRUE
					 * 1. The option exists in the DB.
					 * 2. If the value and type are different in the DB
					 *
					 * We don't handle the version option. This is handled seperately to accomodate upgrades.
					 */
					if ( isset( $data_from_table[$section_key][$name] ) && ('version' != $name) && (! ($default_data[$section_key][$name] === $data_from_table[$section_key][$name])) ) {
						$default_data[$section_key][$name] = $data_from_table[$section_key][$name];
					}
				}
			}
		}
		return ($default_data);
	}

	/**
	 * Get the value for data. If there's no data return the default value.
	 *
	 * @param string $key
	 * @param string $option
	 * @return mixed
	 */
	function getData ( $key, $option )
	{
		if ( $this->data[$option][$key] ) {
			$return = $this->data[$option][$key]; // From Admin Page
		} else {
			$return = $this->default_data[$option][$key]; // Default
		}
		return ($return);
	}

	/**
	 * Get the value for an option. If there's no option is set on the Admin page, return the default value.
	 *
	 * @param string $key
	 * @param string $option
	 * @return mixed
	 */
	function getOption ( $key, $option )
	{
		if ( $this->options[$option][$key] ) {
			$return = $this->options[$option][$key]; // From Admin Page
		} else {
			$return = $this->default_options[$option][$key]; // Default
		}
		return ($return);
	}

	/**
	 * Checks if running version is newer and do upgrades if necessary
	 *
	 * @since 1.2.3
	 *
	 */
	function checkForUpgrade ()
	{
		if ( $this->version > $this->options['general']['version'] ) {
			$this->options['general']['version'] = $this->version;
			update_option( $this->db_options_core, $this->options );
		}
	}

	/**
	 * Get the base directory of a directory structure
	 *
	 * @param string $directory
	 * @return string
	 *
	 * @since 1.0
	 *
	 */
	function getBaseDirectory ( $directory )
	{
		//get public directory structure eg "/top/second/third"
		$public_directory = dirname( $directory );
		//place each directory into array
		$directory_array = explode( '/', $public_directory );
		//get highest or top level in array of directory strings
		$public_base = max( $directory_array );

		return $public_base;
	}

	/**
	 * Returns the wordpress version
	 * Note: 2.7.x will return 2.7
	 *
	 * @return float
	 *
	 * @since 1.0
	 */
	function getWordpressVersion ()
	{
		// Include WordPress version
		require (ABSPATH . WPINC . '/version.php');
		$version = ( float ) $wp_version;
		return $version;
	}

	/**
	 * Get the user's IP
	 *
	 * @return string
	 */
	function getUserIP ()
	{
		if ( isset( $_SERVER ) ) {
			if ( isset( $_SERVER["HTTP_X_REAL_IP"] ) )
				return $_SERVER["HTTP_X_REAL_IP"];

			if ( isset( $_SERVER["HTTP_X_FORWARDED_FOR"] ) ) {
				$long = ip2long( $_SERVER["HTTP_X_FORWARDED_FOR"] );
				/**
				 * Private Ip Ranges
				 * 167772160 - 10.0.0.0
				 * 184549375 - 10.255.255.255
				 * -1408237568 - 172.16.0.0
				 * -1407188993 - 172.31.255.255
				 * -1062731776 - 192.168.0.0
				 * -1062666241 - 192.168.255.255
				 * -1442971648 - 169.254.0.0
				 * -1442906113 - 169.254.255.255
				 * 2130706432 - 127.0.0.0
				 * 2147483647 - 127.255.255.255 (32 bit integer limit!!!)
				 * */
				if ( ! (($long >= 167772160 and $long <= 184549375) or ($long >= - 1408237568 and $long <= - 1407188993) or ($long >= - 1062731776 and $long <= - 1062666241) or ($long >= 2130706432 and $long <= 2147483647) or $long == - 1) ) {
					return $_SERVER["HTTP_X_FORWARDED_FOR"];
				}
			}

			if ( isset( $_SERVER["HTTP_CLIENT_IP"] ) )
				return $_SERVER["HTTP_CLIENT_IP"];

			return $_SERVER["REMOTE_ADDR"];
		}

		if ( getenv( 'HTTP_X_REAL_IP' ) )
			return getenv( 'HTTP_X_REAL_IP' );

		if ( getenv( 'HTTP_X_FORWARDED_FOR' ) ) {
			$long = ip2long( $_SERVER["HTTP_X_FORWARDED_FOR"] );
			if ( ! (($long >= 167772160 and $long <= 184549375) or ($long >= - 1408237568 and $long <= - 1407188993) or ($long >= - 1062731776 and $long <= - 1062666241) or ($long >= 2130706432 and $long <= 2147483647) or $long == - 1) ) {
				return getenv( 'HTTP_X_FORWARDED_FOR' );
			}
		}

		if ( getenv( 'HTTP_CLIENT_IP' ) )
			return getenv( 'HTTP_CLIENT_IP' );

		return getenv( 'REMOTE_ADDR' );
	}

	/**
	 * Insert the CSS file
	 *
	 * @param string $handle CSS Handle
	 * @param string $cssfile
	 *
	 * @since 1.0
	 */
	function handleCssFile ( $handle, $cssfile )
	{
		wp_register_style( $handle, $this->info['install_url'] . $cssfile, array (), $this->version, 'all' );
		if ( did_action( 'wp_print_styles' ) ) { // we already printed the style queue.  Print this one immediately
			wp_print_styles( $handle );
		} else {
			wp_enqueue_style( $handle );
		}
	}

	/**
	 * Local nonce creation. WordPress uses the UID and sometimes I don't want that
	 * Creates a random, one time use token.
	 *
	 * @param string|int $action Scalar value to add context to the nonce.
	 * @return string The one use form token
	 *
	 * @since 1.1
	 */
	function avh_create_nonce ( $action = -1 )
	{
		$i = wp_nonce_tick();
		return substr( wp_hash( $i . $action, 'nonce' ), - 12, 10 );
	}

	/**
	 * Local nonce verification. WordPress uses the UID and sometimes I don't want that
	 * Verify that correct nonce was used with time limit.
	 *
	 * The user is given an amount of time to use the token, so therefore, since the
	 * $action remain the same, the independent variable is the time.
	 *
	 * @since 1.1
	 *
	 * @param string $nonce Nonce that was used in the form to verify
	 * @param string|int $action Should give context to what is taking place and be the same when nonce was created.
	 * @return bool Whether the nonce check passed or failed.
	 */
	function avh_verify_nonce ( $nonce, $action = -1 )
	{
		$r = false;
		$i = wp_nonce_tick();

		// Nonce generated 0-12 hours ago
		if ( substr( wp_hash( $i . $action, 'nonce' ), - 12, 10 ) == $nonce ) {
			$r = 1;
		} elseif ( substr( wp_hash( ($i - 1) . $action, 'nonce' ), - 12, 10 ) == $nonce ) { // Nonce generated 12-24 hours ago
			$r = 2;
		}
		return $r;
	}

	/**
	 * Actual Rest Call
	 *
	 * @param array $query_array
	 * @return array
	 * @since 1.0
	 */
	function handleRESTcall ( $query_array )
	{
		$xml_array = array ();

		$querystring = $this->BuildQuery( $query_array );
		$url = $this->stopforumspam_endpoint . '?' . $querystring;

		// Starting with WordPress 2.7 we'll use the HTTP class.
		if ( function_exists( 'wp_remote_request' ) ) {
			$response = wp_remote_request( $url );
			if ( ! is_wp_error( $response ) ) {
				$xml_array = $this->ConvertXML2Array( $response['body'] );
				if ( ! empty( $xml_array ) ) {
					// Did the call succeed?
					if ( 'true' == $xml_array['response_attr']['success'] ) {
						$return_array = $xml_array['response'];
					} else {
						$return_array = array ('Error' => 'Invalid call to stopforumspam' );
					}
				} else {
					$return_array = array ('Error' => 'Unknown response from stopforumspam' );
				}
			} else {
				$return_array = array ('Error' => $response->errors );
			}
		}

		return ($return_array);
	}

	/**
	 * Format an error message from the WP_Error response by wp_remote_request
	 *
	 * @param array $error
	 * @return string
	 * @since 1.0
	 *
	 */
	function getHttpError ( $error )
	{
		if ( is_array( $error ) ) {
			foreach ( $error as $key => $value ) {
				$error_short = $key;
				$error_long = $value[0];
				$return = 'Error:' . $error_short . ' - ' . $error_long;
			}
		} else {
			$return = 'Error:' . $error;
		}
		return $return;
	}

	/**
	 * Convert an array into a query string
	 *
	 * @param array $array
	 * @param string $convention
	 * @return string
	 * @since 1.0
	 */
	function BuildQuery ( $array = NULL, $convention = '%s' )
	{
		if ( count( $array ) == 0 ) {
			return '';
		} else {
			if ( function_exists( 'http_build_query' ) ) {
				$query = http_build_query( $array );
			} else {
				$query = '';
				foreach ( $array as $key => $value ) {
					if ( is_array( $value ) ) {
						$new_convention = sprintf( $convention, $key ) . '[%s]';
						$query .= BuildQuery( $value, $new_convention );
					} else {
						$key = urlencode( $key );
						$value = urlencode( $value );
						$query .= sprintf( $convention, $key ) . "=$value&";
					}
				}
			}
			return $query;
		}
	}

	/**
	 * Convert XML into an array
	 *
	 * @param string $contents
	 * @param integer $get_attributes
	 * @param string $priority
	 * @return array
	 * @since 1.0
	 * @see http://www.bin-co.com/php/scripts/xml2array/
	 */
	function ConvertXML2Array ( $contents = '', $get_attributes = 1, $priority = 'tag' )
	{
		$xml_values = '';
		$return_array = array ();
		$tag = '';
		$type = '';
		$level = 0;
		$attributes = array ();
		if ( function_exists( 'xml_parser_create' ) ) {
			$parser = xml_parser_create( 'UTF-8' );

			xml_parser_set_option( $parser, XML_OPTION_TARGET_ENCODING, "UTF-8" );
			xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, 0 );
			xml_parser_set_option( $parser, XML_OPTION_SKIP_WHITE, 1 );
			xml_parse_into_struct( $parser, trim( $contents ), $xml_values );
			xml_parser_free( $parser );

			//Initializations
			$xml_array = array ();
			$parent = array ();

			$current = & $xml_array; // Reference


			// Go through the tags.
			$repeated_tag_index = array ();

			// Multiple tags with same name will be turned into an array
			foreach ( $xml_values as $data ) {
				unset( $attributes, $value ); //Remove existing values, or there will be trouble


				// This command will extract these variables into the foreach scope
				// tag(string), type(string), level(int), attributes(array).
				extract( $data ); //We could use the array by itself, but this cooler.


				$result = array ();
				$attributes_data = array ();

				if ( isset( $value ) ) {
					if ( $priority == 'tag' ) {
						$result = $value;
					} else {
						$result['value'] = $value; //Put the value in an associate array if we are in the 'Attribute' mode
					}
				}

				// Set the attributes too
				if ( isset( $attributes ) and $get_attributes ) {
					foreach ( $attributes as $attr => $val ) {
						if ( $priority == 'tag' )
							$attributes_data[$attr] = $val;
						else
							$result['attr'][$attr] = $val; //Set all the attributes in a array called 'attr'
					}
				}

				// See tag status and do what's needed
				if ( $type == "open" ) { // The starting of the tag '<tag>'
					$parent[$level - 1] = & $current;

					if ( ! is_array( $current ) or (! in_array( $tag, array_keys( $current ) )) ) { //Insert New tag
						$current[$tag] = $result;
						if ( $attributes_data )
							$current[$tag . '_attr'] = $attributes_data;
						$repeated_tag_index[$tag . '_' . $level] = 1;

						$current = & $current[$tag];

					} else { // There was another element with the same tag name


						if ( isset( $current[$tag][0] ) ) { //If there is a 0th element it is already an array
							$current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
							$repeated_tag_index[$tag . '_' . $level] ++;
						} else { //This section will make the value an array if multiple tags with the same name appear together
							$current[$tag] = array ($current[$tag], $result );
							//This will combine the existing item and the new item together to make an array
							$repeated_tag_index[$tag . '_' . $level] = 2;

							if ( isset( $current[$tag . '_attr'] ) ) { // The attribute of the last(0th) tag must be moved as well
								$current[$tag]['0_attr'] = $current[$tag . '_attr'];
								unset( $current[$tag . '_attr'] );
							}
						}
						$last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
						$current = & $current[$tag][$last_item_index];
					}
				} elseif ( $type == "complete" ) { //Tags that ends in 1 line '<tag />'
					//See if the key is already taken.
					if ( ! isset( $current[$tag] ) ) { // New key
						$current[$tag] = $result;
						$repeated_tag_index[$tag . '_' . $level] = 1;
						if ( $priority == 'tag' and $attributes_data )
							$current[$tag . '_attr'] = $attributes_data;
					} else { //If taken, put all things inside a list(array)
						if ( isset( $current[$tag][0] ) and is_array( $current[$tag] ) ) {
							//This will combine the existing item and the new item together to make an array
							$current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
							if ( $priority == 'tag' and $get_attributes and $attributes_data ) {
								$current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
							}
							$repeated_tag_index[$tag . '_' . $level] ++;
						} else { //If it is not an array...
							$current[$tag] = array ($current[$tag], $result ); //...Make it an array using using the existing value and the new value
							$repeated_tag_index[$tag . '_' . $level] = 1;
							if ( $priority == 'tag' and $get_attributes ) {
								if ( isset( $current[$tag . '_attr'] ) ) { //The attribute of the last(0th) tag must be moved as well
									$current[$tag]['0_attr'] = $current[$tag . '_attr'];
									unset( $current[$tag . '_attr'] );
								}
								if ( $attributes_data ) {
									$current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
								}
							}
							$repeated_tag_index[$tag . '_' . $level] ++; //0 and 1 index is already taken
						}
					}
				} elseif ( $type == 'close' ) { //End of tag '</tag>'
					$current = & $parent[$level - 1];
				}
			}
			$return_array = $xml_array;
		}
		return ($return_array);
	}

	function getRestIPLookup ( $ip )
	{
		$iplookup = array ('ip' => $ip );
		return $iplookup;
	}
} //End Class AVH_FDAS_Core

?>