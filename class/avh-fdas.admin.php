<?php
final class AVH_FDAS_Admin
{
	/**
	 * Message management
	 *
	 */
	private $_message = '';
	private $_status = '';

	/**
	 *
	 * @var AVH_FDAS_Core
	 */
	private $_core;

	/**
	 * @var AVH_FDAS_Settings
	 */
	private $_settings;

	/**
	 * @var AVH_FDAS_Classes
	 */
	private $_classes;

	/**
	 * @var AVH_FDAS_DB
	 */
	private $_db;

	private $_add_disabled_notice = false;
	private $_hooks = array ();

	/**
	 * PHP5 Constructor
	 *
	 * @return unknown_type
	 */
	public function __construct ()
	{
		// The Settings Registery
		$this->_settings = AVH_FDAS_Settings::getInstance();

		// The Classes Registery
		$this->_classes = AVH_FDAS_Classes::getInstance();

		// Initialize the plugin
		$this->_core = $this->_classes->load_class( 'Core', 'plugin', TRUE );
		$this->_db = $this->_classes->load_class( 'DB', 'plugin', TRUE );

		// Admin URL and Pagination
		$this->_core->admin_base_url = $this->_settings->siteurl . '/wp-admin/admin.php?page=';
		if ( isset( $_GET['pagination'] ) ) {
			$this->_core->actual_page = ( int ) $_GET['pagination'];
		}
		$this->installPlugin();

		// Admin Capabilities
		add_action( 'init', array (&$this, 'actionInitRoles' ) );

		// Admin menu
		add_action( 'admin_menu', array (&$this, 'actionAdminMenu' ) );

		// Add the ajax action
		add_action( 'wp_ajax_avh-fdas-reportcomment', array (&$this, 'actionAjaxReportComment' ) );

		/**
		 * Admin actions
		 */
		add_action( 'admin_action_blacklist', array (&$this, 'actionHandleBlacklistUrl' ) );
		add_action( 'admin_action_emailreportspammer', array (&$this, 'actionHandleEmailReportingUrl' ) );
		add_action( 'in_plugin_update_message-' . AVH_FDAS_Define::PLUGIN_FILE, array (&$this, 'actionInPluginUpdateMessage' ) );

		/**
		 * Admin Filters
		 *
		 */
		add_filter( 'comment_row_actions', array (&$this, 'filterCommentRowActions' ), 10, 2 );
		add_filter( 'plugin_action_links_' . AVH_FDAS_Define::PLUGIN_FILE, array (&$this, 'filterPluginActions' ), 10, 2 );

		// If the version compare fails do not display the Upgrade notice.
		if ( version_compare( PHP_VERSION, '5', '<' ) ) {
			add_filter( 'transient_update_plugins', array (&$this, 'filterDisableUpgrade' ) );
		}

		return;
	}

	/**
	 * Setup Roles
	 *
	 * @WordPress Action init
	 */
	public function actionInitRoles ()
	{

		$role = get_role( 'administrator' );
		if ( $role != null && ! $role->has_cap( 'role_avh_fdas' ) ) {
			$role->add_cap( 'role_avh_fdas' );
		}
		if ( $role != null && ! $role->has_cap( 'role_admin_avh_fdas' ) ) {
			$role->add_cap( 'role_admin_avh_fdas' );
		}
		// Clean var
		unset( $role );
	}

	/**
	 * Add the Tools and Options to the Management and Options page repectively
	 *
	 * @WordPress Action admin_menu
	 *
	 */
	public function actionAdminMenu ()
	{
		add_menu_page( 'AVH F.D.A.S', 'AVH F.D.A.S', 'role_avh_fdas', AVH_FDAS_Define::MENU_SLUG, array (&$this, 'doMenuOverview' ) );
		$this->_hooks['avhfdas_menu_overview'] = add_submenu_page( AVH_FDAS_Define::MENU_SLUG, 'AVH First Defense Against Spam: ' . __( 'Overview', 'avh-fdas' ), __( 'Overview', 'avh-fdas' ), 'role_avh_fdas', AVH_FDAS_Define::MENU_SLUG_OVERVIEW, array (&$this, 'doMenuOverview' ) );
		$this->_hooks['avhfdas_menu_general'] = add_submenu_page( AVH_FDAS_Define::MENU_SLUG, 'AVH First Defense Against Spam:' . __( 'General Options', 'avh-fdas' ), __( 'General Options', 'avh-fdas' ), 'role_avh_fdas', AVH_FDAS_Define::MENU_SLUG_GENERAL, array (&$this, 'doMenuGeneralOptions' ) );
		$this->_hooks['avhfdas_menu_3rd_party'] = add_submenu_page( AVH_FDAS_Define::MENU_SLUG, 'AVH First Defense Against Spam:' . __( '3rd Party Options', 'avh-fdas' ), __( '3rd Party Options', 'avh-fdas' ), 'role_avh_fdas', AVH_FDAS_Define::MENU_SLUG_3RD_PARTY, array (&$this, 'doMenu3rdPartyOptions' ) );
		$this->_hooks['avhfdas_menu_faq'] = add_submenu_page( AVH_FDAS_Define::MENU_SLUG, 'AVH First Defense Against Spam:' . __( 'F.A.Q', 'avh-fdas' ), __( 'F.A.Q', 'avh-fdas' ), 'role_avh_fdas', AVH_FDAS_Define::MENU_SLUG_FAQ, array (&$this, 'doMenuFAQ' ) );

		// Add actions for menu pages
		add_action( 'load-' . $this->_hooks['avhfdas_menu_overview'], array (&$this, 'actionLoadPageHook_Overview' ) );
		add_action( 'load-' . $this->_hooks['avhfdas_menu_general'], array (&$this, 'actionLoadPageHook_General' ) );
		add_action( 'load-' . $this->_hooks['avhfdas_menu_3rd_party'], array (&$this, 'actionLoadPageHook_3rd_party' ) );
		add_action( 'load-' . $this->_hooks['avhfdas_menu_faq'], array (&$this, 'actionLoadPageHook_faq' ) );

		// Register Styles and Scripts
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';
		wp_register_script( 'avhfdas-admin-js', $this->_settings->js_url . '/avh-fdas.admin' . $suffix . '.js', array ('jquery' ), AVH_FDAS_Define::PLUGIN_VERSION, true );
		wp_register_style( 'avhfdas-admin-css', $this->_settings->css_url . '/avh-fdas.admin.css', array (), AVH_FDAS_Define::PLUGIN_VERSION, 'screen' );

	}

	/**
	 * This function is called when there's an update of the plugin available @ WordPress
	 */
	public function actionInPluginUpdateMessage ()
	{
		$response = wp_remote_get( AVH_FDAS_Define::PLUGIN_README_URL, array ('user-agent' => 'WordPress/AVH ' . AVH_FDAS_Define::PLUGIN_VERSION . '; ' . get_bloginfo( 'url' ) ) );
		if ( ! is_wp_error( $response ) || is_array( $response ) ) {
			$data = $response['body'];
			$matches = null;
			if ( preg_match( '~==\s*Changelog\s*==\s*=\s*Version\s*[0-9.]+\s*=(.*)(=\s*Version\s*[0-9.]+\s*=|$)~Uis', $data, $matches ) ) {
				$changelog = ( array ) preg_split( '~[\r\n]+~', trim( $matches[1] ) );

				$prev_version = null;
				preg_match( '([0-9.]+)', $matches[2], $prev_version );
				echo '<div style="color: #f00;">What\'s new in this version:</div><div style="font-weight: normal;">';
				$ul = false;

				foreach ( $changelog as $index => $line ) {
					if ( preg_match( '~^\s*\*\s*~', $line ) ) {
						if ( ! $ul ) {
							echo '<ul style="list-style: disc; margin-left: 20px;">';
							$ul = true;
						}
						$line = preg_replace( '~^\s*\*\s*~', '', htmlspecialchars( $line ) );
						echo '<li style="width: 50%; margin: 0; float: left; ' . ($index % 2 == 0 ? 'clear: left;' : '') . '">' . $line . '</li>';
					} else {
						if ( $ul ) {
							echo '</ul><div style="clear: left;"></div>';
							$ul = false;
						}
						echo '<p style="margin: 5px 0;">' . htmlspecialchars( $line ) . '</p>';
					}
				}

				if ( $ul ) {
					echo '</ul><div style="clear: left;"></div>';
				}

				if ( $prev_version[0] != AVH_FDAS_Define::PLUGIN_VERSION ) {
					echo '<div style="color: #f00; font-weight: bold;">';
					echo '<br />The installed version, ' . AVH_FDAS_Define::PLUGIN_VERSION . ', is more than one version behind.<br />';
					echo 'More changes have been made since the currently installed version, consider checking the changelog at the plugin\'s <a href="http://blog.avirtualhome.com/wordpress-plugins/avh-first-defense-against-spam/" target="_blank">homepage</a>';
					echo '</div><div style="clear: left;"></div>';
				}

				echo '</div>';
			}
		}
	}

	/**
	 * This function allows the upgrade notice not to appear
	 * @param $option
	 */
	function filterDisableUpgrade ( $option )
	{

		$this_plugin = $this->_settings->plugin_basename;

		// Allow upgrade for version 2
		if ( version_compare( $option->response[$this_plugin]->new_version, '2', '>=' ) ) return $option;
		if ( isset( $option->response[$this_plugin] ) ) {
			//Clear its download link:
			$option->response[$this_plugin]->package = '';
			//Add a notice message
			if ( $this->_add_disabled_notice == false ) {
				add_action( "in_plugin_update_message-$this->plugin_name", create_function( '', 'echo \'<br /><span style="color:red">You need to have PHP 5.2 or higher for the new version !!!</span>\';' ) );
				$this->_add_disabled_notice = true;
			}
		}
		return $option;
	}

	/**
	 * Setup everything needed for the Overview page
	 *
	 */
	public function actionLoadPageHook_Overview ()
	{
		add_meta_box( 'avhfdasBoxStats', __( 'Statistics', 'avhfdas' ), array (&$this, 'metaboxMenuOverview' ), $this->_hooks['avhfdas_menu_overview'], 'normal', 'core' );

		add_filter( 'screen_layout_columns', array (&$this, 'filterScreenLayoutColumns' ), 10, 2 );

		// WordPress core Styles and Scripts
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
		wp_admin_css( 'css/dashboard' );

		// Plugin Style and Scripts
		wp_enqueue_script( 'avhfdas-admin-js' );
		wp_enqueue_style( 'avhfdas-admin-css' );

	}

	/**
	 * Menu Page Overview
	 *
	 * @return none
	 */
	public function doMenuOverview ()
	{
		global $screen_layout_columns;

		// This box can't be unselectd in the the Screen Options
		add_meta_box( 'avhfdasBoxDonations', __( 'Donations', 'avhfdas' ), array (&$this, 'metaboxDonations' ), $this->_hooks['avhfdas_menu_overview'], 'normal', 'core' );
		$hide2 = '';
		switch ( $screen_layout_columns )
		{
			case 2 :
				$width = 'width:49%;';
				break;
			default :
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}

		echo '<div class="wrap avhfdas-wrap">';
		echo $this->_displayIcon( 'index' );
		echo '<h2>' . __( 'AVH First Defense Against Spam Overview', 'avhfdas' ) . '</h2>';
		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';
		echo '			<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes( $this->_hooks['avhfdas_menu_overview'], 'normal', '' );
		echo '			</div>';
		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes( $this->_hooks['avhfdas_menu_overview'], 'side', '' );
		echo '			</div>';
		echo '		</div>';
		echo '<form style="display: none" method="get" action="">';
		echo '<p>';
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		echo '</p>';
		echo '</form>';
		echo '<br class="clear"/>';
		echo '	</div>'; //dashboard-widgets-wrap
		echo '</div>'; // wrap


		$this->_printAdminFooter();
	}

	/**
	 * Metabox Overview of settings
	 *
	 */
	public function metaboxMenuOverview ()
	{
		global $wpdb;

		echo '<p class="sub">';
		_e( 'Spam Statistics', 'avhfdas' );
		echo '</p>';

		echo '<div class="table">';
		echo '<table>';
		echo '<tbody>';
		echo '<tr class="first">';

		$data = $this->_core->getData();
		$spam_count = $data['counters'];
		krsort( $spam_count );
		$have_spam_count_data = false;
		$output = '';
		foreach ( $spam_count as $key => $value ) {
			if ( '190001' == $key ) {
				continue;
			}
			$have_spam_count_data = true;
			$date = date_i18n( 'Y - F', mktime( 0, 0, 0, substr( $key, 4, 2 ), 1, substr( $key, 0, 4 ) ) );
			$output .= '<td class="first b">' . $value . '</td>';
			$output .= '<td class="t">' . sprintf( __( 'Spam stopped in %s', 'avhfdas' ), $date ) . '</td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';
		}
		if ( ! $have_spam_count_data ) {
			$output .= '<td class="first b">' . __( 'No statistics yet', 'avhfdas' ) . '</td>';
			$output .= '<td class="t"></td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';
		}

		echo $output;
		echo '</tbody></table></div>';
		echo '<div class="versions">';
		echo '<p>';
		$use_sfs = $this->_core->getOptionElement('general','use_sfs');
		$use_php = $this->_core->getOptionElement('general','use_php');
		if ( $use_sfs || $use_php ) {
			echo __( 'Checking with ', 'avhfdas' );
			echo ($use_sfs ? '<span class="b">' . __( 'Stop Forum Spam', 'avhfdas' ) . '</span>' : '');

			if ( $use_php ) {
				echo ($use_sfs ? __( ' and ', 'avhfdas' ) : ' ');
				echo '<span class="b">' . __( 'Project Honey Pot', 'avhfdas' ) . '</span>';
			}
		}
		echo '</p></div>';
		echo '<p class="sub">';
		_e( 'IP Cache Statistics', 'avhfdas' );
		echo '</p>';
		echo '<br/>';
		echo '<div class="versions">';
		echo '<p>';
		echo 'IP caching is ';
		if ( 0 == $this->_core->getOptionElement('general','useipcache') ) {
			echo '<span class="b">disabled</span>';
			echo '</p></div>';
		} else {
			echo '<span class="b">enabled</span>';
			echo '</p></div>';
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ip) from $wpdb->avhfdasipcache" ) );
			$count_clean = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ip) from $wpdb->avhfdasipcache WHERE spam=0" ) );
			$count_spam = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ip) from $wpdb->avhfdasipcache WHERE spam=1" ) );
			if ( false === $count ) {
				$count = 0;
			}
			if ( false === $count_clean ) {
				$count_clean = 0;
			}
			if ( false === $count_spam ) {
				$count_spam = 0;
			}

			$output = '';
			echo '<div class="table">';
			echo '<table>';
			echo '<tbody>';
			echo '<tr class="first">';
			$output .= '<td class="first b">' . $count . '</td>';
			$text = (1 == $count) ? 'IP' : 'IP\'s';
			$output .= '<td class="t">Total of ' . $text . ' in the cache</td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';

			$output .= '<td class="first b">' . $count_clean . '</td>';
			$text = (1 == $count_clean) ? 'IP' : 'IP\'s';
			$output .= '<td class="t">Total of ' . $text . ' classified as clean</td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';

			$output .= '<td class="first b">' . $count_spam . '</td>';
			$text = (1 == $count_spam) ? 'IP' : 'IP\'s';
			$output .= '<td class="t">Total of ' . $text . ' classified as spam</td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';

			echo $output;
		}
		echo '</tbody></table></div>';

	}

	/**
	 * Setup the General page
	 *
	 */
	public function actionLoadPageHook_General ()
	{

		add_meta_box( 'avhfdasBoxGeneral', 'General', array (&$this, 'metaboxGeneral' ), $this->_hooks['avhfdas_menu_general'], 'normal', 'core' );
		add_meta_box( 'avhfdasBoxIPCache', 'IP Caching', array (&$this, 'metaboxIPCache' ), $this->_hooks['avhfdas_menu_general'], 'normal', 'core' );
		add_meta_box( 'avhfdasBoxCron', 'Cron', array (&$this, 'metaboxCron' ), $this->_hooks['avhfdas_menu_general'], 'normal', 'core' );
		add_meta_box( 'avhfdasBoxBlackList', 'Blacklist', array (&$this, 'metaboxBlackList' ), $this->_hooks['avhfdas_menu_general'], 'side', 'core' );
		add_meta_box( 'avhfdasBoxWhiteList', 'Whitelist', array (&$this, 'metaboxWhiteList' ), $this->_hooks['avhfdas_menu_general'], 'side', 'core' );

		add_filter( 'screen_layout_columns', array (&$this, 'filterScreenLayoutColumns' ), 10, 2 );

		// WordPress core Styles and Scripts
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
		wp_admin_css( 'css/dashboard' );

		// Plugin Style and Scripts
		wp_enqueue_script( 'avhfdas-admin-js' );
		wp_enqueue_style( 'avhfdas-admin-css' );

	}

	/**
	 * Menu Page general options
	 *
	 * @return none
	 */
	public function doMenuGeneralOptions ()
	{
		global $screen_layout_columns;

		$options_general[] = array ('avhfdas[general][diewithmessage]', 'Show message', 'checkbox', 1, 'Show a message when the connection has been terminated.' );
		$options_general[] = array ('avhfdas[general][emailsecuritycheck]', 'Email on failed security check:', 'checkbox', 1, 'Receive an email when a comment is posted and the security check failed.' );

		$options_cron[] = array ('avhfdas[general][cron_nonces_email]', 'Email result of nonces clean up', 'checkbox', 1, 'Receive an email with the total number of nonces that are deleted. The nonces are used to secure the links found in the emails.' );
		$options_cron[] = array ('avhfdas[general][cron_ipcache_email]', 'Email result of IP cache clean up', 'checkbox', 1, 'Receive an email with the total number of IP\'s that are deleted from the IP caching system.' );

		$options_blacklist[] = array ('avhfdas[general][useblacklist]', 'Use internal blacklist', 'checkbox', 1, 'Check the internal blacklist first. If the IP is found terminate the connection, even when the Termination threshold is a negative number.' );
		$options_blacklist[] = array ('avhfdas[lists][blacklist]', 'Blacklist IP\'s:', 'textarea', 15, 'Each IP should be on a separate line<br />Ranges can be defines as well in the following two formats<br />IP to IP. i.e. 192.168.1.100-192.168.1.105<br />Network in CIDR format. i.e. 192.168.1.0/24', 15 );

		$options_whitelist[] = array ('avhfdas[general][usewhitelist]', 'Use internal whitelist', 'checkbox', 1, 'Check the internal whitelist first. If the IP is found don\t do any further checking.' );
		$options_whitelist[] = array ('avhfdas[lists][whitelist]', 'Whitelist IP\'s', 'textarea', 15, 'Each IP should be on a seperate line<br />Ranges can be defines as well in the following two formats<br />IP to IP. i.e. 192.168.1.100-192.168.1.105<br />Network in CIDR format. i.e. 192.168.1.0/24', 15 );

		$options_ipcache[] = array ('avhfdas[general][useipcache]', 'Use IP Caching', 'checkbox', 1, 'Cache the IP\'s that meet the 3rd party termination threshold and the IP\'s that are not detected by the 3rd party. The connection will be terminated if an IP is found in the cache that was perviously determined to be a spammer' );
		$options_ipcache[] = array ('avhfdas[ipcache][email]', 'Email ', 'checkbox', 1, 'Send an email when a connection is terminate based on the IP found in the cache' );
		$options_ipcache[] = array ('avhfdas[ipcache][daystokeep]', 'Days to keep in cache', 'text', 3, 'Keep the IP in cache for the selected days.' );

		if ( isset( $_POST['updateoptions'] ) ) {
			check_admin_referer( 'avh_fdas_generaloptions' );

			$formoptions = $_POST['avhfdas'];
			$options = $this->_core->getOptions();
			$data = $this->_core->getData();

			$all_data = array_merge( $options_general, $options_blacklist, $options_whitelist, $options_ipcache, $options_cron );
			foreach ( $all_data as $option ) {
				$section = substr( $option[0], strpos( $option[0], '[' ) + 1 );
				$section = substr( $section, 0, strpos( $section, '][' ) );
				$option_key = rtrim( $option[0], ']' );
				$option_key = substr( $option_key, strpos( $option_key, '][' ) + 2 );

				switch ( $section )
				{
					case 'general' :
					case 'ipcache' :
						$current_value = $options[$section][$option_key];
						break;
					case 'lists' :
						$current_value = $data[$section][$option_key];
						break;
				}
				// Every field in a form is set except unchecked checkboxes. Set an unchecked checkbox to 0.


				$newval = (isset( $formoptions[$section][$option_key] ) ? esc_attr( $formoptions[$section][$option_key] ) : 0);
				if ( $newval != $current_value ) { // Only process changed fields.
					// Sort the lists
					if ( 'blacklist' == $option_key || 'whitelist' == $option_key ) {
						$b = explode( "\r\n", $newval );
						natsort( $b );
						$newval = implode( "\r\n", $b );
						unset( $b );
					}
					switch ( $section )
					{
						case 'general' :
						case 'ipcache' :
							$options[$section][$option_key] = $newval;
							break;
						case 'lists' :
							$data[$section][$option_key] = $newval;
							break;
					}
				}
			}
			// Add or remove the Cron Job: avhfdas_clean_ipcache - defined in Public Class
			if ( $options['general']['useipcache'] ) {
				// Add Cron Job if it's not scheduled
				if ( ! wp_next_scheduled( 'avhfdas_clean_ipcache' ) ) {
					wp_schedule_event( time(), 'daily', 'avhfdas_clean_ipcache' );
				}
			} else {
				// Remove Cron Job if it's scheduled
				if ( wp_next_scheduled( 'avhfdas_clean_ipcache' ) ) {
					wp_clear_scheduled_hook( 'avhfdas_clean_ipcache' );
				}
			}
			$this->_core->saveOptions( $options );
			$this->_core->saveData( $data );
			$this->_message = __( 'Options saved', 'avhfdas' );
			$this->_status = 'updated fade';
		}
		// Show messages if needed.
		if ( isset( $_REQUEST['m'] ) ) {
			switch ( $_REQUEST['m'] )
			{
				case AVH_FDAS_Define::REPORTED_DELETED :
					$this->_status = 'updated fade';
					$this->_message = sprintf( __( 'IP [%s] Reported and deleted', 'avhfdas' ), esc_attr( $_REQUEST['i'] ) );
					break;
				case AVH_FDAS_Define::ADDED_BLACKLIST :
					$this->_status = 'updated fade';
					$this->_message = sprintf( __( 'IP [%s] has been added to the blacklist', 'avhfdas' ), esc_attr( $_REQUEST['i'] ) );
					break;
				case AVH_FDAS_Define::REPORTED :
					$this->_status = 'updated fade';
					$this->_message = sprintf( __( 'IP [%s] reported.', 'avhfdas' ), esc_attr( $_REQUEST['i'] ) );
					break;
				case AVH_FDAS_Define::ERROR_INVALID_REQUEST :
					$this->_status = 'error';
					$this->_message = sprintf( __( 'Invalid request.', 'avhfdas' ) );
					break;
				case AVH_FDAS_Define::ERROR_NOT_REPORTED :
					$this->_status = 'error';
					$this->_message = sprintf( __( 'IP [%s] not reported. Probably already processed.', 'avhfdas' ), esc_attr( $_REQUEST['i'] ) );
					break;
				case AVH_FDAS_Define::ERROR_EXISTS_IN_BLACKLIST :
					$this->_status = 'error';
					$this->_message = sprintf( __( 'IP [%s] already exists in the blacklist.', 'avhfdas' ), esc_attr( $_REQUEST['i'] ) );
					break;
				default :
					$this->_status = 'error';
					$this->_message = 'Unknown message request';
			}
		}

		$this->_displayMessage();

		$actual_options = array_merge( $this->_core->getOptions(), $this->_core->getData() );

		$hide2 = '';
		switch ( $screen_layout_columns )
		{
			case 2 :
				$width = 'width:49%;';
				break;
			default :
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}
		$data['options_general'] = $options_general;
		$data['options_cron'] = $options_cron;
		$data['options_blacklist'] = $options_blacklist;
		$data['options_whitelist'] = $options_whitelist;
		$data['options_ipcache'] = $options_ipcache;
		$data['actual_options'] = $actual_options;

		echo '<div class="wrap avhfdas-wrap">';
		echo '<div class="wrap">';
		echo $this->_displayIcon( 'options-general' );
		echo '<h2>' . __( 'General Options', 'avhfdas' ) . '</h2>';
		echo '<form name="avhfdas-generaloptions" id="avhfdas-generaloptions" method="POST" action="admin.php?page=' . AVH_FDAS_Define::MENU_SLUG_GENERAL . '" accept-charset="utf-8" >';
		wp_nonce_field( 'avh_fdas_generaloptions' );
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );

		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';
		echo '			<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes( $this->_hooks['avhfdas_menu_general'], 'normal', $data );
		echo '			</div>';
		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes( $this->_hooks['avhfdas_menu_general'], 'side', $data );
		echo '			</div>';
		echo '		</div>';

		echo '<br class="clear"/>';
		echo '	</div>'; //dashboard-widgets-wrap
		echo '</div>'; // wrap


		echo '<p class="submit"><input	class="button-primary"	type="submit" name="updateoptions" value="' . __( 'Save Changes', 'avhfdas' ) . '" /></p>';
		echo '</form>';

		$this->_printAdminFooter();
	}

	/**
	 * Metabox Display the General Options
	 * @param $data array The data is filled menu setup
	 * @return none
	 */
	public function metaboxGeneral ( $data )
	{
		echo $this->_printOptions( $data['options_general'], $data['actual_options'] );
	}

	/**
	 * Metabox Display the Blacklist Options
	 * @param $data array The data is filled menu setup
	 * @return none
	 */
	public function metaboxBlackList ( $data )
	{
		echo $this->_printOptions( $data['options_blacklist'], $data['actual_options'] );
	}

	/**
	 * Metabox Display the Whitelist Options
	 * @param $data array The data is filled menu setup
	 * @return none
	 */
	public function metaboxWhiteList ( $data )
	{
		echo $this->_printOptions( $data['options_whitelist'], $data['actual_options'] );
	}

	/**
	 * Metabox Display the IP cache Options
	 * @param $data array The data is filled menu setup
	 * @return none
	 */
	public function metaboxIPCache ( $data )
	{
		echo '<p>' . __( 'To use IP caching you must enable it below and set the options. IP\'s are stored in the database so if you have a high traffic website the database can grow quickly' );
		echo $this->_printOptions( $data['options_ipcache'], $data['actual_options'] );
	}

	/**
	 * Metabox Display the cron Options
	 * @param $data array The data is filled menu setup
	 * @return none
	 */
	public function metaboxCron ( $data )
	{
		echo '<p>' . __( 'Once a day cron jobs of this plugin run. You can select to receive an email with additional information about the jobs that ran.' );
		echo $this->_printOptions( $data['options_cron'], $data['actual_options'] );
	}

	/**
	 * Setup everything needed for the 3rd party menu
	 *
	 */
	public function actionLoadPageHook_3rd_party ()
	{

		add_meta_box( 'avhfdasBoxSFS', 'Stop Forum Spam', array (&$this, 'metaboxMenu3rdParty_SFS' ), $this->_hooks['avhfdas_menu_3rd_party'], 'normal', 'core' );
		add_meta_box( 'avhfdasBoxPHP', 'Project Honey Pot', array (&$this, 'metaboxMenu3rdParty_PHP' ), $this->_hooks['avhfdas_menu_3rd_party'], 'side', 'core' );

		add_filter( 'screen_layout_columns', array (&$this, 'filterScreenLayoutColumns' ), 10, 2 );

		// WordPress core Styles and Scripts
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
		wp_admin_css( 'css/dashboard' );

		// Plugin Style and Scripts
		wp_enqueue_script( 'avhfdas-admin-js' );
		wp_enqueue_style( 'avhfdas-admin-css' );

	}

	/**
	 * Menu Page Third Party Options
	 *
	 * @return none
	 */
	public function doMenu3rdPartyOptions ()
	{
		global $screen_layout_columns;

		$options_sfs[] = array ('avhfdas[general][use_sfs]', 'Check with Stop Forum Spam', 'checkbox', 1, 'If checked, the visitor\'s IP will be checked with Stop Forum Spam' );
		$options_sfs[] = array ('avhfdas[sfs][whentoemail]', 'Email threshold', 'text', 3, 'When the frequency of the spammer in the stopforumspam database equals or exceeds this threshold an email is send.<BR />A negative number means an email will never be send.' );
		$options_sfs[] = array ('avhfdas[sfs][emailphp]', 'Email Project Honey Pot Info', 'checkbox', 1, 'Always email Project Honey Pot info when Stop Forum Spam email threshold is reached, disregarding the email threshold set for Project Honey Pot. This only works when you select to check with Project Honey Pot as well.' );
		$options_sfs[] = array ('avhfdas[sfs][whentodie]', 'Termination threshold', 'text', 3, 'When the frequency of the spammer in the stopforumspam database equals or exceeds this threshold the connection is terminated.<BR />A negative number means the connection will never be terminated.<BR /><strong>This option will always be the last one checked.</strong>' );
		$options_sfs[] = array ('avhfdas[sfs][sfsapikey]', 'API Key', 'text', 15, 'You need a Stop Forum Spam API key to report spam.' );
		$options_sfs[] = array ('avhfdas[sfs][error]', 'Email error', 'checkbox', 1, 'Receive an email when the call to Stop Forum Spam Fails' );

		$options_php[] = array ('avhfdas[general][use_php]', 'Check with Honey Pot Project', 'checkbox', 1, 'If checked, the visitor\'s IP will be checked with Honey Pot Project' );
		$options_php[] = array ('avhfdas[php][phpapikey]', 'API Key:', 'text', 15, 'You need a Project Honey Pot API key to check the Honey Pot Project database.' );
		$options_php[] = array ('avhfdas[php][whentoemailtype]', 'Email type threshold:', 'dropdown', '0/1/2/3/4/5/6/7', 'Search Engine/Suspicious/Harvester/Suspicious & Harvester/Comment Spammer/Suspicious & Comment Spammer/Harvester & Comment Spammer/Suspicious & Harvester & Comment Spammer', 'When the type of the spammer in the Project Honey Pot database equals or exceeds this threshold an email is send.<BR />Both the type threshold and the score threshold have to be reached in order to receive an email.' );
		$options_php[] = array ('avhfdas[php][whentoemail]', 'Email score threshold', 'text', 3, 'When the score of the spammer in the Project Honey Pot database equals or exceeds this threshold an email is send.<BR />A negative number means an email will never be send.' );
		$options_php[] = array ('avhfdas[php][whentodietype]', 'Termination type threshold', 'dropdown', '-1/0/1/2/3/4/5/6/7', 'Never/Search Engine/Suspicious/Harvester/Suspicious & Harvester/Comment Spammer/Suspicious & Comment Spammer/Harvester & Comment Spammer/Suspicious & Harvester & Comment Spammer', 'When the type of the spammer in the Project Honey Pot database equals or exceeds this threshold an email is send.<br />Both the type threshold and the score threshold have to be reached in order to termnate the connection. ' );
		$options_php[] = array ('avhfdas[php][whentodie]', 'Termination score threshold', 'text', 3, 'When the score of the spammer in the Project Honey Pot database equals or exceeds this threshold the connection is terminated.<BR />A negative number means the connection will never be terminated.<BR /><strong>This option will always be the last one checked.</strong>' );
		$options_php[] = array ('avhfdas[php][usehoneypot]', 'Use Honey Pot', 'checkbox', 1, 'If you have set up a Honey Pot you can select to have the text below to be added to the message when terminating the connection.<BR />You have to select <em>Show Message</em> in the General Options for this to work.' );
		$options_php[] = array ('avhfdas[php][honeypoturl]', 'Honey Pot URL', 'text', 30, 'The link to the Honey Pot as suggested by Project Honey Pot.' );

		if ( isset( $_POST['updateoptions'] ) ) {
			check_admin_referer( 'avh_fdas_options' );

			$formoptions = $_POST['avhfdas'];
			$options = $this->_core->getOptions();

			$all_data = array_merge( $options_sfs, $options_php );
			foreach ( $all_data as $option ) {
				$section = substr( $option[0], strpos( $option[0], '[' ) + 1 );
				$section = substr( $section, 0, strpos( $section, '][' ) );
				$option_key = rtrim( $option[0], ']' );
				$option_key = substr( $option_key, strpos( $option_key, '][' ) + 2 );

				$current_value = $options[$section][$option_key];
				// Every field in a form is set except unchecked checkboxes. Set an unchecked checkbox to 0.


				$newval = (isset( $formoptions[$section][$option_key] ) ? $formoptions[$section][$option_key] : 0);
				if ( 'sfs' == $section && ('whentoemail' == $option_key || 'whentodie' == $option_key) ) {
					$newval = ( int ) $newval;
				}

				if ( 'php' == $section && ('whentoemail' == $option_key || 'whentodie' == $option_key) ) {
					$newval = ( int ) $newval;
				}

				if ( $newval != $current_value ) { // Only process changed fields
					$options[$section][$option_key] = $newval;
				}

			}
			$note = '';
			if ( empty( $options['php']['phpapikey'] ) ) {
				$options['general']['use_php'] = 0;
				$note = '<br \><br \>' . __( 'You can not use Project Honey Pot without an API key. Use of Project Honey Pot has been disabled', 'avhfdas' );
			}
			$this->_core->saveOptions( $options );
			$this->_message = __( 'Options saved', 'avhfdas' );
			$this->_message .= $note;
			$this->_status = 'updated fade';
			$this->_displayMessage();
		}

		$actual_options = array_merge( $this->_core->getOptions(), $this->_core->getData() );

		$hide2 = '';
		switch ( $screen_layout_columns )
		{
			case 2 :
				$width = 'width:49%;';
				break;
			default :
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}
		$data['options_sfs'] = $options_sfs;
		$data['options_php'] = $options_php;
		$data['actual_options'] = $actual_options;

		echo '<div class="wrap avhfdas-wrap">';
		echo '<div class="wrap">';
		echo $this->_displayIcon( 'options-general' );
		echo '<h2>' . __( '3rd Party Options', 'avhfdas' ) . '</h2>';
		echo '<form name="avhfdas-options" id="avhfdas-options" method="POST" action="admin.php?page=' . AVH_FDAS_Define::MENU_SLUG_3RD_PARTY . '" accept-charset="utf-8" >';
		wp_nonce_field( 'avh_fdas_options' );
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );

		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';
		echo '			<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes( $this->_hooks['avhfdas_menu_3rd_party'], 'normal', $data );
		echo '			</div>';
		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes( $this->_hooks['avhfdas_menu_3rd_party'], 'side', $data );
		echo '			</div>';
		echo '		</div>';

		echo '<br class="clear"/>';
		echo '	</div>'; //dashboard-widgets-wrap
		echo '</div>'; // wrap


		echo '<p class="submit"><input class="button-primary" type="submit" name="updateoptions" value="' . __( 'Save Changes', 'avhfdas' ) . '" /></p>';
		echo '</form>';

		$this->_printAdminFooter();
	}

	/**
	 * Metabox Display the 3rd Party Stop Forum Spam Options
	 * @param $data array The data is filled menu setup
	 * @return none
	 */

	public function metaboxMenu3rdParty_SFS ( $data )
	{
		echo '<p>' . __( 'To check a visitor at Stop Forum Spam you must enable it below. Set the options to your own liking.' );
		echo $this->_printOptions( $data['options_sfs'], $data['actual_options'] );
		//echo '<p>' . __( 'Currently the plugin can not check with Stop Forum Spam untill a better solution has been coded.' );
	//echo  __( 'I apologize for this and will be looking for a solutin in the short run.' ).'</p>';


	}

	/**
	 * Metabox Display the 3rd Party Project Honey Pot Options
	 * @param $data array The data is filled menu setup
	 * @return none
	 */
	public function metaboxMenu3rdParty_PHP ( $data )
	{
		echo '<p>' . __( 'To check a visitor at Project Honey Pot you must enable it below, you must also have an API key. You can get an API key by signing up for free at the <a href="http://www.projecthoneypot.org/create_account.php" target="_blank">Honey Pot Project</a>. Set the options to your own liking.' );
		echo $this->_printOptions( $data['options_php'], $data['actual_options'] );

	}

	/**
	 * Setup everything needed for the FAQ page
	 *
	 */
	public function actionLoadPageHook_faq ()
	{

		add_meta_box( 'avhfdasBoxFAQ', __( 'F.A.Q.', 'avhfdas' ), array (&$this, 'metaboxFAQ' ), $this->_hooks['avhfdas_menu_faq'], 'normal', 'core' );

		add_filter( 'screen_layout_columns', array (&$this, 'filterScreenLayoutColumns' ), 10, 2 );

		// WordPress core Styles and Scripts
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'wp-lists' );
		wp_enqueue_script( 'postbox' );
		wp_admin_css( 'css/dashboard' );

		// Plugin Style and Scripts
		wp_enqueue_script( 'avhfdas-admin-js' );
		wp_enqueue_style( 'avhfdas-admin-css' );

	}

	/**
	 * Menu Page FAQ
	 *
	 * @return none
	 */
	public function doMenuFAQ ()
	{
		global $screen_layout_columns;

		// This box can't be unselectd in the the Screen Options
		add_meta_box( 'avhfdasBoxDonations', __( 'Donations', 'avhfdas' ), array (&$this, 'metaboxDonations' ), $this->_hooks['avhfdas_menu_faq'], 'side', 'core' );
		$hide2 = '';
		switch ( $screen_layout_columns )
		{
			case 2 :
				$width = 'width:49%;';
				break;
			default :
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}

		echo '<div class="wrap avhfdas-wrap">';
		echo $this->_displayIcon( 'index' );
		echo '<h2>' . __( 'AVH First Defense Against Spam Overview', 'avhfdas' ) . '</h2>';
		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';
		echo '			<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes( $this->_hooks['avhfdas_menu_faq'], 'normal', '' );
		echo '			</div>';
		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes( $this->_hooks['avhfdas_menu_faq'], 'side', '' );
		echo '			</div>';
		echo '		</div>';
		echo '<form style="display: none" method="get" action="">';
		echo '<p>';
		wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
		wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
		echo '</p>';
		echo '</form>';
		echo '<br class="clear"/>';
		echo '	</div>'; //dashboard-widgets-wrap
		echo '</div>'; // wrap


		$this->_printAdminFooter();

	}

	/**
	 * Metabox Display the FAQ
	 * @param $data array The data is filled menu setup
	 * @return none
	 */

	public function metaboxFAQ ()
	{
		echo '<p>';
		echo '<span class="b">Why is there an IP caching system?</span><br />';
		echo 'Stop Forum spam has set a limit on the amount of API calls you can make a day, currently it iset at 5000 calls a day.<br />';
		echo 'This means that if you don\'t use the Blacklist and/or Whitelist you are limited to 5000 visits/day on your site. To overcome this possible problem I wrote an IP caching system.<br />';
		echo '</p>';

		echo '<p>';
		echo 'The following IP\'s are cached locally:<br />';
		echo '<ul>';
		echo '<li>Every IP identified as spam and triggering the terminate-the-connection threshold.</li>';
		echo '<li>Every clean IP.</li>';
		echo '</ul>';
		echo '</p>';

		echo '<p>';
		echo 'Only returning IP\'s that were previously identified as spammer and who\'s connection was terminated will update their last seen date in the caching system.<br />';
		echo 'Every day, once a day, a routine runs to remove the IP\'s who\'s last seen date is X amount of days older than the date the routine runs. You can set the days in the adminstration section of the plugin.<br />';
		echo 'You can check the statistics to see how many IP\'s are in the database. If you have a busy site, with a lot of unique visitors, you might have to play with the "Days to keep in cache" setting to keep the size under control.<br />';
		echo '</p>';

		echo '<p>';
		echo '<span class="b">In what order is an IP checked and what action is taken?</span><br />';
		echo 'The plugin checks the visiting IP in the following order, only if that feature is enabled of course.<br />';
		echo '<ul>';
		echo '<li>Whitelist - If found skip the rest of the checks.</li>';
		echo '<li>Blacklist - If found terminate the connection.</li>';
		echo '<li>IP Caching - If found and spam terminate connection, if found and clean skip the rest of the checks.</li>';
		echo '<li>3rd Parties - If found determine action based on result.</li>';
		echo '</ul>';
		echo '</p>';

		echo '<p>';
		echo '<span class="b">Is this plugin enough to block all spam?</span><br />';
		echo 'Unfortunately not.<br />';
		echo 'I don\'t believe there is one solution to block all spam. Personally I have great success with the plugin in combination with Akismet.<br />';
		echo '</p>';

		echo '<p>';
		echo '<span class="b">Does it conflicts with other spam solutions?</span><br />';
		echo 'I\'m currently not aware of any conflicts with other anti-spam solutions.<br />';
		echo '</p>';

		echo '<p>';
		echo '<span class="b">How do I define a range in the blacklist or white list?</span><br />';
		echo 'You can define two sorts of ranges:';
		echo '<ul>';
		echo '<li>From IP to IP. i.e. 192.168.1.100-192.168.1.105</li>';
		echo '<li>A network in CIDR format. i.e. 192.168.1.0/24</li>';
		echo '</ul>';
		echo '</p>';

		echo '<p>';
		echo '<span class="b">How do I report a spammer to Stop Forum Spam?</span><br />';
		echo 'You need to have an API key from Stop Forum Spam. If you do on the Edit Comments pages there is an extra option called, Report & Delete, in the messages identified as spam.<br />';
		echo '</p>';

		echo '<p>';
		echo '<span class="b">How do I get a Stop Forum Spam API key?</span><br />';
		echo 'You will have to sign up on their site, <a href="http://www.stopforumspam.com/signup" target="_blank">http://www.stopforumspam.com/signup</a>.<br />';
		echo '</p>';

		echo '<p>';
		echo '<span class="b">How do I get a Project Honey Pot API key?</span><br />';
		echo 'You will have to sign up on their site, <a href="http://www.projecthoneypot.org/create_account.php" target="_blank">http://www.projecthoneypot.org/create_account.php</a>.<br />';
		echo '</p>';

		echo '<p>';
		echo '<span class="b">What are some score examples for Project Honey Pot?</span><br />';
		echo 'The Threat Rating is a logarithmic score -- much like the Richter\'s scale for measuring earthquakes.';
		echo 'A Threat Rating of 25 can be interpreted as the equivalent of sending 100 spam messages to a honey pot trap.<br />';
		echo '<div class="table">';
		echo '<table>';
		echo '<tbody>';
		echo '<tr class="first">';
		echo '<th>Threat Rating</th><th>IP that is as threatening as one that has sent</th>';
		echo '</tr>';
		echo '<tr>';
		echo '<td class="first">25</td><td class="t">100 spam messages</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td class="first">50</td><td class="t">10,000 spam messages</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td class="first">75</td><td class="t">1,000,000 spam messages</td>';
		echo '</tr>';
		echo '</tbody></table></div>';
		echo '</p>';
	}

	/**
	 * Donation Metabox
	 * @return unknown_type
	 */
	public function metaboxDonations ()
	{
		echo '<p>If you enjoy this plug-in please consider a donation. There are several ways you can show your appreciation</p>';
		echo '<p>';
		echo '<span class="b">Amazon</span><br />';
		echo 'If you decide to buy something from Amazon click the button.<br />';
		echo '<a href="https://www.amazon.com/?&tag=avh-donation-20" target="_blank" title="Amazon Homepage"><img alt="Amazon Button" src="' . $this->_settings->graphics_url . '/us_banner_logow_120x60.gif" /></a></p>';
		echo '<p>';
		echo 'You can send me something from my <a href="http://www.amazon.com/gp/Settings/wishlist/1U3DTWZ72PI7W?tag=avh-donation-20">Amazon Wish List</a>';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">Through Paypal.</span><br />';
		echo 'Click on the Donate button and you will be directed to Paypal where you can make your donation and you don\'t need to have a Paypal account to make a donation.<br />';
		echo '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=S85FXJ9EBHAF2&lc=US&item_name=AVH%20Plugins&item_number=fdas&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted" target="_blank" title="Donate">';
		echo '<img src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" alt="Donate"/></a>';
		echo '</p>';
	}

	/**
	 * Sets the amount of columns wanted for a particuler screen
	 *
	 * @WordPress filter screen_meta_screen
	 * @param $screen
	 * @return strings
	 */

	public function filterScreenLayoutColumns ( $columns, $screen )
	{
		switch ( $screen )
		{
			case $this->_hooks['avhfdas_menu_overview'] :
				$columns[$this->_hooks['avhfdas_menu_overview']] = 2;
				break;
			case $this->_hooks['avhfdas_menu_general'] :
				$columns[$this->_hooks['avhfdas_menu_general']] = 2;
				break;
			case $this->_hooks['avhfdas_menu_3rd_party'] :
				$columns[$this->_hooks['avhfdas_menu_3rd_party']] = 2;
				break;
			case $this->_hooks['avhfdas_menu_faq'] :
				$columns[$this->_hooks['avhfdas_menu_faq']] = 2;
				break;

		}
		return $columns;

	}

	/**
	 * Adds Settings next to the plugin actions
	 *
	 * @WordPress Filter plugin_action_links_avh-first-defense-against-spam/avh-fdas.php
	 * @param array $links
	 * @return array
	 *
	 * @since 1.0
	 */
	public function filterPluginActions ( $links )
	{
		$folder = AVH_Common::getBaseDirectory( $this->_settings->plugin_basename );
		$settings_link = '<a href="admin.php?page=' . $folder . '">' . __( 'Settings', 'avhfdas' ) . '</a>';
		array_unshift( $links, $settings_link ); // before other links
		return $links;
	}

	/**
	 * Adds an extra option on the comment row
	 *
	 * @WordPress Filter comment_row_actions
	 * @param array $actions
	 * @param class $comment
	 * @return array
	 * @since 1.0
	 */
	public function filterCommentRowActions ( $actions, $comment )
	{
		if ( (! empty( $this->_core->getOptionElement('sfs','sfsapikey') )) && isset( $comment->comment_approved ) && 'spam' == $comment->comment_approved ) {
			$report_url = clean_url( wp_nonce_url( "admin.php?avhfdas_ajax_action=avh-fdas-reportcomment&id=$comment->comment_ID", "report-comment_$comment->comment_ID" ) );
			$actions['report'] = '<a class=\'delete:the-comment-list:comment-' . $comment->comment_ID . ':e7e7d3:action=avh-fdas-reportcomment vim-d vim-destructive\' href="' . $report_url . '">Report & Delete</a>';
		}
		return $actions;
	}

	/**
	 * Checks if the user clicked on the Report & Delete link.
	 *
	 * @WordPress Action wp_ajax_avh-fdas-reportcomment
	 *
	 */
	public function actionAjaxReportComment ()
	{
		global $wpdb;
		if ( 'avh-fdas-reportcomment' == $_POST['action'] ) {
			$comment_id = absint( $_REQUEST['id'] );
			check_ajax_referer( 'report-comment_' . $comment_id );
			if ( ! $comment = get_comment( $comment_id ) ) {
				$this->_comment_footer_die( __( 'Oops, no comment with this ID.' ) . sprintf( ' <a href="%s">' . __( 'Go back' ) . '</a>!', 'edit-comments.php' ) );
			}
			if ( ! current_user_can( 'edit_post', $comment->comment_post_ID ) ) {
				$this->_comment_footer_die( __( 'You are not allowed to edit comments on this post.' ) );
			}
			$options = $this->_core->getOptions();
			// If we use IP Cache and the Reported IP isn't spam, delete it from the IP cache.
			if ( 1 == $options['general']['useipcache'] ) {
				$ip_info = $this->_db->getIP( $comment->comment_author_IP );
				if ( is_object( $ip_info ) && 0 == $ip_info->spam ) {
					$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->avhfdasipcache WHERE ip=INET_ATON(%s)", $comment->comment_author_IP ) );
				}
			}
			$this->handleReportSpammer( $comment->comment_author, $comment->comment_author_email, $comment->comment_author_IP );

			// Delete the comment
			$r = wp_delete_comment( $comment->comment_ID );
			die( $r ? '1' : '0' );
		}
	}

	/**
	 * Handles the admin_action emailreportspammer call.
	 *
	 * @WordPress Action admin_action_emailreportspammer
	 * @since 1.2
	 *
	 */
	public function actionHandleEmailReportingUrl ()
	{
		if ( ! (isset( $_REQUEST['action'] ) && 'emailreportspammer' == $_REQUEST['action']) ) {
			return;
		}
		$a = esc_html( $_REQUEST['a'] );
		$e = esc_html( $_REQUEST['e'] );
		$i = esc_html( $_REQUEST['i'] );

		$extra = '&m=' . AVHFDAS_ERROR_INVALID_REQUEST . '&i=' . $i;
		if ( AVH_Security::verifyNonce( $_REQUEST['_avhnonce'], $a . $e . $i ) ) {
			$all = get_option( $this->_core->get_db_nonces() );
			$extra = '&m=' . AVHFDAS_ERROR_NOT_REPORTED . '&i=' . $i;
			if ( isset( $all[$_REQUEST['_avhnonce']] ) ) {
				$this->handleReportSpammer( $a, $e, $i );
				unset( $all[$_REQUEST['_avhnonce']] );
				update_option( $this->_core->db_nonce, $all );
				$extra = '&m=' . AVHFDAS_REPORTED . '&i=' . $i;
			}
			unset( $all );
		}
		wp_redirect( admin_url( 'admin.php?page=' . AVH_FDAS_Define::MENU_SLUG_GENERAL . $extra ) );
	}

	/**
	 * Do the HTTP call to and report the spammer
	 *
	 * @param unknown_type $username
	 * @param unknown_type $email
	 * @param unknown_type $ip_addr
	 */
	private function _handleReportSpammer ( $username, $email, $ip_addr )
	{
		$email = empty( $email ) ? 'meseaffibia@gmail.com' : $email;
		$url = 'http://www.stopforumspam.com/post.php';
		wp_remote_post( $url, array ('body' => array ('username' => $username, 'ip_addr' => $ip_addr, 'email' => $email, 'api_key' => $this->_core->getOptionElement('sfs','sfsapikey') ) ) );
	}

	/**
	 * Handles the admin_action_blacklist call
	 *
	 * @WordPress Action admin_action_blacklist
	 *
	 */
	public function actionHandleBlacklistUrl ()
	{
		if ( ! (isset( $_REQUEST['action'] ) && 'blacklist' == $_REQUEST['action']) ) {
			return;
		}
		$ip = $_REQUEST['i'];

		if ( AVH_Security::verifyNonce( $_REQUEST['_avhnonce'], $ip ) ) {
			$blacklist = $this->_core->getDataElement('lists','blacklist');
			if ( ! empty( $blacklist ) ) {
				$b = explode( "\r\n", $blacklist );
			} else {
				$b = array ();
			}
			if ( ! (in_array( $ip, $b )) ) {
				array_push( $b, $ip );
				$this->_setBlacklistOption( $b );
				wp_redirect( admin_url( 'admin.php?page=' . AVH_FDAS_Define::MENU_SLUG_GENERAL . '&m=' . AVHFDAS_ADDED_BLACKLIST . '&i=' . $ip ) );
			} else {
				wp_redirect( admin_url( 'admin.php?page=' . AVH_FDAS_Define::MENU_SLUG_GENERAL . '&m=' . AVHFDAS_ERROR_EXISTS_IN_BLACKLIST . '&i=' . $ip ) );
			}
		} else {
			wp_redirect( admin_url( 'admin.php?page=' . AVH_FDAS_Define::MENU_SLUG_GENERAL . '&m=' . AVHFDAS_ERROR_INVALID_REQUEST ) );
		}
	}

	/**
	 * Update the blacklist in the proper format
	 *
	 * @param array $b
	 */
	private function _setBlacklistOption ( $blacklist )
	{
		$data = $this->_core->getData();
		natsort( $blacklist );
		$blacklist_formatted = implode( "\r\n", $blacklist );
		$data['lists']['blacklist'] = $blacklist_formatted;
		$this->_core->saveData( $data );
	}

	/**
	 * Update the whitelist in the proper format
	 *
	 * @param array $b
	 */
	private function _setWhitelistOption ( $b )
	{
		$data = $this->_core->getData();
		natsort( $b );
		$x = implode( "\r\n", $b );
		$data['lists']['whitelist'] = $x;
		$this->_core->saveData( $data );
	}

	/**
	 * Called on activation of the plugin.
	 *
	 */
	public function installPlugin ()
	{
		global $wpdb;

		require_once (ABSPATH . 'wp-admin/includes/upgrade.php');

		// Add Cron Job, the action is added in the Public class.
		if ( ! wp_next_scheduled( 'avhfdas_clean_nonce' ) ) {
			wp_schedule_event( time(), 'daily', 'avhfdas_clean_nonce' );
		}

		// Setup nonces db in options
		if ( ! (get_option( $this->_core->get_db_nonces() )) ) {
			update_option( $this->_core->get_db_nonces(), $this->_core->get_default_nonces() );
			wp_cache_flush(); // Delete cache
		}

		// Setup the DB Tables
		$charset_collate = '';

		if ( version_compare( mysql_get_server_info(), '4.1.0', '>=' ) ) {
			if ( ! empty( $wpdb->charset ) ) $charset_collate = 'DEFAULT CHARACTER SET ' . $wpdb->charset;
			if ( ! empty( $wpdb->collate ) ) $charset_collate .= ' COLLATE ' . $wpdb->collate;
		}

		if ( $wpdb->get_var( 'show tables like \'' . $wpdb->avhfdasipcache . '\'' ) != $wpdb->avhfdasipcache ) {

			$sql = 'CREATE TABLE `' . $wpdb->avhfdasipcache . '` (
  					`ip` int(10) unsigned NOT NULL,
  					`added` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  					`lastseen` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\',
  					`spam` tinyint(1) NOT NULL,
  					PRIMARY KEY (`ip`),
  					KEY `added` (`added`),
  					KEY `lastseen` (`lastseen`)
					) ' . $charset_collate . ';';

			$result = $wpdb->query( $sql );
		}

	}

	/**
	 * Called on deactivation of the plugin.
	 *
	 */
	public function deactivatePlugin ()
	{
		// Remove the administrative capilities
		$role = get_role( 'administrator' );
		if ( $role != null && $role->has_cap( 'role_avh_fdas' ) ) {
			$role->remove_cap( 'role_avh_fdas' );
		}
		if ( $role != null && $role->has_cap( 'role_admin_avh_fdas' ) ) {
			$role->remove_cap( 'role_admin_avh_fdas' );
		}

		// Deactivate the cron action as the the plugin is deactivated.
		wp_clear_scheduled_hook( 'avhfdas_clean_nonce' );
		wp_clear_scheduled_hook( 'avhfdas_clean_ipcache' );
	}

	############## Admin WP Helper ##############
	/**
	 * Display plugin Copyright
	 *
	 */
	private function _printAdminFooter ()
	{
		echo '<div class="clear">';
		echo '<p class="footer_avhfdas">';
		printf( __( '&copy; Copyright 2010 <a href="http://blog.avirtualhome.com/" title="My Thoughts">Peter van der Does</a> | AVH First Defense Against Spam version %s', 'avhfdas' ), AVH_FDAS_Define::PLUGIN_VERSION );
		echo '</p>';
	}

	/**
	 * Display WP alert
	 *
	 */
	private function _displayMessage ()
	{
		if ( $this->_message != '' ) {
			$message = $this->_message;
			$status = $this->_status;
			$this->_message = $this->_status = ''; // Reset
		}
		if ( isset( $message ) ) {
			$status = ($status != '') ? $status : 'updated fade';
			echo '<div id="message"	class="' . $status . '">';
			echo '<p><strong>' . $message . '</strong></p></div>';
		}
	}

	/**
	 * Displays the icon needed. Using this instead of core in case we ever want to show our own icons
	 * @param $icon strings
	 * @return string
	 */
	private function _displayIcon ( $icon )
	{
		return ('<div class="icon32" id="icon-' . $icon . '"><br/></div>');
	}

	/**
	 * Ouput formatted options
	 *
	 * @param array $option_data
	 * @return string
	 */
	private function _printOptions ( $option_data, $option_actual )
	{
		// Generate output
		$output = '';
		$output .= "\n" . '<table class="form-table avhfdas-options">' . "\n";
		foreach ( $option_data as $option ) {
			$section = substr( $option[0], strpos( $option[0], '[' ) + 1 );
			$section = substr( $section, 0, strpos( $section, '][' ) );
			$option_key = rtrim( $option[0], ']' );
			$option_key = substr( $option_key, strpos( $option_key, '][' ) + 2 );
			// Helper
			if ( $option[2] == 'helper' ) {
				$output .= '<tr style="vertical-align: top;"><td class="helper" colspan="2">' . $option[4] . '</td></tr>' . "\n";
				continue;
			}
			switch ( $option[2] )
			{
				case 'checkbox' :
					$input_type = '<input type="checkbox" id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr( $option[3] ) . '" ' . $this->_isChecked( '1', $option_actual[$section][$option_key] ) . ' />' . "\n";
					$explanation = $option[4];
					break;
				case 'dropdown' :
					$selvalue = explode( '/', $option[3] );
					$seltext = explode( '/', $option[4] );
					$seldata = '';
					foreach ( ( array ) $selvalue as $key => $sel ) {
						$seldata .= '<option value="' . $sel . '" ' . (($option_actual[$section][$option_key] == $sel) ? 'selected="selected"' : '') . ' >' . ucfirst( $seltext[$key] ) . '</option>' . "\n";
					}
					$input_type = '<select id="' . $option[0] . '" name="' . $option[0] . '">' . $seldata . '</select>' . "\n";
					$explanation = $option[5];
					break;
				case 'text-color' :
					$input_type = '<input type="text" ' . (($option[3] > 50) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr( stripcslashes( $option_actual[$section][$option_key] ) ) . '" size="' . $option[3] . '" /><div class="box_color ' . $option[0] . '"></div>' . "\n";
					$explanation = $option[4];
					break;
				case 'textarea' :
					$input_type = '<textarea rows="' . $option[5] . '" ' . (($option[3] > 50) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" size="' . $option[3] . '" />' . esc_attr( stripcslashes( $option_actual[$section][$option_key] ) ) . '</textarea>';
					$explanation = $option[4];
					break;
				case 'text' :
				default :
					$input_type = '<input type="text" ' . (($option[3] > 50) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . esc_attr( stripcslashes( $option_actual[$section][$option_key] ) ) . '" size="' . $option[3] . '" />' . "\n";
					$explanation = $option[4];
					break;
			}
			// Additional Information
			$extra = '';
			if ( $explanation ) {
				$extra = '<br /><span class="description">' . __( $explanation ) . '</span>' . "\n";
			}
			// Output
			$output .= '<tr style="vertical-align: top;"><th align="left" scope="row"><label for="' . $option[0] . '">' . __( $option[1] ) . '</label></th><td>' . $input_type . '	' . $extra . '</td></tr>' . "\n";
		}
		$output .= '</table>' . "\n";
		return $output;
	}

	/**
	 * Used in forms to set an option checked
	 *
	 * @param mixed $checked
	 * @param mixed $current
	 * @return strings
	 */
	private function _isChecked ( $checked, $current )
	{
		$return = '';
		if ( $checked == $current ) {
			$return = ' checked="checked"';
		}
		return $return;
	}

	/**
	 * Display error message at bottom of comments.
	 *
	 * @param string $msg Error Message. Assumed to contain HTML and be sanitized.
	 */
	private function _comment_footer_die ( $msg )
	{
		echo "<div class='wrap'><p>$msg</p></div>";
		die();
	}

}
?>