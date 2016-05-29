<?php
if ( ! defined('AVH_FRAMEWORK')) {
	die('You are not allowed to call this page directly.');
}
if ( ! class_exists('AVH_Settings_Registry')) {

	abstract class AVH_Settings_Registry {
		/**
		 * Our array of settings
		 *
		 * @access protected
		 */
		private $settings = array();

		/**
		 * Stores settings in the registry
		 *
		 * @param string $data
		 * @param string $key The key for the array
		 *
		 * @return void
		 */
		public function storeSetting($key, $data) {
			$this->settings[ $key ] = $data;
			$this->$key             = $data;
		}

		/**
		 * Gets a setting from the registry
		 *
		 * @param string $key The key in the array
		 *
		 * @return mixed
		 */
		public function getSetting($key) {
			return $this->settings[ $key ];
		}

		/**
		 * Removes a setting from the registry
		 *
		 * @param string $key The key for the array
		 */
		public function removeSetting($key) {
			unset($this->settings[ $key ]);
		}
	}
}
if ( ! class_exists('AVH_Class_Registry')) {

	/**
	 * Class registry
	 */
	abstract class AVH_Class_Registry {
		/**
		 * Our array of objects
		 *
		 * @access protected
		 * @var array
		 */
		private $objects = array();
		private $dir;
		private $class_file_prefix;
		private $class_name_prefix;

		/**
		 * Loads a class
		 *
		 * @param string  $class Name of the class you want to load
		 * @param string  $type  What kind of class, System, Plugin
		 * @param boolean $store Store the class in the registry
		 *
		 * @return object
		 */
		public function load_class($class, $type = 'system', $store = false) {
			if (isset($this->objects[ $class ])) {
				return ($this->objects[ $class ]);
			}
			switch ($type) {
				case 'plugin':
					$in   = '/class';
					$file = $this->class_file_prefix . $class . '.php';
					break;
				case 'system':
				default:
					$in   = '/libs';
					$file = 'avh-' . $class . '.php';
			}
			require_once($this->dir . $in . '/' . strtolower($file));
			$name   = ('system' == $type) ? 'AVH_' . $class : $this->class_name_prefix . $class;
			$object = new $name();
			if ($store) {
				$this->objects[ $class ] = $object;
			}

			return $object;
		}

		/**
		 *
		 * @param string $dir The directory to set
		 */
		public function setDir($dir) {
			$this->dir = $dir;
		}

		/**
		 *
		 * @param string $prefix The class file prefix to set
		 */
		public function setClassFilePrefix($prefix) {
			$this->class_file_prefix = $prefix;
		}

		/**
		 *
		 * @param string $prefix The class name prefix to set
		 */
		public function setClassNamePrefix($prefix) {
			$this->class_name_prefix = $prefix;
		}

		public function setClassProperties($properties) {
			$default_properties = array('type' => 'system', 'store' => false);
		}
	}
}
