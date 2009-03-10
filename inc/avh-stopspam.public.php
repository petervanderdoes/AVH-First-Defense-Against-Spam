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
		$result = $this->handleRESTcall( $this->getRestIPLookup( $ip ) );
		$spaminfo = $this->ConvertXML2Array( $result );
		$time_end = microtime( true );
		$time = $time_end - $time_start;
		if ( 'yes' == $spaminfo['appears'] ) {
			$this->handleSpammer( $ip, $spaminfo, $time );
		}
	}

	function handleSpammer ( $ip, $info, $time )
	{
		$message = 'Spam IP	' . $ip . '\n';
		$message .= 'Last Seen	' . $info['lastseen'] . '\n';
		$message .= 'Frequency	' . $info['frequency'] . '\n';
		$message .= 'Call took	' . $time . '\n';
		wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Spammer detected' ), get_option( 'blogname' ) ), $message );
	}
}
?>