<?php
/*
Plugin Name: AVH Stop Spam
Plugin URI: http://blog.avirtualhome.com/wordpress-plugins
Description: This plugin gives you the ability to block spammers and add them to the htaccess file to deny access.
Version: 1.0
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

// Check version.
global $wp_version;

if ( ( float ) $wp_version >= 2.7 ) {
	require (dirname ( __FILE__ ) . '/avh-stopspam.client.php');
} else {
	$message = '<div class="updated fade"><p><strong>' . __ ( 'AVH Stop Spam can\'t work with this WordPress version !', 'avhstopspam' ) . '</strong></p></div>';
	add_action ( 'admin_notices', create_function ( '', "echo '$message';" ) );

}
?>