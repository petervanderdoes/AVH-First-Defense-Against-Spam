<?php
if ( ! defined('AVH_FRAMEWORK')) {
	die('You are not allowed to call this page directly.');
}

final class AVH_FDAS_Define {
	const ADDED_BLACKLIST           = '101';
	const ERROR_EXISTS_IN_BLACKLIST = '202';
	const ERROR_INVALID_REQUEST     = '200';
	const ERROR_NOT_REPORTED        = '201';
	/**
	 * Plugin Specfic Constants
	 */
	const MENU_SLUG           = 'avh-first-defense-against-spam';
	const MENU_SLUG_3RD_PARTY = 'avh-first-defense-against-3rd-party';
	const MENU_SLUG_FAQ       = 'avh-first-defense-against-spam-faq';
	const MENU_SLUG_GENERAL   = 'avh-first-defense-against-spam-general';
	const MENU_SLUG_IP_CACHE  = 'avh-first-defense-against-spam-ip-cache-log';
	const MENU_SLUG_OVERVIEW  = 'avh-first-defense-against-spam';
	// URL For Stop Forum API
	const PLUGIN_FILE = 'avh-first-defense-against-spam/avh-fdas.php';
	// Menu Slugs for Admin menu
	const PLUGIN_PATH       = 'avh-first-defense-against-spam';
	const PLUGIN_README_URL = 'http://svn.wp-plugins.org/avh-first-defense-against-spam/trunk/readme.txt';
	/**
	 * General Constants
	 */
	const PLUGIN_VERSION         = '3.7.2-dev.1';
	const REPORTED               = '102';
	const REPORTED_DELETED       = '100';
	const STOPFORUMSPAM_ENDPOINT = 'http://api.stopforumspam.org/api';
}
