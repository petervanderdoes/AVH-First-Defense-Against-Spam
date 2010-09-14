<?php
/*
Plugin Name: AVH First Defense Against Spam
Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
Description: This plugin gives you the ability to block spammers before content is served.
Version: 2.0-dev31
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
$_basename = plugin_basename( __FILE__ );
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
define( 'AVHFDAS_README_URL', 'http://svn.wp-plugins.org/avh-first-defense-against-spam/trunk/readme.txt' );
define( 'AVHFDAS_FILE', 'avh-first-defense-against-spam/avh-fdas.php' );

require_once ($_dir . '/class/avh-fdas.registry.php');

if ( AVH_Common::getWordpressVersion() >= 2.8 ) {
	require_once ($_dir . '/helpers/avh-security.php');
	require_once ($_dir . '/helpers/avh-visitor.php');

	$_classes = AVH_FDAS_Classes::getInstance();
	$_classes->setDir( $_dir );
	$_classes->setClassFilePrefix( 'avh-fdas.' );
	$_classes->setClassNamePrefix( 'AVH_FDAS_' );
	unset( $_classes );

	$_settings = AVH_FDAS_Settings::getInstance();
	$_settings->storeSetting( 'plugin_dir', $_dir );
	$_settings->storeSetting( 'plugin_basename', $_basename );

	require ($_dir . '/avh-fdas.client.php');
} else {
	add_action( 'activate_' . AVHFDAS_FILE, 'avh_fdas_remove_plugin' );

}

function avh_fdas_remove_plugin ()
{
	$active_plugins = ( array ) get_option( 'active_plugins' );

	// workaround for WPMU deactivation bug
	remove_action( 'deactivate_' . AVHFDAS_FILE, 'deactivate_sitewide_plugin' );

	$key = array_search( AVHFDAS_FILE, $active_plugins );

	if ( $key !== false ) {
		do_action( 'deactivate_plugin', AVHFDAS_FILE );

		array_splice( $active_plugins, $key, 1 );

		do_action( 'deactivate_' . AVHFDAS_FILE );
		do_action( 'deactivated_plugin', AVHFDAS_FILE );

		update_option( 'active_plugins', $active_plugins );

	} else {
		do_action( 'deactivate_' . AVHFDAS_FILE );
	}

	ob_end_clean();
	wp_die( __( 'AVH First Defense Against Spam can\'t work with this WordPress version!', 'avhfdas' ) );
}
?>