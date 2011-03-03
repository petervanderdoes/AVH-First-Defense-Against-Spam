<?php

/**
 * AVH First Defense Against Spam Database Class
 *
 * @author Peter van der Does
 * @copyright 2009
 */
class AVH_FDAS_DB
{
	
	private $_query_vars;

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

	public function getIPs ($query_vars)
	{
		global $wpdb;
		
		$defaults = array('ip'=>'', 'added'=>'', 'lastseen'=>'', 'status'=>'all', 'search'=>'', 'offset'=>'', 'number'=>'', 'orderby'=>'', 'order'=>'DESC', 'count'=>FALSE);
		$this->_query_vars = wp_parse_args($query_vars, $defaults);
		extract($this->_query_vars, EXTR_SKIP);
		
		$order = ('ASC' == strtoupper($order)) ? 'ASC' : 'DESC';
		
		if (! empty($orderby)) {
			$ordersby = is_array($orderby) ? $orderby : preg_split('/[,\s]/', $orderby);
			$ordersby = array_intersect($ordersby, array('ip', 'lastseen', 'added', 'spam'));
			$orderby = empty($ordersby) ? 'added' : implode(', ', $ordersby);
		} else {
			$orderby = 'added, ip';
		}
		
		$number = absint($number);
		$offset = absint($offset);
		
		if (! empty($number)) {
			if ($offset) {
				$limits = 'LIMIT ' . $offset . ',' . $number;
			} else {
				$limits = 'LIMIT ' . $number;
			}
		} else {
			$limits = '';
		}
		
		if ($count) {
			$fields = 'COUNT(*)';
		} else {
			$fields = '*';
		}
		
		$join = '';
		switch ($status) {
			case 'ham':
				$where = 'spam = 0';
				break;
			case 'spam':
				$where = 'spam = 1';
				break;
			case 'all':
			default:
				$where = '1=1';
		}
		
		if (! empty($ip)) {
			$where .= $wpdb->prepare(' AND ip = INET_ATON(%d)', $ip);
		}
		
		$query = "SELECT $fields FROM $wpdb->avhfdasipcache $join WHERE $where ORDER BY $orderby $order $limits";
		
		if ($count) {
			return $wpdb->get_var($query);
		}
		
		$ips = $wpdb->get_results($query);
		return $ips;
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
		$result = $wpdb->query($wpdb->prepare("INSERT INTO $wpdb->avhfdasipcache (ip, spam, added, lastseen) VALUES (INET_ATON(%s), %d, %s, %s)", $ip, $spam, $date, $date));
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

	public function countIPs ()
	{
		global $wpdb;
		$key = md5( __CLASS__ . '::'.__FUNCTION__ ) ;
		$last_changed = wp_cache_get('last_changed', 'comment');
		if ( !$last_changed ) {
			$last_changed = time();
			wp_cache_set('last_changed', $last_changed, 'comment');
		}
		$cache_key = "avhfdas-count-ips:$key:$last_changed";
		
		$count = wp_cache_get($cache_key, 'counts');
		
		if (false !== $count) {
			return $count;
		}
		
		$where = '';
		
		$count = $wpdb->get_results("SELECT spam, COUNT( * ) AS num_ips FROM {$wpdb->avhfdasipcache} GROUP BY spam", ARRAY_A);
		
		$total = 0;
		$approved = array('0'=>'ham', '1'=>'spam');
		$known_types = array_keys($approved);
		foreach ((array) $count as $row) {
			// Don't count post-trashed toward totals
			$total += $row['num_ips'];
			if (in_array($row['spam'], $known_types))
				$stats[$approved[$row['spam']]] = (int) $row['num_ips'];
		}
		
		$stats['all'] = $total;
		foreach ($approved as $key) {
			if (empty($stats[$key]))
				$stats[$key] = 0;
		}
		
		$stats = (object) $stats;
		wp_cache_set($cache_key, $stats, 'counts');
		
		return $stats;
	}
}