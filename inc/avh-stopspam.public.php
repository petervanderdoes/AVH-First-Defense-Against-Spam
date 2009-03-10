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
		$action=getOption('action','general');
		$actions=strrev(str_pad(decbin($action),4,'0',STR_PAD_LEFT));
		
		// Option 1 is email
		if ('1' == $actions{0}) {
			$message = 'Spam IP	' . $ip . '\n';
			$message .= 'Last Seen	' . $info['lastseen'] . '\n';
			$message .= 'Frequency	' . $info['frequency'] . '\n';
			$message .= 'Call took	' . $time . '\n';
			wp_mail( get_option( 'admin_email' ), sprintf( __( '[%s] Spammer detected' ), get_option( 'blogname' ) ), $message );
		}
	}
	
	function getActions($action)
	{
		$actions=array();
		$w=decbin($action);
		$w=str_pad($w,4,'0',STR_PAD_LEFT);
		$l=strlen($w);
		for ($i=$l-1; $i==0;$i--) {
			if ($w{$i} = '1') {
				
			}
		}
	}
}
?>