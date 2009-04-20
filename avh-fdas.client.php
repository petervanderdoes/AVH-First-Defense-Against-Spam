<?php
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

		$this->version = "1.2-rc2";
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
		$this->handleOptions();
		$this->handleData();

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
	 * Sets the class property "data" to the data stored in the DB and if they do not exists set them to the default data
	 *
	 * @since 1.0
	 *
	 */
	function handleData ()
	{
		$default_data = $this->default_data;

		// Get options from WP options
		$data_from_table = get_option( $this->db_options_data );

		if ( empty( $data_from_table ) ) {
			$data_from_table = $this->default_data; // New installation
		} else {
			// Update default options by getting not empty values from options table
			foreach ( $default_data as $section_key => $section_array ) {
				foreach ( $section_array as $name => $value ) {
					if ( ! is_null( $data_from_table[$section_key][$name] ) ) {
						if ( is_int( $value ) ) {
							$default_data[$section_key][$name] = ( int ) $data_from_table[$section_key][$name];
						} else {
							$default_data[$section_key][$name] = $data_from_table[$section_key][$name];
						}
					}
				}
			}
		}
		// Set the class property for data
		$this->data = $default_data;
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
	 * Sets the class property "options" to the options stored in the DB and if they do not exists set them to the default options
	 * Checks if upgrades are necessary based on the version number
	 *
	 * @since 1.0
	 *
	 */
	function handleOptions ()
	{
		$default_options = $this->default_options;

		// Get options from WP options
		$options_from_table = get_option( $this->db_options_core );

		if ( empty( $options_from_table ) ) {
			$options_from_table = $this->default_options; // New installation
		} else {
			// Update default options by getting not empty values from options table
			foreach ( $default_options as $section_key => $section_array ) {
				foreach ( $section_array as $name => $value ) {
					if ( ! is_null( $options_from_table[$section_key][$name] ) ) {
						if ( is_int( $value ) ) {
							$default_options[$section_key][$name] = ( int ) $options_from_table[$section_key][$name];
						} else {
							$default_options[$section_key][$name] = $options_from_table[$section_key][$name];
						}
					}
				}
			}

			// If a newer version is running do upgrades if neccesary and update the database.
			if ( $this->version > $options_from_table['general']['version'] ) {
				$default_options['general']['version'] = $this->version;
				update_option( $this->db_options_core, $default_options );
			}
		}
		// Set the class property for options
		$this->options = $default_options;
	} // End handleOptions()


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
			} else {
				$return_array = array ('Error' => $response->errors );
			}
		} else { // Prior to WordPress 2.7 we'll use the Snoopy Class.
			require_once (ABSPATH . 'wp-includes/class-snoopy.php');
			$snoopy = new Snoopy( );
			$snoopy->fetch( $url );
			if ( ! $snoopy->error ) {
				$response = $snoopy->results;
				$xml_array = $this->ConvertXML2Array( $response );
			} else {
				$response = array ($snoopy->error => array (0 => $url ) );
				$return_array = array ('Error' => $response );
			}
		}

		// It will be empty if we had an error.
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
		foreach ( $error as $key => $value ) {
			$error_short = $key;
			$error_long = $value[0];
		}
		return '<strong>avhfdas error:' . $error_short . ' - ' . $error_long . '</strong>';
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


/**
 * Initialize the plugin
 *
 */
function avh_FDAS_init ()
{
	// Admin
	if ( is_admin() ) {
		require (dirname( __FILE__ ) . '/inc/avh-fdas.admin.php');
		$avhfdas_admin = & new AVH_FDAS_Admin( );

		// Activation Hook
		register_activation_hook( __FILE__, array (& $avhfdas_admin, 'installPlugin' ) );

		// Deactivation Hook
		register_deactivation_hook( __FILE__, array (&$avhfdas_admin, 'deactivatePlugin'));;
	}

	// Include the public class
	require (dirname( __FILE__ ) . '/inc/avh-fdas.public.php');
	$avhfdas_public = & new AVH_FDAS_Public( );

} // End avh_FDAS__init()


add_action ( 'plugins_loaded', 'avh_FDAS_init' );
?>