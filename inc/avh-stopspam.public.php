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
		$ip = $_SERVER['REMOTE_ADDR'];
		$result = $this->handleRESTcall( $this->getRestIPLookup( $ip ) );
		$info = $this->ConvertXML2Array( $result );
		if ( 'yes' == $info['appears'] ) {
			$message =  'Spam IP	' . $ip.'\n';
			$message .= 'Last Seen	' . $info['lastseen'].'\n';
			$message .= 'Frequency	' . $info['frequency'];
			wp_mail( 'pdoes@avirtualhome.com', sprintf( __( '[%s] Spammer detected' ), get_option( 'blogname' ) ), $message );
		}
	}
}
?>