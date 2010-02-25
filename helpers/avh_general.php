<?php

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
if ( ! function_exists( 'avh_getUserIP' ) ) {

	/**
	 * Get the user's IP
	 *
	 * @return string
	 */
	function avh_getUserIP ( $single = TRUE )
	{
		$ip = array ();

		if ( isset( $_SERVER ) ) {
			$ip[] = $_SERVER['REMOTE_ADDR'];

			if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				$ip = array_merge( $ip, explode( ',', $_SERVER['HTTP_CLIENT_IP'] ) );
			}

			if ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				$ip = array_merge( $ip, explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			}

			if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
				$ip = array_merge( $ip, explode( ',', $_SERVER['HTTP_X_REAL_IP'] ) );
			}
		} else {
			$ip[] = getenv( 'REMOTE_ADDR' );
			if ( getenv( 'HTTP_CLIENT_IP' ) ) {
				$ip = array_merge( $ip, explode( ',', getenv( 'HTTP_CLIENT_IP' ) ) );
			}

			if ( getenv( 'HTTP_X_FORWARDED_FOR' ) ) {
				$ip = array_merge( $ip, explode( ',', getenv( 'HTTP_X_FORWARDED_FOR' ) ) );
			}

			if ( getenv( 'HTTP_X_REAL_IP' ) ) {
				$ip = array_merge( $ip, explode( ',', getenv( 'HTTP_X_REAL_IP' ) ) );
			}
		}

		$dec_octet = '(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|[0-9])';
		$ip4_address = $dec_octet . '.' . $dec_octet . '.' . $dec_octet . '.' . $dec_octet;

		// Remove any non-IP stuff
		$x = count( $ip );
		$match = array ();
		for ( $i = 0; $i < $x; $i ++ ) {
			if ( preg_match( '/^' . $ip4_address . '$/', $ip[$i], $match ) ) {
				$ip[$i] = $match[0];
			} else {
				$ip[$i] = '';
			}
			if ( empty( $ip[$i] ) ) {
				unset( $ip[$i] );
			}
		}

		$ip = array_values( array_unique( $ip ) );
		if ( ! $ip[0] ) {
			$ip[0] = '0.0.0.0'; // for some strange reason we don't have a IP
		}
		$return = $ip[0];
		if ( ! $single ) {
			$return = join( ',', $ip );
		} else {

			// decide which IP to use, trying to avoid local addresses
			$ip = array_reverse( $ip );
			foreach ( $ip as $i ) {
				if ( preg_match( '/^(127\.|10\.|192\.168\.|172\.((1[6-9])|(2[0-9])|(3[0-1]))\.)/', $i ) ) {
					continue;
				} else {
					$return = $i;
					break;
				}
			}
		}
		return $return;
	}
}
if ( ! function_exists( 'avh_create_nonce' ) ) {

	/**
	 * Local nonce creation. WordPress uses the UID and sometimes I don't want that
	 * Creates a random, one time use token.
	 *
	 * @param string|int $action Scalar value to add context to the nonce.
	 * @return string The one use form token
	 *
	 */
	function avh_create_nonce ( $action = -1 )
	{
		$i = wp_nonce_tick();
		return substr( wp_hash( $i . $action, 'nonce' ), - 12, 10 );
	}
}

if ( ! function_exists( 'avh_verify_nonce' ) ) {

	/**
	 * Local nonce verification. WordPress uses the UID and sometimes I don't want that
	 * Verify that correct nonce was used with time limit.
	 *
	 * The user is given an amount of time to use the token, so therefore, since the
	 * $action remain the same, the independent variable is the time.
	 *
	 * @param string $nonce Nonce that was used in the form to verify
	 * @param string|int $action Should give context to what is taking place and be the same when nonce was created.
	 * @return bool Whether the nonce check passed or failed.
	 */
	function avh_verify_nonce ( $nonce, $action = -1 )
	{

		$r = false;
		$i = wp_nonce_tick();
		// Nonce generated 0-12 hours ago
		if ( substr( wp_hash( $i . $action, 'nonce' ), - 12, 10 ) == $nonce ) {
			$r = 1;
		} elseif ( substr( wp_hash( ($i - 1) . $action, 'nonce' ), - 12, 10 ) == $nonce ) { // Nonce generated 12-24 hours ago
			$r = 2;
		}
		return $r;
	}
}