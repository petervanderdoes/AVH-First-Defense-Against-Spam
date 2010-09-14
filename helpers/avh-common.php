<?php
if ( ! defined( 'AVH_FRAMEWORK' ) ) die( 'You are not allowed to call this page directly.' );

if ( ! class_exists( 'AVH_Common' ) ) {
	final class AVH_Common
	{

		/**
		 * Sends the email
		 *
		 */
		public static function sendMail ( $to, $subject, $message )
		{
			$footer[] = '';
			$footer[] = '--';
			$footer[] = sprintf( __( 'Your blog is protected by AVH First Defense Against Spam v%s' ), $this->_settings->version );
			$footer[] = 'http://blog.avirtualhome.com/wordpress-plugins';

			$message = array_merge( $message, $footer );
			foreach ( $message as $line ) {
				$msg .= $line . "/r/n";
			}

			wp_mail( $to, $subject, $msg );
			return;
		}

		/**
	 * Returns the wordpress version
	 * Note: 2.7.x will return 2.7
	 *
	 * @return float
	 */
	public static function getWordpressVersion ()
	{
		static $_version = NULL;

		if ( ! isset( $_version ) ) {

			// Include WordPress version
			require (ABSPATH . WPINC . '/version.php');
			$_version = ( float ) $wp_version;
		}
		return $_version;
	}

		/**
	 * Determines if the current version of PHP is greater then the supplied value
	 *
	 * @param	string
	 * @return	bool
	 */
	public static function isPHP ( $version = '5.0.0' )
	{
		static $_is_php = NULL;
		$version = ( string ) $version;

		if ( ! isset( $_is_php[$version] ) ) {
			$_is_php[$version] = (version_compare( PHP_VERSION, $version ) < 0) ? FALSE : TRUE;
		}

		return $_is_php[$version];
	}

		/**
	 * Get the base directory of a directory structure
	 *
	 * @param string $directory
	 * @return string
	 *
	 */
	public static function getBaseDirectory ( $directory )
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

}