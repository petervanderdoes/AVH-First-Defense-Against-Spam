<?php
if (! defined('AVH_FRAMEWORK'))
	die('You are not allowed to call this page directly.');

class AVH_FDAS_Public
{

	/**
	 *
	 * @var AVH_FDAS_Core
	 */
	private $_core;

	/**
	 *
	 * @var AVH_Settings_Registry
	 */
	private $_settings;

	/**
	 *
	 * @var AVH_Class_registry
	 */
	private $_classes;

	/**
	 *
	 * @var AVH_FDAS_SpamCheck
	 */
	private $_spamcheck;
	private $_core_options;

	/**
	 * PHP5 Constructor
	 */
	public function __construct ()
	{
		add_action('init', array ( $this, 'handleInitializePlugin' ), 10);
	}

	public function handleInitializePlugin ()
	{
		// Get The Registry
		$this->_settings = AVH_FDAS_Settings::getInstance();
		$this->_classes = AVH_FDAS_Classes::getInstance();

		// Initialize the plugin
		$this->_core = $this->_classes->load_class('Core', 'plugin', true);
		$this->_spamcheck = $this->_classes->load_class('SpamCheck', 'plugin', true);
		$this->_core_options = $this->_core->getOptions();

		// Public actions and filters
		if (1 == $this->_core_options['general']['commentnonce']) {
			add_action('comment_form', array ( $this, 'actionAddNonceFieldToComment' ));
			add_filter('preprocess_comment', array ( $this, 'filterCheckNonceFieldToComment' ), 1);
		}
		add_action('get_header', array ( $this, 'handleActionGetHeader' ));

		add_action('pre_comment_on_post', array ( $this, 'handleActionPreCommentOnPost' ), 1);
		add_filter('registration_errors', array ( $this, 'handleFilterRegistrationErrors' ), 10, 3);
		add_filter('wpmu_validate_user_signup', array ( $this, 'handleFilterWPMUValidateUserSignup' ), 1);

		if ($this->_core_options['php']['usehoneypot']) {
			add_action('comment_form', array ( $this, 'handleActionDisplayHoneypotUrl' ));
			add_action('login_footer', array ( $this, 'handleActionDisplayHoneypotUrl' ));
		}
		// Private actions for Cron
		add_action('avhfdas_clean_nonce', array ( $this, 'actionHandleCronCleanNonce' ));
		add_action('avhfdas_clean_ipcache', array ( $this, 'actionHandleCronCleanIpCache' ));

		/**
		 * Hook in registration process for Events Manager
		 */
		if (defined('EM_VERSION')) {
			add_filter('em_registration_errors', array ( $this, 'handleFilterRegistrationErrors' ), 10, 3);
		}
	}

	/**
	 * Add a nonce field to the comments.
	 *
	 * @WordPress Action - comment_form
	 */
	public function actionAddNonceFieldToComment ($post_id)
	{
		echo $this->_core->getComment();
		wp_nonce_field('avh-first-defense-against-spam_' . $post_id, '_avh_first_defense_against_spam', false);
	}

	/**
	 * Clean up the nonce DB by using Cron
	 *
	 * @WordPress: Action avhfdas_clean_nonce
	 */
	public function actionHandleCronCleanNonce ()
	{
		$removed = 0;
		$options = $this->_core->getOptions();
		$all = get_option($this->_core->getDbNonces());
		if (is_array($all)) {
			foreach ($all as $key => $value) {
				if (! (false === AVH_Security::verifyNonce($key, $value))) {
					unset($all[$key]);
					$removed ++;
				}
			}
			update_option($this->_core->getDbNonces(), $all);
		}
		if ($options['general']['cron_nonces_email']) {
			$to = get_option('admin_email');
			$subject = sprintf('[%s] AVH First Defense Against Spam - Cron - ' . __('Clean nonces', 'avh-fdas'), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));
			$message[] = sprintf(__('Deleted %d nonce\'s from the database', 'avh-fdas'), $removed);
			AVH_Common::sendMail($to, $subject, $message, $this->_settings->getSetting('mail_footer'));
		}
	}

	/**
	 * Cleans the IP cache table
	 *
	 * @WordPress: Action avhfdas_clean_ipcache
	 */
	public function actionHandleCronCleanIpCache ()
	{
		global $wpdb;
		$options = $this->_core->getOptions();
		$date = current_time('mysql');
		$days = $options['ipcache']['daystokeep'];
		$result = $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->avhfdasipcache WHERE ((TO_DAYS(%s))-(TO_DAYS(added))) > %d", $date, $days));
		if ($options['general']['cron_ipcache_email']) {
			$to = get_option('admin_email');
			$subject = sprintf('[%s] AVH First Defense Against Spam - Cron - ' . __('Clean IP cache', 'avh-fdas'), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));
			$message[] = sprintf(__('Deleted %d IP\'s from the cache', 'avh-fdas'), $result);
			AVH_Common::sendMail($to, $subject, $message, $this->_settings->getSetting('mail_footer'));
		}
	}

	/**
	 * Check the nonce field set with a comment.
	 *
	 * @WordPress Filter preprocess_comment
	 *
	 * @param mixed $commentdata
	 * @return mixed
	 * @since 1.2
	 *
	 */
	public function filterCheckNonceFieldToComment ($commentdata)
	{
		// When we're in Admin no need to check the nonce.
		if ((! defined('WP_ADMIN')) && (! defined('XMLRPC_REQUEST'))) {
			if (empty($commentdata['comment_type'])) { // If it's a trackback or pingback this has a value
				$nonce = wp_create_nonce('avh-first-defense-against-spam_' . $commentdata['comment_post_ID']);
				if (! wp_verify_nonce($_POST['_avh_first_defense_against_spam'], 'avh-first-defense-against-spam_' . $commentdata['comment_post_ID'])) {
					if (1 == $this->_core->getOptionElement('general', 'emailsecuritycheck')) {
						$to = get_option('admin_email');
						$ip = AVH_Visitor::getUserIp();
						$sfs_apikey = $this->_core->getOptionElement('sfs', 'sfsapikey');
						$commentdata['comment_author_email'] = empty($commentdata['comment_author_email']) ? 'meseaffibia@gmail.com' : $commentdata['comment_author_email'];
						$subject = sprintf('[%s] AVH First Defense Against Spam - ' . __('Comment security check failed', 'avh-fdas'), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));
						if (isset($_POST['_avh_first_defense_against_spam'])) {
							$message[] = __('Reason:	The nonce check failed.', 'avh-fdas');
						} else {
							$message[] = __('Reason:	An attempt was made to directly access wp-comment-post.php', 'avh-fdas');
						}
						$message[] = sprintf(__('Username:	%s', 'avh-fdas'), $commentdata['comment_author']);
						$message[] = sprintf(__('Email:		%s', 'avh-fdas'), $commentdata['comment_author_email']);
						$message[] = sprintf(__('IP:		%s', 'avh-fdas'), $ip);
						$message[] = '';
						$message[] = __('Comment trying to post:', 'avh-fdas');
						$message[] = __('--- START OF COMMENT ---', 'avh-fdas');
						$message[] = $commentdata['comment_content'];
						$message[] = __('--- END OF COMMENT ---', 'avh-fdas');
						$message[] = '';
						if ('' != $sfs_apikey && (! empty($commentdata['comment_author_email']))) {
							$q['action'] = 'emailreportspammer';
							$q['a'] = $commentdata['comment_author'];
							$q['e'] = $commentdata['comment_author_email'];
							$q['i'] = $ip;
							$q['_avhnonce'] = AVH_Security::createNonce($q['a'] . $q['e'] . $q['i']);
							$query = $this->_core->BuildQuery($q);
							$report_url = admin_url('admin.php?' . $query);
							$message[] = sprintf(__('Report spammer: %s'), $report_url);
						}
						$message[] = sprintf(__('For more information: http://www.stopforumspam.com/search?q=%s'), $ip);
						$blacklisturl = admin_url('admin.php?action=blacklist&i=') . $ip . '&_avhnonce=' . AVH_Security::createNonce($ip);
						$message[] = sprintf(__('Add to the local blacklist: %s'), $blacklisturl);
						AVH_Common::sendMail($to, $subject, $message, $this->_settings->getSetting('mail_footer'));
					}
					// Only keep track if we have the ability to report add Stop Forum Spam
					if ('' != $sfs_apikey && (! empty($commentdata['comment_author_email']))) {
						// Prevent a spam attack to overflow the database.
						if (! ($this->_checkDbNonces($q['_avhnonce']))) {
							$option = get_option($this->_core->getDbNonces());
							$option[$q['_avhnonce']] = $q['a'] . $q['e'] . $q['i'];
							update_option($this->_core->getDbNonces(), $option);
						}
					}
					$m = __('<p>Cheating huh</p>', 'avh-fdas');
					$m .= __('<p>Protected by: AVH First Defense Against Spam</p>', 'avh-fdas');
					if ($this->_core->getOptionElement('php', 'usehoneypot')) {
						$m .= $this->_spamcheck->getHtmlHoneyPotUrl();
					}
					wp_die($m);
				}
			}
		}
		return $commentdata;
	}

	/**
	 * Checks if the spammer is in our database.
	 *
	 * @param string $nonce
	 * @return boolean
	 */
	private function _checkDbNonces ($nonce)
	{
		$return = false;
		$all = get_option($this->_core->getDbNonces());
		if (is_array($all)) {
			if (array_key_exists($nonce, $all)) {
				$return = true;
			}
		}
		return ($return);
	}

	/**
	 * Check during the action get_header
	 */
	public function handleActionGetHeader ()
	{
		if (! (did_action('pre_comment_on_post'))) {
			$this->_spamcheck->setVisiting_email('');
			$this->_spamcheck->doSpamcheckMain();
		}
	}

	/**
	 * Handle before the comment is processed.
	 *
	 * @param int $comment_id
	 */
	public function handleActionPreCommentOnPost ($comment_id)
	{
		$email = isset($_POST['email']) ? $_POST['email'] : '';
		$this->_spamcheck->setVisiting_email($email);
		$this->_spamcheck->doSpamcheckPreCommentPost();
	}

	/**
	 * Handle when a user registers
	 */
	public function handleFilterRegistrationErrors ($errors, $sanitized_user_login, $user_email)
	{
		$this->_spamcheck->setVisiting_email($user_email);
		$errors = $this->_spamcheck->doSpamcheckUserRegister($errors);

		return $errors;
	}

	/**
	 * Handle before the comment is processed.
	 *
	 * @param int $comment_id
	 */
	public function handleFilterWPMUValidateUserSignup ($userInfoArray)
	{
		$email = $userInfoArray['user_email'];
		$this->_spamcheck->setVisiting_email($email);
		$userInfoArray['errors'] = $this->_spamcheck->doSpamcheckUserRegister($userInfoArray['errors']);

		return $userInfoArray;
	}

	/**
	 * Display Honeypot Url on the login form
	 */
	public function handleActionDisplayHoneypotUrl ()
	{
		echo $this->_spamcheck->getHtmlHoneyPotUrl();
	}
}