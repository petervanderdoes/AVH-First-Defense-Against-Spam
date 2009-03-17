<?php
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

		// Admin Capabilities
		add_action( 'init', array (&$this, 'initRoles' ) );

		// Admin menu
		add_action( 'admin_menu', array (&$this, 'adminMenu' ) );

		// CSS Helper
		add_action( 'admin_head', array (&$this, 'helperCSS' ) );

		// Helper JS & jQuery & Prototype
		$avhfdas_pages = array ('avhfdas_options' );
		/**
		 * TODO  With WordPress 2.5 the Tabs UI is build in :)
		 */
		if ( in_array( $_GET['page'], $avhfdas_pages ) ) {
			wp_enqueue_script( 'jquery-ui-tabs' );
		}
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
		add_filter( 'plugin_action_links', array (&$this, 'filterPluginActions' ), 10, 2 );
	}

	/**
	 * Adds Settings next to the plugin actions
	 *
	 */
	function filterPluginActions ( $links, $file )
	{
		static $this_plugin;

		if ( ! $this_plugin )
			$this_plugin = plugin_basename( $this->info['install_dir'] );
		if ( $file )
			$file = $this->getBaseDirectory( $file );
		if ( $file == $this_plugin ) {
			$settings_link = '<a href="options-general.php?page=avhfdas_options">' . __( 'Settings', 'avhfdas' ) . '</a>';
			array_unshift( $links, $settings_link ); // before other links
		//$links = array_merge ( array (	$settings_link ), $links ); // before other links
		}
		return $links;

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
					'Show a message when the connection has been terminated'
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
					'Each IP should be on a seperate line',
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
					'<p>You can by visiting their site at <a href="http://www.stopforumspam.com/add" target="_blank">http://www.stopforumspam.com/add</a><br/>'.
					'I\'m looking to see if I can integrate the reporting of spammers in WordPress.</p>'
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
					'Spammers are known to call the file wp-comments-post.php directly. Normal users would never do this, the above part will block this behavior.</p>'
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
			// Set all checkboxes unset
			if ( isset( $_POST['avh_checkboxes'] ) ) {
				$checkboxes = explode( '|', $_POST['avh_checkboxes'] );
				foreach ( $checkboxes as $value ) {
					$value = preg_replace( '#^[[:alpha:]]+[[:alnum:]]*\[#', '', $value );
					$value = rtrim( $value, ']' );
					$keys = explode( '][', $value );
					$this->setOption( $keys, 0 );
				}
			}
			$formoptions = $_POST['avhfdas'];
			foreach ( $this->options as $key => $value ) {
				if ( 'general' != $key ) { // The data in General is used for internal usage.
					foreach ( $value as $key2 => $value2 ) {
						if (isset( $formoptions[$key][$key2] )) {
							$newval =  attribute_escape( $formoptions[$key][$key2] );
						}
						// Check numeric entries
						if ( 'whentoemail' == $key2 || 'whentodie' == $key2 ) {
							if ( ! is_numeric( $formoptions[$key][$key2] ) ) {
								$newval = $this->default_options[$key][$key2];
							}
							$newval = ( int ) $newval;
						}
						if ( $newval != $value2 ) {
							$this->setOption( array ($key, $key2 ), $newval );
						}
					}
				}
			}
			$this->saveOptions();
			$this->message = 'Options saved';
			$this->status = 'updated';
		} elseif ( isset( $_POST['reset_options'] ) ) {
			check_admin_referer( 'avhfdas-options' );
			$this->resetToDefaultOptions();
			$this->message = __( 'AVH First Defense Against Spam options set to default options!', 'avhfdas' );
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
		echo '<p class="footer_avhamazon">';
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
			$status = ($status != '') ? $status : 'updated';
			echo '<div id="message"	class="' . $status . ' fade">';
			echo '<p><strong>' . $message . '</strong></p></div>';
		}
	}

	/**
	 * Print link to CSS
	 *
	 */
	function helperCSS ()
	{
		$this->handleCssFile( 'avhfdasadmin', '/inc/avh-fdas.admin.css' );
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