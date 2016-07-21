<?php

/**
 * Create seperate Classes registry for the plugin
 *
 * @author pdoes
 *
 */
final class AVH_FDAS_Classes extends AVH_Class_Registry {
	// prevent directly access.
	/**
	 * The instance of the registry
	 *
	 * @access private
	 */
	private static $instance = null;

	// prevent clone.

	public function __construct() {
	}

	/**
	 * Singleton method to access the Registry
	 *
	 * @access public
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __clone() {
	}
}

/**
 * Create seperate Settings Registry for the plugin.
 *
 * @author pdoes
 *
 */
final class AVH_FDAS_Settings extends AVH_Settings_Registry {
	// prevent directly access.
	/**
	 * The instance of the registry
	 *
	 * @access private
	 */
	private static $instance = null;

	// prevent clone.

	public function __construct() {
	}

	/**
	 * Singleton method to access the Registry
	 *
	 * @access public
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __clone() {
	}
}
