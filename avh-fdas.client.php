<?php
// Stop direct call
if (! defined('AVH_FRAMEWORK'))
    die('You are not allowed to call this page directly.');

/**
 * Initialize the plugin
 */
function avh_FDAS_init()
{
    $_settings = AVH_FDAS_Settings::getInstance();
    $_settings->storeSetting('plugin_working_dir', pathinfo(__FILE__, PATHINFO_DIRNAME));
    // Admin
    if (is_admin()) {
        require_once ($_settings->plugin_working_dir . '/class/avh-fdas.admin.php');
        $avhfdas_admin = new AVH_FDAS_Admin();
        // Activation Hook
        register_activation_hook(__FILE__, array(
            &$avhfdas_admin,
            'installPlugin'
        ));
        // Deactivation Hook
        register_deactivation_hook(__FILE__, array(
            &$avhfdas_admin,
            'deactivatePlugin'
        ));
    }
    require_once ($_settings->plugin_working_dir . '/class/avh-fdas.public.php');
    $avhfdas_public = new AVH_FDAS_Public();
} // End avh_FDAS__init()
add_action('plugins_loaded', 'avh_FDAS_init');