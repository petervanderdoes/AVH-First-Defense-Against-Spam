<?php
if ( ! defined( 'AVH_FRAMEWORK' ) ) die( 'You are not allowed to call this page directly.' );

class AVH_FDAS_Public
{
	/**
	 *
	 * @var AVH_FDAS_Core
	 */
	var $core;

	/**
	 * @var AVH_Settings_Registry
	 */
	private $_settings;

	/**
	 * @var AVH_Class_registry
	 */
	private $_classes;

	/**
	 * @var AVH_FDAS_SpamCheck
	 */
	private $_spamcheck;

	private $_core_options;

	/**
	 * PHP5 Constructor
	 *
	 */
	function __construct ()
	{
		// Get The Registry
		$this->_settings = AVH_FDAS_Settings::getInstance();
		$this->_classes = AVH_FDAS_Classes::getInstance();

		// Initialize the plugin
		$this->core = $this->_classes->load_class( 'Core', 'plugin', TRUE );
		$this->_spamcheck = $this->_classes->load_class( 'SpamCheck', 'plugin', TRUE );
		$this->_core_options = $this->core->getOptions();

		// Public actions and filters
		add_action( 'get_header', array (&$this, 'actionHandleMainAction' ) );
		add_action( 'preprocess_comment', array (&$this, 'actionHandlePostingComment' ), 1 );
		add_action( 'comment_form', array (&$this, 'actionAddNonceFieldToComment' ) );
		add_filter( 'preprocess_comment', array (&$this, 'filterCheckNonceFieldToComment' ), 1 );
		add_action( 'register_post', array (&$this, 'actionHandleRegistration' ), 10, 3 );

		// Private actions for Cron
		add_action( 'avhfdas_clean_nonce', array (&$this, 'actionHandleCronCleanNonce' ) );
		add_action( 'avhfdas_clean_ipcache', array (&$this, 'actionHandleCronCleanIPCache' ) );

		add_action( 'avhfdas_checkspam_ip_header', array (&$this->_spamcheck, 'doIPCacheCheck' ) );
		add_action( 'avhfdas_checkspam_ip_header', array (&$this->_spamcheck, 'doProjectHoneyPotIPCheck' ) );
	}

	/**
	 * Add a nonce field to the comments.
	 *
	 * @WordPress Action - comment_form
	 *
	 */
	function actionAddNonceFieldToComment ()
	{
		$post_id = null;
		$post = get_post( $post_id );
		$post_id = 0;
		if ( is_object( $post ) ) {
			$post_id = $post->ID;
		}
		echo $this->core->getComment();
		wp_nonce_field( 'avh-first-defense-against-spam_' . $post_id, '_avh_first_defense_against_spam', false );
	}

	/**
	 * Clean up the nonce DB by using Cron
	 *
	 * @WordPress: Action avhfdas_clean_nonce
	 *
	 */
	function actionHandleCronCleanNonce ()
	{
		$removed = 0;
		$options = $this->core->getOptions();
		$all = get_option( $this->core->db_nonces );
		if ( is_array( $all ) ) {
			foreach ( $all as $key => $value ) {
				if ( ! AVH_Security::verifyNonce( $key, $value ) ) {
					unset( $all[$key] );
					$removed ++;
				}
			}
			update_option( $this->core->db_nonces, $all );
		}

		if ( $options['general']['cron_nonces_email'] ) {
			$to = get_option( 'admin_email' );
			$subject = sprintf( '[%s] AVH First Defense Against Spam - Cron - ' . __( 'Clean nonces', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
			$message[] = sprintf( __( 'Deleted %d nonce\'s from the database', 'avhfdas' ), $removed );
			AVH_Common::sendMail( $to, $subject, $message, $this->_settings->getSetting( 'mail_footer' ) );
		}
	}

	/**
	 * Cleans the IP cache table
	 *
	 * @WordPress: Action avhfdas_clean_ipcache
	 *
	 */
	function actionHandleCronCleanIPCache ()
	{
		global $wpdb;
		$options = $this->core->getOptions();
		$date = current_time( 'mysql' );
		$days = $options['ipcache']['daystokeep'];
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->avhfdasipcache WHERE ((TO_DAYS(%s))-(TO_DAYS(added))) > %d", $date, $days ) );

		if ( $options['general']['cron_ipcache_email'] ) {
			$to = get_option( 'admin_email' );
			$subject = sprintf( '[%s] AVH First Defense Against Spam - Cron - ' . __( 'Clean IP cache', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
			$message[] = sprintf( __( 'Deleted %d IP\'s from the cache', 'avhfdas' ), $result );
			AVH_Common::sendMail( $to, $subject, $message, $this->_settings->getSetting( 'mail_footer' ) );
		}
	}

	/**
	 * Check the nonce field set with a comment.
	 *
	 * @WordPress Filter preprocess_comment
	 * @param mixed $commentdata
	 * @return mixed
	 * @since 1.2
	 *
	 */
	function filterCheckNonceFieldToComment ( $commentdata )
	{
		// When we're in Admin no need to check the nonce.
		if ( ! defined( 'WP_ADMIN' ) ) {
			if ( empty( $commentdata['comment_type'] ) ) { // If it's a trackback or pingback this has a value
				$nonce = wp_create_nonce( 'avh-first-defense-against-spam_' . $commentdata['comment_post_ID'] );
				if ( $nonce != $_POST['_avh_first_defense_against_spam'] ) {
					if ( 1 == $this->core->getOptionElement('general','emailsecuritycheck') ) {
						$to = get_option( 'admin_email' );
						$ip = AVH_Visitor::getUserIP();
						$commentdata['comment_author_email'] = empty( $commentdata['comment_author_email'] ) ? 'meseaffibia@gmail.com' : $commentdata['comment_author_email'];
						$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Comment security check failed', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
						if ( isset( $_POST['_avh_first_defense_against_spam'] ) ) {
							$message[] = __( 'Reason:	The nonce check failed.', 'avhfdas' );
						} else {
							$message[] = __( 'Reason:	An attempt was made to directly access wp-comment-post.php', 'avhfdas' );
						}
						$message[] = sprintf( __( 'Username:	%s', 'avhfdas' ), $commentdata['comment_author'] );
						$message[] = sprintf( __( 'Email:		%s', 'avhfdas' ), $commentdata['comment_author_email'] );
						$message[] = sprintf( __( 'IP:		%s', 'avhfdas' ), $ip );
						$message[] = '';
						$message[] = __( 'Comment trying to post:', 'avhfdas' );
						$message[] = __( '--- START OF COMMENT ---', 'avhfdas' );
						$message[] = $commentdata['comment_content'];
						$message[] = __( '--- END OF COMMENT ---', 'avhfdas' );
						$message[] = '';
						if ( ! empty( $this->core->getOptionElement('sfs','sfsapikey') ) ) {
							$q['action'] = 'emailreportspammer';
							$q['a'] = $commentdata['comment_author'];
							$q['e'] = $commentdata['comment_author_email'];
							$q['i'] = $ip;
							$q['_avhnonce'] = AVH_Security::createNonce( $q['a'] . $q['e'] . $q['i'] );
							$query = $this->core->BuildQuery( $q );
							$report_url = admin_url( 'admin.php?' . $query );
							$message[] = sprintf( __( 'Report spammer: %s' ), $report_url );
						}
						$message[] = sprintf( __( 'For more information: http://www.stopforumspam.com/search?q=%s' ), $ip );

						$blacklisturl = admin_url( 'admin.php?action=blacklist&i=' ) . $ip . '&_avhnonce=' . AVH_Security::createNonce( $ip );
						$message[] = sprintf( __( 'Add to the local blacklist: %s' ), $blacklisturl );

						AVH_Common::sendMail( $to, $subject, $message, $this->_settings->getSetting( 'mail_footer' ) );

					}
					// Only keep track if we have the ability to report add Stop Forum Spam
					if ( ! empty( $this->core->getOptionElement('sfs','sfsapikey') ) ) {
						// Prevent a spam attack to overflow the database.
						if ( ! ($this->checkDB_Nonces( $q['_avhnonce'] )) ) {
							$option = get_option( $this->core->db_nonces );
							$option[$q['_avhnonce']] = $q['a'] . $q['e'] . $q['i'];
							update_option( $this->core->db_nonces, $option );
						}
					}
					$m = __( '<p>Cheating huh</p>', 'avhfdas' );
					$m .= __( '<p>Protected by: AVH First Defense Against Spam</p>', 'avhfdas' );

					if ( $this->core->getOptionElement('php','usehoneypot') ) {
						$m .= '<p>' . $this->core->getOptionElement('php','honeypoturl') . '</p>';
					}
					wp_die( $m );
				}
			}
			$this->actionHandleMainAction();
		}
		return $commentdata;
	}

	/**
	 * Checks if the spammer is in our database.
	 *
	 * @param string $nonce
	 * @return boolean
	 */
	function checkDB_Nonces ( $nonce )
	{
		$return = false;
		$all = get_option( $this->core->db_nonces );
		if ( is_array( $all ) ) {
			if ( array_key_exists( $nonce, $all ) ) {
				$return = true;
			}
		}
		return ($return);
	}

	/**
	 * Handle the main action.
	 *
	 * Checks before content is served
	 * Don't use SFS as it overloads their site.
	 *
	 * @uses PHP
	 * @WordPress Action get_header
	 */
	function actionHandleMainAction ()
	{
		// To be safe, set the spammer_detected to false;
		$this->_spamcheck->spammer_detected = FALSE;
		$this->_spamcheck->checkWhitelist();
		if ( $this->_spamcheck->ip_in_white_list === FALSE ) {
			$this->_spamcheck->checkBlacklist();
			if ( $this->_spamcheck->spammer_detected === FALSE ) {
				$this->_spamcheck->doIPCacheCheck();
				if ( $this->_spamcheck->ip_in_cache === FALSE ) {
					$this->_spamcheck->doProjectHoneyPotIPCheck();
				}
			}
			$this->_spamcheck->handleResults();
		}
	}

	/**
	 * Handle when posting a comment.
	 *
	 * @uses SFS, PHP
	 * @WordPress Action preprocess_comment
	 */
	function actionHandlePostingComment ( $commentdata )
	{
		// To be safe, set the spammer_detected to false;
		$this->_spamcheck->spammer_detected = FALSE;
		$this->_spamcheck->checkWhitelist();
		if ( $this->_spamcheck->ip_in_white_list === FALSE ) {
			$this->_spamcheck->checkBlacklist();
			if ( $this->_spamcheck->spammer_detected === FALSE ) {
				$this->_spamcheck->doIPCacheCheck();
				if ( $this->_spamcheck->ip_in_cache === FALSE ) {
					$this->_spamcheck->doStopForumSpamIPCheck();
					$this->_spamcheck->doProjectHoneyPotIPCheck();
				}
			}
			$this->_spamcheck->handleResults();
		}
		return ($commentdata);
	}

	/**
	 * Handle when a user registers
	 *
	 * @WordPress Action register_post
	 */
	function actionHandleRegistration ( $sanitized_user_login, $user_email, $errors )
	{
		// To be safe, set the spammer_detected to false;
		$this->_spamcheck->spammer_detected = FALSE;
		$this->_spamcheck->checkWhitelist();
		if ( $this->_spamcheck->ip_in_white_list === FALSE ) {
			$this->_spamcheck->checkBlacklist();
			if ( $this->_spamcheck->spammer_detected === FALSE ) {
				$this->_spamcheck->doIPCacheCheck();
				if ( $this->_spamcheck->ip_in_cache === FALSE ) {
					$this->_spamcheck->doStopForumSpamIPCheck();
					$this->_spamcheck->doProjectHoneyPotIPCheck();
				}
			}
			$this->_spamcheck->handleResults();
		}
	}
}
?>