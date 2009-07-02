<?php
require_once (ABSPATH . 'wp-includes/class-snoopy.php');
class AVH_FDAS_Admin
{
	/**
	 * Message management
	 *
	 */
	var $message = '';
	var $status = '';
	var $core;

	function __construct ()
	{
		// Initialize the plugin
		$this->core = & AVH_FDAS_Singleton::getInstance( 'AVH_FDAS_Core' );

		// Admin URL and Pagination
		$this->core->admin_base_url = $this->core->info['siteurl'] . '/wp-admin/admin.php?page=';
		if ( isset( $_GET['pagination'] ) ) {
			$this->core->actual_page = ( int ) $_GET['pagination'];
		}
		$this->installPlugin();

		// Admin Capabilities
		add_action( 'init', array (&$this, 'actionInitRoles' ) );

		// Admin menu
		add_action( 'admin_menu', array (&$this, 'actionAdminMenu' ) );

		// Helper JS & jQuery & Prototype
		$avhfdas_pages = array ('avhfdas_options' );
		if ( in_array( $_GET['page'], $avhfdas_pages ) ) {
			wp_enqueue_script( 'jquery-ui-tabs' );
		}

		// Add the ajax action
		//add_action('admin_init', array(&$this, 'ajaxCheck'));
		add_action( 'wp_ajax_avh-fdas-reportcomment', array (&$this, 'actionAjaxReportComment' ) );

		// Add admin actions
		add_action( 'admin_action_blacklist', array (&$this, 'actionHandleBlacklistUrl' ) );
		add_action( 'admin_action_emailreportspammer', array (&$this, 'actionHandleEmailReportingUrl' ) );

		// Add Filter
		add_filter( 'comment_row_actions', array (&$this, 'filterCommentRowActions' ), 10, 2 );

		/**
		 * Inject CSS and Javascript on the right pages
		 *
		 * Main Action: admin_print_styles-, admin_print-scripts-
		 * Top level page: toplevel_page_avh-first-defense-against-spam
		 * Sub menus: avh-f-d-a-s_page_avh-fdas-general
		 *
		 */
		add_action( 'admin_print_styles-toplevel_page_avh-first-defense-against-spam', array (&$this, 'actionInjectCSS' ) );

		return;
	}

	/**
	 * PHP4 Constructor - Intialize Admin
	 *
	 * @return
	 */
	function AVH_FDAS_Admin ()
	{
		$this->__construct();
	}

	/**
	 * Setup Roles
	 *
	 * @WordPress Action init
	 * @since 1.0
	 */
	function actionInitRoles ()
	{
		if ( function_exists( 'get_role' ) ) {
			$role = get_role( 'administrator' );
			if ( $role != null && ! $role->has_cap( 'avh_fdas' ) ) {
				$role->add_cap( 'avh_fdas' );
			}
			if ( $role != null && ! $role->has_cap( 'admin_avh_fdas' ) ) {
				$role->add_cap( 'admin_avh_fdas' );
			}
			// Clean var
			unset( $role );
		}
	}

	/**
	 * Add the Tools and Options to the Management and Options page repectively
	 *
	 * @WordPress Action admin_menu
	 *
	 */
	function actionAdminMenu ()
	{
		$folder = $this->core->getBaseDirectory( plugin_basename( $this->core->info['plugin_dir'] ) );
		add_menu_page( __( 'AVH F.D.A.S' ), __( 'AVH F.D.A.S' ), 10, $folder, array (&$this, 'handleMenu' ) );
		add_submenu_page( $folder, __( 'Overview' ), __( 'Overview' ), 10, $folder, array (&$this, 'handleMenu' ) );
		add_submenu_page( $folder, __( 'General Options' ), __( 'General Options' ), 10, 'avh-fdas-general', array (&$this, 'handleMenu' ) );
		add_submenu_page( $folder, __( 'Options' ), __( '3rd Party Options' ), 10, 'avh-fdas-3rd-party', array (&$this, 'handleMenu' ) );
		add_filter( 'plugin_action_links_avh-first-defense-against-spam/avh-fdas.php', array (&$this, 'filterPluginActions' ), 10, 2 );
		// Add metaboxes
		add_meta_box('dashboard_right_now', __('Statistics', 'avhfdas'), array(&$this,'doMenuOverview'), 'avhfdas_overview', 'left', 'core');
	}

	/**
	 * Handle the menu options
	 *
	 */
	function handleMenu ()
	{

		switch ( $_GET['page'] ) {
			case 'avh-fdas-general' :
				$this->doMenuGeneralOptions();
				break;
			case 'avh-fdas-3rd-party' :
				$this->doMenu3rdPartyOptions();
				break;
			case 'avh-themed-by-browser' :
			default :
				$this->avhfdas_overview();

		}
		echo '<div class="clear">';
		$this->printAdminFooter();
	}

	function avhfdas_overview() {
		echo '<div class="wrap avhfdas-wrap">';
		echo '<h2>'.__('AVH First Defense Against Spam Overview', 'avhfdas').'</h2>';
		echo '<div id="dashboard-widgets-wrap" class="avhfdas-overview">';
		echo '    <div id="dashboard-widgets" class="metabox-holder">';
		echo '		<div id="post-body">';
		echo '			<div id="dashboard-widgets-main-content">';
		echo '				<div class="postbox-container" style="width:49%;">';
		do_meta_boxes('avhfdas_overview', 'left', '');
		echo '				</div>';
//		echo '	    		<div class="postbox-container" sdtyle="width:49%;">';
//		do_meta_boxes('ngg_overview', 'right', '');
//		echo'				</div>';
		echo '			</div>';
		echo '		</div>';
		echo '    </div>';
		echo '</div>';
		echo '</div>';
		echo '<script type="text/javascript">';
		echo '	//<![CDATA[';
		echo '	jQuery(document).ready( function($) {';
		echo '		// postboxes setup';
		echo '		postboxes.add_postbox_toggles(\'avhfdas-overview\');';
		echo '	});';
		echo '	//]]>';
		echo '</script>';
	}
	/**
	 * Overview of settings
	 *
	 */
	function doMenuOverview() {

		echo '<p class="sub">';
		_e('At a Glance', 'avhfdas');
		echo '</p>';

		echo '<div class="table">';
		echo '<table>';
		echo '<tbody>';
		echo '<tr class="first">';

		$data = $this->core->getData();
		$spam_count=$data['counters'];
		krsort($spam_count);
		$have_spam_count_data=false;
		$output='';
		foreach ($spam_count as $key => $value) {
			if ('190001' == $key) {
				continue;
			}
			$have_spam_count_data=true;
			$date=date_i18n('Y - F',mktime(0,0,0,substr($key,4,2),1,substr($key,0,4)));
			$output .= '<td class="first b">'.$value.'</td>';
			$output .= '<td class="t">'.sprintf(__('Spam stopped in %s','avhfdas'),$date).'</td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';
		}
		if (!$have_spam_count_data) {
			$output .= '<td class="first b">'.__('No statistics yet','avhfdas').'</td>';
			$output .= '<td class="t"></td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';
		}

		echo $output;
		echo '</tbody></table></div>';
		echo '<div class="versions">';
		echo '<p>';
		if ($this->core->options['general']['use_sfs'] || $this->core->options['general']['use_php']) {
			echo __('Checking with ','avhfdas');
			echo ($this->core->options['general']['use_sfs'] ? '<span class="b">'.__('Stop Forum Spam','avhfdas').'</span>' : '');

			if ($this->core->options['general']['use_php']) {
				echo ($this->core->options['general']['use_sfs'] ? __(' and ','avhfdas') : ' ');
				echo '<span class="b">'.__('Project Honey Pot','avhfdas').'</span>';
			}
		}
		echo '</p>';
		echo '</div>';
	}

	function doMenuGeneralOptions ()
	{
		$option_data = array (
			array (
			'avhfdas[general][diewithmessage]',
			'Show message:',
			'checkbox',
			1,
			'Show a message when the connection has been terminated.' ),
			array (
			'avhfdas[general][emailsecuritycheck]',
			'Email on failed security check:',
			'checkbox',
			1,
			'Receive an email when a comment is posted and the security check failed.' ),
			array (
			'avhfdas[general][useblacklist]',
			'Use internal blacklist:',
			'checkbox',
			1,
			'Check the internal blacklist first. If the IP is found terminate the connection, even when the Termination threshold is a negative number.' ),
			array (
			'avhfdas[lists][blacklist]',
			'Blacklist IP\'s:',
			'textarea',
			15,
			'Each IP should be on a separate line<br />Ranges can be defines as well in the following two formats<br />IP to IP. i.e. 192.168.1.100-192.168.1.105<br />Network in CIDR format. i.e. 192.168.1.0/24',
			 15 ),
			array (
			'avhfdas[general][usewhitelist]',
			'Use internal whitelist:',
			'checkbox',
			 1,
			'Check the internal whitelist first. If the IP is found don\t do any further checking.',
			),
			array (
			'avhfdas[lists][whitelist]',
			'Whitelist IP\'s:',
			'textarea',
			15,
			'Each IP should be on a seperate line<br />Ranges can be defines as well in the following two formats<br />IP to IP. i.e. 192.168.1.100-192.168.1.105<br />Network in CIDR format. i.e. 192.168.1.0/24',
			15 ),
		) ;

		if ( isset( $_POST['updateoptions'] ) ) {
			check_admin_referer( 'avh_fdas_generaloptions' );

			$formoptions = $_POST['avhfdas'];
			$options = $this->core->getOptions();
			$data = $this->core->getData();

			foreach ( $option_data as $option ) {
				$section = substr( $option[0], strpos( $option[0], '[' ) + 1 );
				$section = substr( $section, 0, strpos( $section, '][' ) );
				$option_key = rtrim( $option[0], ']' );
				$option_key = substr( $option_key, strpos( $option_key, '][' ) + 2 );

				switch ( $section ) {
					case 'general' :
						$current_value = $options[$section][$option_key];
						break;
					case 'lists' :
						$current_value = $data[$section][$option_key];
						break;
				}
				// Every field in a form is set except unchecked checkboxes. Set an unchecked checkbox to 0.


				$newval = (isset( $formoptions[$section][$option_key] ) ? attribute_escape( $formoptions[$section][$option_key] ) : 0);
				if ( $newval != $current_value ) { // Only process changed fields.
					// Sort the lists
					if ( 'blacklist' == $option_key || 'whitelist' == $option_key ) {
						$b = explode( "\r\n", $newval );
						natsort( $b );
						$newval = implode( "\r\n", $b );
						unset( $b );
					}
					switch ( $section ) {
						case 'general' :
							$options[$section][$option_key] = $newval;
							break;
						case 'lists' :
							$data[$section][$option_key] = $newval;
							break;
					}
				}
			}
			$this->core->saveOptions($options);
			$this->core->saveData($data);
			$this->message = __( 'Options saved', 'avhfdas' );
			$this->status = 'updated fade';
		}
			// Show messages if needed.
		if ( isset( $_REQUEST['m'] ) ) {
			switch ( $_REQUEST['m'] ) {
				case AVHFDAS_REPORTED_DELETED :
					$this->status = 'updated fade';
					$this->message = sprintf( __( 'IP [%s] Reported and deleted', 'avhfdas' ), attribute_escape( $_REQUEST['i'] ) );
					break;
				case AVHFDAS_ADDED_BLACKLIST :
					$this->status = 'updated fade';
					$this->message = sprintf( __( 'IP [%s] has been added to the blacklist', 'avhfdas' ), attribute_escape( $_REQUEST['i'] ) );
					break;
				case AVHFDAS_REPORTED :
					$this->status = 'updated fade';
					$this->message = sprintf( __( 'IP [%s] reported.', 'avhfdas' ), attribute_escape( $_REQUEST['i'] ) );
					break;
				case AVHFDAS_ERROR_INVALID_REQUEST :
					$this->status = 'error';
					$this->message = sprintf( __( 'Invalid request.', 'avhfdas' ) );
					break;
				case AVHFDAS_ERROR_NOT_REPORTED :
					$this->status = 'error';
					$this->message = sprintf( __( 'IP [%s] not reported. Probably already processed.', 'avhfdas' ), attribute_escape( $_REQUEST['i'] ) );
					break;
				case AVHFDAS_ERROR_EXISTS_IN_BLACKLIST :
					$this->status = 'error';
					$this->message = sprintf( __( 'IP [%s] already exists in the blacklist.', 'avhfdas' ), attribute_escape( $_REQUEST['i'] ) );
					break;
				default :
					$this->status = 'error';
					$this->message = 'Unknown message request';
			}
		}

		$this->displayMessage();

		$actual_options=array_merge($this->core->getOptions(),$this->core->getData());
		echo '<div class="wrap">';
		echo '<h2>'.__('General Options', 'avhfdas').'</h2>';
		echo '<form name="avhfdas-generaloptions" id="avhfdas-generaloptions" method="POST" action="admin.php?page=avh-fdas-general" accept-charset="utf-8" >';
		wp_nonce_field( 'avh_fdas_generaloptions' );

		echo '<div id="printOptions">';
		echo $this->printOptions( $option_data, $actual_options );
		echo '</div>';

		echo '<p class="submit"><input	class="button-primary"	type="submit" name="updateoptions" value="' . __( 'Save Changes', 'avhfdas' ) . '" /></p>';
		echo '</form>';
	}

	function doMenu3rdPartyOptions(){
		$options_sfs = array (
			array (
				'avhfdas[general][use_sfs]',
				'Check with Stop Forum Spam:',
				'checkbox',
				1,
				'If checked, the visitor\'s IP will be checked with Stop Forum Spam'
			),
			array (
				'avhfdas[sfs][whentoemail]',
				'Email threshold:',
				'text',
				3,
				'When the frequency of the spammer in the stopforumspam database equals or exceeds this threshold an email is send.<BR />A negative number means an email will never be send.'
			),
			array (
				'avhfdas[sfs][whentodie]',
				'Termination threshold:',
				'text',
				3,
				'When the frequency of the spammer in the stopforumspam database equals or exceeds this threshold the connection is terminated.<BR />A negative number means the connection will never be terminated.<BR /><strong>This option will always be the last one checked.</strong>'
			),
			array (
				'avhfdas[sfs][sfsapikey]',
				'API Key:',
				'text',
				15,
				'You need a Stop Forum Spam API key to report spam.'
			)
		);

		$options_php = array (
		array (
				'avhfdas[general][use_php]',
				'Check with Honey Pot Project:',
				'checkbox',
				1,
				'If checked, the visitor\'s IP will be checked with Honey Pot Project'
			),
			array (
				'avhfdas[php][whentoemail]',
				'Email threshold:',
				'text',
				3,
				'When the score of the spammer in the Project Honey Pot database equals or exceeds this threshold an email is send.<BR />A negative number means an email will never be send.'
			),
			array (
				'avhfdas[php][whentodie]',
				'Termination threshold:',
				'text',
				3,
				'When the score of the spammer in the Project Honey Pot database equals or exceeds this threshold the connection is terminated.<BR />A negative number means the connection will never be terminated.<BR /><strong>This option will always be the last one checked.</strong>'
			),
			array (
				'avhfdas[php][phpapikey]',
				'API Key:',
				'text',
				15,
				'You need a Project Honey Pot API key to check the Honey Pot Project database.'
			)
		);

		if ( isset( $_POST['updateoptions'] ) ) {
			check_admin_referer( 'avh_fdas_options' );

			$formoptions = $_POST['avhfdas'];
			$options = $this->core->getOptions();

			$all_data=array_merge($options_sfs,$options_php);
			foreach ( $all_data as $option ) {
				$section = substr( $option[0], strpos( $option[0], '[' ) + 1 );
				$section = substr( $section, 0, strpos( $section, '][' ) );
				$option_key = rtrim( $option[0], ']' );
				$option_key = substr( $option_key, strpos( $option_key, '][' ) + 2 );

				$current_value = $options[$section][$option_key];
				// Every field in a form is set except unchecked checkboxes. Set an unchecked checkbox to 0.


				$newval = (isset( $formoptions[$section][$option_key] ) ? attribute_escape( $formoptions[$section][$option_key] ) : 0);
				if ( $newval != $current_value ) { // Only process changed fields
					$options[$section][$option_key] = $newval;
				}
			}
			$note='';
			if (empty($options['php']['phpapikey'])) {
				$options['general']['use_php'] =0;
				$note = '<br \><br \>'.__('You can not use Project Honey Pot without an API key. Use of Project Honey Pot has been disabled','avhfdas');
			}
			$this->core->saveOptions($options);
			$this->message = __( 'Options saved', 'avhfdas' );
			$this->message .= $note;
			$this->status = 'updated fade';
			$this->displayMessage();
		}

		$actual_options=array_merge($this->core->getOptions(),$this->core->getData());
		echo '<div class="wrap">';
		echo '<h2>'.__('Options', 'avhfdas').'</h2>';
		echo '<form name="avhfdas-options" id="avhfdas-options" method="POST" action="admin.php?page=avh-fdas-3rd-party" accept-charset="utf-8" >';
		wp_nonce_field( 'avh_fdas_options' );

		echo '<div id="printOptions">';
		echo '<h3>'.__('Stop Forum Spam','avhfdas').'</h3>';
		echo $this->printOptions( $options_sfs, $actual_options );
		echo '<h3>'.__('Project Honey Pot','avhfdas').'</h3>';
		echo $this->printOptions( $options_php, $actual_options );
		echo '</div>';

		echo '<p class="submit"><input class="button-primary" type="submit" name="updateoptions" value="' . __( 'Save Changes', 'avhfdas' ) . '" /></p>';
		echo '</form>';
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
	function filterPluginActions ( $links )
	{
		$folder = $this->core->getBaseDirectory( plugin_basename( $this->core->info['plugin_dir'] ) );
		$settings_link = '<a href="admin.php?page='.$folder.'">' . __( 'Settings', 'avhfdas' ) . '</a>';
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
	function filterCommentRowActions ( $actions, $comment )
	{
		if ( (! empty( $this->core->options['sfs']['sfsapikey'] )) && isset( $comment->comment_approved ) && 'spam' == $comment->comment_approved ) {
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
	function actionAjaxReportComment ()
	{
		if ( 'avh-fdas-reportcomment' == $_POST['action'] ) {
			$comment_id = absint( $_REQUEST['id'] );
			check_ajax_referer( 'report-comment_' . $comment_id );
			if ( ! $comment = get_comment( $comment_id ) ) {
				comment_footer_die( __( 'Oops, no comment with this ID.' ) . sprintf( ' <a href="%s">' . __( 'Go back' ) . '</a>!', 'edit-comments.php' ) );
			}
			if ( ! current_user_can( 'edit_post', $comment->comment_post_ID ) ) {
				comment_footer_die( __( 'You are not allowed to edit comments on this post.' ) );
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
	function actionHandleEmailReportingUrl ()
	{
		if ( ! (isset( $_REQUEST['action'] ) && 'emailreportspammer' == $_REQUEST['action']) ) {
			return;
		}
		$a = wp_specialchars( $_REQUEST['a'] );
		$e = wp_specialchars( $_REQUEST['e'] );
		$i = wp_specialchars( $_REQUEST['i'] );
		$extra = '&m=' . AVHFDAS_ERROR_INVALID_REQUEST . '&i=' . $i;
		if ( $this->core->avh_verify_nonce( $_REQUEST['_avhnonce'], $a . $e . $i ) ) {
			$all = get_option( $this->core->db_options_nonces );
			$extra = '&m=' . AVHFDAS_ERROR_NOT_REPORTED . '&i=' . $i;
			if ( isset( $all[$_REQUEST['_avhnonce']] ) ) {
				$this->handleReportSpammer( $a, $e, $i );
				unset( $all[$_REQUEST['_avhnonce']] );
				update_option( $this->core->db_nonce, $all );
				$extra = '&m=' . AVHFDAS_REPORTED . '&i=' . $i;
			}
			unset( $all );
		}
		wp_redirect( admin_url( 'admin.php?page=avh-fdas-general' . $extra ) );
	}

	/**
	 * Do the HTTP call to and report the spammer
	 *
	 * @param unknown_type $username
	 * @param unknown_type $email
	 * @param unknown_type $ip_addr
	 */
	function handleReportSpammer ( $username, $email, $ip_addr )
	{
		$snoopy_formvar['username'] = $username;
		$snoopy_formvar['email'] = empty( $email ) ? 'no@email.address' : $email;
		$snoopy_formvar['ip_addr'] = $ip_addr;
		$snoopy_formvar['api_key'] = $this->core->options['sfs']['sfsapikey'];
		$snoopy = new Snoopy( );
		$snoopy->agent = "Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.0.8) Gecko/2009032608 Firefox/3.0.8 GTB5";
		$snoopy->submit( 'http://www.stopforumspam.com/add', $snoopy_formvar );
		// @TODO See if we can revert the
	//$result=$snoopy->results;
	//<div class="msg info">Data submitted successfully</div>
	}

	/**
	 * Handles the admin_action_blacklist call
	 *
	 * @WordPress Action admin_action_blacklist
	 *
	 */
	function actionHandleBlacklistUrl ()
	{
		if ( ! (isset( $_REQUEST['action'] ) && 'blacklist' == $_REQUEST['action']) ) {
			return;
		}
		$ip = $_REQUEST['i'];

		if ( $this->core->avh_verify_nonce( $_REQUEST['_avhnonce'], $ip ) ) {
			$blacklist = $this->core->data['lists']['blacklist'];
			if ( ! empty( $blacklist ) ) {
				$b = explode( "\r\n", $blacklist );
			} else {
				$b = array ();
			}
			if ( ! (in_array( $ip, $b )) ) {
				array_push( $b, $ip );
				$this->setBlacklistOption( $b );
				wp_redirect( admin_url( 'admin.php?page=avh-fdas-general&m=' . AVHFDAS_ADDED_BLACKLIST . '&i=' . $ip ) );
			} else {
				wp_redirect( admin_url( 'admin.php?page=avh-fdas-general&m=' . AVHFDAS_ERROR_EXISTS_IN_BLACKLIST . '&i=' . $ip ) );
			}
		} else {
			wp_redirect( admin_url( 'admin.php?page=avh-fdas-general&m=' . AVHFDAS_ERROR_INVALID_REQUEST ) );
		}
	}

	/**
	 * Update the blacklist in the proper format
	 *
	 * @param array $b
	 */
	function setBlacklistOption ( $b )
	{
		$data=$this->core->getData();
		natsort( $b );
		$x = implode( "\r\n", $b );
		$data['lists']['blacklist'] = $x;
		$this->core->saveData($data);
	}

	/**
	 * Update the whitelist in the proper format
	 *
	 * @param array $b
	 */
	function setWhitelistOption ( $b )
	{
		$data=$this->core->getData();
		natsort( $b );
		$x = implode( "\r\n", $b );
		$data['lists']['whitelist'] = $x;
		$this->core->saveData($data);
	}

	/**
	 * Called on activation of the plugin.
	 *
	 */
	function installPlugin ()
	{
		// Add Cron Job, the action is added in the Public class.
		if ( ! wp_next_scheduled( 'avhfdas_clean_nonce' ) ) {
			wp_schedule_event( time(), 'daily', 'avhfdas_clean_nonce' );
		}

		$this->core->loadOptions(); // Options will be created if not in DB
		$this->core->loadData();  // Data will be created if not in DB

		if ( ! (get_option( $this->core->db_options_nonces )) ) {
			update_option( $this->core->db_options_nonces, $this->core->default_nonces );
			wp_cache_flush(); // Delete cache
		}
	}

	/**
	 * Called on deactivation of the plugin.
	 *
	 */
	function deactivatePlugin ()
	{
		// Deactivate the cron action as the the plugin is deactivated.
		wp_clear_scheduled_hook( 'avhfdas_clean_nonce' );
	}

	/**
	 * Update an option value  -- note that this will NOT save the options.
	 *
	 * @param array $optkeys
	 * @param string $optval
	 */
	function setOption ( $optkeys, $optval )
	{
		$key1 = $optkeys[0];
		$key2 = $optkeys[1];
		$this->core->options[$key1][$key2] = $optval;
	}

	/**
	 * Delete all options from DB.
	 *
	 */
	function deleteAllOptions ()
	{
		delete_option( $this->core->db_options_core, $this->core->default_options );
		wp_cache_flush(); // Delete cache
	}

	############## Admin WP Helper ##############
	/**
	 * Display plugin Copyright
	 *
	 */
	function printAdminFooter ()
	{
		echo '<p class="footer_avhfdas">';
		printf( __( '&copy; Copyright 2009 <a href="http://blog.avirtualhome.com/" title="My Thoughts">Peter van der Does</a> | AVH First Defense Against Spam Version %s', 'avhfdas' ), $this->core->version );
		echo '</p>';
	}

	/**
	 * Display WP alert
	 *
	 */
	function displayMessage ()
	{
		if ( $this->message != '' ) {
			$message = $this->message;
			$status = $this->status;
			$this->message = $this->status = ''; // Reset
		}
		if ( $message ) {
			$status = ($status != '') ? $status : 'updated fade';
			echo '<div id="message"	class="' . $status . '">';
			echo '<p><strong>' . $message . '</strong></p></div>';
		}
	}

	/**
	 * Print link to CSS
	 *
	 * @WordPress Action admin_print_styles-settings_page_avhfdas_options
	 */
	function actionInjectCSS ()
	{
		wp_enqueue_style( 'avhfdasadmin', $this->core->info['plugin_url'] . '/inc/avh-fdas.admin.css', array (), $this->core->version, 'screen' );
		wp_admin_css( 'css/dashboard' );
	}

	/**
	 * Ouput formatted options
	 *
	 * @param array $option_data
	 * @return string
	 */
	function printOptions ( $option_data, $option_actual )
	{
		// Generate output
		$output = '';
		$checkbox = '|';
			$output .= "\n" . '<fieldset class="options"><table class="form-table">' . "\n";
			foreach ( $option_data as $option ) {
				$section = substr($option[0],strpos($option[0],'[')+1);
				$section = substr($section,0,strpos($section,']['));
				$option_key = rtrim( $option[0], ']' );
				$option_key = substr( $option_key, strpos( $option_key, '][' ) + 2 );
				// Helper
				if ( $option[2] == 'helper' ) {
					$output .= '<tr style="vertical-align: top;"><td class="helper" colspan="2">' . $option[4] . '</td></tr>' . "\n";
					continue;
				}
				switch ( $option[2] ) {
					case 'checkbox' :
						$input_type = '<input type="checkbox" id="' . $option[0] . '" name="' . $option[0] . '" value="' . attribute_escape( $option[3] ) . '" ' . $this->isChecked( '1', $option_actual[$section][$option_key] ) . ' />' . "\n";
						$checkbox .= $option[0] . '|';
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
						$input_type = '<input type="text" ' . (($option[3] > 50) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . attribute_escape( $option_actual[$section][$option_key] ) . '" size="' . $option[3] . '" /><div class="box_color ' . $option[0] . '"></div>' . "\n";
						$explanation = $option[4];
						break;
					case 'textarea' :
						$input_type = '<textarea rows="' . $option[5] . '" ' . (($option[3] > 50) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" size="' . $option[3] . '" />' . attribute_escape( $option_actual[$section][$option_key] ) . '</textarea>';
						$explanation = $option[4];
						break;
					case 'text' :
					default :
						$input_type = '<input type="text" ' . (($option[3] > 50) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . attribute_escape( $option_actual[$section][$option_key] ) . '" size="' . $option[3] . '" />' . "\n";
						$explanation = $option[4];
						break;
				}
				// Additional Information
				$extra = '';
				if ( $explanation ) {
					$extra = '<div class="avhfdas_explain">' . __( $explanation ) . '</div>' . "\n";
				}
				// Output
				$output .= '<tr style="vertical-align: top;"><th scope="row"><label for="' . $option[0] . '">' . __( $option[1] ) . '</label></th><td>' . $input_type . '	' . $extra . '</td></tr>' . "\n";
			}
			$output .= '</table>' . "\n";
			if ( '|' !== $checkbox )
				$checkbox = ltrim( $checkbox, '|' );
			$output .= '<input	type="hidden" name="avh_checkboxes" value="' . rtrim( $checkbox, '|' ) . '" />';
			$output .= '</fieldset>' . "\n";
		return $output;
	}

	/**
	 * Get nice title for tabs title option
	 *
	 * @param string $id
	 * @return string
	 */
	function getNiceTitleOptions ( $id = '' )
	{
		switch ( $id ) {
			case 'spam' :
				return __( 'General', 'avhfdas' );
				break;
			case 'faq' :
				return __( 'FAQ', 'avhfdas' );
				break;
			case 'about' :
				return __( 'About', 'avhfdas' );
				break;
			case 'tips' :
				return __( 'Tips and Tricks', 'avhfdas' );
				break;
		}
		return 'Unknown';
	}

	/**
	 * Used in forms to set an option checked
	 *
	 * @param mixed $checked
	 * @param mixed $current
	 * @return strings
	 */
	function isChecked ( $checked, $current )
	{
		$return = '';
		if ( $checked == $current ) {
			$return = ' checked="checked"';
		}
		return $return;
	}
}
?>