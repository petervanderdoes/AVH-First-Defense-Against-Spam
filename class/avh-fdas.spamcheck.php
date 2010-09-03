<?php
if ( ! defined( 'AVH_FRAMEWORK' ) )
	die( 'You are not allowed to call this page directly.' );

class AVH_FDAS_SpamCheck
{
	/**
	 *
	 * @var AVH_FDAS_Core
	 */
	var $core;

	/**
	 * @var AVH_Settings_Registry
	 */
	var $Settings;

	/**
	 * @var AVH_Class_registry
	 */
	var $Classes;

	/**
	 * @var use Stop Forum Spam for checking
	 */
	var $use_sfs;

	var $mail_message;

	/**
	 * PHP5 Constructor
	 *
	 */
	function __construct ()
	{
		// Get The Registry
		$this->Settings = AVH_FDAS_Settings::getInstance();
		$this->Classes = AVH_FDAS_Classes::getInstance();

		// Initialize the plugin
		$this->core = $this->Classes->load_class( 'Core', 'plugin', TRUE );

		// Default Stop Forum Spam to false to make sure we don't overload their site.
		$this->use_sfs = FALSE;

	}

	/**
	 * Handle when posting a comment.
	 *
	 * Get the visitors IP and call the stopforumspam API to check if it's a known spammer
	 *
	 * @uses SFS, PHP
	 * @WordPress Action preprocess_comment
	 */
	function doIPCheck ( )
	{
		$ip = avh_getUserIP();
		$ip_in_whitelist = false;
		$options = $this->core->getOptions();
		$data = $this->core->getData();

		if ( 1 == $options['general']['usewhitelist'] && $data['lists']['whitelist'] ) {
			$ip_in_whitelist = $this->checkWhitelist( $ip );
		}

		if ( ! $ip_in_whitelist ) {
			if ( 1 == $options['general']['useblacklist'] && $data['lists']['blacklist'] ) {
				$this->checkBlacklist( $ip ); // The program will terminate if in blacklist.
			}

			$ip_in_cache = false;
			if ( 1 == $options['general']['useipcache'] ) {
				$ipcachedb = & AVH_FDAS_Singleton::getInstance( 'AVH_FDAS_DB' );
				$time_start = microtime( true );
				$ip_in_cache = $ipcachedb->getIP( $ip );
				$time_end = microtime( true );
				$time = $time_end - $time_start;
				$spaminfo['time'] = $time;
			}

			if ( false === $ip_in_cache ) {
				if ( $options['general']['use_sfs'] || $options['general']['use_php'] ) {
					$spaminfo = null;
					$spaminfo['detected'] = FALSE;

					if ( $this->use_sfs && $options['general']['use_sfs'] ) {
						$spaminfo['sfs'] = $this->checkStopForumSpam( $ip );
						if ( 'yes' == $spaminfo['sfs']['appears'] ) {
							$spaminfo['detected'] = true;
						}
					}

					if ( $options['general']['use_php'] ) {
						$spaminfo['php'] = $this->checkProjectHoneyPot( $ip );
						if ( $spaminfo['php'] != null ) {
							$spaminfo['detected'] = true;
						}
					}

					if ( $spaminfo['detected'] ) {
						$this->handleSpammer( $ip, $spaminfo );
					} else {
						if ( 1 == $options['general']['useipcache'] && (! isset( $spaminfo['Error'] )) ) {
							$ipcachedb->insertIP( $ip, 0 );
						}
					}
				}
			} else {
				if ( $ip_in_cache->spam ) {
					$ipcachedb->updateIP( $ip );
					$spaminfo['ip'] = $ip;
					$this->handleSpammerCache( $spaminfo );
				}
			}
		}
	}

	/**
	 * Check an IP with Stop Forum Spam
	 *
	 * @param $ip Visitor's IP
	 * @return $spaminfo Query result
	 */
	function checkStopForumSpam ( $ip )
	{
		$options = $this->core->getOptions();
		$time_start = microtime( true );
		$spaminfo = $this->core->handleRESTcall( $this->core->getRestIPLookup( $ip ) );
		$time_end = microtime( true );
		$time = $time_end - $time_start;
		$spaminfo['time'] = $time;
		if ( isset( $spaminfo['Error'] ) ) {
			// Let's give it one more try.
			$time_start = microtime( true );
			$spaminfo = $this->core->handleRESTcall( $this->core->getRestIPLookup( $ip ) );
			$time_end = microtime( true );
			$time = $time_end - $time_start;
			$spaminfo['time'] = $time;
			if ( isset( $spaminfo['Error'] ) && $options['sfs']['error'] ) {
				$error = $this->core->getHttpError( $spaminfo['Error'] );
				$to = get_option( 'admin_email' );
				$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Error detected', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
				$message = __( 'An error has been detected', 'avhfdas' ) . "\r\n";
				$message .= sprintf( __( 'Error:	%s', 'avhfdas' ), $error ) . "\r\n\r\n";
				$message .= sprintf( __( 'IP:		%s', 'avhfdas' ), $ip ) . "\r\n";
				$message .= sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] ) . "\r\n";
				$message .= sprintf( __( 'Call took:	%s', 'avhafdas' ), $time ) . "\r\n";
				if ( isset( $spaminfo['Debug'] ) ) {
					$message .= sprintf( __( 'Debug:	%s', 'avhafdas' ), $spaminfo['Debug'] ) . "\r\n";
				}
				$this->mail( $to, $subject, $message );
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
	function checkProjectHoneyPot ( $ip )
	{
		$rev = implode( '.', array_reverse( explode( '.', $ip ) ) );
		$projecthoneypot_api_key = $this->core->getOptionElement( 'php', 'phpapikey' );
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
				$searchengines = $this->Settings->getSetting(searchengines);
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
	function checkBlacklist ( $ip )
	{
		$spaminfo = array ();
		$found = $this->checkList( $ip, $this->core->data['lists']['blacklist'] );
		if ( $found ) {
			$spaminfo['blacklist']['appears'] = 'yes';
			$spaminfo['blacklist']['time'] = 'Blacklisted';
			$this->handleSpammer( $ip, $spaminfo );
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
	function checkWhitelist ( $ip )
	{
		$found = $this->checkList( $ip, $this->core->data['lists']['whitelist'] );
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
	function checkList ( $ip, $list )
	{
		$list = explode( "\r\n", $list );
		// Check for single IP's, this is much quicker as going through the list
		$inlist = in_array( $ip, $list ) ? true : false;
		if ( ! $inlist ) { // Not found yet
			foreach ( $list as $check ) {
				if ( $this->checkNetworkMatch( $check, $ip ) ) {
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
	function checkNetworkMatch ( $network, $ip )
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
	function handleSpammer ( $ip, $info )
	{
		$data = $this->core->getData();
		$options = $this->core->getOptions();

		// Email
		$sfs_email = $options['general']['use_sfs'] && ( int ) $options['sfs']['whentoemail'] >= 0 && ( int ) $info['sfs']['frequency'] >= $options['sfs']['whentoemail'];
		$php_email = $options['general']['use_php'] && ( int ) $options['php']['whentoemail'] >= 0 && $info['php']['type'] >= $options['php']['whentoemailtype'] && ( int ) $info['php']['score'] >= $options['php']['whentoemail'];

		if ( $sfs_email || $php_email ) {

			// General part of the email
			$to = get_option( 'admin_email' );
			$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Spammer detected [%s]', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), $ip );
			$message = '';
			$message .= sprintf( __( 'Spam IP:	%s', 'avhfdas' ), $ip ) . "\r\n";
			$message .= sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] ) . "\r\n\r\n";

			// Stop Forum Spam Mail Part
			if ( $options['general']['use_sfs'] && $sfs_email && $this->use_sfs === TRUE ) {
				if ( 'yes' == $info['sfs']['appears'] ) {
					$message .= __( 'Checked at Stop Forum Spam', 'avhfdas' ) . "\r\n";
					$message .= '	' . __( 'Information', 'avhfdas' ) . "\r\n";
					$message .= '	' . sprintf( __( 'Last Seen:	%s', 'avhfdas' ), $info['sfs']['lastseen'] ) . "\r\n";
					$message .= '	' . sprintf( __( 'Frequency:	%s', 'avhfdas' ), $info['sfs']['frequency'] ) . "\r\n";
					$message .= '	' . sprintf( __( 'Call took:	%s', 'avhafdas' ), $info['sfs']['time'] ) . "\r\n";

					if ( $info['sfs']['frequency'] >= $options['sfs']['whentodie'] ) {
						$message .= '	' . sprintf( __( 'Threshold (%s) reached. Connection terminated', 'avhfdas' ), $options['sfs']['whentodie'] ) . "\r\n";
					}
				} else {
					$message .= __( 'Stop Forum Spam has no information', 'avhfdas' ) . "\r\n";
				}
				$message .= "\r\n	" . sprintf( __( 'For more information: http://www.stopforumspam.com/search?q=%s' ), $ip ) . "\r\n\r\n";
			}

			if ( 'no' == $info['sfs']['appears'] ) {
				$message .= __( 'Stop Forum Spam has no information', 'avhfdas' ) . "\r\n\r\n";
			}

			// Project Honey pot Mail Part
			if ( $options['general']['use_php'] && ($php_email || $options['sfs']['emailphp']) ) {
				if ( $info['php'] != null ) {
					$message .= __( 'Checked at Project Honey Pot', 'avhfdas' ) . "\r\n";
					$message .= '	' . __( 'Information', 'avhfdas' ) . "\r\n";
					$message .= '	' . sprintf( __( 'Days since last activity:	%s', 'avhfdas' ), $info['php']['days'] ) . "\r\n";
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

					$message .= '	' . sprintf( __( 'Type:				%s', 'avhfdas' ), $type ) . "\r\n";
					if ( 0 == $info['php']['type'] ) {
						$message .= '	' . sprintf( __( 'Search Engine:	%s', 'avhfdas' ), $info['php']['engine'] ) . "\r\n";
					} else {
						$message .= '	' . sprintf( __( 'Score:				%s', 'avhfdas' ), $info['php']['score'] ) . "\r\n";
					}
					$message .= '	' . sprintf( __( 'Call took:			%s', 'avhafdas' ), $info['php']['time'] ) . "\r\n";

					if ( $info['php']['score'] >= $options['php']['whentodie'] && $info['php']['type'] >= $options['php']['whentodietype'] ) {
						$message .= '	' . sprintf( __( 'Threshold score (%s) and type (%s) reached. Connection terminated', 'avhfdas' ), $options['php']['whentodie'], $type ) . "\r\n";
					}
				} else {
					$message .= __( 'Project Honey Pot has no information', 'avhfdas' ) . "\r\n";
				}
				$message .= "\r\n";
			}

			// General End
			if ( 'Blacklisted' != $info['blacklist']['time'] ) {
				$blacklisturl = admin_url( 'admin.php?action=blacklist&i=' ) . $ip . '&_avhnonce=' . avh_create_nonce( $ip );
				$message .= sprintf( __( 'Add to the local blacklist: %s' ), $blacklisturl ) . "\r\n";
			}
			$this->mail( $to, $subject, $message );
		}

		// Check if we have to terminate the connection.
		// This should be the very last option.
		$sfs_die = $options['general']['use_sfs'] && $info['sfs']['frequency'] >= $options['sfs']['whentodie'];
		$php_die = $options['general']['use_php'] && $info['php']['type'] >= $options['php']['whentodietype'] && $info['php']['score'] >= $options['php']['whentodie'];
		$blacklist_die = 'Blacklisted' == $info['blacklist']['time'];

		if ( 1 == $options['general']['useipcache'] ) {
			$ipcachedb = & AVH_FDAS_Singleton::getInstance( 'AVH_FDAS_DB' );
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
			$this->core->saveData( $data );
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
	function handleSpammerCache ( $info )
	{
		$data = $this->core->getData();
		$options = $this->core->getOptions();

		$cache_email = $options['ipcache']['email'];
		if ( $cache_email ) {

			// General part of the email
			$to = get_option( 'admin_email' );
			$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Spammer detected [%s]', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ), $info['ip'] );
			$message = '';
			$message .= sprintf( __( 'Spam IP:	%s', 'avhfdas' ), $info['ip'] ) . "\r\n";
			$message .= sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] ) . "\r\n\r\n";

			$message .= __( 'IP exists in the cache', 'avhfdas' ) . "\r\n";
			$message .= '	' . sprintf( __( 'Check took:			%s', 'avhafdas' ), $info['time'] ) . "\r\n";
			$message .= "\r\n";

			// General End
			$blacklisturl = admin_url( 'admin.php?action=blacklist&i=' ) . $info['ip'] . '&_avhnonce=' . avh_create_nonce( $info['ip'] );
			$message .= sprintf( __( 'Add to the local blacklist: %s' ), $blacklisturl ) . "\r\n";
			$this->mail( $to, $subject, $message );
		}

		// Update the counter
		$period = date( 'Ym' );
		if ( array_key_exists( $period, $data['counters'] ) ) {
			$data['counters'][$period] += 1;
		} else {
			$data['counters'][$period] = 1;
		}
		$this->core->saveData( $data );
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
	 * Sends the email
	 *
	 */
	function mail ( $to, $subject, $message )
	{
		$message .= "\r\n" . '--' . "\r\n";
		$message .= sprintf( __( 'Your blog is protected by AVH First Defense Against Spam v%s' ), $this->Settings->getSetting( 'version' ) ) . "\r\n";
		$message .= 'http://blog.avirtualhome.com/wordpress-plugins' . "\r\n";

		wp_mail( $to, $subject, $message );
		return;
	}
}
?>