<?php
/*
Plugin Name: AVH First Defense Against Spam
Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
Description: This plugin gives you the ability to block spammers before content is served.
Version: 3.0-dev1
Author: Peter van der Does
Author URI: http://blog.avirtualhome.com/

Copyright 2009  Peter van der Does  (email : peter@avirtualhome.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( ! defined( 'AVH_FRAMEWORK' ) ) {
	define( 'AVH_FRAMEWORK', TRUE );
}

$_dir = pathinfo( __FILE__, PATHINFO_DIRNAME );
require_once $_dir . '/libs/class-registry.php';
require_once $_dir . '/helpers/avh-common.php';
require_once $_dir . '/helpers/avh-security.php';
require_once $_dir . '/helpers/avh-visitor.php';

// Define Message Numbers
define( 'AVHFDAS_REPORTED_DELETED', '100' );
define( 'AVHFDAS_ADDED_BLACKLIST', '101' );
define( 'AVHFDAS_REPORTED', '102' );
define( 'AVHFDAS_ERROR_INVALID_REQUEST', '200' );
define( 'AVHFDAS_ERROR_NOT_REPORTED', '201' );
define( 'AVHFDAS_ERROR_EXISTS_IN_BLACKLIST', '202' );

/**
 * Create seperate registry for the plugin.
 * @author pdoes
 *
 */
final class AVH_FDAS_Settings extends AVH_Registry
{

	//prevent directly access.
	public function __construct ()
	{
	}

	//prevent clone.
	public function __clone ()
	{
	}

	/**
	 * The instance of the registry
	 * @access private
	 */
	private static $_instance;

	/**
	 * Singleton method to access the Registry
	 * @access public
	 */
	public static function singleton ()
	{
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

}

if ( avh_getWordpressVersion() >= 2.7 ) {
	require_once $_dir . '/helpers/avh-security.php';
	require_once $_dir . '/helpers/avh-visitor.php';


	$Settings = AVH_FDAS_Settings::singleton();
	$Settings->setDir( $_dir );
	$Settings->setClassFilePrefix('avh-fdas.');
	$Settings->setClassNamePrefix('AVH_FDAS_');
	$Settings->storeSetting( 'plugin_dir', $_dir );
	unset( $Settings );

	require ($_dir . '/avh-fdas.client.php');
} else {
	add_action( 'activate_avh-first-defense-against-spam/avh-fdas.php', 'avh_fdas_remove_plugin' );

}

function avh_fdas_remove_plugin ()
{
	$current = get_option( 'active_plugins' );
	$num = array_search( 'avh-first-defense-against-spam/avh-fdas.php', $current );
	array_splice( $current, $num, 1 ); // Array-fu!
	update_option( 'active_plugins', $current );
	ob_end_clean();
	wp_die( __( 'AVH First Defense Against Spam can\'t work with this WordPress version!', 'avhfdas' ) );
}
?>