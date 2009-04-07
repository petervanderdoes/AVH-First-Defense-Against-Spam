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
		add_action( 'get_header', array (&$this, 'handleMainAction' ) );

		add_action('comment_form',array(&$this,'addNonceFieldToComment'));
		add_filter('preprocess_comment',array(&$this,'checkNonceFieldToComment'),1);
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

	function addNonceFieldToComment ()
	{
		global $post;

		$post_id = 0;
		if ( ! empty( $post ) ) {
			$post_id = $post->ID;
		}
		wp_nonce_field( 'avh-first-defense-against-spam_' . $post_id, '_avh_first_defense_against_spam', false );
	}

	function checkNonceFieldToComment ($commentdata)
	{
		if (wp_create_nonce( 'avh-first-defense-against-spam_' . $commentdata['comment_post_ID']) != $_POST['_avh_first_defense_against_spam'] ) {
			$site_name = str_replace( '"', "'", get_option( 'blogname' ) );

			$to = get_option( 'admin_email' );

			$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Invalid nonce field', 'avhfdas' ), $site_name );
			$message = sprintf( __( 'IP:			%s', 'avhfdas' ), $_SERVER['REMOTE_ADDR'] ) . "\r\n";
			$message .= sprintf( __( 'For more information: http://www.stopforumspam.com/search?q=%s' ), $ip ) . "\r\n\r\n";

			wp_mail( $to, $subject, $message );

			die('Cheating huh');
		}
	}
	/**
	 * Handle the main action.
	 * Get the visitors IP and call the stopforumspam API to check if it's a known spammer
	 *
	 * @since 1.0
	 */
	function handleMainAction ()
	{
		$ip = $_SERVER['REMOTE_ADDR'];
		$ip_in_whitelist = false;

		if ( 1 == $this->options['spam']['usewhitelist'] && $this->options['spam']['whitelist'] ) {
			$ip_in_whitelist = $this->checkWhitelist( $ip );
		}

		if ( ! $ip_in_whitelist ) {
			if ( 1 == $this->options['spam']['useblacklist'] && $this->options['spam']['blacklist'] ) {
				$this->checkBlacklist( $ip ); // The program will terminate if in blacklist.
			}

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
					$message .= sprintf( __( 'IP:			%s', 'avhfdas' ), $ip ) . "\r\n";
					$message .= sprintf( __( 'Accessing:	%s', 'avhfdas' ), $_SERVER['REQUEST_URI'] ) . "\r\n";
					$message .= sprintf( __( 'Call took:	%s', 'avhafdas' ), $time ) . "\r\n";

					wp_mail( $to, $subject, $message );
				}
			}

			if ( 'yes' == $spaminfo['appears'] ) {
				$this->handleSpammer( $ip, $spaminfo, $time );
			}
		}
	}

	/**
	 * Check blacklist table
	 *
	 * @param string $ip
	 */
	function checkBlacklist ( $ip )
	{
		$b = explode( "\r\n", $this->options['spam']['blacklist'] );
		if ( in_array( $ip, $b ) ) {
			$spaminfo['appears'] = 'yes';
			$spaminfo['frequency'] = abs( $this->options['spam']['whentodie'] ); // Blaclisted IP's will always be terminated.
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
		$return = false;
		$b = explode( "\r\n", $this->options['spam']['whitelist'] );
		if ( in_array( $ip, $b ) ) {
			$return = true;
		}
		return $return;
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
		$this->data['spam']['counter']++;
		update_option( $this->db_data, $this->data );

		// Email
		if ( $this->options['spam']['whentoemail'] >= 0 && (int)$info['frequency'] >= $this->options['spam']['whentoemail'] ) {
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
				$blacklisturl = admin_url( 'admin.php?action=blacklist&i=' ) . $ip .'&_avhnonce='.$this->avh_create_nonce($ip);
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