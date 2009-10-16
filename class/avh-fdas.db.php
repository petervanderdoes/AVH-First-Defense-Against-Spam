<?php
/**
 * AVH First Defense Against Spam Database Class
 *
 * @author Peter van der Does
 * @copyright 2009
 */
class AVH_FDAS_DB
{

	/**
	 * PHP4 constructor.
	 *
	 */
	function AVH_FDAS_DB ()
	{
		return $this->__construct();
	}

	/**
	 * PHP5 Constructor
	 * Init the Database Abstraction layer
	 *
	 */
	function __construct ()
	{
		register_shutdown_function( array (&$this, '__destruct' ) );
	}

	/**
	 * PHP5 style destructor and will run when database object is destroyed.
	 *
	 * @return bool Always true
	 */
	function __destruct ()
	{
		return true;
	}

	/**
	 * Get all the DB info of an IP
	 * @param $ip
	 * @return ip Object (false if not found)
	 */
	function getIP ( $ip )
	{
		global $wpdb;

		// Query database
		$result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->avhfdasipcache WHERE ip = INET_ATON(%s)", $ip ) );

		if ( $result ) {
			return $result;
		} else {
			return false;
		}
	}

	/**
	 * Insert the IP into the DB
	 * @param $ip string
	 * @param $spam number
	 * @return Object (false if not found)
	 */
	function insertIP ( $ip, $spam )
	{
		global $wpdb;
		$date = current_time( 'mysql' );
		$result = $wpdb->query( $wpdb->prepare( "INSERT INTO $wpdb->avhfdasipcache (ip, spam, added, lastseen) VALUES (INET_ATON(%s), %d, %s, %s)", $ip, $spam, $date, $date ) );

		if ( $result ) {
			return $result;
		} else {
			return false;
		}
	}

	/**
	 * Insert the IP into the DB
	 * @param $ip string
	 * @return Object (false if not found)
	 */
	function updateIP ( $ip )
	{
		global $wpdb;
		$date = current_time( 'mysql' );
		$result = $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->avhfdasipcache SET lastseen=%s WHERE ip=INET_ATON(%s)", $date, $ip ) );

		if ( $result ) {
			return $result;
		} else {
			return false;
		}
	}

}
?>