<?php
class AVH_FDAS_Public extends AVH_FDAS_Core
{

	/**
	 * PHP5 Constructor
	 *
	 */
	function __construct ()
	{
		// Initialize the plugin
		parent::__construct();

		// Public actions and filters
		add_action( 'get_header', array (&$this, 'actionHandleMainAction' ) );

		add_action( 'comment_form', array (&$this, 'actionAddNonceFieldToComment' ) );
		add_filter( 'preprocess_comment', array (&$this, 'filterCheckNonceFieldToComment' ), 1 );

		// Private action for Cron
		add_action( 'avhfdas_clean_nonce', array (&$this, 'actionHandleCronCleanNonce' ) );

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
	 * @since 1.2
	 *
	 */
	function actionAddNonceFieldToComment ()
	{
		global $post;

		$post_id = 0;
		if ( ! empty( $post ) ) {
			$post_id = $post->ID;
		}
		echo $this->comment_general;
		wp_nonce_field( 'avh-first-defense-against-spam_' . $post_id, '_avh_first_defense_against_spam', false );
	}

	/**
	 * Clean up the nonce DB by using Cron
	 *
	 * @WordPress: Action avhfdas_clean_nonce
	 * @since 1.2
	 *
	 */
	function actionHandleCronCleanNonce ()
	{
		$all = get_option( $this->db_options_nonces );
		if ( is_array( $all ) ) {
			foreach ( $all as $key => $value ) {
				if ( ! $this->avh_verify_nonce( $key, $value ) ) {
					unset( $all[$key] );
				}
			}
			update_option( $this->db_options_nonces, $all );
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
					if ( 1 == $this->options['spam']['emailsecuritycheck'] ) {
						$site_name = str_replace( '"', "'", get_option( 'blogname' ) );

						$to = get_option( 'admin_email' );
						$ip = $this->getUserIP();
						$commentdata['comment_author_email'] = empty( $commentdata['comment_author_email'] ) ? 'no@email.address' : $commentdata['comment_author_email'];

						$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Comment security check failed', 'avhfdas' ), $site_name );
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

						if ( ! empty( $this->options['spam']['sfsapikey'] ) ) {
							$q['action'] = 'emailreportspammer';
							$q['a'] = $commentdata['comment_author'];
							$q['e'] = $commentdata['comment_author_email'];
							$q['i'] = $ip;
							$q['_avhnonce'] = $this->avh_create_nonce( $q['a'] . $q['e'] . $q['i'] );
							$query = $this->BuildQuery( $q );
							$report_url = admin_url( 'admin.php?' . $query );
							$message .= sprintf( __( 'Report spammer: %s' ), $report_url ) . "\r\n";
						}

						$message .= sprintf( __( 'For more information: http://www.stopforumspam.com/search?q=%s' ), $ip ) . "\r\n\r\n";

						wp_mail( $to, $subject, $message );
					}

					// Only keep track if we have the ability to report add Stop Forum Spam
					if ( ! empty( $this->options['spam']['sfsapikey'] ) ) {
						// Prevent a spam attack to overflow the database.
						if ( ! ($this->checkDB_Nonces( $q['_avhnonce'] )) ) {
							$option = get_option( $this->db_options_nonces );

							$option[$q['_avhnonce']] = $q['a'] . $q['e'] . $q['i'];

							update_option( $this->db_options_nonces, $option );
						}
					}

					$m = __( '<p>Cheating huh</p>', 'avhfdas' );
					$m .= __( '<p>Protected by: AVH First Defense Against Spam</p>', 'avhfdas' );

					wp_die( $m );
				}
			}
		}
		return $commentdata;
	}


	/**
	 * Checks if the spammer is in our database.
	 *
	 * @param string $nonce
	 * @return boolean
	 * @since 1.2
	 */
	function checkDB_Nonces ( $nonce )
	{
		$return = false;
		$all = get_option( $this->db_options_nonces );
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
	 * @since 1.0
	 */
	function actionHandleMainAction ()
	{
		$ip = $this->getUserIP();
		$ip_in_whitelist = false;

		if ( 1 == $this->options['spam']['usewhitelist'] && $this->options['spam']['whitelist'] ) {
			$ip_in_whitelist = $this->checkWhitelist( $ip );
		}

		if ( ! $ip_in_whitelist ) {
			if ( 1 == $this->options['spam']['useblacklist'] && $this->options['spam']['blacklist'] ) {
				$this->checkBlacklist( $ip ); // The program will terminate if in blacklist.
			}

			$spaminfo=null;
			$spaminfo['detected'] = FALSE;
			$spaminfo['sfs'] = $this->checkStopForumSpam($ip);
			if ('yes' == $spaminfo['sfs']['appears']) {
				$spaminfo['detected'] = true;
			}
			$spaminfo['php'] = $this->checkProjectHoneyPot($ip);

			if ( $spaminfo['detected'] ) {
				$this->handleSpammer( $ip, $spaminfo, $time );
			}
		}
	}

	function checkStopForumSpam ($ip){
					$time_start = microtime( true );
			$spaminfo = $this->handleRESTcall( $this->getRestIPLookup( $ip ) );
			$time_end = microtime( true );
			$time = $time_end - $time_start;

			if ( isset( $spaminfo['Error'] ) ) {
				// Let's give it one more try.
				$time_start = microtime( true );
				$spaminfo = $this->handleRESTcall( $this->getRestIPLookup( $ip ) );
				$time_end = microtime( true );
				$time = $time_end - $time_start;
				if ( isset( $spaminfo['Error'] ) ) {
					$error = $this->getHttpError( $spaminfo['Error'] );
					$site_name = str_replace( '"', "'", get_option( 'blogname' ) );

					$to = get_option( 'admin_email' );

					$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Error detected', 'avhfdas' ), $site_name );

					$message = __( 'An error has been detected', 'avhfdas' ) . "\r\n";
					$message .= sprintf( __( 'Error:	%s', 'avhfdas' ), $error ) . "\r\n\r\n";
					$message .= sprintf( __( 'IP:		%s', 'avhfdas' ), $ip ) . "\r\n";
					$message .= sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] ) . "\r\n";
					$message .= sprintf( __( 'Call took:	%s', 'avhafdas' ), $time ) . "\r\n";

					wp_mail( $to, $subject, $message );
				}
			}
		return ($spaminfo);
	}
    function checkProjectHoneyPot ($ip)
    {
        $rev = implode('.', array_reverse(explode('.', $ip)));
        $projecthoneypot_api_key = 'oufobohxcevj';
        //
        // Check the IP against projecthoneypot.org
        //
        $spaminfo=null;
        $lookup = $projecthoneypot_api_key . '.' . $rev . '.dnsbl.httpbl.org';
        if ($lookup != gethostbyname($lookup)) {
            $sTempArr = explode('.', gethostbyname($lookup));
            $spaminfo['days'] = $sTempArr[1];
            switch ($sTempArr[3]) {
                case "0":
                    $spaminfo['type'] = "Search Engine";
                    break;
                case "1":
                    $spaminfo['type'] = "Suspicious";
                    break;
                case "2":
                    $spaminfo['type'] = "Harvester";
                    break;
                case "3":
                    $spaminfo['type'] = "Suspicious & Harvester";
                    break;
                case "4":
                    $spaminfo['type'] = "Comment Spammer";
                    break;
                case "5":
                    $spaminfo['type'] = "Suspicious & Comment Spammer";
                    break;
                case "6":
                    $spaminfo['type'] = "Harvester & Comment Spammer";
                    break;
                case "7":
                    $spaminfo['type'] = "Suspicious & Harvester & Comment Spammer";
                    break;
            }
            if ('0' == $sTempArr[3]) {
            	$spaminfo['score'] = '0';
            	$spaminfo['engine'] = $this->searchengine[$sTempArr[2]];
            } else {
            	$spaminfo['score'] = $sTempArr[2];
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
		$found = $this->checkList( $ip, $this->options['spam']['blacklist'] );

		if ( $found ) {
			$spaminfo['appears'] = 'yes';
			$spaminfo['frequency'] = abs( $this->options['spam']['whentodie'] ); // Blacklisted IP's will always be terminated.
			$time = 'Blacklisted';
			$this->handleSpammer( $ip, $spaminfo, $time );
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
		$found = $this->checkList( $ip, $this->options['spam']['whitelist'] );
		return $found;
	}

	/**
	 * Check if an IP exists in a list
	 *
	 * @param string $ip
	 * @param string $list
	 * @return boolean
	 *
	 * @since 1.2.3
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
	 * Handle a known spam IP
	 *
	 * @param string $ip - The spammers IP
	 * @param array $info - Information from stopforumspam
	 * @param string $time - Time it took the call to stopforumspam
	 *
	 */
	function handleSpammer ( $ip, $info, $time )
	{

		// Update the counter
		$this->data['spam']['counter'] ++;
		update_option( $this->db_options_data, $this->data );

		// Email
		if ( $this->options['spam']['whentoemail'] >= 0 && ( int ) $info['frequency'] >= $this->options['spam']['whentoemail'] ) {
			$site_name = str_replace( '"', "'", get_option( 'blogname' ) );

			$to = get_option( 'admin_email' );

			$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Spammer detected [%s]', 'avhfdas' ), $site_name, $ip );

			$message = __( 'Stop Forum Spam has the following statistics:', 'avhfdas' ) . "\r\n";
			$message .= sprintf( __( 'Spam IP:	%s', 'avhfdas' ), $ip ) . "\r\n";
			$message .= sprintf( __( 'Last Seen:	%s', 'avhfdas' ), $info['lastseen'] ) . "\r\n";
			$message .= sprintf( __( 'Frequency:	%s', 'avhfdas' ), $info['frequency'] ) . "\r\n\r\n";

			if ( $info['frequency'] >= $this->options['spam']['whentodie'] ) {
				$message .= sprintf( __( 'Threshold (%s) reached. Connection terminated', 'avhfdas' ), $this->options['spam']['whentodie'] ) . "\r\n\r\n";
			}

			$message .= sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] ) . "\r\n";

			if ( 'Blacklisted' == $time ) {
				$message .= __( 'IP was in black list table', 'avhfdas' ) . "\r\n\r\n";
			} else {
				$message .= sprintf( __( 'Call took:	%s', 'avhafdas' ), $time ) . "\r\n\r\n";
				$blacklisturl = admin_url( 'admin.php?action=blacklist&i=' ) . $ip . '&_avhnonce=' . $this->avh_create_nonce( $ip );
				$message .= sprintf( __( 'Add to the local blacklist: %s' ), $blacklisturl ) . "\r\n";
			}

			$message .= sprintf( __( 'For more information: http://www.stopforumspam.com/search?q=%s' ), $ip ) . "\r\n\r\n";

			wp_mail( $to, $subject, $message );

		}

		// This should be the very last option.
		if ( $this->options['spam']['whentodie'] >= 0 && ( int ) $info['frequency'] >= $this->options['spam']['whentodie'] ) {
			if ( 1 == $this->options['spam']['diewithmessage'] ) {
				if ( 'Blacklisted' == $time ) {
					$m = sprintf( __( '<h1>Access has been blocked.</h1><p>Your IP [%s] is registered in our <em>Blacklisted</em> database.<BR /></p>', 'avhfdas' ), $ip );
				} else {
					$m = sprintf( __( '<h1>Access has been blocked.</h1><p>Your IP [%s] is registered in the Stop Forum Spam database.<BR />If you feel this is incorrect please contact <a href="http://www.stopforumspam.com">Stop Forum Spam</a></p>', 'avhfdas' ), $ip );
				}
				$m .= __( '<p>Protected by: AVH First Defense Against Spam</p>', 'avhfdas' );
				wp_die( $m );
			} else {
				die();
			}
		}
	}
}
?>