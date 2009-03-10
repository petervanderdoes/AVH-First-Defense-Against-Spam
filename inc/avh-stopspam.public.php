<?php
class AVHStopSpamPublic extends AVHStopSpamCore
{

	/**
	 * PHP5 Constructor
	 *
	 */
	function __construct ()
	{
		parent::__construct();
		
		// Initialize!
		add_action( 'wp_head', array (&$this, 'initPublic' ) );
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

	function initPublic ()
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

	function handleSpammer ( $ip, $info, $time )
	{
		$action = $this->getOption( 'action', 'general' );
		$actions = strrev( str_pad( decbin( $action ), 4, '0', STR_PAD_LEFT ) );
		
		// First option: Email
		if ( '1' == $actions{0} ) {
			$site_name =  str_replace('"', "'", get_option( 'blogname' ));
			
			$to = get_option( 'admin_email' );
			$site_email = str_replace(array('<', '>'), array('', ''), $to);
			
			$subject = sprintf( __( '[%s] Spammer detected' ), $site_name) ;
			
			$message =  sprintf('Spam IP:	%s',$ip) ."\r\n";
			$message .= sprintf('Last Seen:	%s', $info['lastseen']) . "\r\n";
			$message .= sprintf('Frequency:	%s', $info['frequency']) . "\r\n";
			$message .= sprintf('Call took:	%s',$time) . "\r\n";
			
			if ($info['frequency'] >= $this->options['spam']['whentodie']) {
				$message .= sprinf('Treshhold for dying: %s',$this->options['spam']['whentodie']) ."\r\n";
			}
			
			wp_mail($to, $subject, $message);
			
		}
		
		// Second Option: Add the the .htaccess file
		if ('1' == $actions{1}) {
			$counter=$this->options['spam']['counter'];
			$counter++;
			$this->options['spam']['counter'] = $counter;
			update_option($this->db_options_name_core,$this->options);
			
		}
		// This should be the very last option
		if ( $this->options['general']['die'] ) {
			if ($info['frequency'] >= $this->options['spam']['whentodie']) {
				die();
			}
		}
	}

}
?>