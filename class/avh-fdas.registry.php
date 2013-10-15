<?php

/**
 * Create seperate Settings Registry for the plugin.
 *
 * @author pdoes
 *
 */
final class AVH_FDAS_Settings extends AVH_Settings_Registry
{

	// prevent directly access.
	public function __construct ()
	{}

	// prevent clone.
	public function __clone ()
	{}

	/**
	 * The instance of the registry
	 *
	 * @access private
	 */
	private static $_instance = null;

	/**
	 * Singleton method to access the Registry
	 *
	 * @access public
	 */
	public static function getInstance ()
	{
		if (self::$_instance === null) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
}

/**
 * Create seperate Classes registry for the plugin
 *
 * @author pdoes
 *
 */
final class AVH_FDAS_Classes extends AVH_Class_Registry
{

	// prevent directly access.
	public function __construct ()
	{}

	// prevent clone.
	public function __clone ()
	{}

	/**
	 * The instance of the registry
	 *
	 * @access private
	 */
	private static $_instance = null;

	/**
	 * Singleton method to access the Registry
	 *
	 * @access public
	 */
	public static function getInstance ()
	{
		if (self::$_instance === null) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}
}