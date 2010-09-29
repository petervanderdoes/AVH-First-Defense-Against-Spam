<?php
if ( ! defined( 'AVH_FRAMEWORK' ) ) die( 'You are not allowed to call this page directly.' );

if ( ! class_exists( 'AVH_Visitor' ) ) {
	final class AVH_Visitor
	{

		/**
		 * Get the user's IP
		 *
		 * @return string
		 */
		public static function getUserIP ( $single = TRUE )
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
}