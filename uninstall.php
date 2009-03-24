<?php
// This is an include file, all normal WordPress functions will still work.
// Because the plugin is already deactivated it won't regonize any class declarations.

if ( ! defined ( 'ABSPATH' ) && ! defined ( 'WP_UNINSTALL_PLUGIN' ) ) exit ();
global $file;
if ( 'avh-first-defense-against-spam' == dirname ( $file ) ) {
	delete_option ( 'avhfdas' );
	delete_option ( 'avhfdas_data' );
}

?>