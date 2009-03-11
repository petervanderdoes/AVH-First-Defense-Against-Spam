<?php
class AVHStopSpamAdmin extends AVHStopSpamCore 
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
		$avhstopspam_pages = array ('avhstopspam_options' );
		/**
		 * TODO  With WordPress 2.5 the Tabs UI is build in :)
		 */
		if ( in_array( $_GET['page'], $avhstopspam_pages ) ) {
			wp_enqueue_script( 'jquery-ui-tabs' );
		}
		return;
	}

	/**
	 * PHP4 Constructor - Intialize Admin
	 *
	 * @return
	 */
	function AVHStopSpamAdmin ()
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
			if ( $role != null && ! $role->has_cap( 'avh_stopspam' ) ) {
				$role->add_cap( 'avh_stopspam' );
			}
			if ( $role != null && ! $role->has_cap( 'admin_avh_stopspam' ) ) {
				$role->add_cap( 'admin_avh_stopspam' );
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
		add_options_page( __( 'AVH StopSpam: Options', 'avhstopspam' ), 'AVH Stop Spam', 'avh_stopspam', 'avhstopspam_options', array (&$this, 'pageOptions' ) );
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
			$this_plugin = plugin_basename( $this->info['install_dir']);
		if ( $file )
			$file = $this->getBaseDirectory( $file );
		if ( $file == $this_plugin ) {
			$settings_link = '<a href="options-general.php?page=avhstopspam_options">' . __( 'Settings', 'avhstopspam' ) . '</a>';
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
			'general' => array (
				array (
					'avhstopspam[spam][whentoemail]',
					'Email threshold:',
					'text',
					3,
					'When the frequency of the spammer in the stopforumspam database equals or exceeds this threshold an email is send.<BR />A negative number means an email will never be send.'
				),
				array(
					'avhstopspam[spam][whentodie]'.
					'Termination threshold:',
					'text',
					3,
					'When the frequency of the spammer in the stopforumspam database equals or exceeds this threshold the connection is terminated.<BR />A negative number means the connection will never be terminated.<BR /><strong>This option will always be the last one checked.</strong>'
				),
			),
			'faq' => array (
				array (
					'text-helper',
					'text-helper',
					'helper',
					'',
					'None yet'
				)
			),
			'about' => array (
				array (
					'text-helper',
					'text-helper',
					'helper',
					'',
					'<p>The AVH Stop Spam plugin gives you the ability to block potential spammers based on the Stop Forum Spam database<br />' . 
					'<b>Support</b><br />' . 
					'For support visit the AVH support forums at <a href="http://forums.avirtualhome.com/">http://forums.avirtualhome.com/</a><br /><br />' . 
					'<b>Developer</b><br />' . 'Peter van der Does'
				)
			)
		);
			
		// Update or reset options
		if ( isset( $_POST['updateoptions'] ) ) {
			check_admin_referer( 'avhstopspam-options' );
			// Set all checkboxes unset
			if ( isset( $_POST['avh_checkboxes'] ) ) {
				$checkboxes = explode( '|', $_POST['avh_checkboxes'] );
				foreach ( $checkboxes as $value ) {
					$value = ltrim( $value, 'option[' );
					$value = rtrim( $value, ']' );
					$keys = explode( '][', $value );
					$this->setOption( $keys, 0 );
				}
			}
			$formoptions = $_POST['avhstopspam'];
			foreach ( $this->options as $key => $value ) {
				foreach ( $value as $key2 => $value2 ) {
					$newval = (isset( $formoptions[$key][$key2] )) ? attribute_escape( $formoptions[$key][$key2] ) : '0';
					// Check numeric entries
					if ( 'whentoemail' == $key2 || 'whentodie' == $key2 ) {
						if ( ! is_numeric( $formoptions[$key][$key2] ) ) {
							$newval = $this->default_options[$key][$key2];
						}
					}
					if ( $newval != $value2 ) {
						$this->setOption( array ($key, $key2 ), $newval );
					}
				}
			}
			$this->saveOptions();
			$this->message = 'Options saved';
			$this->status = 'updated';
		} elseif ( isset( $_POST['reset_options'] ) ) {
			$this->resetToDefaultOptions();
			$this->message = __( 'AVH Stop Spam options set to default options!', 'avhstopspam' );
		}
		
		$this->displayMessage();
		
		echo '<script type="text/javascript">';
		echo 'jQuery(document).ready( function() {';
		echo 'jQuery(\'#printOptions\').tabs({fxSlide: true});';
		echo '});';
		echo '</script>';
		
		echo '<div class="wrap avh_wrap">';
		echo '<h2>';
		_e( 'AVH Stop Spam: Options', 'avhstopspam' );
		echo '</h2>';
		echo '<form	action="' . $this->admin_base_url . 'avhstopspam_options' . '"method="post">';
		echo '<div id="printOptions">';
		echo '<ul class="avhstopspam_submenu">';
		foreach ( $option_data as $key => $value ) {
			echo '<li><a href="#' . sanitize_title( $key ) . '">' . $this->getNiceTitleOptions( $key ) . '</a></li>';
		}
		echo '</ul>';
		echo $this->printOptions( $option_data );
		echo '</div>';
		
		$buttonprimary = ($this->info['wordpress_version'] < 2.7) ? '' : 'button-primary';
		$buttonsecondary = ($this->info['wordpress_version'] < 2.7) ? '' : 'button-secondary';
		echo '<p class="submit"><input	class="' . $buttonprimary . '"	type="submit" name="updateoptions" value="' . __( 'Save Changes', 'avhstopspam' ) . '" />';
		echo '<input class="' . $buttonsecondary . '" type="submit" name="reset_options" onclick="return confirm(\'' . __( 'Do you really want to restore the default options?', 'avhstopspam' ) . '\')" value="' . __( 'Reset Options', 'avhstopspam' ) . '" /></p>';
		wp_nonce_field( 'avhstopspam-options' );
		echo '</form>';
		
		$this->printAdminFooter();
		echo '</div>';
	}

	/**
	 * Add initial avh-stopspam options in DB
	 *
	 */
	function installPlugin ()
	{
		$options_from_table = get_option( $this->db_options_name_core );
		if ( ! $options_from_table ) {
			$this->resetToDefaultOptions();
		}

	}

	############## WP Options ##############
	/**
	 * Removes the plugin, old style of doing it.
	 *
	 * @param string $plugin
	 */
	function removePlugin ( $plugin )
	{
		$current = get_option( 'active_plugins' );
		array_splice( $current, array_search( $plugin, $current ), 1 ); // Array-fu!
		update_option( 'active_plugins', $current );
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
		$c=$this->options['general']['counter'];
		$this->options = $this->default_options;
		$this->options['general']['counter']=$c;
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
		printf( __( '&copy; Copyright 2009 <a href="http://blog.avirtualhome.com/" title="My Thoughts">Peter van der Does</a> | AVH Stop Spam Version %s', 'avhstopspam' ), $this->version );
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
		if ( $this->info['wordpress_version'] >= 2.7 ) {
			$this->handleCssFile( 'avhstopspamadmin', '/inc/avh-stopspam.admin.css' );
		}
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
						$input_type = '<input type="checkbox" id="' . $option[0] . '" name="' . $option[0] . '" value="' . attribute_escape( $option[3] ) . '" ' . checked( '1', $option_actual[$section][$option_key] ) . ' />' . "\n";
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

					case 'text' :
					default :
						$input_type = '<input type="text" ' . (($option[3] > 50) ? ' style="width: 95%" ' : '') . 'id="' . $option[0] . '" name="' . $option[0] . '" value="' . attribute_escape( $option_actual[$section][$option_key] ) . '" size="' . $option[3] . '" />' . "\n";
						$explanation = $option[4];
						break;
				}

				// Additional Information
				$extra = '';
				if ( $explanation ) {
					$extra = '<div class="avhstopspam_explain">' . __( $explanation ) . '</div>' . "\n";
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
			case 'general' :
				return __( 'General', 'avhstopspam' );
				break;
			case 'faq' :
				return __( 'FAQ', 'avhstopspam' );
				break;
			case 'about' :
				return __( 'About', 'avhstopspam' );
				break;
		}
		return 'Unknown';
	}
}
?>