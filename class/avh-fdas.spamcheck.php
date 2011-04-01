<?php
if (! defined('AVH_FRAMEWORK'))
	die('You are not allowed to call this page directly.');

class AVH_FDAS_SpamCheck
{
	/**
	 *
	 * @var AVH_FDAS_Core
	 */
	private $_core;
	/**
	 * @var AVH_Settings_Registry
	 */
	private $_settings;
	/**
	 * @var AVH_Class_registry
	 */
	private $_classes;
	/**
	 *
	 * @var AVH_FDAS_DB
	 */
	private $_ipcachedb;
	private $_visiting_ip;
	private $_core_options;
	private $_core_data;
	private $_accessing;
	private $_spamcheck_functions_array;
	private $_spammer_detected;
	private $_ip_in_white_list;
	private $_ip_in_cache;
	private $_spaminfo;
	private $_doing_sfs;

	/**
	 * PHP5 Constructor
	 *
	 */
	public function __construct ()
	{
		// Get The Registry
		$this->_settings = AVH_FDAS_Settings::getInstance();
		$this->_classes = AVH_FDAS_Classes::getInstance();
		// Initialize the plugin
		$this->_core = $this->_classes->load_class('Core', 'plugin', true);
		$this->_ipcachedb = $this->_classes->load_class('DB', 'plugin', true);
		$this->_visiting_ip = AVH_Visitor::getUserIp();
		$this->_core_options = $this->_core->getOptions();
		$this->_core_data = $this->_core->getData();
		$this->_spaminfo = null;
		$this->_spammer_detected = false;
		$this->_ip_in_white_list = false;
		$this->_ip_in_cache = false;
		$this->_spamcheck_functions_array[00] = 'Blacklist';
		$this->_spamcheck_functions_array[02] = 'IpCache';
		$this->_spamcheck_functions_array[05] = 'StopForumSpam';
		$this->_spamcheck_functions_array[10] = 'ProjectHoneyPot';
		$this->_spamcheck_functions_array[11] = 'Spamhaus';
	}

	/**
	 *
	 * Run all the checks for the main action.
	 * We don't check with Stop Forum Spam as this overloads their site.
	 *
	 */
	public function doSpamcheckMain ()
	{
		if ($this->_visiting_ip != '0.0.0.0') { // Visiting IP is a private IP, we don't check private IP's
			unset($this->_spamcheck_functions_array[05]); // @TODO make this more flexible
			$this->_doSpamCheckFunctions();
			$this->_spamcheck_functions_array[05] = array(&$this, '_doIpCheckStopForumSpam');
		}
	}

	/**
	 * Run the checks for the action pre_comment_on_post.
	 *
	 */
	public function doSpamcheckPreCommentPost ()
	{
		if ($this->_visiting_ip != '0.0.0.0') { // Visiting IP is a private IP, we don't check private IP's
			$this->_doSpamCheckFunctions();
		}
	}

	/**
	 * Run the checks for the action register_post.
	 *
	 */
	public function doSpamcheckUserRegister ()
	{
		if ($this->_visiting_ip != '0.0.0.0') { // Visiting IP is a private IP, we don't check private IP's
			$this->_doSpamCheckFunctions();
		}
	}

	/**
	 * Check the cache for the IP
	 *
	 */
	private function _doIpCheckCache ()
	{
		if (1 == $this->_core_options['general']['useipcache']) {
			$time_start = microtime(true);
			$this->_ip_in_cache = $this->_ipcachedb->getIP($this->_visiting_ip);
			$time_end = microtime(true);
			$time = $time_end - $time_start;
			if (! (false === $this->_ip_in_cache)) {
				if ($this->_ip_in_cache->spam == "1") {
					$this->_spaminfo['cache']['time'] = $time;
					$this->_spammer_detected = true;
				}
			}
		}
	}

	/**
	 * Run through all the functions that will do spamchecking.
	 *
	 */
	private function _doSpamCheckFunctions ()
	{
		$this->_spammer_detected = false;
		ksort($this->_spamcheck_functions_array);
		
		$_did_sfs = false;
		// We're not checking with Stop Forum Spam. We set the value as if we did.
		if (! in_array('StopForumSpam', $this->_spamcheck_functions_array)) {
			$_did_sfs == true;
		}
		
		$this->_checkWhitelist();
		if ($this->_ip_in_white_list === false) {
			foreach ($this->_spamcheck_functions_array as $key => $spam_check) {
				// Hardcode the built in Spam Check options as this is faster then using call_user_func.
				switch ($spam_check) {
					case 'Blacklist':
						$this->_checkBlacklist();
						break;
					case 'IpCache':
						$this->_doIpCheckCache();
						break;
					case 'StopForumSpam':
						$this->_doIpCheckStopForumSpam();
						$_did_sfs = true;
						break;
					case 'ProjectHoneyPot':
						$this->_doIpCheckProjectHoneyPot();
						break;
					case 'Spamhaus':
						$this->_doIpCheckSpamhaus();
						break;
					default:
						call_user_func($spam_check);
						break;
				}
				if ($this->_spammer_detected || ($_did_sfs && is_object($this->_ip_in_cache ))) {
					if( is_object($this->_ip_in_cache ) || $this->_checkTerminateConnection()) {
						break;
					}
				}
			}
			$this->_handleResults();
		}
	}

	/**
	 * Do Project Honey Pot with Visitor
	 *
	 * Sets the _spaminfo['detected'] to true when a spammer is detected.
	 *
	 */
	private function _doIpCheckProjectHoneyPot ()
	{
		if ($this->_core_options['general']['use_php']) {
			$reverse_ip = implode('.', array_reverse(explode('.', $this->_visiting_ip)));
			$projecthoneypot_api_key = $this->_core_options['php']['phpapikey'];
			$this->_spaminfo['php'] = null;
			//
			// Check the IP against projecthoneypot.org
			//
			$time_start = microtime(true);
			$lookup = $projecthoneypot_api_key . '.' . $reverse_ip . '.dnsbl.httpbl.org.';
			$info = explode('.', gethostbyname($lookup));
			// The first octet needs to be 127.
			// Quote from the HTTPBL Api documentation: If the first octet in the response is not 127 it means an error condition has occurred and your query may not have been formatted correctly.
			// Reference :http://www.projecthoneypot.org/httpbl_api.php
			if ('127' == $info[0]) {
				$this->_spammer_detected = true;
				$time_end = microtime(true);
				$time = $time_end - $time_start;
				$this->_spaminfo['php']['time'] = $time;
				$this->_spaminfo['php']['days'] = $info[1];
				$this->_spaminfo['php']['type'] = $info[3];
				if ('0' == $info[3]) {
					$this->_spaminfo['php']['score'] = '0';
					$this->_spaminfo['php']['engine'] = $this->_settings->searchengines[$info[2]];
				} else {
					$this->_spaminfo['php']['score'] = $info[2];
				}
			}
		}
	}

	/**
	 * Do Project Honey Pot with Visitor
	 *
	 * Sets the _spaminfo['detected'] to true when a spammer is detected.
	 *
	 */
	private function _doIpCheckSpamhaus ()
	{
		if ($this->_core_options['general']['use_sh']) {
			$reverse_ip = implode('.', array_reverse(explode('.', $this->_visiting_ip)));
			$this->_spaminfo['sh'] = null;
			//
			// Check the IP against spamhaus.org
			//
			$time_start = microtime(true);
			$lookup = $reverse_ip . '.zen.spamhaus.org.';
			$info = explode('.', gethostbyname($lookup));
			if ('127' == $info[0] && (int) $info[3] < 10) {
				$this->_spammer_detected = true;
				$time_end = microtime(true);
				$time = $time_end - $time_start;
				$this->_spaminfo['sh']['time'] = $time;
				if ($info[3] == "2" || $info[3] == "3") {
					$this->_spaminfo['sh']['which'] = 'The Spamhaus Block List';
				}
				if ((int) $info[3] >= 4 && (int) $info[3] <= 7) {
					$this->_spaminfo['sh']['which'] = 'Exploits Block List';
				}
			}
		}
	}

	/**
	 * Function to handle everything when a potential spammer is detected.
	 *
	 */
	private function _handleResults ()
	{
		global $post;
		if (true === $this->_spammer_detected) {
			if ('/wp-comments-post.php' == $_SERVER['REQUEST_URI']) {
				$title = isset($post->post_title) ? $post->post_title : '';
				$id = isset($post->ID) ? $post->ID : 0;
				// Trying to post a comment, lets determine which post they are trying to post at
				$this->_accessing = sprintf(__('Commenting on:	"%s" ( %s )', 'avh-fdas'), apply_filters('the_title', $title, $id), get_permalink($post->ID));
			} else {
				$this->_accessing = sprintf(__('Accessing:	%s', 'avh-fdas'), $_SERVER['REQUEST_URI']);
			}
			/*if (!empty($_POST)) {
				$this->_accessing .= "\n".'$_POST data'."\n";
				foreach ($_POST as $key => $value) {
					$this->_accessing .= $key .' => '.$value."\n";
				}
			}*/
			if (is_object($this->_ip_in_cache)) {
				$this->_handleSpammerCache();
			} else {
				$this->_handleSpammer();
			}
		} else {
			if (is_object($this->_ip_in_cache)) {
				$this->_ipcachedb->updateIpCache(array('ip'=>$this->_visiting_ip, 'lastseen'=>current_time('mysql')));
			} else {
				if (1 == $this->_core_options['general']['useipcache']) {
					$this->_ipcachedb->insertIp($this->_visiting_ip, 0);
				}
			}
		}
	}

	/**
	 * Convert the Stop Forum Spam data to something I already was using.
	 *
	 * @param $data
	 */
	private function _convertStopForumSpamCall ($data)
	{
		if (isset($data['Error'])) {
			return ($data);
		}
		if (isset($data['ip'])) {
			return ($data['ip']);
		}
		return (array('Error'=>array('Unknown Return'=>'Stop Forum Spam returned an unknow string: ' . var_export($data, true))));
	}

	/**
	 * Check an IP with Stop Forum Spam
	 *
	 * @param $ip Visitor's IP
	 * @return $_spaminfo Query result
	 */
	private function _doIpCheckStopForumSpam ()
	{
		if ($this->_core_options['general']['use_sfs']) {
			$time_start = microtime(true);
			$result = $this->_core->handleRestCall($this->_core->getRestIPLookup($this->_visiting_ip));
			$time_end = microtime(true);
			$this->_spaminfo['sfs'] = $this->_convertStopForumSpamCall($result);
			$time = $time_end - $time_start;
			$this->_spaminfo['sfs']['time'] = $time;
			if (isset($this->_spaminfo['sfs']['Error'])) {
				if ($this->_core_options['sfs']['error']) {
					$error = $this->_core->getHttpError($this->_spaminfo['sfs']['Error']);
					$to = get_option('admin_email');
					$subject = sprintf('[%s] AVH First Defense Against Spam - ' . __('Error detected', 'avh-fdas'), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));
					$message[] = __('An error has been detected', 'avh-fdas');
					$message[] = sprintf(__('Error:	%s', 'avh-fdas'), $error);
					$message[] = '';
					$message[] = sprintf(__('IP:		%s', 'avh-fdas'), $this->_visiting_ip);
					$message[] = sprintf(__('Accessing:	%s', 'avh-fdas'), $_SERVER['REQUEST_URI']);
					$message[] = sprintf(__('Call took:	%s', 'avh-fdas'), $time);
					AVH_Common::sendMail($to, $subject, $message, $this->_settings->getSetting('mail_footer'));
				}
				$this->_spaminfo['sfs'] = null;
			} else {
				if (1 == $this->_spaminfo['sfs']['appears']) {
					$this->_spammer_detected = true;
				}
			}
		}
	}

	/**
	 * Check blacklist table
	 *
	 * @param string $ip
	 */
	private function _checkBlacklist ()
	{
		if ($this->_core_options['general']['useblacklist']) {
			$found = $this->_checkList($this->_core->getDataElement('lists', 'blacklist'));
			if ($found) {
				$this->_spammer_detected = true;
				$this->_spaminfo['blacklist']['time'] = 'Blacklisted';
			}
		}
	}

	/**
	 * Check the White list table. Return true if in the table
	 *
	 * @param string $ip
	 * @return boolean
	 *
	 * @since 1.1
	 */
	private function _checkWhitelist ()
	{
		if ($this->_core_options['general']['usewhitelist']) {
			$found = $this->_checkList($this->_core->getDataElement('lists', 'whitelist'));
			if ($found) {
				$this->_ip_in_white_list = true;
			}
		}
	}

	/**
	 * Check if an IP exists in a list
	 *
	 * @param string $ip
	 * @param string $list
	 * @return boolean
	 *
	 */
	private function _checkList ($list)
	{
		$list = explode("\r\n", $list);
		// Check for single IP's, this is much quicker as going through the list
		$inlist = in_array($this->_visiting_ip, $list) ? true : false;
		if (! $inlist) { // Not found yet
			foreach ($list as $check) {
				if ($this->_checkNetworkMatch($check)) {
					$inlist = true;
					break;
				}
			}
		}
		return ($inlist);
	}

	/**
	 * Check if an IP exist in a range
	 * Range can be formatted as:
	 * ip-ip (192.168.1.100-192.168.1.103)
	 * ip/mask (192.168.1.0/24)
	 *
	 * @param string $network
	 * @param string $ip
	 * @return boolean
	 */
	private function _checkNetworkMatch ($network)
	{
		$return = false;
		$network = trim($network);
		$ip = trim($this->_visiting_ip);
		$d = strpos($network, '-');
		if ($d === false) {
			$ip_arr = explode('/', $network);
			if (isset($ip_arr[1])) {
				$network_long = ip2long($ip_arr[0]);
				$x = ip2long($ip_arr[1]);
				$mask = long2ip($x) == $ip_arr[1] ? $x : (0xffffffff << (32 - $ip_arr[1]));
				$ip_long = ip2long($ip);
				$return = ($ip_long & $mask) == ($network_long & $mask);
			}
		} else {
			$from = ip2long(trim(substr($network, 0, $d)));
			$to = ip2long(trim(substr($network, $d + 1)));
			$ip = ip2long($ip);
			$return = ($ip >= $from and $ip <= $to);
		}
		return ($return);
	}

	/**
	 * Handle a known spam IP found by the 3rd party
	 *
	 * @param string $ip - The spammers IP
	 * @param array $info - Information
	 *
	 */
	private function _handleSpammer ()
	{
		// Email
		$sfs_email = isset($this->_spaminfo['sfs']) && (int) $this->_core_options['sfs']['whentoemail'] >= 0 && (int) $this->_spaminfo['sfs']['frequency'] >= $this->_core_options['sfs']['whentoemail'];
		$php_email = isset($this->_spaminfo['php']) && (int) $this->_core_options['php']['whentoemail'] >= 0 && $this->_spaminfo['php']['type'] >= $this->_core_options['php']['whentoemailtype'] && (int) $this->_spaminfo['php']['score'] >= $this->_core_options['php']['whentoemail'];
		$sh_email = isset($this->_spaminfo['sh']);
		if ($sfs_email || $php_email || $sh_email) {
			// General part of the email
			$to = get_option('admin_email');
			$subject = sprintf('[%s] AVH First Defense Against Spam - ' . __('Spammer detected [%s]', 'avh-fdas'), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES), $this->_visiting_ip);
			$message[] = sprintf(__('Spam IP:	%s', 'avh-fdas'), $this->_visiting_ip);
			$message[] = $this->_accessing;
			$message[] = '';
			// Stop Forum Spam Mail Part
			if ($sfs_email) {
				if ($this->_spaminfo['sfs']['appears']) {
					$message[] = __('Checked at Stop Forum Spam', 'avh-fdas');
					$message[] = '	' . __('Information', 'avh-fdas');
					$message[] = '	' . sprintf(__('Last Seen:	%s', 'avh-fdas'), $this->_spaminfo['sfs']['lastseen']);
					$message[] = '	' . sprintf(__('Frequency:	%s', 'avh-fdas'), $this->_spaminfo['sfs']['frequency']);
					$message[] = '	' . sprintf(__('Call took:	%s', 'avhafdas'), $this->_spaminfo['sfs']['time']);
					if ($this->_spaminfo['sfs']['frequency'] >= $this->_core_options['sfs']['whentodie']) {
						$message[] = '	' . sprintf(__('Threshold (%s) reached. Connection terminated', 'avh-fdas'), $this->_core_options['sfs']['whentodie']);
					}
				} else {
					$message[] = __('Stop Forum Spam has no information', 'avh-fdas');
				}
				$message[] = '';
				$message[] = sprintf(__('For more information: http://www.stopforumspam.com/search?q=%s'), $this->_visiting_ip);
				$message[] = '';
			}

			// Project Honey pot Mail Part
			if ($php_email) {
				if ($this->_spaminfo['php'] != null) {
					$message[] = __('Checked at Project Honey Pot', 'avh-fdas');
					$message[] = '	' . __('Information', 'avh-fdas');
					$message[] = '	' . sprintf(__('Days since last activity:	%s', 'avh-fdas'), $this->_spaminfo['php']['days']);
					switch ($this->_spaminfo['php']['type']) {
						case "0":
							$type = "Search Engine";
							break;
						case "1":
							$type = "Suspicious";
							break;
						case "2":
							$type = "Harvester";
							break;
						case "3":
							$type = "Suspicious & Harvester";
							break;
						case "4":
							$type = "Comment Spammer";
							break;
						case "5":
							$type = "Suspicious & Comment Spammer";
							break;
						case "6":
							$type = "Harvester & Comment Spammer";
							break;
						case "7":
							$type = "Suspicious & Harvester & Comment Spammer";
							break;
					}
					$message[] = '	' . sprintf(__('Type:				%s', 'avh-fdas'), $type);
					if (0 == $this->_spaminfo['php']['type']) {
						$message[] = '	' . sprintf(__('Search Engine:	%s', 'avh-fdas'), $this->_spaminfo['php']['engine']);
					} else {
						$message[] = '	' . sprintf(__('Score:				%s', 'avh-fdas'), $this->_spaminfo['php']['score']);
					}
					$message[] = '	' . sprintf(__('Call took:			%s', 'avhafdas'), $this->_spaminfo['php']['time']);
					if ($this->_spaminfo['php']['score'] >= $this->_core_options['php']['whentodie'] && $this->_spaminfo['php']['type'] >= $this->_core_options['php']['whentodietype']) {
						$message[] = '	' . sprintf(__('Threshold score (%s) and type (%s) reached. Connection terminated', 'avh-fdas'), $this->_core_options['php']['whentodie'], $type);
					}
				} else {
					$message[] = __('Project Honey Pot has no information', 'avh-fdas');
				}
				$message[] = '';
			}
			// Spamhaus Mail part
			if ($sh_email) {
				if ($this->_spaminfo['sh'] != null) {
					$message[] = __('IP found at Spamhaus', 'avh-fdas');
					$message[] = '	' . __('Information', 'avh-fdas');
					$message[] = '	' . sprintf(__('Classification:		%s.', 'avh-fdas'), $this->_spaminfo['sh']['which']);
					$message[] = '	' . sprintf(__('Call took:		%s', 'avhafdas'), $this->_spaminfo['sh']['time']);
					$message[] = '	' . __('Connection terminated', 'avh-fdas');
				} else {
					$message[] = __('Spamhaus has no information', 'avh-fdas');
				}
				$message[] = '';
			}
			// General End
			if (! isset($this->_spaminfo['blacklist'])) {
				$blacklisturl = admin_url('admin.php?action=blacklist&i=') . $this->_visiting_ip . '&_avhnonce=' . AVH_Security::createNonce($this->_visiting_ip);
				$message[] = sprintf(__('Add to the local blacklist: %s'), $blacklisturl);
			}
			AVH_Common::sendMail($to, $subject, $message, $this->_settings->getSetting('mail_footer'));
		}
		// Check if we have to terminate the connection.
		// This should be the very last option.
		$_die = $this->_checkTerminateConnection();
		
		if (_die) {
			if (1 == $this->_core_options['general']['useipcache']) {
				$this->_ipcachedb->insertIp($this->_visiting_ip, 1);
			}
				
			// Update the counter
			$this->_updateSpamCounter();
			// Terminate the connection
			$this->_doTerminateConnection();
		}
	}

	/**
	 * Handle a spammer found in the IP cache
	 * @param $info
	 * @return unknown_type
	 */
	private function _handleSpammerCache ()
	{
		if ($this->_core_options['ipcache']['email']) {
			// General part of the email
			$to = get_option('admin_email');
			$subject = sprintf('[%s] AVH First Defense Against Spam - ' . __('Spammer detected [%s]', 'avh-fdas'), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES), $this->_visiting_ip);
			$message = array();
			$message[] = sprintf(__('Spam IP:	%s', 'avh-fdas'), $this->_visiting_ip);
			$message[] = $this->_accessing;
			$message[] = '';
			$message[] = __('IP exists in the cache', 'avh-fdas');
			$message[] = '	' . sprintf(__('Check took:			%s', 'avh-fdas'), $this->_spaminfo['cache']['time']);
			$message[] = '';
			// General End
			$blacklisturl = admin_url('admin.php?action=blacklist&i=') . $this->_visiting_ip . '&_avhnonce=' . AVH_Security::createNonce($this->_visiting_ip);
			$message[] = sprintf(__('Add to the local blacklist: %s'), $blacklisturl);
			AVH_Common::sendMail($to, $subject, $message, $this->_settings->getSetting('mail_footer'));
		}
		// Update the counter
		$this->_updateSpamCounter();
		// Update Last seen value
		$this->_ipcachedb->updateIpCache(array('ip'=>$this->_visiting_ip, 'lastseen'=>current_time('mysql')));
		// Terminate the connection
		$this->_doTerminateConnection();
	}

	/**
	 *
	 * Updates the spam counter
	 */
	private function _updateSpamCounter ()
	{
		// Update the counter
		$period = date('Ym');
		if (array_key_exists($period, $this->_core_data['counters'])) {
			$this->_core_data['counters'][$period] += 1;
		} else {
			$this->_core_data['counters'][$period] = 1;
		}
		$this->_core->saveData($this->_core_data);
	}

	private function _checkTerminateConnection(){
		$sfs_die = isset($this->_spaminfo['sfs']) && $this->_spaminfo['sfs']['frequency'] >= $this->_core_options['sfs']['whentodie'];
		$php_die = isset($this->_spaminfo['php']) && $this->_spaminfo['php']['type'] >= $this->_core_options['php']['whentodietype'] && $this->_spaminfo['php']['score'] >= $this->_core_options['php']['whentodie'];
		$sh_die = isset($this->_spaminfo['sh']);
		$blacklist_die = (isset($this->_spaminfo['blacklist']) && 'Blacklisted' == $this->_spaminfo['blacklist']['time']);
		
		return ($sfs_die || $php_die || $sh_die || $blacklist_die);
	}
	/**
	 *
	 * Terminates the connection.
	 */
	private function _doTerminateConnection ()
	{
		/**
		 * This tells the following plugins to not cache this page
		 * W3 Total cache
		 * WP-Supercache
		 */
		define('DONOTCACHEPAGE', true);
		if (1 == $this->_core_options['general']['diewithmessage']) {
			if (is_object($this->_ip_in_cache)) {
				$m = sprintf('<h1>' . __('Access has been blocked.', 'avh-fdas') . '</h1><p>' . __('Your IP [%s] has been identified as spam', 'avh-fdas') . '</p>', $this->_visiting_ip);
			} else {
				if (isset($this->_spaminfo['blacklist']) && 'Blacklisted' == $this->_spaminfo['blacklist']['time']) {
					$m = sprintf('<h1>' . __('Access has been blocked.', 'avh-fdas') . '</h1><p>' . __('Your IP [%s] is registered in our <em>Blacklisted</em> database.', 'avh-fdas') . '<BR /></p>', $this->_visiting_ip);
				} else {
					$where = '';
					if (isset($this->_spaminfo['sfs'])) {
						$where .= 'Stop Forum Spam ';
					}
					if (isset($this->_spaminfo['php'])) {
						$where .= ($where == '' ? '' : 'and ');
						$where .= 'Project Honey Pot ';
					}
					if (isset($this->_spaminfo['sh'])) {
						$where .= ($where == '' ? '' : 'and ');
						$where .= 'Spamhaus ';
					}
					$m = sprintf('<h1>' . __('Access has been blocked.', 'avh-fdas') . '</h1><p>' . __('Your IP [%s] is found at %s.', 'avh-fdas') . '<BR />' . __('If you feel this is incorrect please contact them.', 'avh-fdas') . '</p>', $this->_visiting_ip, $where);
				}
			}
			$m .= '<p>' . __('Protected by: ', 'avh-fdas') . 'AVH First Defense Against Spam</p>';
			if ($this->_core_options['php']['usehoneypot']) {
				$m .= $this->getHtmlHoneyPotUrl();
			}
			wp_die($m);
		} else {
			die();
		}
	}

	/**
	 *
	 * Display the honeypot URL
	 */
	public function getHtmlHoneyPotUrl ()
	{
		return ('<p><div style="display: none;"><a href="' . $this->_core->getOptionElement('php', 'honeypoturl') . '">AVH Software</a></div></p>');
	}
}