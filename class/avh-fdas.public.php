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
	var $Settings;

	/**
	 * @var AVH_Class_registry
	 */
	var $Classes;

	/**
	 * @var AVH_FDAS_SpamCheck
	 */
	var $spamcheck;

	/**
	 * PHP5 Constructor
	 *
	 */
	function __construct ()
	{
		// Get The Registry
		$this->Settings = AVH_FDAS_Settings::getInstance();
		$this->Classes = AVH_FDAS_Classes::getInstance();

		// Initialize the plugin
		$this->core = $this->Classes->load_class( 'Core', 'plugin', TRUE );
		$this->spamcheck = $this->Classes->load_class( 'SpamCheck', 'plugin', TRUE );

		// Public actions and filters
		add_action( 'get_header', array (&$this, 'actionHandleMainAction' ) );
		add_action( 'preprocess_comment', array (&$this, 'actionHandlePostingComment' ), 1 );
		add_action( 'comment_form', array (&$this, 'actionAddNonceFieldToComment' ) );
		add_filter( 'preprocess_comment', array (&$this, 'filterCheckNonceFieldToComment' ), 1 );
		add_action( 'register_post', array (&$this, 'actionHandleRegistration' ), 10, 3 );

		// Private actions for Cron
		add_action( 'avhfdas_clean_nonce', array (&$this, 'actionHandleCronCleanNonce' ) );
		add_action( 'avhfdas_clean_ipcache', array (&$this, 'actionHandleCronCleanIPCache' ) );

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
		$all = get_option( $this->core->db_options_nonces );
		if ( is_array( $all ) ) {
			foreach ( $all as $key => $value ) {
				if ( ! avh_verify_nonce( $key, $value ) ) {
					unset( $all[$key] );
					$removed ++;
				}
			}
			update_option( $this->core->db_options_nonces, $all );
		}

		if ( $options['general']['cron_nonces_email'] ) {
			$to = get_option( 'admin_email' );
			$subject = sprintf( '[%s] AVH First Defense Against Spam - Cron - ' . __( 'Clean nonces', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
			$message = sprintf( __( 'Deleted %d nonce\'s from the database', 'avhfdas' ), $removed );
			$this->mail( $to, $subject, $message );
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
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->avhfdasipcache WHERE ((TO_DAYS(%s))-(TO_DAYS(lastseen))) > %d", $date, $days ) );

		if ( $options['general']['cron_ipcache_email'] ) {
			$to = get_option( 'admin_email' );
			$subject = sprintf( '[%s] AVH First Defense Against Spam - Cron - ' . __( 'Clean IP cache', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
			$message = sprintf( __( 'Deleted %d IP\'s from the cache', 'avhfdas' ), $result );
			$this->mail( $to, $subject, $message );
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
					if ( 1 == $this->core->options['general']['emailsecuritycheck'] ) {
						$to = get_option( 'admin_email' );
						$ip = avh_getUserIP();
						$commentdata['comment_author_email'] = empty( $commentdata['comment_author_email'] ) ? 'meseaffibia@gmail.com' : $commentdata['comment_author_email'];
						$subject = sprintf( __( '[%s] AVH First Defense Against Spam - Comment security check failed', 'avhfdas' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) );
						if ( isset( $_POST['_avh_first_defense_against_spam'] ) ) {
							$message = __( 'Reason:	The nonce check failed.', 'avhfdas' ) . "\r\n";
						} else {
							$message = __( 'Reason:	An attempt was made to directly access wp-comment-post.php', 'avhfdas' ) . "\r\n";
						}
						$message .= sprintf( __( 'Username:	%s', 'avhfdas' ), $commentdata['comment_author'] ) . "\r\n";
						$message .= sprintf( __( 'Email:		%s', 'avhfdas' ), $commentdata['comment_author_email'] ) . "\r\n";
						$message .= sprintf( __( 'IP:		%s', 'avhfdas' ), $ip ) . "\r\n\r\n";
						$message .= __( 'Comment trying to post:', 'avhfdas' ) . "\r\n";
						$message .= __( '--- START OF COMMENT ---', 'avhfdas' ) . "\r\n";
						$message .= $commentdata['comment_content'] . "\r\n";
						$message .= __( '--- END OF COMMENT ---', 'avhfdas' ) . "\r\n\r\n";
						if ( ! empty( $this->core->options['sfs']['sfsapikey'] ) ) {
							$q['action'] = 'emailreportspammer';
							$q['a'] = $commentdata['comment_author'];
							$q['e'] = $commentdata['comment_author_email'];
							$q['i'] = $ip;
							$q['_avhnonce'] = avh_create_nonce( $q['a'] . $q['e'] . $q['i'] );
							$query = $this->core->BuildQuery( $q );
							$report_url = admin_url( 'admin.php?' . $query );
							$message .= sprintf( __( 'Report spammer: %s' ), $report_url ) . "\r\n";
						}
						$message .= sprintf( __( 'For more information: http://www.stopforumspam.com/search?q=%s' ), $ip ) . "\r\n\r\n";

						$blacklisturl = admin_url( 'admin.php?action=blacklist&i=' ) . $ip . '&_avhnonce=' . avh_create_nonce( $ip );
						$message .= sprintf( __( 'Add to the local blacklist: %s' ), $blacklisturl ) . "\r\n";

						$this->mail( $to, $subject, $message );

					}
					// Only keep track if we have the ability to report add Stop Forum Spam
					if ( ! empty( $this->core->options['sfs']['sfsapikey'] ) ) {
						// Prevent a spam attack to overflow the database.
						if ( ! ($this->checkDB_Nonces( $q['_avhnonce'] )) ) {
							$option = get_option( $this->core->db_options_nonces );
							$option[$q['_avhnonce']] = $q['a'] . $q['e'] . $q['i'];
							update_option( $this->core->db_options_nonces, $option );
						}
					}
					$m = __( '<p>Cheating huh</p>', 'avhfdas' );
					$m .= __( '<p>Protected by: AVH First Defense Against Spam</p>', 'avhfdas' );

					if ( $this->core->options['php']['usehoneypot'] ) {
						$m .= '<p>' . $this->core->options['php']['honeypoturl'] . '</p>';
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
		$all = get_option( $this->core->db_options_nonces );
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

		// For the main action we don't check with Stop Forum Spam
		$this->spamcheck->doIPCheck( 'main' );
	}

	/**
	 * Handle when posting a comment.
	 *
	 * @uses SFS, PHP
	 * @WordPress Action preprocess_comment
	 */
	function actionHandlePostingComment ( $commentdata )
	{
		$this->spamcheck->doIPCheck( 'comment' );
		return ($commentdata);
	}

	/**
	 * Handle when a user registers
	 *
	 * @WordPress Action register_post
	 */
	function actionHandleRegistration ( $sanitized_user_login, $user_email, $errors )
	{
		$this->spamcheck->doIPCheck( 'registration' );

	}
}
?>