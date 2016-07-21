<?php
if ( ! defined('AVH_FRAMEWORK')) {
	die('You are not allowed to call this page directly.');
}

class AVH_FDAS_SpamCheck {
	private $accessing;
	/**
	 *
	 * @var AVH_Class_registry
	 */
	private $classes;
	/**
	 *
	 * @var AVH_FDAS_Core
	 */
	private $core;
	private $core_data;
	private $core_options;
	private $ip_in_cache;
	private $ip_in_white_list;
	/**
	 *
	 * @var AVH_FDAS_DB
	 */
	private $ipcachedb;
	/**
	 *
	 * @var AVH_Settings_Registry
	 */
	private $settings;
	private $spamcheck_functions_array;
	private $spaminfo;
	private $spammer_detected;
	private $visiting_email;
	private $visiting_ip;

	/**
	 * PHP5 Constructor
	 */
	public function __construct() {
		// Get The Registry
		$this->settings = AVH_FDAS_Settings::getInstance();
		$this->classes  = AVH_FDAS_Classes::getInstance();
		// Initialize the plugin
		$this->core                          = $this->classes->load_class('Core', 'plugin', true);
		$this->ipcachedb                     = $this->classes->load_class('DB', 'plugin', true);
		$this->visiting_ip                   = AVH_Visitor::getUserIp();
		$this->visiting_email                = '';
		$this->core_options                  = $this->core->getOptions();
		$this->core_data                     = $this->core->getData();
		$this->spaminfo                      = null;
		$this->spammer_detected              = false;
		$this->ip_in_white_list              = false;
		$this->ip_in_cache                   = false;
		$this->spamcheck_functions_array[00] = 'Blacklist';
		$this->spamcheck_functions_array[02] = 'IpCache';
		$this->spamcheck_functions_array[05] = 'StopForumSpam';
		$this->spamcheck_functions_array[10] = 'ProjectHoneyPot';
		$this->spamcheck_functions_array[11] = 'Spamhaus';
	}

	/**
	 * Run all the checks for the main action.
	 * We don't check with Stop Forum Spam as this overloads their site.
	 */
	public function doSpamcheckMain() {
		if ($this->visiting_ip != '0.0.0.0') { // Visiting IP is a private IP, we don't check private IP's
			unset($this->spamcheck_functions_array[05]); // @TODO make this more flexible
			$this->doSpamCheckFunctions();
			$this->handleResults();
			$this->spamcheck_functions_array[05] = 'StopForumSpam';
		}
	}

	/**
	 * Run the checks for the action pre_comment_on_post.
	 */
	public function doSpamcheckPreCommentPost() {
		if ($this->visiting_ip != '0.0.0.0') { // Visiting IP is a private IP, we don't check private IP's
			$this->checkHttpReferer();
			$this->doSpamCheckFunctions();
			$this->handleResults();
		}
	}

	/**
	 * Run the checks for the action register_post.
	 *
	 * @param $errors
	 *
	 * @return WP_Error
	 */
	public function doSpamcheckUserRegister($errors) {
		if ($this->visiting_ip != '0.0.0.0') { // Visiting IP is a private IP, we don't check private IP's
			$this->doSpamCheckFunctions();
			if (true === $this->spammer_detected) {
				if (is_wp_error($errors)) {
					$errors->add('spammer_detected', '<strong>ERROR</strong>: Invalid Credentials');
				} else {
					$errors = new WP_Error('spammer_detected', '<strong>ERROR</strong>: Invalid Credentials');
				}
			}
		}

		return $errors;
	}

	/**
	 * Display the honeypot URL
	 */
	public function getHtmlHoneyPotUrl() {
		$url         = $this->core->getOptionElement('php', 'honeypoturl');
		$words       = array(
			'intermittently',
			'tawse',
			'goldurn',
			'coemption',
			'semipurposive',
			'tensibly',
			'dissident',
			'reductive',
			'plowstaff',
			'sprang',
			'intersoluble',
			'mildly',
			'unrumpled',
			'freeway',
			'overappreciative',
			'prealliance',
			'hypercoagulability',
			'makalu',
			'aspersive',
			'colleagueship',
			'feminacy',
			'cuirie',
			'vanir',
			'unvitalized',
			'noncreativity',
			'interproportional',
			'areosystyle',
			'exsolve',
			'replow',
			'septuor',
			'comptrollership',
			'mortarless',
			'ruddily',
			'find',
			'poppy',
			'knowledgeless',
			'amenorrheal',
			'referenced',
			'veranda',
			'parishad',
			'lexeme',
			'expediency',
			'anemotropism',
			'bangalay',
			'complexional',
			'uneminent',
			'stephenville',
			'lozenge',
			'archiepiscopacy',
			'propitiable'
		);
		$keys        = array_rand($words, 2);
		$text        = $words[ $keys[0] ] . '-' . $words[ $keys[1] ];
		$url_array[] = '<div style="display: none;"><a href="%s">%s</a></div>';
		$url_array[] = '<a href="%s" style="display: none;">%s</a>';
		$url_array[] = '<a href="%s"><span style="display: none;">%s</span></a>';
		$url_array[] = '<a href="%s"><!-- %s --></a>';
		$url_array[] = '<!-- <a href="%s">%s</a> -->';
		$url_array[] = '<div style="position: absolute; top: -250px; left: -250px;"><a href="%s">%s</a></div>';
		$url_array[] = '<a href="%s"><span style="display: none;">%s</span></a>';
		$full_url    = sprintf($url_array[ array_rand($url_array) ], $url, $text);

		return ($full_url);
	}

	/**
	 * @return string
	 */
	public function getVisiting_email() {
		return $this->visiting_email;
	}

	/**
	 *
	 * @param string $visiting_email
	 */
	public function setVisiting_email($visiting_email) {
		$this->visiting_email = $visiting_email;
	}

	/**
	 * Check blacklist table
	 *
	 */
	private function checkBlacklist() {
		if ($this->core_options['general']['useblacklist']) {
			$found = $this->checkList($this->core->getDataElement('lists', 'blacklist'));
			if ($found) {
				$this->spammer_detected              = true;
				$this->spaminfo['blacklist']['time'] = 'Blacklisted';
			}
		}
	}

	private function checkHttpReferer() {
		if ($this->visiting_ip != '0.0.0.0') { // Visiting IP is a private IP, we don't check private IP's
			$die = false;
			if ( ! isset($_SERVER['HTTP_REFERER'])) {
				$die = true;
			}
			if ($die) {
				if (1 == $this->core_options['general']['useipcache']) {
					$this->ip_in_cache = $this->ipcachedb->getIP($this->visiting_ip);
					if (is_object($this->ip_in_cache)) {
						$this->ipcachedb->updateIpCache(array(
							                                'ip'       => $this->visiting_ip,
							                                'spam'     => 1,
							                                'lastseen' => current_time('mysql')
						                                ));
					} else {
						$this->ipcachedb->insertIp($this->visiting_ip, 1);
					}
				}
				// Update the counter
				$this->updateSpamCounter();
				$this->setNoCaching();
				if (1 == $this->core_options['general']['diewithmessage']) {
					$m = sprintf('<h1>' .
					             __('Access has been blocked.', 'avh-fdas') .
					             '</h1><p>' .
					             __('You are trying to beat the system.',
					                'avh-fdas') .
					             '<BR />');
					$m .= '<p>' . __('Protected by: ', 'avh-fdas') . 'AVH First Defense Against Spam</p>';
					if ($this->core_options['php']['usehoneypot']) {
						$m .= $this->getHtmlHoneyPotUrl();
					}
					wp_die($m);
				} else {
					die();
				}
			}
		}
	}

	/**
	 * Check if an IP exists in a list
	 *
	 * @param string $list
	 *
	 * @return boolean
	 *
	 */
	private function checkList($list) {
		$list = explode("\r\n", $list);
		// Check for single IP's, this is much quicker as going through the list
		$inlist = in_array($this->visiting_ip, $list) ? true : false;
		if ( ! $inlist) { // Not found yet
			foreach ($list as $check) {
				if ($this->checkNetworkMatch($check)) {
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
	 *
	 * @return boolean
	 */
	private function checkNetworkMatch($network) {
		$return  = false;
		$network = trim($network);
		$ip      = trim($this->visiting_ip);
		$d       = strpos($network, '-');
		if ($d === false) {
			$ip_arr = explode('/', $network);
			if (isset($ip_arr[1])) {
				$network_long = ip2long($ip_arr[0]);
				$x            = ip2long($ip_arr[1]);
				$mask         = long2ip($x) == $ip_arr[1] ? $x : (0xffffffff << (32 - $ip_arr[1]));
				$ip_long      = ip2long($ip);
				$return       = ($ip_long & $mask) == ($network_long & $mask);
			}
		} else {
			$from   = ip2long(trim(substr($network, 0, $d)));
			$to     = ip2long(trim(substr($network, $d + 1)));
			$ip     = ip2long($ip);
			$return = ($ip >= $from and $ip <= $to);
		}

		return ($return);
	}

	private function checkTerminateConnection() {
		$sfs_die       = isset($this->spaminfo['sfs']) &&
		                 ($this->spaminfo['sfs']['ip']['frequency'] >= $this->core_options['sfs']['whentodie'] ||
		                  $this->spaminfo['sfs']['email']['frequency'] >=
		                  $this->core_options['sfs']['whentodie_email']);
		$php_die       = isset($this->spaminfo['php']) &&
		                 $this->spaminfo['php']['type'] >= $this->core_options['php']['whentodietype'] &&
		                 $this->spaminfo['php']['score'] >= $this->core_options['php']['whentodie'];
		$sh_die        = isset($this->spaminfo['sh']);
		$blacklist_die = (isset($this->spaminfo['blacklist']) && 'Blacklisted' == $this->spaminfo['blacklist']['time']);

		return ($sfs_die || $php_die || $sh_die || $blacklist_die);
	}

	/**
	 * Check the White list table.
	 * Return true if in the table
	 *
	 */
	private function checkWhitelist() {
		if ($this->core_options['general']['usewhitelist']) {
			$found = $this->checkList($this->core->getDataElement('lists', 'whitelist'));
			if ($found) {
				$this->ip_in_white_list = true;
			}
		}
	}

	/**
	 * Convert the Stop Forum Spam data to something I already was using.
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	private function convertStopForumSpamCall($data) {
		if (isset($data['Error'])) {
			return ($data);
		}

		$return['ip']['appears']      = false;
		$return['ip']['frequency']    = - 1;
		$return['email']['appears']   = false;
		$return['email']['frequency'] = - 1;
		if (isset($data['ip'])) {
			$return['ip'] = $data['ip'];
		}
		if (isset($data['email'])) {
			$return['email'] = $data['email'];
		}

		return $return;
	}

	/**
	 * Check the cache for the IP
	 */
	private function doIpCheckCache() {
		if (1 == $this->core_options['general']['useipcache']) {
			$time_start        = microtime(true);
			$this->ip_in_cache = $this->ipcachedb->getIP($this->visiting_ip);
			$time_end          = microtime(true);
			$time              = $time_end - $time_start;
			if ( ! (false === $this->ip_in_cache)) {
				if ($this->ip_in_cache->spam == "1") {
					$this->spaminfo['cache']['time'] = $time;
					$this->spammer_detected          = true;
				}
			}
		}
	}

	/**
	 * Do Project Honey Pot with Visitor
	 *
	 * Sets the _spaminfo['detected'] to true when a spammer is detected.
	 */
	private function doIpCheckProjectHoneyPot() {
		if ($this->core_options['general']['use_php']) {
			$reverse_ip              = implode('.', array_reverse(explode('.', $this->visiting_ip)));
			$projecthoneypot_api_key = $this->core_options['php']['phpapikey'];
			$this->spaminfo['php']   = null;

			// Check the IP against projecthoneypot.org
			$time_start = microtime(true);
			$lookup     = $projecthoneypot_api_key . '.' . $reverse_ip . '.dnsbl.httpbl.org.';
			$info       = explode('.', gethostbyname($lookup));
			// The first octet needs to be 127.
			// Quote from the HTTPBL Api documentation: If the first octet in
			// the response is not 127 it means an error condition has occurred
			// and your query may not have been formatted correctly.
			// Reference :http://www.projecthoneypot.org/httpbl_api.php
			if ('127' == $info[0]) {
				$time_end                      = microtime(true);
				$time                          = $time_end - $time_start;
				$this->spaminfo['php']['time'] = $time;
				$this->spaminfo['php']['days'] = $info[1];
				$this->spaminfo['php']['type'] = $info[3];
				if ('0' == $info[3]) {
					$this->spaminfo['php']['score']  = '0';
					$this->spaminfo['php']['engine'] = $this->settings->searchengines[ $info[2] ];
				} else {
					$this->spaminfo['php']['score'] = $info[2];
					$this->spammer_detected         = $this->spaminfo['php']['type'] >=
					                                  $this->core_options['php']['whentodietype'] &&
					                                  $this->spaminfo['php']['score'] >=
					                                  $this->core_options['php']['whentodie'];
				}
			}
		}
	}

	/**
	 * Do Project Honey Pot with Visitor
	 *
	 * Sets the _spaminfo['detected'] to true when a spammer is detected.
	 */
	private function doIpCheckSpamhaus() {
		if ($this->core_options['general']['use_sh']) {
			$reverse_ip           = implode('.', array_reverse(explode('.', $this->visiting_ip)));
			$this->spaminfo['sh'] = null;

			// Check the IP against spamhaus.org
			$time_start = microtime(true);
			$lookup     = $reverse_ip . '.zen.spamhaus.org.';
			$info       = explode('.', gethostbyname($lookup));
			if ('127' == $info[0] && (int) $info[3] < 10 && (int) $info[3] != 4) {
				$this->spammer_detected       = true;
				$time_end                     = microtime(true);
				$time                         = $time_end - $time_start;
				$this->spaminfo['sh']['time'] = $time;
				if ($info[3] == "2" || $info[3] == "3") {
					$this->spaminfo['sh']['which'] = 'The Spamhaus Block List';
				}
				if ((int) $info[3] >= 4 && (int) $info[3] <= 7) {
					$this->spaminfo['sh']['which'] = 'Exploits Block List';
				}
			}
		}
	}

	/**
	 * Check an IP with Stop Forum Spam
	 *
	 */
	private function doIpCheckStopForumSpam() {
		if ($this->core_options['general']['use_sfs']) {
			$time_start                    = microtime(true);
			$result                        = $this->core->handleRestCall($this->core->getRestIPLookup($this->visiting_ip,
			                                                                                          $this->visiting_email));
			$time_end                      = microtime(true);
			$this->spaminfo['sfs']         = $this->convertStopForumSpamCall($result);
			$time                          = $time_end - $time_start;
			$this->spaminfo['sfs']['time'] = $time;
			if (isset($this->spaminfo['sfs']['Error'])) {
				if ($this->core_options['sfs']['error']) {
					$error     = $this->core->getHttpError($this->spaminfo['sfs']['Error']);
					$to        = get_option('admin_email');
					$subject   = sprintf('[%s] AVH First Defense Against Spam - ' . __('Error detected', 'avh-fdas'),
					                     wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));
					$message[] = __('An error has been detected', 'avh-fdas');
					$message[] = sprintf(__('Error:	%s', 'avh-fdas'), $error);
					$message[] = '';
					$message[] = sprintf(__('IP:		%s', 'avh-fdas'), $this->visiting_ip);
					$message[] = sprintf(__('Accessing:	%s', 'avh-fdas'), $_SERVER['REQUEST_URI']);
					$message[] = sprintf(__('Call took:	%s', 'avh-fdas'), $time);
					AVH_Common::sendMail($to, $subject, $message, $this->settings->getSetting('mail_footer'));
				}
				$this->spaminfo['sfs'] = null;
			} else {
				$this->spaminfo['sfs']['appears'] = false;
				if ($this->spaminfo['sfs']['ip']['appears'] || $this->spaminfo['sfs']['email']['appears']) {
					$this->spaminfo['sfs']['appears'] = true;
				}
				if (true === $this->spaminfo['sfs']['appears']) {
					$this->spammer_detected = ($this->spaminfo['sfs']['ip']['frequency'] >=
					                           $this->core_options['sfs']['whentodie'] ||
					                           $this->spaminfo['sfs']['email']['frequency'] >=
					                           $this->core_options['sfs']['whentodie_email']);
				}
			}
		}
	}

	/**
	 * Run through all the functions that will do spamchecking.
	 *
	 * Explanation for the use of $did_sfs:
	 * When a visitor comes to the main page and the IP is checked with either
	 * Project Honey Pot or Spamhaus the result could be that the visiting IP
	 * is marked as ham. This doesn't mean that that IP isn't registed with
	 * Stop Forum Spam. Therefor when we have the IP in cache and it's ham we will
	 * check it with Stop Forum Spam.
	 *
	 * In the next release I might add an extra field the the IP cache DB indicating
	 * which check declared it ham and if all of them declared it ham we can safely
	 * consider it ham.
	 */
	private function doSpamCheckFunctions() {
		$this->spammer_detected = false;
		ksort($this->spamcheck_functions_array);

		$this->checkWhitelist();
		if ($this->ip_in_white_list === false) {
			foreach ($this->spamcheck_functions_array as $key => $spam_check) {
				// Hardcode the built in Spam Check options as this is faster then using call_user_func.
				switch ($spam_check) {
					case 'Blacklist':
						$this->checkBlacklist();
						break;
					case 'IpCache':
						$this->doIpCheckCache();
						break;
					case 'StopForumSpam':
						$this->doIpCheckStopForumSpam();
						break;
					case 'ProjectHoneyPot':
						$this->doIpCheckProjectHoneyPot();
						break;
					case 'Spamhaus':
						$this->doIpCheckSpamhaus();
						break;
					default:
						call_user_func($spam_check);
						break;
				}
				if ($this->spammer_detected) {
					break;
				}
			}

			// When Project Honey pot detects a search enigine it's a safe IP
			if (isset($this->spaminfo['php']['engine'])) {
				$this->spammer_detected = false;
			}
		}
	}

	/**
	 * Terminates the connection.
	 */
	private function doTerminateConnection() {
		$this->setNoCaching();

		if (1 == $this->core_options['general']['diewithmessage']) {
			if (is_object($this->ip_in_cache)) {
				$m = sprintf('<h1>' .
				             __('Access has been blocked.', 'avh-fdas') .
				             '</h1><p>' .
				             __('Your IP [%s] has been identified as spam',
				                'avh-fdas') .
				             '</p>',
				             $this->visiting_ip);
			} else {
				if (isset($this->spaminfo['blacklist']) && 'Blacklisted' == $this->spaminfo['blacklist']['time']) {
					$m = sprintf('<h1>' .
					             __('Access has been blocked.', 'avh-fdas') .
					             '</h1><p>' .
					             __('Your IP [%s] is registered in our <em>Blacklisted</em> database.',
					                'avh-fdas') .
					             '<BR /></p>',
					             $this->visiting_ip);
				} else {
					$where = '';
					if (isset($this->spaminfo['sfs'])) {
						$where .= 'Stop Forum Spam ';
					}
					if (isset($this->spaminfo['php'])) {
						$where .= ($where == '' ? '' : 'and ');
						$where .= 'Project Honey Pot ';
					}
					if (isset($this->spaminfo['sh'])) {
						$where .= ($where == '' ? '' : 'and ');
						$where .= 'Spamhaus ';
					}
					$m = sprintf('<h1>' .
					             __('Access has been blocked.', 'avh-fdas') .
					             '</h1><p>' .
					             __('Your IP [%s] is found at %s.',
					                'avh-fdas') .
					             '<BR />' .
					             __('If you feel this is incorrect please contact them.', 'avh-fdas') .
					             '</p>',
					             $this->visiting_ip,
					             $where);
				}
			}
			$m .= '<p>' . __('Protected by: ', 'avh-fdas') . 'AVH First Defense Against Spam</p>';
			if ($this->core_options['php']['usehoneypot']) {
				$m .= $this->getHtmlHoneyPotUrl();
			}
			wp_die($m);
		} else {
			die();
		}
	}

	/**
	 * Function to handle everything when a potential spammer is detected.
	 */
	private function handleResults() {
		global $post;
		if (true === $this->spammer_detected) {
			if ('/wp-comments-post.php' == $_SERVER['REQUEST_URI']) {
				$title = isset($post->post_title) ? $post->post_title : '';
				$id    = isset($post->ID) ? $post->ID : 0;
				// Trying to post a comment, lets determine which post they are trying to post at
				$this->accessing = sprintf(__('Commenting on:	"%s" ( %s )', 'avh-fdas'),
				                           apply_filters('the_title', $title, $id),
				                           get_permalink($post->ID));
			} else {
				$this->accessing = sprintf(__('Accessing:	%s', 'avh-fdas'), $_SERVER['REQUEST_URI']);
			}
			if (is_object($this->ip_in_cache) && 1 == $this->ip_in_cache->spam) {
				$this->handleSpammerCache();
			} else {
				$this->handleSpammer();
			}
		} else {
			if (1 == $this->core_options['general']['useipcache'] && ( ! isset($this->spaminfo['blacklist']))) {
				if (is_object($this->ip_in_cache)) {
					$this->ipcachedb->updateIpCache(array(
						                                'ip'       => $this->visiting_ip,
						                                'lastseen' => current_time('mysql')
					                                ));
				} else {
					$this->ipcachedb->insertIp($this->visiting_ip, 0);
				}
			}
		}
	}

	/**
	 * Handle a known spam IP found by the 3rd party
	 *
	 */
	private function handleSpammer() {
		// Email
		$sfs_email = isset($this->spaminfo['sfs']) &&
		             (int) $this->core_options['sfs']['whentoemail'] >= 0 &&
		             ((int) $this->spaminfo['sfs']['ip']['frequency'] >= $this->core_options['sfs']['whentoemail'] ||
		              (int) $this->spaminfo['sfs']['email']['frequency'] >= $this->core_options['sfs']['whentoemail']);
		$php_email = isset($this->spaminfo['php']) &&
		             (int) $this->core_options['php']['whentoemail'] >= 0 &&
		             $this->spaminfo['php']['type'] >= $this->core_options['php']['whentoemailtype'] &&
		             (int) $this->spaminfo['php']['score'] >= $this->core_options['php']['whentoemail'];
		$sh_email  = isset($this->spaminfo['sh']) && $this->core_options['spamhaus']['email'];
		if ($sfs_email || $php_email || $sh_email) {
			// General part of the email
			$to      = get_option('admin_email');
			$subject = sprintf('[%s] AVH First Defense Against Spam - ' . __('Spammer detected [%s]', 'avh-fdas'),
			                   wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
			                   $this->visiting_ip);

			$message[] = $this->accessing;
			$message[] = '';

			// Stop Forum Spam Mail Part
			if ($sfs_email) {
				if ($this->spaminfo['sfs']['appears']) {
					$message[] = __('Checked at', 'avh-fdas') . ' Stop Forum Spam';
					$message[] = '	' . __('Information', 'avh-fdas');
					$checks    = array('ip' => 'IP', 'email' => 'E-Mail');
					foreach ($checks as $key => $value) {
						if ($this->spaminfo['sfs'][ $key ]['appears']) {
							$message[] = '	' . sprintf(__('%s information', 'avh-fdas'), $value);
							$message[] = '	' . sprintf(__('Last Seen:	%s', 'avh-fdas'),
							                              $this->spaminfo['sfs'][ $key ]['lastseen']);
							$message[] = '	' . sprintf(__('Frequency:	%s', 'avh-fdas'),
							                              $this->spaminfo['sfs'][ $key ]['frequency']);
							$message[] = '';
						}
					}
					$message[] = '	' . sprintf(__('Call took:	%s', 'avhafdas'), $this->spaminfo['sfs']['time']);
					if ($this->spaminfo['sfs']['ip']['frequency'] >= $this->core_options['sfs']['whentodie']) {
						$message[] = '	' .
						             sprintf(__('IP threshold (%s) reached. Connection terminated', 'avh-fdas'),
						                     $this->core_options['sfs']['whentodie']);
					}
					if ($this->spaminfo['sfs']['email']['frequency'] >= $this->core_options['sfs']['whentodie_email']) {
						$message[] = '	' .
						             sprintf(__('E-Mail threshold (%s) reached. Connection terminated', 'avh-fdas'),
						                     $this->core_options['sfs']['whentodie_email']);
					}
				} else {
					$message[] = 'Stop Forum Spam ' . __('has no information', 'avh-fdas');
				}
				$message[] = '';
				$message[] = sprintf(__('For more information:', 'avhfdas') .
				                     ' http://www.stopforumspam.com/search?q=%s',
				                     $this->visiting_ip);
				$message[] = '';
			}

			// Project Honey pot Mail Part
			if ($php_email) {
				if ($this->spaminfo['php'] != null) {
					$message[] = __('Checked at', 'avh-fdas') . ' Project Honey Pot';
					$message[] = '	' . __('Information', 'avh-fdas');
					$message[] = '	' . sprintf(__('Days since last activity:	%s', 'avh-fdas'),
					                              $this->spaminfo['php']['days']);
					switch ($this->spaminfo['php']['type']) {
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
					if (0 == $this->spaminfo['php']['type']) {
						$message[] = '	' . sprintf(__('Search Engine:	%s', 'avh-fdas'),
						                              $this->spaminfo['php']['engine']);
					} else {
						$message[] = '	' . sprintf(__('Score:				%s', 'avh-fdas'),
						                              $this->spaminfo['php']['score']);
					}
					$message[] = '	' . sprintf(__('Call took:			%s', 'avhafdas'),
					                              $this->spaminfo['php']['time']);
					if ($this->spaminfo['php']['score'] >= $this->core_options['php']['whentodie'] &&
					    $this->spaminfo['php']['type'] >= $this->core_options['php']['whentodietype']
					) {
						$message[] = '	' .
						             sprintf(__('Threshold score (%s) and type (%s) reached. Connection terminated',
						                        'avh-fdas'),
						                     $this->core_options['php']['whentodie'],
						                     $type);
					}
				} else {
					$message[] = 'Project Honey Pot ' . __('has no information', 'avh-fdas');
				}
				$message[] = '';
			}

			// Spamhaus Mail part
			if ($sh_email) {
				if ($this->spaminfo['sh'] != null) {
					$message[] = __('IP found at', 'avh-fdas') . ' Spamhaus';
					$message[] = '	' . __('Information', 'avh-fdas');
					$message[] = '	' . sprintf(__('Classification:		%s.', 'avh-fdas'),
					                              $this->spaminfo['sh']['which']);
					$message[] = '	' . sprintf(__('Call took:		%s', 'avhafdas'),
					                              $this->spaminfo['sh']['time']);
					$message[] = '	' . __('Connection terminated', 'avh-fdas');
				} else {
					$message[] = 'Spamhaus ' . __('has no information', 'avh-fdas');
				}
				$message[] = '';
			}
			// General End
			if ( ! isset($this->spaminfo['blacklist'])) {
				$blacklisturl = admin_url('admin.php?action=blacklist&i=') .
				                $this->visiting_ip .
				                '&_avhnonce=' .
				                AVH_Security::createNonce($this->visiting_ip);
				$message[]    = sprintf(__('Add to the local blacklist: %s'), $blacklisturl);
			}
			AVH_Common::sendMail($to, $subject, $message, $this->settings->getSetting('mail_footer'));
		}
		// Check if we have to terminate the connection.
		// This should be the very last option.
		$die = $this->checkTerminateConnection();

		if ($die) {
			if (1 == $this->core_options['general']['useipcache']) {
				if (is_object($this->ip_in_cache)) {
					$this->ipcachedb->updateIpCache(array(
						                                'ip'       => $this->visiting_ip,
						                                'spam'     => 1,
						                                'lastseen' => current_time('mysql')
					                                ));
				} else {
					$this->ipcachedb->insertIp($this->visiting_ip, 1);
				}
			}

			// Update the counter
			$this->updateSpamCounter();
			// Terminate the connection
			$this->doTerminateConnection();
		}
	}

	/**
	 * Handle a spammer found in the IP cache
	 *
	 */
	private function handleSpammerCache() {
		if ($this->core_options['ipcache']['email']) {
			// General part of the email
			$to        = get_option('admin_email');
			$subject   = sprintf('[%s] AVH First Defense Against Spam - ' . __('Spammer detected [%s]', 'avh-fdas'),
			                     wp_specialchars_decode(get_option('blogname'), ENT_QUOTES),
			                     $this->visiting_ip);
			$message   = array();
			$message[] = sprintf(__('Spam IP:	%s', 'avh-fdas'), $this->visiting_ip);
			$message[] = $this->accessing;
			$message[] = '';
			$message[] = __('IP exists in the cache', 'avh-fdas');
			$message[] = '	' . sprintf(__('Check took:			%s', 'avh-fdas'),
			                              $this->spaminfo['cache']['time']);
			$message[] = '';
			// General End
			$blacklisturl = admin_url('admin.php?action=blacklist&i=') .
			                $this->visiting_ip .
			                '&_avhnonce=' .
			                AVH_Security::createNonce($this->visiting_ip);
			$message[]    = sprintf(__('Add to the local blacklist: %s'), $blacklisturl);
			AVH_Common::sendMail($to, $subject, $message, $this->settings->getSetting('mail_footer'));
		}
		// Update the counter
		$this->updateSpamCounter();
		// Update Last seen value
		$this->ipcachedb->updateIpCache(array('ip' => $this->visiting_ip, 'lastseen' => current_time('mysql')));
		// Terminate the connection
		$this->doTerminateConnection();
	}

	/**
	 * Set No Cacheing for several Caching plugins.
	 */
	private function setNoCaching() {
		/**
		 * This tells the following plugins to not cache this page
		 * W3 Total cache
		 * WP-Supercache
		 */
		define('DONOTCACHEPAGE', true);

		/**
		 * The following two line tells the plugin Hyper Cache not to cache
		 * this page.
		 */
		global $hyper_cache_stop;
		$hyper_cache_stop = true;
	}

	/**
	 * Updates the spam counter
	 */
	private function updateSpamCounter() {
		$period = date('Ym');
		if (array_key_exists($period, $this->core_data['counters'])) {
			$this->core_data['counters'][ $period ] += 1;
		} else {
			$this->core_data['counters'][ $period ] = 1;
		}
		$this->core->saveData($this->core_data);
	}
}
