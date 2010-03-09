<?php
if ( ! defined( 'AVH_FRAMEWORK' ) )
	die( 'You are not allowed to call this page directly.' );

class AVH_FDAS_Core
{
	/**
	 * Version of AVH First Defense Against Spam
	 *
	 * @var string
	 */
	var $version;
	var $db_version;

	/**
	 * Comments used in HTML do identify the plugin
	 *
	 * @var string
	 */
	var $comment;

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
	var $default_honey;
	var $default_ipcache;
	var $default_nonces;
	var $data;
	var $default_data;
	var $default_data_lists;
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

	var $searchengines;

	/**
	 *
	 * @var AVH_FDAS_Settings
	 */
	var $Settings;

	/**
	 * PHP5 constructor
	 *
	 */
	function __construct ()
	{
		$this->Settings = AVH_FDAS_Settings::singleton();

		$this->Settings->storeSetting( 'version', '3.0-dev1' );
		$this->db_version = 8;
		$this->comment = '<!-- AVH First Defense Against Spam version ' . $this->Settings->getSetting('version');
		$this->db_options_core = 'avhfdas';
		$this->db_options_data = 'avhfdas_data';
		$this->db_options_nonces = 'avhfdas_nonces';

		/**
		 * Default options - General Purpose
		 */
		$this->default_general_options = array ('version' => $this->Settings->getSetting('version'), 'dbversion' => $this->db_version, 'use_sfs' => 1, 'use_php' => 0, 'useblacklist' => 1, 'usewhitelist' => 1, 'diewithmessage' => 1, 'emailsecuritycheck' => 0, 'useipcache' => 0, 'cron_nonces_email' => 0, 'cron_ipcache_email' => 0 );
		$this->default_spam = array ('whentoemail' => - 1, 'emailphp' => 0, 'whentodie' => 3, 'sfsapikey' => '', 'error' => 0 );
		$this->default_honey = array ('whentoemailtype' => - 1, 'whentoemail' => - 1, 'whentodietype' => 4, 'whentodie' => 25, 'phpapikey' => '', 'usehoneypot' => 0, 'honeypoturl' => '' );
		$this->default_ipcache = array ('email' => 0, 'daystokeep' => 7 );
		$this->default_spam_data = array ('190001' => 0 );
		$this->default_data_lists = array ('blacklist' => '', 'whitelist' => '' );

		/**
		 * Default Options - All as stored in the DB
		 */
		$this->default_options = array ('general' => $this->default_general_options, 'sfs' => $this->default_spam, 'php' => $this->default_honey, 'ipcache' => $this->default_ipcache );
		$this->default_data = array ('counters' => $this->default_spam_data, 'lists' => $this->default_data_lists );
		$this->default_nonces = array ('default' => $this->default_nonces_data );

		/**
		 * Set the options for the program
		 *
		 */
		$this->loadOptions();
		$this->loadData();
		$this->setTables();
		//$this->data = $this->handleOptionsDB( $this->default_data, $this->db_options_data );


		// Check if we have to do upgrades
		if ( (! isset( $this->options['general']['dbversion'] )) || $this->options['general']['dbversion'] < $this->db_version ) {
			$this->doUpgrade();
		}

		$this->searchengines = array ('0' => 'Undocumented', '1' => 'AltaVista', '2' => 'Ask', '3' => 'Baidu', '4' => 'Excite', '5' => 'Google', '6' => 'Looksmart', '7' => 'Lycos', '8' => 'MSN', '9' => 'Yahoo', '10' => 'Cuil', '11' => 'InfoSeek', '12' => 'Miscellaneous' );

		$this->Settings->storeSetting( 'siteurl', get_option( 'siteurl' ) );
		$this->Settings->storeSetting( 'lang_dir', $this->Settings->getSetting( 'working_dir' ) . '/lang' );
		$this->Settings->storeSetting( 'graphics_url', WP_PLUGIN_URL . '/images' );
		$this->Settings->storeSetting( 'js_url', WP_PLUGIN_URL . '/js' );
		$this->Settings->storeSetting( 'css_url', WP_PLUGIN_URL . '/css' );

		$this->stopforumspam_endpoint = 'http://www.stopforumspam.com/api';

		/**
		 * TODO Localization
		 */
		// Localization.
		//$locale = get_locale();
		//if ( !empty( $locale ) ) {
		//	$mofile = $this->info['plugin_dir'].'/languages/avhamazon-'.$locale.'.mo';
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
	 * Setup DB Tables
	 * @return unknown_type
	 */
	function setTables ()
	{
		global $wpdb;

		// add DB pointer
		$wpdb->avhfdasipcache = $wpdb->prefix . 'avhfdas_ipcache';
	}

	/**
	 * Sets data according to the the DB
	 *
	 * @param array $default_data
	 * @param atring $optionsdb
	 * @return array
	 *
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
	 * Checks if running version is newer and do upgrades if necessary
	 *
	 */
	function doUpgrade ()
	{
		$options = $this->getOptions();
		$data = $this->getData();

		if ( version_compare( $options['general']['version'], '2.0-rc1', '<' ) ) {
			list ( $options, $data ) = $this->doUpgrade20( $options, $data );
		}

		// Introduced dbversion starting with v2.1
		if ( ! isset( $options['general']['dbversion'] ) || $options['general']['dbversion'] < 4 ) {
			list ( $options, $data ) = $this->doUpgrade21( $options, $data );
		}

		if ( $options['general']['dbversion'] < 5 ) {
			list ( $options, $data ) = $this->doUpgrade22( $options, $data );
		}

		// Add none existing sections and/or elements to the options
		foreach ( $this->default_options as $section => $default_options ) {
			if ( ! array_key_exists( $section, $options ) ) {
				$options[$section] = $default_options;
				continue;
			}
			foreach ( $default_options as $element => $default_value ) {
				if ( ! array_key_exists( $element, $options[$section] ) ) {
					$options[$section][$element] = $default_value;
				}
			}
		}

		// Add none existing sections and/or elements to the data
		foreach ( $this->default_data as $section => $default_data ) {
			if ( ! array_key_exists( $section, $data ) ) {
				$data[$section] = $default_data;
				continue;
			}
			foreach ( $default_data as $element => $default_value ) {
				if ( ! array_key_exists( $element, $data[$section] ) ) {
					$data[$section][$element] = $default_value;
				}
			}
		}
		$options['general']['version'] = $this->Settings->getSetting('version');
		$options['general']['dbversion'] = $this->db_version;
		$this->saveOptions( $options );
		$this->saveData( $data );
	}

	/**
	 * Upgrade to version 2.0
	 *
	 * @param array $old_options
	 * @param array $old_data
	 * @return array
	 *
	 */
	function doUpgrade20 ( $old_options, $old_data )
	{
		$new_options = $old_options;
		$new_data = $old_data;

		// Move elements from one section to another
		$keys = array ('diewithmessage', 'useblacklist', 'usewhitelist', 'emailsecuritycheck' );
		foreach ( $keys as $value ) {
			$new_options['general'][$value] = $old_options['spam'][$value];
			unset( $new_options['spam'][$value] );
		}

		// Move elements from options to data
		$keys = array ('blacklist', 'whitelist' );
		foreach ( $keys as $value ) {
			$new_data['lists'][$value] = $old_options['spam'][$value];
			unset( $new_options['spam'][$value] );
		}

		// Section renamed
		$new_options['sfs'] = $new_options['spam'];
		unset( $new_options['spam'] );

		// New counter system
		unset( $new_data['spam']['counter'] );

		return array ($new_options, $new_data );
	}

	/**
	 * Upgrade to version 2.1
	 *
	 * @param array $old_options
	 * @param array $old_data
	 * @return array
	 *
	 */
	function doUpgrade21 ( $old_options, $old_data )
	{
		$new_options = $old_options;
		$new_data = $old_data;

		// Changed Administrative capabilties names
		$role = get_role( 'administrator' );
		if ( $role != null && $role->has_cap( 'avh_fdas' ) ) {
			$role->remove_cap( 'avh_fdas' );
			$role->add_cap( 'role_avh_fdas' );
		}
		if ( $role != null && $role->has_cap( 'admin_avh_fdas' ) ) {
			$role->remove_cap( 'admin_avh_fdas' );
			$role->add_cap( 'role_admin_avh_fdas' );
		}

		return array ($new_options, $new_data );
	}

	/**
	 * Upgrade to version 2.2
	 *
	 * @param $options
	 * @param $data
	 */
	function doUpgrade22 ( $old_options, $old_data )
	{
		global $wpdb;

		$new_options = $old_options;
		$new_data = $old_data;

		$sql = 'ALTER TABLE `' . $wpdb->avhfdasipcache . '`
				CHANGE COLUMN `date` `added` DATETIME  NOT NULL DEFAULT \'0000-00-00 00:00:00\',
				ADD COLUMN `lastseen` DATETIME  NOT NULL DEFAULT \'0000-00-00 00:00:00\' AFTER `added`,
				DROP INDEX `date`,
				ADD INDEX `added`(`added`),
				ADD INDEX `lastseen`(`lastseen`);';
		$result = $wpdb->query( $sql );

		$sql = 'UPDATE ' . $wpdb->avhfdasipcache . ' SET `lastseen` = `added`;';
		$result = $wpdb->query( $sql );

		return array ($new_options, $new_data );
	}

	/**
	 * Actual Rest Call
	 *
	 * @param array $query_array
	 * @return array
	 */
	function handleRESTcall ( $query_array )
	{
		$xml_array = array ();
		$querystring = $this->BuildQuery( $query_array );
		$url = $this->stopforumspam_endpoint . '?' . $querystring;
		// Starting with WordPress 2.7 we'll use the HTTP class.
		if ( function_exists( 'wp_remote_request' ) ) {
			$response = wp_remote_request( $url, array ('user-agent' => 'WordPress/AVH ' . $this->Settings->getSetting( 'version' ) . '; ' . get_bloginfo( 'url' ) ) );
			if ( ! is_wp_error( $response ) ) {
				$xml_array = $this->ConvertXML2Array( $response['body'] );
				if ( ! empty( $xml_array ) ) {
					// Did the call succeed?
					if ( 'true' == $xml_array['response_attr']['success'] ) {
						$return_array = $xml_array['response'];
					} else {
						if ( isset( $xml_array['response']['error'] ) ) {
							$return_array = array ('Error' => $xml_array['response']['error'] );
						} else {
							$return_array = array ('Error' => 'Unsuccesfull response from SFS', 'Debug' => var_export( $response, true ) . "/n" . var_export( $xml_array, true ) );
						}
					}
				} else {
					$return_array = array ('Error' => 'Unknown response from stopforumspam', 'Debug' => var_export( $response, true ) . "/n" . var_export( $xml_array, true ) );
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
	 */
	function getHttpError ( $error )
	{
		if ( is_array( $error ) ) {
			foreach ( $error as $key => $value ) {
				$error_short = $key;
				$error_long = $value[0];
				$return = $error_short . ' - ' . $error_long;
			}
		} else {
			$return = $error;
		}
		return $return;
	}

	/**
	 * Convert an array into a query string
	 *
	 * @param array $array
	 * @param string $convention
	 * @return string
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
						$query .= $this->BuildQuery( $value, $new_convention );
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
			$current = &$xml_array; // Reference
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
					$parent[$level - 1] = &$current;
					if ( ! is_array( $current ) or (! in_array( $tag, array_keys( $current ) )) ) { //Insert New tag
						$current[$tag] = $result;
						if ( $attributes_data )
							$current[$tag . '_attr'] = $attributes_data;
						$repeated_tag_index[$tag . '_' . $level] = 1;
						$current = &$current[$tag];
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
						$current = &$current[$tag][$last_item_index];
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
					$current = &$parent[$level - 1];
				}
			}
			$return_array = $xml_array;
		}
		return ($return_array);
	}

	/**
	 * Parameter for Rest Call IP Lookup
	 *
	 * @param string $ip
	 * @return array
	 */
	function getRestIPLookup ( $ip )
	{
		$iplookup = array ('ip' => $ip );
		return $iplookup;
	}

	/*********************************
	 * *
	 * Methods for variable: options *
	 * *
	 ********************************/

	/**
	 * @param array $data
	 */
	function setOptions ( $options )
	{
		$this->options = $options;
	}

	/**
	 * return array
	 */
	function getOptions ()
	{
		return ($this->options);
	}

	/**
	 * Save all current options and set the options
	 *
	 */
	function saveOptions ( $options )
	{
		update_option( $this->db_options_core, $options );
		wp_cache_flush(); // Delete cache
		$this->setOptions( $options );
	}

	/**
	 * Retrieves the plugin options from the WordPress options table and assigns to class variable.
	 * If the options do not exists, like a new installation, the options are set to the default value.
	 *
	 * @return none
	 */
	function loadOptions ()
	{
		$options = get_option( $this->db_options_core );
		if ( false === $options ) { // New installation
			$this->resetToDefaultOptions();
		} else {
			$this->setOptions( $options );
		}
	}

	/**
	 * Get the value for an option element. If there's no option is set on the Admin page, return the default value.
	 *
	 * @param string $key
	 * @param string $option
	 * @return mixed
	 */
	function getOptionElement ( $option, $key )
	{
		if ( $this->options[$option][$key] ) {
			$return = $this->options[$option][$key]; // From Admin Page
		} else {
			$return = $this->default_options[$option][$key]; // Default
		}
		return ($return);
	}

	/**
	 * Reset to default options and save in DB
	 *
	 */
	function resetToDefaultOptions ()
	{
		$this->options = $this->default_options;
		$this->saveOptions( $this->default_options );
	}

	/******************************
	 * *
	 * Methods for variable: data *
	 * *
	 *****************************/

	/**
	 * @param array $data
	 */
	function setData ( $data )
	{
		$this->data = $data;
	}

	/**
	 * @return array
	 */
	function getData ()
	{
		return ($this->data);
	}

	/**
	 * Save all current data to the DB
	 * @param array $data
	 *
	 */
	function saveData ( $data )
	{
		update_option( $this->db_options_data, $data );
		wp_cache_flush(); // Delete cache
		$this->setData( $data );
	}

	/**
	 * Retrieve the data from the DB
	 *
	 * @return array
	 */
	function loadData ()
	{
		$data = get_option( $this->db_options_data );
		if ( false === $data ) { // New installation
			$this->resetToDefaultData();
		} else {
			$this->setData( $data );
		}
		return;
	}

	/**
	 * Get the value of a data element. If there is no value return false
	 *
	 * @param string $option
	 * @param string $key
	 * @return mixed
	 * @since 0.1
	 */
	function getDataElement ( $option, $key )
	{
		if ( $this->data[$option][$key] ) {
			$return = $this->data[$option][$key];
		} else {
			$return = false;
		}
		return ($return);
	}

	/**
	 * Reset to default data and save in DB
	 *
	 */
	function resetToDefaultData ()
	{
		$this->data = $this->default_data;
		$this->saveData( $this->default_data );
	}

	/*********************************
	 * *
	 * Methods for variable: comment *
	 * *
	 *********************************/

	/**
	 * @return string
	 */
	function getComment ( $str = '' )
	{
		return $this->comment . ' ' . trim( $str ) . ' -->';
	}

} //End Class AVH_FDAS_Core
?>
