<?php
if (! defined('AVH_FRAMEWORK'))
	die('You are not allowed to call this page directly.');

class AVH_FDAS_IPCacheList extends WP_List_Table
{

	/**
	 *
	 * @var AVH_FDAS_Core
	 */
	private $_core;

	/**
	 *
	 * @var AVH_Settings_Registry
	 */
	private $_settings;

	/**
	 *
	 * @var AVH_Class_registry
	 */
	private $_classes;

	/**
	 *
	 * @var AVH_FDAS_DB
	 */
	private $_ipcachedb;
	public $messages;
	public $screen;

	function __construct ()
	{

		// Get The Registry
		$this->_settings = AVH_FDAS_Settings::getInstance();
		$this->_classes = AVH_FDAS_Classes::getInstance();
		// Initialize the plugin
		$this->_core = $this->_classes->load_class('Core', 'plugin', true);
		$this->_ipcachedb = $this->_classes->load_class('DB', 'plugin', true);

		$this->screen = 'avh_f_d_a_s_page_avh_first_defense_against_spam_ip_cache_';
		$default_status = get_user_option('avhfdas_ip_cache_list_last_view');
		if (empty($default_status))
			$default_status = 'all';
		$status = isset($_REQUEST['avhfdas_ip_cache_list_status']) ? $_REQUEST['avhfdas_ip_cache_list_status'] : $default_status;
		if (! in_array($status, array ( 'all', 'ham', 'spam', 'search' ))) {
			$status = 'all';
		}
		if ($status != $default_status && 'search' != $status) {
			update_user_meta(get_current_user_id(), 'avhfdas_ip_cache_list_last_view', $status);
		}

		$page = $this->get_pagenum();

		if (AVH_Common::getWordpressVersion() >= 3.2) {
			parent::__construct(array ( 'plural' => 'ips', 'singular' => 'ip', 'ajax' => true ));
		} else {
			parent::WP_List_Table(array ( 'plural' => 'ips', 'singular' => 'ip', 'ajax' => true ));
		}
	}

	function ajax_user_can ()
	{
		return true;
	}

	function prepare_items ()
	{
		global $post_id, $ip_status, $search, $comment_type;

		$ip_status = isset($_REQUEST['ip_status']) ? $_REQUEST['ip_status'] : 'all';
		if (! in_array($ip_status, array ( 'all', 'ham', 'spam' ))) {
			$ip_status = 'all';
		}

		$search = (isset($_REQUEST['s'])) ? $_REQUEST['s'] : '';

		$orderby = (isset($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'added';
		$order = (isset($_REQUEST['order'])) ? $_REQUEST['order'] : 'DESC';

		$ips_per_page = $this->get_per_page($ip_status);

		$doing_ajax = defined('DOING_AJAX') && DOING_AJAX;

		if (isset($_REQUEST['number'])) {
			$number = (int) $_REQUEST['number'];
		} else {
			$number = $ips_per_page + min(8, $ips_per_page); // Grab a few extra, when changing the 8 changes are need in avh-fdas.ipcachelist.js
		}

		$page = $this->get_pagenum();

		if (isset($_REQUEST['start'])) {
			$start = $_REQUEST['start'];
		} else {
			$start = ($page - 1) * $ips_per_page;
		}

		if ($doing_ajax && isset($_REQUEST['offset'])) {
			$start += $_REQUEST['offset'];
		}

		$args = array ( 'status' => $ip_status, 'search' => $search, 'offset' => $start, 'number' => $number, 'orderby' => $orderby, 'order' => $order );

		$_ips = $this->_ipcachedb->getIpCache($args);
		$this->items = array_slice($_ips, 0, $ips_per_page);
		$this->extra_items = array_slice($_ips, $ips_per_page);

		$total_ips = $this->_ipcachedb->getIpCache(array_merge($args, array ( 'count' => true, 'offset' => 0, 'number' => 0 )));

		$this->set_pagination_args(array ( 'total_items' => $total_ips, 'per_page' => $ips_per_page ));

		$s = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';
	}

	function get_per_page ($ip_status = 'all')
	{
		$ips_per_page = $this->get_items_per_page('ipcachelog_per_page');
		$ips_per_page = apply_filters('ipcachelog_per_page', $ips_per_page, $ip_status);
		return $ips_per_page;
	}

	function no_items ()
	{
		_e('Nothing in the cache.');
	}

	function get_columns ()
	{
		global $status;

		return array ( 'cb' => '<input type="checkbox" />', 'ip' => __('IP'), 'spam' => __('Ham/Spam', 'avh-fdas'), 'added' => __('Date added', 'avh-fdas'), 'lastseen' => __('Last seen', 'avh-fdas') );
	}

	function get_sortable_columns ()
	{
		return array ( 'ip' => 'ip', 'added' => 'added', 'lastseen' => 'lastseen' );
	}

	function display_tablenav ($which)
	{
		global $status;

		parent::display_tablenav($which);
	}

	function get_views ()
	{
		global $totals, $ip_status;

		// $total_ips = $this->_ipcachedb->getIpCache(array ( 'count' => true, 'offset' => 0, 'number' => 0 ));
		$num_ips = $this->_ipcachedb->countIps();
		$status_links = array ();
		$stati = array ( 'all' => _nx_noop('All', 'All', 'ips'), 'ham' => _n_noop('Ham <span class="count">(<span class="ham-count">%s</span>)</span>', 'Ham <span class="count">(<span class="ham-count">%s</span>)</span>'), 'spam' => _n_noop('Spam <span class="count">(<span class="spam-count">%s</span>)</span>', 'Spam <span class="count">(<span class="spam-count">%s</span>)</span>') );

		$link = 'admin.php?page=' . AVH_FDAS_Define::MENU_SLUG_IP_CACHE;

		foreach ($stati as $status => $label) {
			$class = ($status == $ip_status) ? ' class="current"' : '';

			if (! isset($num_ips->$status)) {
				$num_ips->$status = 10;
			}
			$link = add_query_arg('ip_status', $status, $link);
			/*
			 * // I toyed with this, but decided against it. Leaving it in here in case anyone thinks it is a good idea. ~ Mark if ( !empty( $_REQUEST['s'] ) ) $link = add_query_arg( 's', esc_attr( stripslashes( $_REQUEST['s'] ) ), $link );
			 */
			$status_links[$status] = "<a href='$link'$class>" . sprintf(translate_nooped_plural($label, $num_ips->$status), number_format_i18n($num_ips->$status)) . '</a>';
		}

		return $status_links;
	}

	function get_bulk_actions ()
	{
		global $ip_status;

		$actions = array ();

		if (in_array($ip_status, array ( 'all', 'ham' ))) {
			$actions['spam'] = __('Mark as spam');
		}
		if (in_array($ip_status, array ( 'all', 'spam' ))) {
			$actions['ham'] = __('Mark as ham');
		}
		$actions['delete'] = __('Delete');
		$actions['blacklist'] = __('Blacklist');

		return $actions;
	}

	function extra_tablenav ($which)
	{
		global $status;

		if ('recently_activated' == $status) {
			?>
<div class="alignleft actions">
				<?php
			submit_button(__('Clear List'), 'secondary', 'clear-recent-list', false);
			?>
			</div>
<?php
		}
	}

	function current_action ()
	{
		if (isset($_POST['clear-recent-list']))
			return 'clear-recent-list';

		return parent::current_action();
	}

	function display ()
	{
		extract($this->_args);

		wp_nonce_field("fetch-list-" . get_class($this), '_ajax_fetch_list_nonce');

		$this->display_tablenav('top');

		echo '<table class="' . implode(' ', $this->get_table_classes()) . '" cellspacing="0">';
		echo '<thead>';
		echo '<tr>';
		$this->print_column_headers();
		echo '</tr>';
		echo '</thead>';

		echo '<tfoot>';
		echo '<tr>';
		$this->print_column_headers(false);
		echo '</tr>';
		echo '</tfoot>';

		echo '<tbody id="the-ipcache-list" class="list:ipcachelog">';
		$this->display_rows_or_placeholder();
		echo '</tbody>';

		echo '<tbody id="the-extra-ipcache-list" class="list:ipcachelog" style="display: none;">';
		$this->items = $this->extra_items;
		$this->display_rows();
		echo '</tbody>';
		echo '</table>';

		$this->display_tablenav('bottom');
	}

	function single_row ($a_ip)
	{
		$ip = $a_ip;
		$status = ($ip->spam == 0 ? '' : 'spammed');
		echo '<tr id="ip-' . $ip->ip . '" class="' . $status . '">';
		echo $this->single_row_columns($ip);
		echo "</tr>";
	}

	function column_cb ($ip)
	{
		echo "<input type='checkbox' name='delete_ips[]' value='$ip->ip' />";
	}

	function column_ip ($ip)
	{
		global $ip_status;

		$ip_text = long2ip($ip->ip);
		echo $ip_text;

		echo '<div id="inline-' . $ip->ip . '" class="hidden">';
		echo '<div class="ip_hamspam">' . ($ip->spam == 0 ? 'ham' : 'spam') . '</div>';
		echo '</div>';

		$del_nonce = esc_html('_wpnonce=' . wp_create_nonce("delete-ip_$ip->ip"));
		$blacklist_nonce = esc_html('_wpnonce=' . wp_create_nonce("blacklist-ip_$ip->ip"));
		$hamspam_nonce = esc_html('_wpnonce=' . wp_create_nonce("hamspam-ip_$ip->ip"));

		$url = "admin.php?page=avh-first-defense-against-spam-ip-cache-log&i=$ip->ip";

		$ham_url = esc_url($url . "&action=hamip&$hamspam_nonce");
		$spam_url = esc_url($url . "&action=spamip&$hamspam_nonce");
		$blacklist_url = esc_url($url . "&action=blacklistip&$blacklist_nonce");
		$delete_url = esc_url($url . "&action=deleteip&$del_nonce");

		$actions = array ( 'ham' => '', 'spam' => '', 'blacklist' => '', 'delete' => '' );

		if ($ip_status && 'all' != $ip_status) { // not looking at all spam
			$actions['spam'] = "<a href='$spam_url' class='delete:the-ipcache-list:ip-$ip->ip:e7e7d3:f=hs&amp;ns=1' title='" . esc_attr__('Mark this IP as spam', 'avh-fdas') . "'>" . __('Spam', 'avh-fdas') . '</a>';
			$actions['ham'] = "<a href='$ham_url' class='delete:the-ipcache-list:ip-$ip->ip:e7e7d3:f=hs&amp;ns=0' title='" . esc_attr__('Mark this IP as ham', 'avh-fdas') . "'>" . __('Ham', 'avh-fdas') . '</a>';
		} else {
			$actions['spam'] = "<a href='$spam_url' class='dim:the-ipcache-list:ip-$ip->ip:spammed:e7e7d3:e7e7d3:new_status=1' title='" . esc_attr__('Mark this IP as spam', 'avh-fdas') . "'>" . __('Spam', 'avh-fdas') . '</a>';
			$actions['ham'] = "<a href='$ham_url' class='dim:the-ipcache-list:ip-$ip->ip:spammed:e7e7d3:e7e7d3:new_status=0' title='" . esc_attr__('Mark this IP as ham', 'avh-fdas') . "'>" . __('Ham', 'avh-fdas') . '</a>';
		}
		;

		$actions['blacklist'] = "<a href='$blacklist_url' class='delete:the-ipcache-list:ip-$ip->ip:e7e7d3:f=bl delete' title='" . esc_attr__('Blacklist this IP', 'avh-fdas') . "'>" . __('Blacklist', 'avh-fdas') . '</a>';
		$actions['delete'] = "<a href='$delete_url' class='delete:the-ipcache-list:ip-$ip->ip::f=dl delete' title='" . esc_attr__('Delete this IP', 'avh-fdas') . "'>" . __('Delete', 'avh-fdas') . '</a>';
		$i = 0;

		echo '<div class="row-actions">';
		foreach ($actions as $action => $link) {
			++ $i;
			((('ham' == $action || 'spam' == $action) && 2 === $i) || 1 === $i) ? $sep = '' : $sep = ' | ';

			echo "<span class='set_$action'>$sep$link</span>";
		}
		echo '</div>';
	}

	function column_spam ($ip)
	{
		switch ($ip->spam) {
			case 0:
				$text = 'Ham';
				break;
			case 1:
				$text = 'Spam';
				break;
			default:
				$text = 'Unknown';
				break;
		}
		echo $text;
	}

	function column_lastseen ($ip)
	{
		$date = mysql2date(get_option('date_format'), $ip->lastseen) . ' at ' . mysql2date(get_option('time_format'), $ip->lastseen);
		echo $date;
	}

	function column_added ($ip)
	{
		$date = mysql2date(get_option('date_format'), $ip->added) . ' at ' . mysql2date(get_option('time_format'), $ip->added);
		echo $date;
	}
}