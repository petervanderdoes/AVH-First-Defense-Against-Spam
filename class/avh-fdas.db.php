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
	 * PHP5 Constructor
	 * Init the Database Abstraction layer
	 *
	 */
	public function __construct ()
	{
		register_shutdown_function(array(&$this, '__destruct'));
	}

	/**
	 * PHP5 style destructor and will run when database object is destroyed.
	 *
	 * @return bool Always true
	 */
	public function __destruct ()
	{
		return true;
	}

	/**
	 * Get all the DB info of an IP
	 * @param $ip
	 * @return ip Object (false if not found)
	 */
	public function getIP ($ip)
	{
		global $wpdb;
		// Query database
		$result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->avhfdasipcache WHERE ip = INET_ATON(%s)", $ip));
		if ($result) {
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
	public function insertIP ($ip, $spam)
	{
		global $wpdb;
		$date = current_time('mysql');
		$result = $wpdb->query(
		$wpdb->prepare("INSERT INTO $wpdb->avhfdasipcache (ip, spam, added, lastseen) VALUES (INET_ATON(%s), %d, %s, %s)", $ip, 
		$spam, $date, $date));
		if ($result) {
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
	public function updateIP ($ip)
	{
		global $wpdb;
		$date = current_time('mysql');
		$result = $wpdb->query($wpdb->prepare("UPDATE $wpdb->avhfdasipcache SET lastseen=%s WHERE ip=INET_ATON(%s)", $date, $ip));
		if ($result) {
			return $result;
		} else {
			return false;
		}
	}

	/**
	 * Mark an known IP as spam
	 * @param $ip
	 */
	public function doMarkIPSpam ($ip)
	{
		global $wpdb;
		$ip_info = $this->getIP($ip);
		if (is_object($ip_info)) {
			$result = $wpdb->query($wpdb->prepare("UPDATE $wpdb->avhfdasipcache SET spam=1 WHERE ip=INET_ATON(%s)", $ip));
		}
	}
}
?>