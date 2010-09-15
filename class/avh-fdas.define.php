<?php
if ( ! defined( 'AVH_FRAMEWORK' ) ) die( 'You are not allowed to call this page directly.' );

final class AVH_FDAS_Define
{
	/**
	 * General Constants
	 */
	const PLUGIN_VERSION = '2.0-dev31';

	const PLUGIN_README_URL = 'http://svn.wp-plugins.org/avh-first-defense-against-spam/trunk/readme.txt';
	const PLUGIN_FILE = 'avh-first-defense-against-spam/avh-fdas.php';

	/**
	 * Plugin Specfic Constants
	 */

	// Message Numbers
	const REPORTED_DELETED = '100';
	const ADDED_BLACKLIST = '101';
	const REPORTED = '102';
	const ERROR_INVALID_REQUEST = '200';
	const ERROR_NOT_REPORTED = '201';
	const ERROR_EXISTS_IN_BLACKLIST = '202';

	// URL For Stop Forum API
	const STOPFORUMSPAM_ENDPOINT = 'http://www.stopforumspam.com/api';
}