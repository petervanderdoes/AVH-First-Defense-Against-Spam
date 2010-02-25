<?php
if ( ! defined('AVH_FRAMEWORK')) die( 'You are not allowed to call this page directly.' );

if ( ! function_exists( 'avh_getBaseDirectory' ) ) {

	/**
	 * Get the base directory of a directory structure
	 *
	 * @param string $directory
	 * @return string
	 *
	 */
	function avh_getBaseDirectory ( $directory )
	{
		//get public directory structure eg "/top/second/third"
		$public_directory = dirname( $directory );
		//place each directory into array
		$directory_array = explode( '/', $public_directory );
		//get highest or top level in array of directory strings
		$public_base = max( $directory_array );
		return $public_base;
	}
}

if ( ! function_exists( 'avh_getWordpressVersion' ) ) {

	/**
	 * Returns the wordpress version
	 * Note: 2.7.x will return 2.7
	 *
	 * @return float
	 */
	function avh_getWordpressVersion ()
	{
		static $_version=NULL;

		if ( ! isset( $_version ) ) {

			// Include WordPress version
			require (ABSPATH . WPINC . '/version.php');
			$_version = ( float ) $wp_version;
		}
		return $_version;
	}

}

if ( ! function_exists( 'avh_is_php' ) ) {

	/**
	 * Determines if the current version of PHP is greater then the supplied value
	 *
	 * @param	string
	 * @return	bool
	 */
	function avh_is_php ( $version = '5.0.0' )
	{
		static $_is_php=NULL;
		$version = ( string ) $version;

		if ( ! isset( $_is_php[$version] ) ) {
			$_is_php[$version] = (version_compare( PHP_VERSION, $version ) < 0) ? FALSE : TRUE;
		}

		return $_is_php[$version];
	}
}
