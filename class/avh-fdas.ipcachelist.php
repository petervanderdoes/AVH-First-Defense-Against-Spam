<?php
if (! defined('AVH_FRAMEWORK'))
	die('You are not allowed to call this page directly.');

class AVH_FDAS_IPCacheList extends WP_List_Table {
	/**
	 *
	 * @var AVH_FDAS_Core
	 */
	private $_core;
	/**
	 * @var AVH_Settings_Registry
	 */
	private $_settings;
	/**
	 * @var AVH_Class_registry
	 */
	private $_classes;
	/**
	 *
	 * @var AVH_FDAS_DB
	 */
	private $_ipcachedb;
	
	
	function __construct() {

		// Get The Registry
		$this->_settings = AVH_FDAS_Settings::getInstance();
		$this->_classes = AVH_FDAS_Classes::getInstance();
		// Initialize the plugin
		$this->_core = $this->_classes->load_class('Core', 'plugin', TRUE);
		$this->_ipcachedb = $this->_classes->load_class('DB', 'plugin', TRUE);
		
		$default_status = get_user_option( 'avhfdas_ip_cache_list_last_view' );
		if ( empty( $default_status ) )
			$default_status = 'all';
		$status = isset( $_REQUEST['avhfdas_ip_cache_list_status'] ) ? $_REQUEST['avhfdas_ip_cache_list_status'] : $default_status;
		if ( !in_array( $status, array( 'all', 'ham', 'spam', 'search' ) ) ){
			$status = 'all';}
		if ( $status != $default_status && 'search' != $status ){
			update_user_meta( get_current_user_id(), 'avhfdas_ip_cache_list_last_view', $status );}

		$page = $this->get_pagenum();
		
		parent::WP_List_Table( array(
			'plural' => 'ip\'s',
			'singular' => 'ip',
			'ajax' => true,
		) );
	}
	
	function get_table_classes() {
		return array( 'widefat', $this->_args['plural'] );
	}

	function ajax_user_can() {
		return TRUE;
	}

	function prepare_items() {
		global $post_id, $ip_status, $search, $comment_type;

		$ip_status = isset( $_REQUEST['ip_status'] ) ? $_REQUEST['ip_status'] : 'all';
		if ( !in_array( $ip_status, array( 'all', 'ham', 'spam' ) ) ) {
			$_status = 'all';
		}
		
		$search = ( isset( $_REQUEST['s'] ) ) ? $_REQUEST['s'] : '';
		
		$orderby = ( isset( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : '';
		$order = ( isset( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : '';
		
		$ips_per_page = $this->get_per_page( $ip_status );
				
		$doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;

		if ( isset( $_REQUEST['number'] ) ) {
			$number = (int) $_REQUEST['number'];
		}
		else {
			$number = $ips_per_page + min( 8, $ips_per_page ); // Grab a few extra
		}
		
		$page = $this->get_pagenum();

		if ( isset( $_REQUEST['start'] ) ) {
			$start = $_REQUEST['start'];
		} else {
			$start = ( $page - 1 ) * $ips_per_page;
		}

		if ( $doing_ajax && isset( $_REQUEST['offset'] ) ) {
			$start += $_REQUEST['offset'];
		}
		
		$args = array ('status' => $ip_status,
			'search' => $search,
			'offset' => $start,
			'number' => $number,
			'orderby' => $orderby,
			'order' => $order,);
		
		$_ips = $this->_ipcachedb->getIPs( $args );
		$this->items = array_slice( $_ips, 0, $ips_per_page );
		$this->extra_items = array_slice( $_ips, $ips_per_page );

		$total_ips = $this->_ipcachedb->getIPs( array_merge( $args, array('count' => true, 'offset' => 0, 'number' => 0) ) );

		$this->set_pagination_args( array(
			'total_items' => $total_ips,
			'per_page' => $ips_per_page,
		) );
		
	}

	function _search_callback( ) {
		static $term;
		if ( is_null( $term ) )
			$term = stripslashes( $_REQUEST['s'] );
		// @todo Create search funtion for IP
		return false;
	}

	function _order_callback( $plugin_a, $plugin_b ) {
		global $orderby, $order;

		$a = $plugin_a[$orderby];
		$b = $plugin_b[$orderby];

		if ( $a == $b )
			return 0;

		if ( 'DESC' == $order )
			return ( $a < $b ) ? 1 : -1;
		else
			return ( $a < $b ) ? -1 : 1;
	}

	function get_per_page( $ip_status = 'all' ) {
		$ips_per_page = $this->get_items_per_page( 'edit_ips_per_page' );
		$ips_per_page = apply_filters( 'ips_per_page', $ips_per_page, $ip_status );
		return $ips_per_page;
	}
	
	function no_items() {
		global $plugins;

		if ( !empty( $plugins['all'] ) )
			_e( 'No plugins found.' );
		else
			_e( 'You do not appear to have any plugins available at this time.' );
	}

	function get_columns() {
		global $status;

		return array(
			'cb'          =>  '<input type="checkbox" />',
			'ip'        => __( 'IP' ),
			'spam' => __( 'Ham/Spam', 'avh-fdas' ),
			'added' => __('Date added', 'avh-fdas'),
			'lastseen' => __('Last seen', 'avh-fdas')
		);
	}

	function get_sortable_columns() {
		return array();
	}

	function display_tablenav( $which ) {
		global $status;

		parent::display_tablenav( $which );
	}

	function get_views ()
	{
		global $totals, $status;
		
		$total_ips = $this->_ipcachedb->getIPs(array('count'=>true, 'offset'=>0, 'number'=>0));
		$num_ips = $this->_ipcachedb->countIps();
		$status_links = array();
		$stati = array('all'=>_nx_noop('All', 'All', 'ip\'s'), // singular not used
			'ham'=>_n_noop('Ham <span class="count">(<span class="ham-count">%s</span>)</span>', 'Ham <span class="count">(<span class="ham-count">%s</span>)</span>'),
			'spam'=>_n_noop('Spam <span class="count">(<span class="spam-count">%s</span>)</span>', 'Spam <span class="count">(<span class="spam-count">%s</span>)</span>'));
		
		foreach ($stati as $status => $label) {
			$class = ($status == $ip_status) ? ' class="current"' : '';
			
			if (! isset($num_ips->$status))
				$num_ips->$status = 10;
			$link = add_query_arg('status', $status, $link);
				/*
			// I toyed with this, but decided against it. Leaving it in here in case anyone thinks it is a good idea. ~ Mark
			if ( !empty( $_REQUEST['s'] ) )
				$link = add_query_arg( 's', esc_attr( stripslashes( $_REQUEST['s'] ) ), $link );
			*/
			$status_links[$status] = "<a href='$link'$class>" . sprintf(translate_nooped_plural($label, $num_comments->$status), number_format_i18n($num_comments->$status)) . '</a>';
		}
		
		return $status_links;
	}

	function get_bulk_actions() {
		global $status;

		$actions = array();

		$screen = get_current_screen();

		$actions['delete-selected'] = __( 'Delete' );
		
		return $actions;
	}

	function bulk_actions( $which ) {
		global $status;

		parent::bulk_actions( $which );
	}

	function extra_tablenav( $which ) {
		global $status;

		if ( 'recently_activated' == $status ) { ?>
<div class="alignleft actions">
				<?php submit_button( __( 'Clear List' ), 'secondary', 'clear-recent-list', false ); ?>
			</div>
<?php }
	}

	function current_action() {
		if ( isset($_POST['clear-recent-list']) )
			return 'clear-recent-list';

		return parent::current_action();
	}

	function display_rows() {
		global $status;

		$screen = get_current_screen();

		foreach ( $this->items as $plugin_file => $plugin_data )
			$this->single_row( $plugin_file, $plugin_data );
	}

	function single_row( $plugin_file, $plugin_data ) {
		global $status, $page, $s;

		$context = $status;

		$screen = get_current_screen();

		// preorder
		$actions = array(
			'network_deactivate' => '', 'deactivate' => '',
			'network_only' => '', 'activate' => '',
			'network_activate' => '',
			'edit' => '',
			'delete' => '',
		);

		$prefix = $screen->is_network ? 'network_admin_' : '';
		$actions = apply_filters( $prefix . 'plugin_action_links', array_filter( $actions ), $plugin_file, $plugin_data, $context );
		$actions = apply_filters( $prefix . "plugin_action_links_$plugin_file", $actions, $plugin_file, $plugin_data, $context );

		$class = $is_active ? 'active' : 'inactive';
		$checkbox_id =  "checkbox_" . md5($plugin_data['Name']);
		$checkbox = in_array( $status, array( 'mustuse', 'dropins' ) ) ? '' : "<input type='checkbox' name='checked[]' value='" . esc_attr( $plugin_file ) . "' id='" . $checkbox_id . "' /><label class='screen-reader-text' for='" . $checkbox_id . "' >" . __('Select') . " " . $plugin_data['Name'] . "</label>";
		if ( 'dropins' != $context ) {
			$description = '<p>' . ( $plugin_data['Description'] ? $plugin_data['Description'] : '&nbsp;' ) . '</p>';
			$plugin_name = $plugin_data['Name'];
		}

		$id = sanitize_title( $plugin_name );

		echo "<tr id='$id' class='$class'>";

		list( $columns, $hidden ) = $this->get_column_info();

		foreach ( $columns as $column_name => $column_display_name ) {
			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			switch ( $column_name ) {
				case 'cb':
					echo "<th scope='row' class='check-column'>$checkbox</th>";
					break;
				case 'name':
					echo "<td class='plugin-title'$style><strong>$plugin_name</strong>";
					echo $this->row_actions( $actions, true );
					echo "</td>";
					break;
				case 'description':
					echo "<td class='column-description desc'$style>
						<div class='plugin-description'>$description</div>
						<div class='$class second plugin-version-author-uri'>";

					$plugin_meta = array();
					if ( !empty( $plugin_data['Version'] ) )
						$plugin_meta[] = sprintf( __( 'Version %s' ), $plugin_data['Version'] );
					if ( !empty( $plugin_data['Author'] ) ) {
						$author = $plugin_data['Author'];
						if ( !empty( $plugin_data['AuthorURI'] ) )
							$author = '<a href="' . $plugin_data['AuthorURI'] . '" title="' . esc_attr__( 'Visit author homepage' ) . '">' . $plugin_data['Author'] . '</a>';
						$plugin_meta[] = sprintf( __( 'By %s' ), $author );
					}
					if ( ! empty( $plugin_data['PluginURI'] ) )
						$plugin_meta[] = '<a href="' . $plugin_data['PluginURI'] . '" title="' . esc_attr__( 'Visit plugin site' ) . '">' . __( 'Visit plugin site' ) . '</a>';

					$plugin_meta = apply_filters( 'plugin_row_meta', $plugin_meta, $plugin_file, $plugin_data, $status );
					echo implode( ' | ', $plugin_meta );

					echo "</div></td>";
					break;
				default:
					echo "<td class='$column_name column-$column_name'$style>";
					do_action( 'manage_plugins_custom_column', $column_name, $plugin_file, $plugin_data );
					echo "</td>";
			}
		}

		echo "</tr>";

		do_action( 'after_plugin_row', $plugin_file, $plugin_data, $status );
		do_action( "after_plugin_row_$plugin_file", $plugin_file, $plugin_data, $status );
	}
}

?>
