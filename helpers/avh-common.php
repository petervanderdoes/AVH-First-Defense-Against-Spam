<?php
if ( ! defined( 'AVH_FRAMEWORK' ) )
	die( 'You are not allowed to call this page directly.' );

if ( ! class_exists( 'avh_Registry' ) ) {
	/**
	 * Class registry
	 *
	 */
	final class avh_Registry
	{

		/**
		 * Our array of objects
		 * @access private
		 * @var array
		 */
		private static $_objects = array ();

		/**
		 * Our array of settings
		 * @access private
		 */
		private static $settings = array ();

		private static $_dir;
		private static $_class_prefix = array ();
		private static $_class_name_prefix = array ();

		/**
		 * The instance of the registry
		 * @access private
		 */
		private static $_instance;

		//prevent directly access.
		private function __construct ()
		{
		}

		//prevent clone.
		public function __clone ()
		{
		}

		/**
		 * Singleton method to access the Registry
		 * @access public
		 */
		public static function singleton ()
		{
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Loads a class
		 *
		 * @param unknown_type $class
		 * @param unknown_type $type
		 */
		public function &load_class ( $class, $type = 'system' )
		{
			if ( isset( self::$_objects[$class] ) ) {
				return (self::$_objects[$class]);
			}

			switch ( $type )
			{
				case 'plugin' :
					$in = '/class';
					$file = self::$_class_prefix[$class] . $class . '.php';
					break;
				case 'system' :
				default :
					$in = '/libs';
					$file = 'avh-' . $class . '.php';
			}
			require_once self::$_dir . $in . '/' . $file;
			$name = ('system' == $type) ? 'AVH_' . $class : self::$_class_prefix[$class] . $class;
			self::$_objects[$class] = & self::instantiate_class( new $name() );
		}

		public function storeSetting ( $key, $data )
		{
			self::$settings[$key] = $data;
		}

		public function getSetting ( $key )
		{
			return self::$settings[$key];
		}

		/**
		 * Instantiate Class
		 *
		 * Returns a new class object by reference, used by load_class() and the DB class.
		 * Required to make PHP 5.3 cry.
		 *
		 * Use: $obj =& instantiate_class(new Foo());
		 *
		 * @access	public
		 * @param	object
		 * @return	object
		 */
		protected function &instantiate_class ( &$class_object )
		{
			return $class_object;
		}

		/**
		 * @param $dir the $dir to set
		 */
		public function setDir ( $dir )
		{
			self::$_dir = $dir;
		}

		/**
		 * @param $class Unique Identifier
		 * @param $class_prefix the $class_prefix to set
		 */
		public function setClass_prefix ( $class, $class_prefix )
		{
			self::$_class_prefix[$class] = $class_prefix;
		}

		/**
		 * @param $class Unique Identifier
		 * @param $class_name_prefix the $class_name_prefix to set
		 */
		public function setClass_name_prefix ( $class, $class_name_prefix )
		{
			self::$_class_name_prefix[$class] = $class_name_prefix;
		}

	}
}
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
		static $_version = NULL;

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
		static $_is_php = NULL;
		$version = ( string ) $version;

		if ( ! isset( $_is_php[$version] ) ) {
			$_is_php[$version] = (version_compare( PHP_VERSION, $version ) < 0) ? FALSE : TRUE;
		}

		return $_is_php[$version];
	}
}