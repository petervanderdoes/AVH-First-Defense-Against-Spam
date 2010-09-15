<?php
if ( ! defined( 'AVH_FRAMEWORK' ) ) die( 'You are not allowed to call this page directly.' );

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
	 * The $use_xx variables are used to determine if that specific 3rd party can used for that check.
	 * For example: We can't use Stop Forum Spam to check every IP, only at comments and register.
	 */
	private $_useStopForumSpam;
	private $_useProjectHoneyPot;
	private $_useCache;

	private $_MailMessage;

	public $checkSection;

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
		$this->_core = $this->_classes->load_class( 'Core', 'plugin', TRUE );

		// Default Stop Forum Spam to false to make sure we don't overload their site.
		$this->_useStopForumSpam = FALSE;

	}

	/**
	 * Handle when posting a comment.
	 *
	 * Get the visitors IP and call the stopforumspam API to check if it's a known spammer
	 *
	 * @uses SFS, PHP
	 * @WordPress Action preprocess_comment
	 */
	public function doIPCheck ( $check )
	{
		$this->checkSection = $check;
		$this->_setWhichSpam();

		$ip = avh_getUserIP();
		$ip_in_whitelist = false;
		$options = $this->_core->getOptions();
		$data = $this->_core->getData();

		if ( 1 == $options['general']['usewhitelist'] && $data['lists']['whitelist'] ) {
			$ip_in_whitelist = $this->_checkWhitelist( $ip );
		}

		if ( ! $ip_in_whitelist ) {
			if ( 1 == $options['general']['useblacklist'] && $data['lists']['blacklist'] ) {
				$this->_checkBlacklist( $ip ); // The program will terminate if in blacklist.
			}

			$ip_in_cache = false;
			if ( $this->_useCache ) {
				$ipcachedb = $this->_classes->load_class( 'DB', 'plugin', TRUE );
				$time_start = microtime( true );
				$ip_in_cache = $ipcachedb->getIP( $ip );
				$time_end = microtime( true );
				$time = $time_end - $time_start;
				$spaminfo['time'] = $time;
			}

			if ( false === $ip_in_cache ) {
				if ( $this->_useStopForumSpam || $this->_useProjectHoneyPot ) {
					$spaminfo = null;
					$spaminfo['detected'] = FALSE;

					if ( $this->_useStopForumSpam ) {
						$spaminfo['sfs'] = $this->_checkStopForumSpam( $ip );
						if ( 'yes' == $spaminfo['sfs']['appears'] ) {
							$spaminfo['detected'] = true;
						}
					}

					if ( $this->_useProjectHoneyPot ) {
						$spaminfo['php'] = $this->_checkProjectHoneyPot( $ip );
						if ( $spaminfo['php'] != null ) {
							$spaminfo['detected'] = true;
						}
					}

					if ( $spaminfo['detected'] ) {
						$this->_handleSpammer( $ip, $spaminfo );
					} else {
						if ( $this->_useCache && (! isset( $spaminfo['Error'] )) ) {
							$ipcachedb->insertIP( $ip, 0 );
						}
					}
				}
			} else {
				if ( $ip_in_cache->spam ) {
					$ipcachedb->updateIP( $ip );
					$spaminfo['ip'] = $ip;
					$this->_handleSpammerCache( $spaminfo );
				}
			}
		}
	}

	private function _setWhichSpam ()
	{
		switch ( strtolower( $this->checkSection ) )
		{
			case 'comment' :
				$this->setUseStopForumSpam( TRUE );
				$this->setUseProjectHoneyPot( TRUE );
				$this->setUseCache( TRUE );
				break;
			case 'main' :
				$this->setUseStopForumSpam( FALSE );
				$this->setUseProjectHoneyPot( TRUE );
				$this->setUseCache( TRUE );
				break;
			case 'registration' :
				$this->setUseStopForumSpam( TRUE );
				$this->setUseProjectHoneyPot( TRUE );
				$this->setUseCache( TRUE );
				break;
			default :
				$this->setUseStopForumSpam( FALSE );
				$this->setUseProjectHoneyPot( TRUE );
				$this->setUseCache( TRUE );
				break;
		}

	}

	/**
	 * Convert the Stop Forum Spam data to something I already was using.
	 *
	 * @param $data
	 */
	private function _convertStopForumSpamCall ( $data )
	{
		if ( isset( $data['Error'] ) ) {
			return ($data);
		}
		if ( isset( $data['ip'] ) ) {
			return ($data['ip']);
		}
	}

	/**
	 * Check an IP with Stop Forum Spam
	 *
	 * @param $ip Visitor's IP
	 * @return $spaminfo Query result
	 */
	private function _checkStopForumSpam ( $ip )
	{
		$options = $this->_core->getOptions();
		$time_start = microtime( true );
		$result = $this->_core->handleRESTcall( $this->_core->getRestIPLookup( $ip ) );
		$spaminfo = $this->_convertStopForumSpamCall( $result );
		$time_end = microtime( true );
		$time = $time_end - $time_start;
		$spaminfo['time'] = $time;
		if ( isset( $spaminfo['Error'] ) ) {
			// Let's give it one more try.
			$time_start = microtime( true );
			$result = $this->_core->handleRESTcall( $this->_core->getRestIPLookup( $ip ) );
			$spaminfo = $this->_convertStopForumSpamCall( $result );
			$time_end = microtime( true );
			$time = $time_end - $time_start;
			$spaminfo['time'] = $time;
			if ( isset( $spaminfo['Error'] ) && $options['sfs']['error'] ) {
				$error = $this->_core->getHttpError( $spaminfo['Error'] );
				$to = get_option( 'admin_email' );
				$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Error detected', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
				$message[] = __( 'An error has been detected', 'avhfdas' );
				$message[] = sprintf( __( 'Error:	%s', 'avhfdas' ), $error );
				$message[] = '';
				$message[] = sprintf( __( 'IP:		%s', 'avhfdas' ), $ip );
				$message[] = sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] );
				$message[] = sprintf( __( 'Call took:	%s', 'avhafdas' ), $time );
				if ( isset( $spaminfo['Debug'] ) ) {
					$message[] = sprintf( __( 'Debug:	%s', 'avhafdas' ), $spaminfo['Debug'] );
				}
				AVH_Common::sendMail( $to, $subject, $message );
			}
		}
		return ($spaminfo);
	}

	/**
	 * Check an IP with Project Honey Pot
	 *
	 * @param $ip Visitor's IP
	 * @return $spaminfo Query result
	 */
	private function _checkProjectHoneyPot ( $ip )
	{
		$rev = implode( '.', array_reverse( explode( '.', $ip ) ) );
		$projecthoneypot_api_key = $this->_core->getOptionElement( 'php', 'phpapikey' );
		//
		// Check the IP against projecthoneypot.org
		//
		$spaminfo = null;
		$time_start = microtime( true );
		$lookup = $projecthoneypot_api_key . '.' . $rev . '.dnsbl.httpbl.org';
		if ( $lookup != gethostbyname( $lookup ) ) {
			$time_end = microtime( true );
			$time = $time_end - $time_start;
			$spaminfo['time'] = $time;
			$info = explode( '.', gethostbyname( $lookup ) );
			$spaminfo['days'] = $info[1];
			$spaminfo['type'] = $info[3];
			if ( '0' == $info[3] ) {
				$spaminfo['score'] = '0';
				$searchengines = $this->_settings->searchengines;
				$spaminfo['engine'] = $searchengines[$info[2]];
			} else {
				$spaminfo['score'] = $info[2];
			}
		}
		return ($spaminfo);
	}

	/**
	 * Check blacklist table
	 *
	 * @param string $ip
	 */
	private function _checkBlacklist ( $ip )
	{
		$spaminfo = array ();
		$found = $this->_checkList( $ip, $this->_core->data['lists']['blacklist'] );
		if ( $found ) {
			$spaminfo['blacklist']['appears'] = 'yes';
			$spaminfo['blacklist']['time'] = 'Blacklisted';
			$this->_handleSpammer( $ip, $spaminfo );
		}
	}

	/**
	 * Check the White list table. Return TRUE if in the table
	 *
	 * @param string $ip
	 * @return boolean
	 *
	 * @since 1.1
	 */
	private function _checkWhitelist ( $ip )
	{
		$found = $this->_checkList( $ip, $this->_core->data['lists']['whitelist'] );
		return $found;
	}

	/**
	 * Check if an IP exists in a list
	 *
	 * @param string $ip
	 * @param string $list
	 * @return boolean
	 *
	 */
	private function _checkList ( $ip, $list )
	{
		$list = explode( "\r\n", $list );
		// Check for single IP's, this is much quicker as going through the list
		$inlist = in_array( $ip, $list ) ? true : false;
		if ( ! $inlist ) { // Not found yet
			foreach ( $list as $check ) {
				if ( $this->_checkNetworkMatch( $check, $ip ) ) {
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
	private function _checkNetworkMatch ( $network, $ip )
	{
		$return = false;
		$network = trim( $network );
		$ip = trim( $ip );
		$d = strpos( $network, '-' );
		if ( $d === false ) {
			$ip_arr = explode( '/', $network );
			if ( isset( $ip_arr[1] ) ) {
				$network_long = ip2long( $ip_arr[0] );
				$x = ip2long( $ip_arr[1] );
				$mask = long2ip( $x ) == $ip_arr[1] ? $x : (0xffffffff << (32 - $ip_arr[1]));
				$ip_long = ip2long( $ip );
				$return = ($ip_long & $mask) == ($network_long & $mask);
			}
		} else {
			$from = ip2long( trim( substr( $network, 0, $d ) ) );
			$to = ip2long( trim( substr( $network, $d + 1 ) ) );
			$ip = ip2long( $ip );
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
	private function _handleSpammer ( $ip, $info )
	{
		$data = $this->_core->getData();
		$options = $this->_core->getOptions();

		// Email
		$sfs_email = $this->_useStopForumSpam && ( int ) $options['sfs']['whentoemail'] >= 0 && ( int ) $info['sfs']['frequency'] >= $options['sfs']['whentoemail'];
		$php_email = $this->_useProjectHoneyPot && ( int ) $options['php']['whentoemail'] >= 0 && $info['php']['type'] >= $options['php']['whentoemailtype'] && ( int ) $info['php']['score'] >= $options['php']['whentoemail'];

		if ( $sfs_email || $php_email ) {

			// General part of the email
			$to = get_option( 'admin_email' );
			$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Spammer detected [%s]', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), $ip );
			$message[] = sprintf( __( 'Spam IP:	%s', 'avhfdas' ), $ip );
			$message[] = sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] );
			$message[] = '';

			// Stop Forum Spam Mail Part
			if ( $sfs_email && $this->_useStopForumSpam ) {
				if ( 'yes' == $info['sfs']['appears'] ) {
					$message[] = __( 'Checked at Stop Forum Spam', 'avhfdas' );
					$message[] = '	' . __( 'Information', 'avhfdas' );
					$message[] = '	' . sprintf( __( 'Last Seen:	%s', 'avhfdas' ), $info['sfs']['lastseen'] );
					$message[] = '	' . sprintf( __( 'Frequency:	%s', 'avhfdas' ), $info['sfs']['frequency'] );
					$message[] = '	' . sprintf( __( 'Call took:	%s', 'avhafdas' ), $info['sfs']['time'] );

					if ( $info['sfs']['frequency'] >= $options['sfs']['whentodie'] ) {
						$message[] = '	' . sprintf( __( 'Threshold (%s) reached. Connection terminated', 'avhfdas' ), $options['sfs']['whentodie'] );
					}
				} else {
					$message[] = __( 'Stop Forum Spam has no information', 'avhfdas' );
				}
				$message[] = '';
				$message[] = sprintf( __( 'For more information: http://www.stopforumspam.com/search?q=%s' ), $ip );
				$message[] = '';
			}

			if ( 'no' == $info['sfs']['appears'] ) {
				$message[] = __( 'Stop Forum Spam has no information', 'avhfdas' );
				$message[] = '';
			}

			// Project Honey pot Mail Part
			if ( $this->_useProjectHoneyPot && ($php_email || $options['sfs']['emailphp']) ) {
				if ( $info['php'] != null ) {
					$message[] = __( 'Checked at Project Honey Pot', 'avhfdas' );
					$message[] = '	' . __( 'Information', 'avhfdas' );
					$message[] = '	' . sprintf( __( 'Days since last activity:	%s', 'avhfdas' ), $info['php']['days'] );
					switch ( $info['php']['type'] )
					{
						case "0" :
							$type = "Search Engine";
							break;
						case "1" :
							$type = "Suspicious";
							break;
						case "2" :
							$type = "Harvester";
							break;
						case "3" :
							$type = "Suspicious & Harvester";
							break;
						case "4" :
							$type = "Comment Spammer";
							break;
						case "5" :
							$type = "Suspicious & Comment Spammer";
							break;
						case "6" :
							$type = "Harvester & Comment Spammer";
							break;
						case "7" :
							$type = "Suspicious & Harvester & Comment Spammer";
							break;
					}

					$message[] = '	' . sprintf( __( 'Type:				%s', 'avhfdas' ), $type );
					if ( 0 == $info['php']['type'] ) {
						$message[] = '	' . sprintf( __( 'Search Engine:	%s', 'avhfdas' ), $info['php']['engine'] );
					} else {
						$message[] = '	' . sprintf( __( 'Score:				%s', 'avhfdas' ), $info['php']['score'] );
					}
					$message[] = '	' . sprintf( __( 'Call took:			%s', 'avhafdas' ), $info['php']['time'] );

					if ( $info['php']['score'] >= $options['php']['whentodie'] && $info['php']['type'] >= $options['php']['whentodietype'] ) {
						$message[] = '	' . sprintf( __( 'Threshold score (%s) and type (%s) reached. Connection terminated', 'avhfdas' ), $options['php']['whentodie'], $type );
					}
				} else {
					$message[] = __( 'Project Honey Pot has no information', 'avhfdas' );
				}
				$message[] = '';
			}

			// General End
			if ( 'Blacklisted' != $info['blacklist']['time'] ) {
				$blacklisturl = admin_url( 'admin.php?action=blacklist&i=' ) . $ip . '&_avhnonce=' . AVH_Security::createNonce( $ip );
				$message[] = sprintf( __( 'Add to the local blacklist: %s' ), $blacklisturl );
			}
			AVH_Common::sendMail( $to, $subject, $message );
		}

		// Check if we have to terminate the connection.
		// This should be the very last option.
		$sfs_die = $this->_useStopForumSpam && $info['sfs']['frequency'] >= $options['sfs']['whentodie'];
		$php_die = $this->_useProjectHoneyPot && $info['php']['type'] >= $options['php']['whentodietype'] && $info['php']['score'] >= $options['php']['whentodie'];
		$blacklist_die = 'Blacklisted' == $info['blacklist']['time'];

		if ( 1 == $options['general']['useipcache'] ) {
			$ipcachedb = $this->_classes->load_class( 'DB', 'plugin', TRUE );
			if ( $sfs_die || $php_die ) {
				$ipcachedb->insertIP( $ip, 1 );
			}
		}

		if ( $sfs_die || $php_die || $blacklist_die ) {
			// Update the counter
			$period = date( 'Ym' );
			if ( array_key_exists( $period, $data['counters'] ) ) {
				$data['counters'][$period] += 1;
			} else {
				$data['counters'][$period] = 1;
			}
			$this->_core->saveData( $data );
			if ( 1 == $options['general']['diewithmessage'] ) {
				if ( 'Blacklisted' == $info['blacklist']['time'] ) {
					$m = sprintf( __( '<h1>Access has been blocked.</h1><p>Your IP [%s] is registered in our <em>Blacklisted</em> database.<BR /></p>', 'avhfdas' ), $ip );
				} else {
					$m = sprintf( __( '<h1>Access has been blocked.</h1><p>Your IP [%s] is registered in the Stop Forum Spam or Project Honey Pot database.<BR />If you feel this is incorrect please contact them</p>', 'avhfdas' ), $ip );
				}
				$m .= '<p>Protected by: AVH First Defense Against Spam</p>';
				if ( $options['php']['usehoneypot'] ) {
					$m .= '<p>' . $options['php']['honeypoturl'] . '</p>';
				}
				wp_die( $m );
			} else {
				die();
			}
		}
	}

	/**
	 * Handle a spammer found in the IP cache
	 * @param $info
	 * @return unknown_type
	 */
	private function _handleSpammerCache ( $info )
	{
		$data = $this->_core->getData();
		$options = $this->_core->getOptions();

		$cache_email = $options['ipcache']['email'];
		if ( $cache_email ) {

			// General part of the email
			$to = get_option( 'admin_email' );
			$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Spammer detected [%s]', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), $info['ip'] );
			$message = array ();
			$message[] = sprintf( __( 'Spam IP:	%s', 'avhfdas' ), $info['ip'] );
			$message[] = sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] );
			$message[] = '';
			$message[] = __( 'IP exists in the cache', 'avhfdas' );
			$message[] = '	' . sprintf( __( 'Check took:			%s', 'avhafdas' ), $info['time'] );
			$message[] = '';

			// General End
			$blacklisturl = admin_url( 'admin.php?action=blacklist&i=' ) . $info['ip'] . '&_avhnonce=' . AVH_Security::createNonce( $info['ip'] );
			$message[] = sprintf( __( 'Add to the local blacklist: %s' ), $blacklisturl );
			AVH_Common::sendMail( $to, $subject, $message );
		}

		// Update the counter
		$period = date( 'Ym' );
		if ( array_key_exists( $period, $data['counters'] ) ) {
			$data['counters'][$period] += 1;
		} else {
			$data['counters'][$period] = 1;
		}
		$this->_core->saveData( $data );
		if ( 1 == $options['general']['diewithmessage'] ) {
			$m = sprintf( __( '<h1>Access has been blocked.</h1><p>Your IP [%s] has been identified as spam</p>', 'avhfdas' ), $info['ip'] );
			$m .= '<p>Protected by: AVH First Defense Against Spam</p>';
			if ( $options['php']['usehoneypot'] ) {
				$m .= '<p>' . $options['php']['honeypoturl'] . '</p>';
			}
			wp_die( $m );
		} else {
			die();
		}
	}

	/**
	 * @return the $_useStopForumSpam
	 */
	public function getUseStopForumSpam ()
	{
		return $this->_useStopForumSpam;
	}

	/**
	 * @param $_useStopForumSpam the $_useStopForumSpam to set
	 */
	public function setUseStopForumSpam ( $useStopForumSpam )
	{
		$this->_useStopForumSpam = $this->_core->getOptionElement( 'general', 'use_sfs' ) ? $useStopForumSpam : FALSE;
	}

	/**
	 * @return the $_useProjHoneyPot
	 */
	public function getUseProjectHoneyPot ()
	{
		return $this->_useProjectHoneyPot;
	}

	/**
	 * @param $_useProjHoneyPot the $_useProjHoneyPot to set
	 */
	public function setUseProjectHoneyPot ( $useProjectHoneyPot )
	{
		$this->_useProjectHoneyPot = $this->_core->getOptionElement( 'general', 'use_php' ) ? $useProjectHoneyPot : FALSE;
	}

	/**
	 * @return the $_useCache
	 */
	public function getUseCache ()
	{
		return $this->_useCache;
	}

	/**
	 * @param $_useCache the $_useCache to set
	 */
	public function setUseCache ( $useCache )
	{
		$this->_useCache = $this->_core->getOptionElement( 'general', 'useipcache' ) ? $useCache : FALSE;
	}

}
?>