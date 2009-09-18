<?php
// Stop direct call
if ( preg_match( '#' . basename( __FILE__ ) . '#', $_SERVER['PHP_SELF'] ) ) {
	die( 'You are not allowed to call this page directly.' );
}

/**
 * Singleton Class
 */
class AVH_FDAS_Singleton
{

	function &getInstance ( $class, $arg1 = null )
	{
		static $instances = array (); // array of instance names
		if ( array_key_exists( $class, $instances ) ) {
			$instance = & $instances[$class];
		} else {
			if ( ! class_exists( $class ) ) {
				switch ( $class ) {
					case 'AVH_FDAS_Core' :
						require_once (dirname( __FILE__ ) . '/class/avh-fdas.core.php');
						break;
					case 'AVH_FDAS_Admin' :
						require_once (dirname( __FILE__ ) . '/class/avh-fdas.admin.php');
						break;
					case 'AVH_FDAS_Public' :
						require_once (dirname( __FILE__ ) . '/class/avh-fdas.public.php');
						break;
					case 'AVH_FDAS_DB' :
						require_once (dirname( __FILE__ ) . '/class/avh-fdas.db.php');
						break;

				}
			}
			$instances[$class] = new $class( $arg1 );
			$instance = & $instances[$class];
		}
		return $instance;
	} // getInstance
} // singleton


/**
 * Initialize the plugin
 *
 */
function avh_FDAS_init ()
{
	// Admin
	if ( is_admin() ) {
		$avhfdas_admin = & AVH_FDAS_Singleton::getInstance( 'AVH_FDAS_Admin' );

		// Activation Hook
		register_activation_hook( __FILE__, array (& $avhfdas_admin, 'installPlugin' ) );

		// Deactivation Hook
		register_deactivation_hook( __FILE__, array (& $avhfdas_admin, 'deactivatePlugin' ) );
	}

	$avhfdas_public = & AVH_FDAS_Singleton::getInstance( 'AVH_FDAS_Public' );

} // End avh_FDAS__init()


add_action( 'plugins_loaded', 'avh_FDAS_init' );
?>