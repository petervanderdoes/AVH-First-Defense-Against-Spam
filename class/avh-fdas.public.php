<?php
class AVH_FDAS_Public
{
	var $core;

	/**
	 * PHP5 Constructor
	 *
	 */
	function __construct ()
	{
		// Initialize the plugin
		$this->core = & AVH_FDAS_Singleton::getInstance( 'AVH_FDAS_Core' );

		// Public actions and filters
		add_action( 'get_header', array (&$this, 'actionHandleMainAction' ) );
		add_action('preprocess_comment', array(&$this,'actionHandleSFSAction'),1);
		add_action( 'comment_form', array (&$this, 'actionAddNonceFieldToComment' ) );
		add_filter( 'preprocess_comment', array (&$this, 'filterCheckNonceFieldToComment' ), 1 );

		// Private actions for Cron
		add_action( 'avhfdas_clean_nonce', array (&$this, 'actionHandleCronCleanNonce' ) );
		add_action( 'avhfdas_clean_ipcache', array (&$this, 'actionHandleCronCleanIPCache' ) );

	}

	/**
	 * PHP4 Constructor
	 *
	 * @return AVH_FDAS_Public
	 */
	function AVH_FDAS_Public ()
	{
		$this->__construct();
	}

	/**
	 * Add a nonce field to the comments.
	 *
	 * @WordPress Action - comment_form
	 *
	 */
	function actionAddNonceFieldToComment ()
	{
		global $post;
		$post_id = 0;
		if ( ! empty( $post ) ) {
			$post_id = $post->ID;
		}
		echo $this->core->getComment();
		wp_nonce_field( 'avh-first-defense-against-spam_' . $post_id, '_avh_first_defense_against_spam', false );
	}

	/**
	 * Clean up the nonce DB by using Cron
	 *
	 * @WordPress: Action avhfdas_clean_nonce
	 *
	 */
	function actionHandleCronCleanNonce ()
	{
		$removed = 0;
		$options = $this->core->getOptions();
		$all = get_option( $this->core->db_options_nonces );
		if ( is_array( $all ) ) {
			foreach ( $all as $key => $value ) {
				if ( ! $this->core->avh_verify_nonce( $key, $value ) ) {
					unset( $all[$key] );
					$removed ++;
				}
			}
			update_option( $this->core->db_options_nonces, $all );
		}

		if ( $options['general']['cron_nonces_email'] ) {
			$to = get_option( 'admin_email' );
			$subject = sprintf( '[%s] AVH First Defense Against Spam - Cron - ' . __( 'Clean nonces', 'avhfdas' ), get_option( 'blogname' ) );
			$message = sprintf( __( 'Deleted %d nonce\'s from the database', 'avhfdas' ), $removed );
			$this->mail( $to, $subject, $message );
		}
	}

	/**
	 * Cleans the IP cache table
	 *
	 * @WordPress: Action avhfdas_clean_ipcache
	 *
	 */
	function actionHandleCronCleanIPCache ()
	{
		global $wpdb;
		$options = $this->core->getOptions();
		$date = current_time( 'mysql' );
		$days = $options['ipcache']['daystokeep'];
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->avhfdasipcache WHERE ((TO_DAYS(%s))-(TO_DAYS(lastseen))) > %d", $date, $days ) );

		if ( $options['general']['cron_ipcache_email'] ) {
			$to = get_option( 'admin_email' );
			$subject = sprintf( '[%s] AVH First Defense Against Spam - Cron - ' . __( 'Clean IP cache', 'avhfdas' ), get_option( 'blogname' ) );
			$message = sprintf( __( 'Deleted %d IP\'s from the cache', 'avhfdas' ), $result );
			$this->mail( $to, $subject, $message );
		}
	}

	/**
	 * Check the nonce field set with a comment.
	 *
	 * @WordPress Filter preprocess_comment
	 * @param mixed $commentdata
	 * @return mixed
	 * @since 1.2
	 *
	 */
	function filterCheckNonceFieldToComment ( $commentdata )
	{
		// When we're in Admin no need to check the nonce.
		if ( ! defined( 'WP_ADMIN' ) ) {
			if ( empty( $commentdata['comment_type'] ) ) { // If it's a trackback or pingback this has a value
				$nonce = wp_create_nonce( 'avh-first-defense-against-spam_' . $commentdata['comment_post_ID'] );
				if ( $nonce != $_POST['_avh_first_defense_against_spam'] ) {
					if ( 1 == $this->core->options['general']['emailsecuritycheck'] ) {
						$to = get_option( 'admin_email' );
						$ip = $this->core->getUserIP();
						$commentdata['comment_author_email'] = empty( $commentdata['comment_author_email'] ) ? 'meseaffibia@gmail.com' : $commentdata['comment_author_email'];
						$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Comment security check failed', 'avhfdas' ), get_option( 'blogname' ) );
						if ( isset( $_POST['_avh_first_defense_against_spam'] ) ) {
							$message = __( 'Reason:	The nonce check failed.', 'avhfdas' ) . "\r\n";
						} else {
							$message = __( 'Reason:	An attempt was made to directly access wp-comment-post.php', 'avhfdas' ) . "\r\n";
						}
						$message .= sprintf( __( 'Username:	%s', 'avhfdas' ), $commentdata['comment_author'] ) . "\r\n";
						$message .= sprintf( __( 'Email:		%s', 'avhfdas' ), $commentdata['comment_author_email'] ) . "\r\n";
						$message .= sprintf( __( 'IP:		%s', 'avhfdas' ), $ip ) . "\r\n\r\n";
						$message .= __( 'Comment trying to post:', 'avhfdas' ) . "\r\n";
						$message .= __( '--- START OF COMMENT ---', 'avhfdas' ) . "\r\n";
						$message .= $commentdata['comment_content'] . "\r\n";
						$message .= __( '--- END OF COMMENT ---', 'avhfdas' ) . "\r\n\r\n";
						if ( ! empty( $this->core->options['sfs']['sfsapikey'] ) ) {
							$q['action'] = 'emailreportspammer';
							$q['a'] = $commentdata['comment_author'];
							$q['e'] = $commentdata['comment_author_email'];
							$q['i'] = $ip;
							$q['_avhnonce'] = $this->core->avh_create_nonce( $q['a'] . $q['e'] . $q['i'] );
							$query = $this->core->BuildQuery( $q );
							$report_url = admin_url( 'admin.php?' . $query );
							$message .= sprintf( __( 'Report spammer: %s' ), $report_url ) . "\r\n";
						}
						$message .= sprintf( __( 'For more information: http://www.stopforumspam.com/search?q=%s' ), $ip ) . "\r\n\r\n";

						$blacklisturl = admin_url( 'admin.php?action=blacklist&i=' ) . $ip . '&_avhnonce=' . $this->core->avh_create_nonce( $ip );
						$message .= sprintf( __( 'Add to the local blacklist: %s' ), $blacklisturl ) . "\r\n";

						$this->mail( $to, $subject, $message );

					}
					// Only keep track if we have the ability to report add Stop Forum Spam
					if ( ! empty( $this->core->options['sfs']['sfsapikey'] ) ) {
						// Prevent a spam attack to overflow the database.
						if ( ! ($this->checkDB_Nonces( $q['_avhnonce'] )) ) {
							$option = get_option( $this->core->db_options_nonces );
							$option[$q['_avhnonce']] = $q['a'] . $q['e'] . $q['i'];
							update_option( $this->core->db_options_nonces, $option );
						}
					}
					$m = __( '<p>Cheating huh</p>', 'avhfdas' );
					$m .= __( '<p>Protected by: AVH First Defense Against Spam</p>', 'avhfdas' );

					if ( $this->core->options['php']['usehoneypot'] ) {
						$m .= '<p>' . $this->core->options['php']['honeypoturl'] . '</p>';
					}
					wp_die( $m );
				}
			}
			$this->actionHandleMainAction();
		}
		return $commentdata;
	}

	/**
	 * Checks if the spammer is in our database.
	 *
	 * @param string $nonce
	 * @return boolean
	 */
	function checkDB_Nonces ( $nonce )
	{
		$return = false;
		$all = get_option( $this->core->db_options_nonces );
		if ( is_array( $all ) ) {
			if ( array_key_exists( $nonce, $all ) ) {
				$return = true;
			}
		}
		return ($return);
	}

	/**
	 * Handle the main action.
	 * Get the visitors IP and call the stopforumspam API to check if it's a known spammer
	 *
	 * @WordPress Action get_header
	 */
	function actionHandleMainAction ()
	{

		$ip = $this->core->getUserIP();
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
				if ( $options['general']['use_php'] ) {
					$spaminfo = null;
					$spaminfo['detected'] = FALSE;

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
	 * Handle the SFS action.
	 * Only check with SFS when a comment is really posted
	 * Get the visitors IP and call the stopforumspam API to check if it's a known spammer
	 *
	 * @WordPress Action preprocess_comment
	 */
	function actionHandleSFSAction ( $commentdata )
	{

		$ip = $this->core->getUserIP();

		$options = $this->core->getOptions();
		$data = $this->core->getData();

		if ( 1 == $options['general']['useipcache'] ) {
			$ipcachedb = & AVH_FDAS_Singleton::getInstance( 'AVH_FDAS_DB' );
			$time_start = microtime( true );
			$ip_in_cache = $ipcachedb->getIP( $ip );
			$time_end = microtime( true );
			$time = $time_end - $time_start;
			$spaminfo['time'] = $time;
		}

		if ( $options['general']['use_sfs'] || $options['general']['use_php'] ) {
			$spaminfo = null;
			$spaminfo['detected'] = FALSE;

			if ( $options['general']['use_sfs'] ) {
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
				$this->handleSpammer( $ip, $spaminfo, 'comment' );
			} else {
				if ( 1 == $options['general']['useipcache'] && (! isset( $spaminfo['Error'] )) ) {
					$ipcachedb->insertIP( $ip, 0 );
				}
			}
		}
		return ($commentdata);
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
				$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Error detected', 'avhfdas' ), get_option( 'blogname' ) );
				$message = __( 'An error has been detected', 'avhfdas' ) . "\r\n";
				$message .= sprintf( __( 'Error:	%s', 'avhfdas' ), $error ) . "\r\n\r\n";
				$message .= sprintf( __( 'IP:		%s', 'avhfdas' ), $ip ) . "\r\n";
				$message .= sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] ) . "\r\n";
				$message .= sprintf( __( 'Call took:	%s', 'avhafdas' ), $time ) . "\r\n";
				if ( is_set( $spaminfo['Debug'] ) ) {
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
				$spaminfo['engine'] = $this->core->searchengines[$info[2]];
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
	 * @param string $time -
	 *
	 */
	function handleSpammer ( $ip, $info, $whichaction='main' )
	{
		$data = $this->core->getData();
		$options = $this->core->getOptions();

		// Email
		$sfs_email = $options['general']['use_sfs'] && ( int ) $options['sfs']['whentoemail'] >= 0 && ( int ) $info['sfs']['frequency'] >= $options['sfs']['whentoemail'];
		$php_email = $options['general']['use_php'] && ( int ) $options['php']['whentoemail'] >= 0 && $info['php']['type'] >= $options['php']['whentoemailtype'] && ( int ) $info['php']['score'] >= $options['php']['whentoemail'];

		if ( $sfs_email || $php_email ) {

			// General part of the email
			$to = get_option( 'admin_email' );
			$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Spammer detected [%s]', 'avhfdas' ), get_option( 'blogname' ), $ip );
			$message = '';
			$message .= sprintf( __( 'Spam IP:	%s', 'avhfdas' ), $ip ) . "\r\n";
			$message .= sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] ) . "\r\n\r\n";

			// Stop Forum Spam Mail Part
			if ( $options['general']['use_sfs'] && $sfs_email && $whichaction == 'comment') {
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
				$blacklisturl = admin_url( 'admin.php?action=blacklist&i=' ) . $ip . '&_avhnonce=' . $this->core->avh_create_nonce( $ip );
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
			$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Spammer detected [%s]', 'avhfdas' ), get_option( 'blogname' ), $info['ip'] );
			$message = '';
			$message .= sprintf( __( 'Spam IP:	%s', 'avhfdas' ), $info['ip'] ) . "\r\n";
			$message .= sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] ) . "\r\n\r\n";

			$message .= __( 'IP exists in the cache', 'avhfdas' ) . "\r\n";
			$message .= '	' . sprintf( __( 'Check took:			%s', 'avhafdas' ), $info['time'] ) . "\r\n";
			$message .= "\r\n";

			// General End
			$blacklisturl = admin_url( 'admin.php?action=blacklist&i=' ) . $info['ip'] . '&_avhnonce=' . $this->core->avh_create_nonce( $info['ip'] );
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
		$message .= sprintf( __( 'Your blog is protected by AVH First Defense Against Spam v%s' ), $this->core->version ) . "\r\n";
		$message .= 'http://blog.avirtualhome.com/wordpress-plugins' . "\r\n";

		wp_mail( $to, $subject, $message );
		return;
	}
}
?>