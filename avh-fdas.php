<?php
/**
 * Plugin Name: AVH First Defense Against Spam Plugin
 * URI: http://blog.avirtualhome.com/wordpress-plugins
 * Description: This plugin gives you the ability to block spammers before content is served.
 * Version: 3.7.1
 * Author: Peter van der Does
 * Author URI: http://blog.avirtualhome.com/
 *
 * License: GPL v3
 * Copyright (C) 2009-2013, Peter van der Does - peter@avirtualhome.com
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('AVH_FRAMEWORK')) {
    define('AVH_FRAMEWORK', true);
}
$_dir = dirname(__FILE__);
$_basename = plugin_basename(__FILE__);
require_once($_dir . '/libs/avh-registry.php');
require_once($_dir . '/libs/avh-common.php');
require_once($_dir . '/libs/avh-security.php');
require_once($_dir . '/libs/avh-visitor.php');
require_once($_dir . '/class/avh-fdas.registry.php');
require_once($_dir . '/class/avh-fdas.define.php');

if (AVH_Common::getWordpressVersion() >= 2.8) {
    $_classes = AVH_FDAS_Classes::getInstance();
    $_classes->setDir($_dir);
    $_classes->setClassFilePrefix('avh-fdas.');
    $_classes->setClassNamePrefix('AVH_FDAS_');
    unset($_classes);

    $_settings = AVH_FDAS_Settings::getInstance();
    $_settings->storeSetting('plugin_dir', $_dir);
    $_settings->storeSetting('plugin_basename', $_basename);
    require($_dir . '/avh-fdas.client.php');
} else {
    add_action('activate_' . AVH_FDAS_Define::PLUGIN_FILE, 'avh_fdas_remove_plugin');
}

function avh_fdas_remove_plugin()
{
    $active_plugins = (array) get_option('active_plugins');

    // workaround for WPMU deactivation bug
    remove_action('deactivate_' . AVH_FDAS_Define::PLUGIN_FILE, 'deactivate_sitewide_plugin');
    $key = array_search(AVH_FDAS_Define::PLUGIN_FILE, $active_plugins);

    if ($key !== false) {
        do_action('deactivate_plugin', AVH_FDAS_Define::PLUGIN_FILE);
        array_splice($active_plugins, $key, 1);
        do_action('deactivate_' . AVH_FDAS_Define::PLUGIN_FILE);
        do_action('deactivated_plugin', AVH_FDAS_Define::PLUGIN_FILE);
        update_option('active_plugins', $active_plugins);
    } else {
        do_action('deactivate_' . AVH_FDAS_Define::PLUGIN_FILE);
    }
    ob_end_clean();
    wp_die('AVH First Defense Against Spam ' . __('can\'t work with this WordPress version!', 'avh-fdas'));
}
