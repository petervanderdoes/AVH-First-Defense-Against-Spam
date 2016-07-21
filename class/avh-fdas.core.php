<?php
if ( ! defined('AVH_FRAMEWORK')) {
	die('You are not allowed to call this page directly.');
}

class AVH_FDAS_Core {
	/**
	 * Paths and URI's of the WordPress information, 'home', 'siteurl', 'install_url', 'install_dir'
	 *
	 * @var array
	 */
	var $info;
	/**
	 * Comments used in HTML do identify the plugin
	 *
	 * @var string
	 */
	private $comment;
	private $data;
	/**
	 * Options set for the plugin
	 *
	 * @var array
	 */
	/**
	 * Properties used for the plugin data
	 */
	private $db_data;
	/**
	 * Properties used for the plugin nonces data
	 */
	private $db_nonces;
	/**
	 * Properties used for the plugin options
	 */
	private $db_options;
	private $db_version;
	private $default_data;
	private $default_data_lists;
	private $default_data_spam;
	private $default_nonces;
	private $default_nonces_data;
	private $default_options;
	private $default_options_general;
	private $default_options_honey;
	private $default_options_ipcache;
	private $default_options_spam;
	private $default_options_spamhaus;
	private $options;
	/**
	 *
	 * @var AVH_FDAS_Settings
	 */
	private $settings;

	/**
	 * PHP5 constructor
	 */
	public function __construct() {
		$this->settings   = AVH_FDAS_Settings::getInstance();
		$this->db_version = 28;
		$this->comment    = '<!-- AVH First Defense Against Spam version ' . AVH_FDAS_Define::PLUGIN_VERSION;
		$this->db_options = 'avhfdas';
		$this->db_data    = 'avhfdas_data';
		$this->db_nonces  = 'avhfdas_nonces';
		/**
		 * Default options - General Purpose
		 */
		$this->default_options_general  = array(
			'version'            => AVH_FDAS_Define::PLUGIN_VERSION,
			'dbversion'          => $this->db_version,
			'use_sfs'            => 1,
			'use_php'            => 0,
			'use_sh'             => 0,
			'useblacklist'       => 1,
			'addblacklist'       => 0,
			'usewhitelist'       => 1,
			'diewithmessage'     => 1,
			'emailsecuritycheck' => 0,
			'useipcache'         => 0,
			'commentnonce'       => 0,
			'cron_nonces_email'  => 0,
			'cron_ipcache_email' => 0
		);
		$this->default_options_spam     = array(
			'whentoemail'     => - 1,
			'whentodie'       => 3,
			'whentodie_email' => 15,
			'sfsapikey'       => '',
			'error'           => 0
		);
		$this->default_options_honey    = array(
			'whentoemailtype' => - 1,
			'whentoemail'     => - 1,
			'whentodietype'   => 4,
			'whentodie'       => 25,
			'phpapikey'       => '',
			'usehoneypot'     => 0,
			'honeypoturl'     => ''
		);
		$this->default_options_spamhaus = array('email' => 0);
		$this->default_options_ipcache  = array('email' => 0, 'daystokeep' => 7);
		$this->default_options          = array(
			'general'  => $this->default_options_general,
			'sfs'      => $this->default_options_spam,
			'php'      => $this->default_options_honey,
			'ipcache'  => $this->default_options_ipcache,
			'spamhaus' => $this->default_options_spamhaus
		);
		/**
		 * Default Data
		 */
		$this->default_data_spam  = array('190001' => 0);
		$this->default_data_lists = array('blacklist' => '', 'whitelist' => '');
		$this->default_data       = array(
			'counters' => $this->default_data_spam,
			'lists'    => $this->default_data_lists
		);
		/**
		 * Default Nonces
		 */
		$this->default_nonces_data = null;
		$this->default_nonces      = array('default' => $this->default_nonces_data);

		/**
		 * Set the options for the program
		 */
		$this->loadOptions();
		$this->loadData();
		$this->setTables();
		// Check if we have to do upgrades
		if (( ! isset($this->options['general']['dbversion'])) || $this->getOptionElement('general',
		                                                                                  'dbversion') <
		                                                          $this->db_version
		) {
			$this->doUpgrade();
		}
		$this->settings->storeSetting('siteurl', get_option('siteurl'));
		$this->settings->storeSetting('graphics_url', plugins_url('images', $this->settings->plugin_basename));
		$this->settings->storeSetting('js_url', plugins_url('js', $this->settings->plugin_basename));
		$this->settings->storeSetting('css_url', plugins_url('css', $this->settings->plugin_basename));
		$this->settings->storeSetting('lang_dir', AVH_FDAS_Define::PLUGIN_PATH . '/lang/');
		$this->settings->storeSetting('searchengines',
		                              array(
			                              '0'  => 'Undocumented',
			                              '1'  => 'AltaVista',
			                              '2'  => 'Ask',
			                              '3'  => 'Baidu',
			                              '4'  => 'Excite',
			                              '5'  => 'Google',
			                              '6'  => 'Looksmart',
			                              '7'  => 'Lycos',
			                              '8'  => 'MSN',
			                              '9'  => 'Yahoo',
			                              '10' => 'Cuil',
			                              '11' => 'InfoSeek',
			                              '12' => 'Miscellaneous'
		                              ));

		$footer[] = '';
		$footer[] = '--';
		$footer[] = sprintf('Your blog is protected by AVH First Defense Against Spam v%s',
		                    AVH_FDAS_Define::PLUGIN_VERSION);
		$footer[] = 'http://blog.avirtualhome.com/wordpress-plugins';
		$this->settings->storeSetting('mail_footer', $footer);

		load_plugin_textdomain('avh-fdas', false, $this->settings->lang_dir);

		return;
	}

	/**
	 * Convert an array into a query string
	 *
	 * @param array  $array
	 * @param string $convention
	 *
	 * @return string
	 */
	public function BuildQuery($array = null, $convention = '%s') {
		if (count($array) == 0) {
			return '';
		} else {
			if (function_exists('http_build_query')) {
				$query = http_build_query($array);
			} else {
				$query = '';
				foreach ($array as $key => $value) {
					if (is_array($value)) {
						$new_convention = sprintf($convention, $key) . '[%s]';
						$query .= $this->BuildQuery($value, $new_convention);
					} else {
						$key   = urlencode($key);
						$value = urlencode($value);
						$query .= sprintf($convention, $key) . "=$value&";
					}
				}
			}

			return $query;
		}
	}

	/**
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	public function getComment($str = '') {
		return $this->comment . ' ' . trim($str) . ' -->';
	}

	/**
	 *
	 * @return array
	 */
	public function getData() {
		return ($this->data);
	}

	/**
	 * Get the value of a data element.
	 * If there is no value return false
	 *
	 * @param string $option
	 * @param string $key
	 *
	 * @return mixed
	 * @since 0.1
	 */
	public function getDataElement($option, $key) {
		if ($this->data[ $option ][ $key ]) {
			$return = $this->data[ $option ][ $key ];
		} else {
			$return = false;
		}

		return ($return);
	}

	/**
	 *
	 * @return string
	 */
	public function getDbNonces() {
		return $this->db_nonces;
	}

	/**
	 *
	 * @return array
	 */
	public function getDefaultNonces() {
		return $this->default_nonces;
	}

	/**
	 * Format an error message from the WP_Error response by wp_remote_request
	 *
	 * @param array $error
	 *
	 * @return string
	 */
	public function getHttpError($error) {
		if (is_array($error)) {
			foreach ($error as $key => $value) {
				$error_short = $key;
				$error_long  = $value;
				$return      = $error_short . ' - ' . $error_long;
			}
		} else {
			$return = $error;
		}

		return $return;
	}

	/**
	 * Get the value for an option element.
	 * If there's no option is set on the Admin page, return the default value.
	 *
	 * @param string $key
	 * @param string $option
	 *
	 * @return mixed
	 */
	public function getOptionElement($option, $key) {
		if (isset($this->options[ $option ][ $key ])) {
			$return = $this->options[ $option ][ $key ]; // From Admin Page
		} else {
			$return = $this->default_options[ $option ][ $key ]; // Default
		}

		return ($return);
	}

	/**
	 * return array
	 */
	public function getOptions() {
		return ($this->options);
	}

	/**
	 * Parameter for Rest Call IP Lookup
	 *
	 * @param string $ip
	 * @param string $email
	 *
	 * @return array
	 */
	public function getRestIPLookup($ip, $email = '') {
		$iplookup = array('ip' => $ip, 'f' => 'serial');
		if ( ! empty($email)) {
			$iplookup['email'] = $email;
		}

		return $iplookup;
	}

	/**
	 * Actual Rest Call
	 *
	 * @param array $query_array
	 *
	 * @return array
	 */
	public function handleRestCall($query_array) {
		$querystring = $this->BuildQuery($query_array);
		$url         = AVH_FDAS_Define::STOPFORUMSPAM_ENDPOINT . '?' . $querystring;
		// Starting with WordPress 2.7 we'll use the HTTP class.
		if (function_exists('wp_remote_request')) {
			$response = wp_remote_request($url,
			                              array(
				                              'user-agent' => 'WordPress/AVH ' .
				                                              AVH_FDAS_Define::PLUGIN_VERSION .
				                                              '; ' .
				                                              get_bloginfo('url')
			                              ));
			if ( ! is_wp_error($response)) {
				$return_array = unserialize($response['body']);
				if ( ! isset($return_array['success'])) {
					$return_array = array(
						'Error' => array(
							'Unknown Return' => 'Stop Forum Spam returned an unknow string: ' . var_export($response,
							                                                                               true)
						)
					);
				}
			} else {
				$return_array = array('Error' => $response->get_error_messages());
			}
		}

		return ($return_array);
	}

	/**
	 * *******************************
	 * *
	 * Methods for variable: options *
	 * *
	 * ******************************
	 */

	/**
	 * Save all current data to the DB
	 *
	 * @param array $data
	 *
	 */
	public function saveData($data) {
		update_option($this->db_data, $data);
		wp_cache_flush(); // Delete cache
		$this->setData($data);
	}

	/**
	 * Save all current options and set the options
	 *
	 * @param $options
	 */
	public function saveOptions($options) {
		update_option($this->db_options, $options);
		wp_cache_flush(); // Delete cache
		$this->setOptions($options);
	}

	/**
	 * Upgrade DB 23
	 *
	 * Change: Remove option to email Project Honey Pot info when Stop Forum Spam threshold is reached
	 *
	 * @param array $old_options
	 * @param array $old_data
	 *
	 * @return array
	 *
	 */
	private function _doUpgrade23($old_options, $old_data) {
		$new_options = $old_options;
		$new_data    = $old_data;
		unset($new_options['general']['emailphp']);

		return array($new_options, $new_data);
	}

	/**
	 * Checks if running version is newer and do upgrades if necessary
	 */
	private function doUpgrade() {
		$options = $this->getOptions();
		$data    = $this->getData();
		if (version_compare($options['general']['version'], '2.0-rc1', '<')) {
			list ($options, $data) = $this->doUpgrade20($options, $data);
		}
		// Introduced dbversion starting with v2.1
		if ( ! isset($options['general']['dbversion']) || $options['general']['dbversion'] < 4) {
			list ($options, $data) = $this->doUpgrade21($options, $data);
		}
		if ($options['general']['dbversion'] < 5) {
			list ($options, $data) = $this->doUpgrade22($options, $data);
		}

		if ($options['general']['dbversion'] < 23) {
			list ($options, $data) = $this->_doUpgrade23($options, $data);
		}
		if ($options['general']['dbversion'] < 26) {
			list ($options, $data) = $this->doUpgrade26($options, $data);
		}

		// Add none existing sections and/or elements to the options
		foreach ($this->default_options as $section => $default_options) {
			if ( ! array_key_exists($section, $options)) {
				$options[ $section ] = $default_options;
				continue;
			}
			foreach ($default_options as $element => $default_value) {
				if ( ! array_key_exists($element, $options[ $section ])) {
					$options[ $section ][ $element ] = $default_value;
				}
			}
		}
		// Add none existing sections and/or elements to the data
		foreach ($this->default_data as $section => $default_data) {
			if ( ! array_key_exists($section, $data)) {
				$data[ $section ] = $default_data;
				continue;
			}
			foreach ($default_data as $element => $default_value) {
				if ( ! array_key_exists($element, $data[ $section ])) {
					$data[ $section ][ $element ] = $default_value;
				}
			}
		}
		$options['general']['version']   = AVH_FDAS_Define::PLUGIN_VERSION;
		$options['general']['dbversion'] = $this->db_version;
		$this->saveOptions($options);
		$this->saveData($data);
	}

	/**
	 * Upgrade to version 2.0
	 *
	 * @param array $old_options
	 * @param array $old_data
	 *
	 * @return array
	 *
	 */
	private function doUpgrade20($old_options, $old_data) {
		$new_options = $old_options;
		$new_data    = $old_data;
		// Move elements from one section to another
		$keys = array('diewithmessage', 'useblacklist', 'usewhitelist', 'emailsecuritycheck');
		foreach ($keys as $value) {
			$new_options['general'][ $value ] = $old_options['spam'][ $value ];
			unset($new_options['spam'][ $value ]);
		}
		// Move elements from options to data
		$keys = array('blacklist', 'whitelist');
		foreach ($keys as $value) {
			$new_data['lists'][ $value ] = $old_options['spam'][ $value ];
			unset($new_options['spam'][ $value ]);
		}
		// Section renamed
		$new_options['sfs'] = $new_options['spam'];
		unset($new_options['spam']);
		// New counter system
		unset($new_data['spam']['counter']);

		return array($new_options, $new_data);
	}

	/**
	 * Upgrade to version 2.1
	 *
	 * @param array $old_options
	 * @param array $old_data
	 *
	 * @return array
	 *
	 */
	private function doUpgrade21($old_options, $old_data) {
		$new_options = $old_options;
		$new_data    = $old_data;
		// Changed Administrative capabilties names
		$role = get_role('administrator');
		if ($role != null && $role->has_cap('avh_fdas')) {
			$role->remove_cap('avh_fdas');
			$role->add_cap('avh_fdas_user_fdas');
		}
		if ($role != null && $role->has_cap('admin_avh_fdas')) {
			$role->remove_cap('admin_avh_fdas');
			$role->add_cap('avh_fdas_admin');
		}

		return array($new_options, $new_data);
	}

	/**
	 * ****************************
	 * *
	 * Methods for variable: data *
	 * *
	 * ***************************
	 */

	/**
	 * Upgrade to version 2.2
	 *
	 * @param $old_options
	 * @param $old_data
	 *
	 * @return array
	 */
	private function doUpgrade22($old_options, $old_data) {
		global $wpdb;
		$new_options = $old_options;
		$new_data    = $old_data;
		$sql         = 'ALTER TABLE `' . $wpdb->avhfdasipcache . '`
				CHANGE COLUMN `date` `added` DATETIME  NOT null DEFAULT \'0000-00-00 00:00:00\',
				ADD COLUMN `lastseen` DATETIME  NOT null DEFAULT \'0000-00-00 00:00:00\' AFTER `added`,
				DROP INDEX `date`,
				ADD INDEX `added`(`added`),
				ADD INDEX `lastseen`(`lastseen`);';
		$wpdb->query($sql);
		$sql = 'UPDATE ' . $wpdb->avhfdasipcache . ' SET `lastseen` = `added`;';
		$wpdb->query($sql);

		return array($new_options, $new_data);
	}

	private function doUpgrade26($old_options, $old_data) {
		global $wp_roles;
		$new_options = $old_options;
		$new_data    = $old_data;
		if ( ! isset($wp_roles)) {
			$wp_roles = new WP_Roles();
		}

		foreach ($wp_roles->roles as $wp_role => $info) {
			$role = get_role($wp_role);
			if ($role != null && $role->has_cap('role_admin_avh_fdas')) {
				$role->remove_cap('role_admin_avh_fdas');
			}
			if ($role != null && $role->has_cap('role_avh_fdas')) {
				$role->remove_cap('role_avh_fdas');
				$role->add_cap('avh_fdas_admin');
			}
		}

		return array($new_options, $new_data);
	}

	/**
	 * Retrieve the data from the DB
	 *
	 * @return array
	 */
	private function loadData() {
		$data = get_option($this->db_data);
		if (false === $data) { // New installation
			$this->resetToDefaultData();
		} else {
			$this->setData($data);
		}

		return;
	}

	/**
	 * Retrieves the plugin options from the WordPress options table and assigns to class variable.
	 * If the options do not exists, like a new installation, the options are set to the default value.
	 *
	 */
	private function loadOptions() {
		$options = get_option($this->db_options);
		if (false === $options) { // New installation
			$this->resetToDefaultOptions();
		} else {
			$this->setOptions($options);
		}
	}

	/**
	 * Reset to default data and save in DB
	 */
	private function resetToDefaultData() {
		$this->data = $this->default_data;
		$this->saveData($this->default_data);
	}

	/**
	 * Reset to default options and save in DB
	 */
	private function resetToDefaultOptions() {
		$this->options = $this->default_options;
		$this->saveOptions($this->default_options);
	}

	/**
	 *
	 * @param array $data
	 */
	private function setData($data) {
		$this->data = $data;
	}

	/**
	 *
	 * @param $options
	 *
	 */
	private function setOptions($options) {
		$this->options = $options;
	}

	/**
	 * Setup DB Tables
	 *
	 */
	private function setTables() {
		global $wpdb;
		// add DB pointer
		$wpdb->avhfdasipcache = $wpdb->prefix . 'avhfdas_ipcache';
	}
} //End Class AVH_FDAS_Core
