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
	public function getIP ($_ip, $_output = OBJECT)
	{
		global $wpdb;
		$_ip = AVH_Common::getIp2long($_ip);
		
		// Query database
		$_result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->avhfdasipcache WHERE ip = %s", $_ip));
		
		if (NULL === $_result) {
			return false;
		}
		if ($_output == OBJECT) {
			return $_ip;
		} elseif ($_output == ARRAY_A) {
			$__comment = get_object_vars($_ip);
			return $__comment;
		} elseif ($_output == ARRAY_N) {
			$__comment = array_values(get_object_vars($_ip));
			return $__comment;
		} else {
			return $_ip;
		}
	}
	/**
	 * Delete an IP from the DB
	 * @param $ip
	 * @return ip Object (false if not found)
	 */
	public function deleteIp($ip)
	{
		global $wpdb;
		$ip  = AVH_Common::getIp2long($ip);
		// Query database
		$result = $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->avhfdasipcache WHERE ip = %s", $ip));
		if ($result) {
			return $result;
		} else {
			return false;
		}
	}

	public function getIpCache ($query_vars)
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
			$orderby = 'ip';
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
			$ip  = AVH_Common::getIp2long($ip);
			$where .= $wpdb->prepare(' AND ip = %s', $ip);
		}
		if ( '' !== $search )
			$where .= $this->_getSearchSql( $search, array( 'ip' ) );
		
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
	public function insertIp ($ip, $spam)
	{
		global $wpdb;
		$ip  = AVH_Common::getIp2long($ip);
		$date = current_time('mysql');
		$result = $wpdb->query($wpdb->prepare("INSERT INTO $wpdb->avhfdasipcache (ip, spam, added, lastseen) VALUES (%s, %d, %s, %s)", $ip, $spam, $date, $date));
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
	public function updateIpCache ($ip_cache_arr)
	{
		global $wpdb;
		
		$ip_cache_arr['ip']  = AVH_Common::getIp2long($ip_cache_arr['ip']);
		
		$_ip = $this->getIpCache($args['ip'], ARRAY_A);
		
		$_ip = esc_sql($_ip);
		
		$ip_cache_arr = array_merge($_ip,$ip_cache_arr);
		
		extract(stripslashes_deep($ip_cache_arr), EXTR_SKIP);

		$data = compact('ip', 'spam', 'added', 'lastseen');
		$return = $wpdb->update( $wpdb->avhfdasipcache, $data, compact( 'ip' ) );

		return $return;
	}

	/**
	 * Mark an known IP as spam
	 * @param $ip
	 */
	public function doMarkIpAsSpam ($ip)
	{
		global $wpdb;
		$ip  = AVH_Common::getIp2long($ip);
		$ip_info = $this->getIP($ip);
		if (is_object($ip_info)) {
			$result = $wpdb->query($wpdb->prepare("UPDATE $wpdb->avhfdasipcache SET spam=1 WHERE ip=%s", $ip));
		}
	}

	public function countIps ()
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
	
	private function _getSearchSql( $string, $cols ) {
		
		if (in_array('ip', $cols)) {
			$ip =  esc_sql(AVH_Common::getIp2long($string));
		}
		$string = esc_sql( like_escape( $string ) );
		
		$searches = array();
		foreach ( $cols as $col ){
			if ('ip' == $col) {
				$searches[] = "$col = '$ip'";
			}
			$searches[] = "$col LIKE '%$string%'";
		}
		return ' AND (' . implode(' OR ', $searches) . ')';
	}
}