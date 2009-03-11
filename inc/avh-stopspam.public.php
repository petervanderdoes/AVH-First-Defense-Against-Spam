<?php
class AVHStopSpamPublic extends AVHStopSpamCore
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
	}

	/**
	 * PHP4 Constructor
	 *
	 * @return AVHStopSpamPublic
	 */
	function AVHStopSpamPublic ()
	{
		$this->__construct();
	
	}

	/**
	 * Handle the main action.
	 * Get the visitors IP and call the stopforumspam API to check if it's a known spammer
	 *
	 * @since 1.0
	 */
	function handleMainAction ()
	{
		$time_start = microtime( true );
		$ip = $_SERVER['REMOTE_ADDR'];
		$spaminfo = $this->handleRESTcall( $this->getRestIPLookup( $ip ) );
		$time_end = microtime( true );
		$time = $time_end - $time_start;
		if ( 'yes' == $spaminfo['appears'] ) {
			$this->handleSpammer( $ip, $spaminfo, $time );
		}
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
		$counter = $this->options['spam']['counter'];
		$counter ++;
		$this->options['spam']['counter'] = $counter;
		update_option( $this->db_options_name_core, $this->options );
		
		// Email
		if ( $info['frequency'] >= $this->options['spam']['whentoemail'] ) {
			$site_name = str_replace( '"', "'", get_option( 'blogname' ) );
			
			$to = get_option( 'admin_email' );
			
			$subject = sprintf( __( '[%s] AVH Stop Spam - Spammer detected', 'avhstopspam' ), $site_name );
			
			$message = __( 'Stop Forum Spam has the following statistics:', 'avhstopspam' ) . "\r\n";
			$message .= sprintf( __( 'Spam IP:	%s', 'avhstopspam' ), $ip ) . "\r\n";
			$message .= sprintf( __( 'Last Seen:	%s', 'avhstopspam' ), $info['lastseen'] ) . "\r\n";
			$message .= sprintf( __( 'Frequency:	%s', 'avhstopspam' ), $info['frequency'] ) . "\r\n";
			
			if ( $info['frequency'] >= $this->options['spam']['whentoblock'] ) {
				$message .= sprintf( __( 'Treshhold:	%s', 'avhstopspam' ), $this->options['spam']['whentoblock'] ) . "\r\n";
			}
			
			$message .= sprintf( __( 'Accessing:	%s', 'avhstopspam' ), $_SERVER['REQUEST_URI'] ) . "\r\n";
			
			$message .= sprintf( __( 'Call took:	%s', 'avhastopspam' ), $time ) . "\r\n";
			wp_mail( $to, $subject, $message );
		
		}
		
		// This should be the very last option.
		if ( $info['frequency'] >= $this->options['spam']['whentodie'] ) {
			if ( $this->options['spam']['diewithmessage'] ) {
				wp_die( sprintf( __( 'Access has been blocked.<BR />Your IP [$s] is registered in the Stop Forum Spam database. If you feel this is incorrect please contact <a href="http://www.stopforumspam.com">Stop Forum Spam</a>', 'avhstopspam' ), $ip ) );
				;
			}
		}
	}
}
?>