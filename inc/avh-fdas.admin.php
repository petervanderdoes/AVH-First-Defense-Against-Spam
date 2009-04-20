<?php
require_once( ABSPATH . 'wp-includes/class-snoopy.php' );
class AVH_FDAS_Admin extends AVH_FDAS_Core
{

	/**
	 * Message management
	 *
	 */
	var $message = '';
	var $status = '';

	function __construct ()
	{
		// Initialize the plugin
		parent::__construct();

		// Admin URL and Pagination
		$this->admin_base_url = $this->info['siteurl'] . '/wp-admin/admin.php?page=';
		if ( isset( $_GET['pagination'] ) ) {
			$this->actual_page = ( int ) $_GET['pagination'];
		}

		$this->installPlugin();
		// Admin Capabilities
		add_action( 'init', array (&$this, 'initRoles' ) );

		// Admin menu
		add_action( 'admin_menu', array (&$this, 'adminMenu' ) );

		// CSS Helper
		add_action( 'admin_print_styles-settings_page_avhfdas_options', array (&$this, 'injectCSS' ) );

		// Helper JS & jQuery & Prototype
		$avhfdas_pages = array ('avhfdas_options' );
		if ( in_array( $_GET['page'], $avhfdas_pages ) ) {
			wp_enqueue_script( 'jquery-ui-tabs' );
		}

		// Add the ajax action
		//add_action('admin_init', array(&$this, 'ajaxCheck'));
		add_action ( 'wp_ajax_avh-fdas-reportcomment', array ( &$this, 'ajaxCheck' ) );

		// Add admin actions
		add_action('admin_action_blacklist',array(&$this,'handleBlacklistUrl'));
		add_action('admin_action_emailreportspammer',array(&$this,'handleEmailReportingUrl'));

		// Add Filter
		add_filter('comment_row_actions',array(&$this,'filterCommentRowActions'),10,2);
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
	 */
	function initRoles ()
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
	 */
	function adminMenu ()
	{
		add_options_page( __( 'AVH First Defense Against Spam: Options', 'avhfdas' ), 'AVH First Defense Against Spam', 'avh_fdas', 'avhfdas_options', array (&$this, 'pageOptions' ) );
		add_filter( 'plugin_action_links_avh-first-defense-against-spam/avh-fdas.php', array (&$this, 'filterPluginActions' ) );
	}

	/**
	 * Adds Settings next to the plugin actions
	 *
	 * @param array $links
	 * @return array
	 *
	 * @since 1.0
	 */
	function filterPluginActions ( $links )
	{
		$settings_link = '<a href="options-general.php?page=avhfdas_options">' . __( 'Settings', 'avhfdas' ) . '</a>';
		array_unshift( $links, $settings_link ); // before other links
		return $links;

	}

	/**
	 * Adds an extra option on the comment row
	 *
	 * @param array $actions
	 * @param class $comment
	 * @return array
	 * @since 1.0
	 */
	function filterCommentRowActions($actions,$comment){
		if ( (! empty( $this->options['spam']['sfsapikey'] )) && isset( $comment->comment_approved ) && 'spam' == $comment->comment_approved ) {
			$report_url = clean_url( wp_nonce_url("admin.php?avhfdas_ajax_action=avh-fdas-reportcomment&id=$comment->comment_ID", "report-comment_$comment->comment_ID" ) );
			$actions['report'] = '<a class=\'delete:the-comment-list:comment-'.$comment->comment_ID.':e7e7d3:action=avh-fdas-reportcomment vim-d vim-destructive\' href="'.$report_url.'">Report & Delete</a>';
		}
		return $actions;
	}

	/**
	 * Checks if the user clicked on the Report & Delete link.
	 *
	 */
	function ajaxCheck ()
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

			$this->handleReportSpammer($comment->comment_author,$comment->comment_author_email,$comment->comment_author_IP);

			// Delete the comment
			$r = wp_delete_comment( $comment->comment_ID );
			die( $r ? '1' : '0' );
		}
	}

	/**
	 * Handles the admin_action emailreportspammer call.
	 *
	 * @since 1.2
	 *
	 */
	function handleEmailReportingUrl ()
	{
		if ( ! (isset( $_REQUEST['action'] ) && 'emailreportspammer' == $_REQUEST['action']) ) {
			return;
		}

		$a = wp_specialchars( $_REQUEST['a'] );
		$e = wp_specialchars( $_REQUEST['e'] );
		$i = wp_specialchars( $_REQUEST['i'] );
		$extra = '&m=10&i=' . $i;
		if ( $this->avh_verify_nonce( $_REQUEST['_avhnonce'], $a . $e . $i ) ) {
			$all = get_option( $this->db_nonces );
			$extra = '&m=11&i=' . $i;
			if ( isset( $all[$_REQUEST['_avhnonce']] ) ) {
				$this->handleReportSpammer( $a, $e, $i );
				unset( $all[$_REQUEST['_avhnonce']] );
				update_option( $this->db_nonce, $all );
				$extra = '&m=12&i=' . $i;
			}
			unset( $all );
		}
		wp_redirect( admin_url( 'options-general.php?page=avhfdas_options' . $extra ) );
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
		$snoopy_formvar['api_key'] = $this->options['spam']['sfsapikey'];
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
	 */
	function handleBlacklistUrl ()
	{
		if ( ! (isset( $_REQUEST['action'] ) && 'blacklist' == $_REQUEST['action']) ) {
			return;
		}

		$ip = $_REQUEST['i'];
		if ( $this->avh_verify_nonce( $_REQUEST['_avhnonce'], $ip ) ) {

			if ( ! empty( $this->options['spam']['blacklist'] ) ) {
				$b = explode( "\r\n", $this->options['spam']['blacklist'] );
			} else {
				$b = array ();
			}
			if ( ! (in_array( $ip, $b )) ) {
				array_push( $b, $ip );
				$this->setBlacklistOption( $b );
			}
		}
		wp_redirect( admin_url( 'options-general.php?page=avhfdas_options&m=2&i=' . $ip ) );
	}

	/**
	 * Update the blacklist in the proper format
	 *
	 * @param array $b
	 */
	function setBlacklistOption($b)
	{
		natsort($b);
		$x=implode("\r\n",$b);
		$this->options['spam']['blacklist']=$x;
		$this->saveOptions();
	}
	/**
	 * Update the whitelist in the proper format
	 *
	 * @param array $b
	 */
	function setWhitelistOption($b)
	{
		natsort($b);
		$x=implode("\r\n",$b);
		$this->options['spam']['whitelist']=$x;
		$this->saveOptions();
	}
	/**
	 * WP Page Options- AVH Amazon options
	 *
	 */
	function pageOptions ()
	{
		$option_data = array (
			'spam' => array (
				array (
					'avhfdas[spam][whentoemail]',
					'Email threshold:',
					'text',
					3,
					'When the frequency of the spammer in the stopforumspam database equals or exceeds this threshold an email is send.<BR />A negative number means an email will never be send.'
				),
				array(
					'avhfdas[spam][whentodie]',
					'Termination threshold:',
					'text',
					3,
					'When the frequency of the spammer in the stopforumspam database equals or exceeds this threshold the connection is terminated.<BR />A negative number means the connection will never be terminated.<BR /><strong>This option will always be the last one checked.</strong>'
				),
				array (
					'avhfdas[spam][diewithmessage]',
					'Show message:',
					'checkbox',
					1,
					'Show a message when the connection has been terminated.'
				),
				array (
					'avhfdas[spam][emailsecuritycheck]',
					'Email on failed security check:',
					'checkbox',
					1,
					'Receive an email when a comment is posted and the security check failed.'
				),
				array (
					'avhfdas[spam][sfsapikey]',
					'API Key:',
					'text',
					15,
					'You need a Stop Forum Spam API key to report spam.'
				),
				array (
					'avhfdas[spam][useblacklist]',
					'Use internal blacklist:',
					'checkbox',
					1,
					'Check the internal blacklist first. If the IP is found terminate the connection, even when the Termination threshold is a negative number.'
				),
				array (
					'avhfdas[spam][blacklist]',
					'Blacklist IP\'s:',
					'textarea',
					15,
					'Each IP should be on a seperate line<br />Ranges can be defines as well in the following two formats<br />IP to IP. i.e. 192.168.1.100-192.168.1.105<br />Network in CIDR format. i.e. 192.168.1.0/24',
					15),
				array (
					'avhfdas[spam][usewhitelist]',
					'Use internal whitelist:',
					'checkbox',
					1,
					'Check the internal whitelist first. If the IP is found don\t do any further checking.'
				),
				array (
					'avhfdas[spam][whitelist]',
					'Whitelist IP\'s:',
					'textarea',
					15,
					'Each IP should be on a seperate line<br />Ranges can be defines as well in the following two formats<br />IP to IP. i.e. 192.168.1.100-192.168.1.105<br />Network in CIDR format. i.e. 192.168.1.0/24',
					15)
			),
			'faq' => array (
				array (
					'text-helper',
					'text-helper',
					'helper',
					'',
					'<h3>Why is the default threshold for terminating set to 3?</h3>'.
					'<p>People, like you and me, report spammers to Stop Forum Spam. Sometimes a mistake is made and a normal IP is reported as a spammer.<br />'.
					'To be safe not to block a non-spammer, I have set the threshold to 3.</p>'.
					'<h3>Is this plugin enough to block all spam?</h3>'.
					'<p>Unfortunately not. I don\'t believe there is one solution to block all spam.<br />'.
					'Personally I have great success with the plugin in combination with Akismet.</p>'.
					'<h3>Why bother with this plugin? My other solutions keep my blog free from spam.</h3>'.
					'<p>The way a potential spammer is blocked is different from other solutions so far.<br />'.
					'This plugin blocks the spammer before WordPress generates the page and shows it to the visitor.<br/>'.
					'This has the following advantages:<br />'.
					'<nbsp>* It saves bandwidth.<br/>'.
					'<nbsp>* It saves CPU cycles.<br/>'.
					'<nbsp>* If you keep track of how many visitors your site has, either by using Google\'s Analytics, WP-Stats or any other one, it will give you a cleaner statistic of visits your site receives.<br/>'.
					'</p>'.
					'<h3>Does it conflicts with other spam solutions?</h3>'.
					'<p>I\'m currently not aware of any conflicts with other spam solutions.</p>'.
					'<h3>Can I report a spammer at Stop Forum Spam?</h3>'.
					'<p>You need an API key which you can get by visiting <a href="http://www.stopforumspam.com/add" target="_blank">http://www.stopforumspam.com/add</a><br/>'.
					'When you enter your API key in this plugin you\'ll be able to report comments marked as spam to Stop Forum Spam.<br />'.
					'There will be an extra option called Report for comments marked as spam.</p>'.
					'<h3>What do the emails mean that I receive?</h3>'.
					'<p><em>AVH First Defense Against Spam - Error detected</em><br/>'.
					'This means the call to Stop Forum Spam failed. The error explains the problem. I actually check the connection again if the first one fails and only then report the error.<br />'.
					'The plugin was unable to check if the visiting IP is in the database and therefor does not block it.</p>'.
					'<p><em>AVH First Defense Against Spam - Spammer detected</em><br />'.
					'The call to the database was successful and the IP was found in the database.<br />'.
					'You can actually receive two sort of emails with this subject line.<br />'.
					'One will have the line: <em>Threshold (3) reached. Connection terminated.</em> This means because the Threshold, set in the admin section, was reached the connection was terminated. In other words the spammer is stopped before any content was served.<br />'.
					'The other message is without the Threshold message. This means the IP is in the database but the connection is not terminate because the threshold was not reached. In this case the normal content is served to the visitor.</p>'.
					'<p><em>AVH First Defense Against Spam - Comment security check failed</em><br />'.
					'Somebody tried to post a comment but it failed the security check.<br />'.
					'Some spammers try to comment by directly accessing the WordPress core file responsible for posting the comment (wp-comments-post.php).<br />'.
					'This file does not trigger the check of AVH First Defense Against Spam to the site Stop Forum Spam.<br />'.
					'For this reason I added an extra security option that will check if the posting of the comment is coming from the blog itself.<br />'.
					'If it fails and you have checked the option <em>Email on failed security check</em>, you will receive this email.</p>'
				)
			),
			'tips' => array (
				array (
					'text-helper',
					'text-helper',
					'helper',
					'',
					'<h3>Deny direct access to add comments.</h3>' .
					'<p>Add the following lines to your .htaccess file above the WordPress section. <br />' .
					'<pre>&#60;IfModule mod_rewrite.c> <br />' .
					'RewriteEngine On <br />' .
					'RewriteBase / <br />' .
					'RewriteCond %{REQUEST_METHOD} POST <br />' .
					'RewriteCond %{THE_REQUEST} .wp-comments-post\.php.* <br />' .
					'RewriteCond %{HTTP_REFERER} !.*example\.com/.*$ [OR] <br />' .
					'RewriteCond %{HTTP_USER_AGENT} ^$ <br />' .
					'RewriteRule (.*) http://%{REMOTE_ADDR}/ [R=301,L] <br />' .
					'&#60;/IfModule></pre>' .
					'<p>Replace example\.com with your domain, for me it would be avirtualhome\.com<br /><br />' .
					'Spammers are known to call the file wp-comments-post.php directly. Normal users would never do this, the above part will block this behavior.<br />'.
					'As of version 1.2 of this plugin you no longer need this, the plugin has a security feature that takes care of these kind of spammers</p>'
				)
			),
			'about' => array (
				array (
					'text-helper',
					'text-helper',
					'helper',
					'',
					'<p>The AVH First Defense Against Spam plugin gives you the ability to block potential spammers based on the Stop Forum Spam database or the local blacklist<br />' .
					'<b>Support</b><br />' .
					'For support visit the AVH support forums at <a href="http://forums.avirtualhome.com/">http://forums.avirtualhome.com/</a><br /><br />' .
					'<b>Developer</b><br />' . 'Peter van der Does'
				)
			)
		);

		// Update or reset options
		if ( isset( $_POST['updateoptions'] ) ) {
			check_admin_referer( 'avhfdas-options' );

			$formoptions = $_POST['avhfdas'];
			foreach ( $this->options as $key => $value ) {
				if ( 'general' != $key ) { // The data in General is used for internal usage.
					foreach ( $value as $key2 => $value2 ) {
						// Every field in a form is filled except unchecked checkboxes. Set an unchecked checkbox to 0.
						$newval = (isset( $formoptions[$key][$key2] ) ? attribute_escape( $formoptions[$key][$key2] ) : 0);

						// Check numeric entries
						if ( 'whentoemail' == $key2 || 'whentodie' == $key2 ) {
							if ( ! is_numeric( $formoptions[$key][$key2] ) ) {
								$newval = $this->default_options[$key][$key2];
							}
							$newval = ( int ) $newval;
						}
						if ( 'blacklist' == $key2 || 'whitelist' == $key2) {
							$b = explode( "\r\n", $newval );
							natsort( $b );
							$newval = implode( "\r\n", $b );
							unset( $b );
						}
						if ( $newval != $value2 ) {
							$this->setOption( array ($key, $key2 ), $newval );
						}
					}
				}
			}
			$this->saveOptions();
			$this->message = 'Options saved';
			$this->status = 'updated fade';
		} elseif ( isset( $_POST['reset_options'] ) ) {
			check_admin_referer( 'avhfdas-options' );
			$this->resetToDefaultOptions();
			$this->message = __( 'AVH First Defense Against Spam options set to default options!', 'avhfdas' );
		}

		// Show messages if needed.
		if ( isset( $_REQUEST['m'] ) ) {
			switch ( $_REQUEST['m'] ) {
				case '1' :
					$this->status = 'updated fade';
					$this->message = sprintf( __( 'IP [%s] Reported and deleted', 'avhfdas' ), attribute_escape( $_REQUEST['i'] ) );
					break;
				case '2' :
					$this->status = 'updated fade';
					$this->message = sprintf( __( 'IP [%s] has been added to the blacklist', 'avhfdas' ), attribute_escape( $_REQUEST['i'] ) );
					break;
				case '10' :
					$this->status = 'error';
					$this->message = sprintf( __( 'Invalid request.', 'avhfdas' ) );
					break;
				case '11' :
					$this->status = 'error';
					$this->message = sprintf( __( 'IP [%s] not reported. Probably already processed.', 'avhfdas' ), attribute_escape( $_REQUEST['i'] ) );
					break;
				case '12' :
					$this->status = 'updated fade';
					$this->message = sprintf( __( 'IP [%s] reported.', 'avhfdas' ), attribute_escape( $_REQUEST['i'] ) );
					break;
				default :
					$this->message = '';
			}
		}
		$this->displayMessage();

		echo '<script type="text/javascript">';
		echo 'jQuery(document).ready( function() {';
		echo 'jQuery(\'#printOptions > ul\').tabs();';
		echo '});';
		echo '</script>';

		echo '<div class="wrap avh_wrap">';
		echo '<h2>';
		_e( 'AVH First Defense Against Spam: Options', 'avhfdas' );
		echo '</h2>';
		echo '<form	action="' . $this->admin_base_url . 'avhfdas_options' . '"method="post">';
		echo '<div id="printOptions">';
		echo '<ul class="avhfdas_submenu">';
		foreach ( $option_data as $key => $value ) {
			echo '<li><a href="#' . sanitize_title( $key ) . '">' . $this->getNiceTitleOptions( $key ) . '</a></li>';
		}
		echo '</ul>';
		echo $this->printOptions( $option_data );
		echo '</div>';

		echo '<p class="submit"><input	class="button-primary" type="submit" name="updateoptions" value="' . __( 'Save Changes', 'avhfdas' ) . '" />';
		echo '<input class="button-secondary" type="submit" name="reset_options" onclick="return confirm(\'' . __( 'Do you really want to restore the default options?', 'avhfdas' ) . '\')" value="' . __( 'Reset Options', 'avhfdas' ) . '" /></p>';
		wp_nonce_field( 'avhfdas-options' );
		echo '</form>';

		$this->printAdminFooter();
		echo '</div>';
	}

	/**
	 * Add initial avh-fdas options in DB
	 *
	 */
	function installPlugin ()
	{
		if ( ! (get_option( $this->db_options_name_core )) ) {
			$this->resetToDefaultOptions();
		}

		if ( ! (get_option( $this->db_data )) ) {
			$this->data = $this->default_data;
			update_option( $this->db_data, $this->data );
			wp_cache_flush(); // Delete cache
		}

		if ( ! (get_option( $this->db_nonces )) ) {
			update_option( $this->db_nonces, $this->default_emailreport );
			wp_cache_flush(); // Delete cache
		}
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
		$this->options[$key1][$key2] = $optval;
	}

	/**
	 * Save all current options
	 *
	 */
	function saveOptions ()
	{
		update_option( $this->db_options_name_core, $this->options );
		wp_cache_flush(); // Delete cache
	}

	/**
	 * Reset to default options
	 *
	 */
	function resetToDefaultOptions ()
	{
		$this->options = $this->default_options;
		update_option( $this->db_options_name_core, $this->options );
		wp_cache_flush(); // Delete cache
	}

	/**
	 * Delete all options from DB.
	 *
	 */
	function deleteAllOptions ()
	{
		delete_option( $this->db_options_name_core, $this->default_options );
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
		printf( __( '&copy; Copyright 2009 <a href="http://blog.avirtualhome.com/" title="My Thoughts">Peter van der Does</a> | AVH First Defense Against Spam Version %s', 'avhfdas' ), $this->version );
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
	 */
	function injectCSS ()
	{
		wp_enqueue_style( 'avhfdasadmin', $this->info['install_url'] . '/inc/avh-fdas.admin.css', array (), $this->version, 'screen' );
	}

	/**
	 * Ouput formatted options
	 *
	 * @param array $option_data
	 * @return string
	 */
	function printOptions ( $option_data )
	{
		// Get actual options
		$option_actual = ( array ) $this->options;

		// Generate output
		$output = '';
		$checkbox = '|';
		foreach ( $option_data as $section => $options ) {
			$output .= "\n" . '<div id="' . sanitize_title( $section ) . '"><fieldset class="options"><legend>' . $this->getNiceTitleOptions( $section ) . '</legend><table class="form-table">' . "\n";
			foreach ( ( array ) $options as $option ) {
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
			$output .= '</fieldset></div>' . "\n";
		}
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
		if ( $checked == $current )
			return (' checked="checked"');
	}
}
?>