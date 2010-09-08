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
$_basename = plugin_basename(__FILE__);
require_once ($_dir . '/libs/class-registry.php');
require_once ($_dir . '/helpers/avh-common.php');
require_once ($_dir . '/helpers/avh-security.php');
require_once ($_dir . '/helpers/avh-visitor.php');

// Define Message Numbers
define( 'AVHFDAS_REPORTED_DELETED', '100' );
define( 'AVHFDAS_ADDED_BLACKLIST', '101' );
define( 'AVHFDAS_REPORTED', '102' );
define( 'AVHFDAS_ERROR_INVALID_REQUEST', '200' );
define( 'AVHFDAS_ERROR_NOT_REPORTED', '201' );
define( 'AVHFDAS_ERROR_EXISTS_IN_BLACKLIST', '202' );
define ('AVHFDAS_README_URL','http://svn.wp-plugins.org/avh-first-defense-against-spam/trunk/readme.txt');

require_once ($_dir . '/class/avh-fdas.registry.php');

if ( avh_getWordpressVersion() >= 2.7 ) {
	require_once ($_dir . '/helpers/avh-security.php');
	require_once ($_dir . '/helpers/avh-visitor.php');

	$Classes = AVH_FDAS_Classes::getInstance();
	$Classes->setDir( $_dir );
	$Classes->setClassFilePrefix( 'avh-fdas.' );
	$Classes->setClassNamePrefix( 'AVH_FDAS_' );
	unset( $Classes );

	$Settings = AVH_FDAS_Settings::getInstance();
	$Settings->storeSetting( 'plugin_dir', $_dir );
	$Settings->storeSetting( 'basename', $_basename );

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