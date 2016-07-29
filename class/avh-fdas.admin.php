<?php

final class AVH_FDAS_Admin {
	private $add_disabled_notice = false;
	/**
	 *
	 * @var AVH_FDAS_Classes
	 */
	private $classes;
	/**
	 *
	 * @var AVH_FDAS_Core
	 */
	private $core;
	/**
	 *
	 * @var AVH_FDAS_DB
	 */
	private $db;
	private $hooks = array();
	/**
	 *
	 * @var AVH_FDAS_IpcacheList
	 */
	private $ip_cache_list;
	/**
	 * Message management
	 */
	private $message = '';
	/**
	 *
	 * @var AVH_FDAS_Settings
	 */
	private $settings;
	private $status = '';

	/**
	 * PHP5 Constructor
	 *
	 */
	public function __construct() {
		// The Settings Registery
		$this->settings = AVH_FDAS_Settings::getInstance();
		// The Classes Registery
		$this->classes = AVH_FDAS_Classes::getInstance();
		add_action('init', array($this, 'handleActionInit'));
	}

	/**
	 * Add the Tools and Options to the Management and Options page repectively
	 *
	 * @WordPress Action admin_menu
	 */
	public function actionAdminMenu() {
		add_menu_page('AVH F.D.A.S',
		              'AVH F.D.A.S',
		              'avh_fdas_admin',
		              AVH_FDAS_Define::MENU_SLUG,
		              array($this, 'menuOverview'));
		$this->hooks['avhfdas_menu_overview']  = add_submenu_page(AVH_FDAS_Define::MENU_SLUG,
		                                                          'AVH First Defense Against Spam: ' .
		                                                          __('Overview', 'avh-fdas'),
		                                                          __('Overview', 'avh-fdas'),
		                                                          'avh_fdas_admin',
		                                                          AVH_FDAS_Define::MENU_SLUG_OVERVIEW,
		                                                          array($this, 'menuOverview'));
		$this->hooks['avhfdas_menu_general']   = add_submenu_page(AVH_FDAS_Define::MENU_SLUG,
		                                                          'AVH First Defense Against Spam:' .
		                                                          __('General Options', 'avh-fdas'),
		                                                          __('General Options', 'avh-fdas'),
		                                                          'avh_fdas_admin',
		                                                          AVH_FDAS_Define::MENU_SLUG_GENERAL,
		                                                          array($this, 'menuGeneralOptions'));
		$this->hooks['avhfdas_menu_3rd_party'] = add_submenu_page(AVH_FDAS_Define::MENU_SLUG,
		                                                          'AVH First Defense Against Spam:' .
		                                                          __('3rd Party Options', 'avh-fdas'),
		                                                          __('3rd Party Options', 'avh-fdas'),
		                                                          'avh_fdas_admin',
		                                                          AVH_FDAS_Define::MENU_SLUG_3RD_PARTY,
		                                                          array($this, 'menu3rdPartyOptions'));

		$this->hooks['avhfdas_menu_ip_cache_log'] = add_submenu_page(AVH_FDAS_Define::MENU_SLUG,
		                                                             'AVH First Defense Against Spam:' .
		                                                             __('IP Cache Log', 'avh-fdas'),
		                                                             __('IP Cache Log', 'avh-fdas'),
		                                                             'avh_fdas_admin',
		                                                             AVH_FDAS_Define::MENU_SLUG_IP_CACHE,
		                                                             array($this, 'menuIpCacheLog'));
		$this->hooks['avhfdas_menu_faq']          = add_submenu_page(AVH_FDAS_Define::MENU_SLUG,
		                                                             'AVH First Defense Against Spam:' .
		                                                             __('F.A.Q', 'avh-fdas'),
		                                                             __('F.A.Q', 'avh-fdas'),
		                                                             'avh_fdas_admin',
		                                                             AVH_FDAS_Define::MENU_SLUG_FAQ,
		                                                             array($this, 'menuFaq'));

		// Add actions for menu pages
		add_action('load-' . $this->hooks['avhfdas_menu_overview'], array($this, 'actionLoadPagehookOverview'));
		add_action('load-' . $this->hooks['avhfdas_menu_general'], array($this, 'actionLoadPagehookGeneral'));
		add_action('load-' . $this->hooks['avhfdas_menu_3rd_party'], array($this, 'actionLoadPagehook3rdParty'));
		add_action('load-' . $this->hooks['avhfdas_menu_faq'], array($this, 'actionLoadPagehookFaq'));

		add_action('load-' . $this->hooks['avhfdas_menu_ip_cache_log'],
		           array($this, 'actionLoadPagehookHandlePostGetIpCacheLog'),
		           5);
		add_action('load-' . $this->hooks['avhfdas_menu_ip_cache_log'],
		           array($this, 'actionLoadPagehookIpCacheLog'));

		if (WP_LOCAL_DEV == true) {
			wp_register_script('avhfdas-ipcachelog-js',
			                   $this->settings->js_url . '/avh-fdas.ipcachelog.js',
			                   array('jquery'),
			                   AVH_FDAS_Define::PLUGIN_VERSION,
			                   true);
			wp_register_script('avhfdas-admin-js',
			                   $this->settings->js_url . '/avh-fdas.admin.js',
			                   array('jquery'),
			                   AVH_FDAS_Define::PLUGIN_VERSION,
			                   true);
			wp_register_style('avhfdas-admin-css',
			                  $this->settings->css_url . '/avh-fdas.admin.css',
			                  array(),
			                  AVH_FDAS_Define::PLUGIN_VERSION,
			                  'screen');
		} else {
			$js_ipcachelog_version = 'cbb45d0';
			wp_register_script('avhfdas-ipcachelog-js',
			                   $this->settings->js_url . '/avh-fdas.ipcachelog-' . $js_ipcachelog_version . '.js',
			                   array('jquery'),
			                   AVH_FDAS_Define::PLUGIN_VERSION,
			                   true);
			$js_admin_version = 'ef1dd23';
			wp_register_script('avhfdas-admin-js',
			                   $this->settings->js_url . '/avh-fdas.admin-' . $js_admin_version . '.js',
			                   array('jquery'),
			                   AVH_FDAS_Define::PLUGIN_VERSION,
			                   true);
			$css_admin_version = 'd91157e';
			wp_register_style('avhfdas-admin-css',
			                  $this->settings->css_url . '/avh-fdas.admin-' . $css_admin_version . '.css',
			                  array(),
			                  AVH_FDAS_Define::PLUGIN_VERSION,
			                  'screen');
		}
	}

	public function actionAjaxIpcacheLog() {
		$id = isset($_POST['id']) ? $_POST['id'] : 0;
		switch ($_POST['action']) {
			case 'dim-ipcachelog':
				check_ajax_referer('hamspam-ip_' . $id);
				$status = isset($_POST['new_status']) ? $_POST['new_status'] : false;
				if (false === $status) {
					$x = new WP_Ajax_Response(array(
						                          'what' => 'ipcachelog',
						                          'id'   => new WP_Error('invalid_status',
						                                                 __('Unknown status parameter', 'avh-fdas'))
					                          ));
					$x->send();
				}
				$result = $this->db->updateIpCache(array('ip' => $id, 'spam' => $status));
				if (false === $result) {
					$x = new WP_Ajax_Response(array(
						                          'what' => 'ipcachelog',
						                          'id'   => new WP_Error('error_updating',
						                                                 __('Error updating the ipcache database table.',
						                                                    'avh-fdas'))
					                          ));
					$x->send();
				}
				die((string) time());
			case 'delete-ipcachelog':
				switch ($_POST['f']) {
					case 'hs': // Marking the IP as ham or spam
						check_ajax_referer('hamspam-ip_' . $id);
						$status = isset($_POST['ns']) ? $_POST['ns'] : false;
						if (false === $status) {
							$x = new WP_Ajax_Response(array(
								                          'what' => 'ipcachelog',
								                          'id'   => new WP_Error('invalid_status',
								                                                 __('Unknown status parameter',
								                                                    'avh-fdas'))
							                          ));
							$x->send();
						}
						$result = $this->db->updateIpCache(array('ip' => $id, 'spam' => $status));
						if (false === $result) {
							$x = new WP_Ajax_Response(array(
								                          'what' => 'ipcachelog',
								                          'id'   => new WP_Error('error_updating',
								                                                 __('Error updating the ipcache database table.',
								                                                    'avh-fdas'))
							                          ));
							$x->send();
						}
						die((string) time());
						break;

					case 'bl': // Adding the IP to the local Blacklist
						check_ajax_referer('blacklist-ip_' . $id);
						$blacklist = $this->core->getDataElement('lists', 'blacklist');
						if ( ! empty($blacklist)) {
							$b = explode("\r\n", $blacklist);
						} else {
							$b = array();
						}
						$ip = long2ip($id);
						if ( ! (in_array($ip, $b))) {
							array_push($b, $ip);
							$this->setBlacklistOption($b);
							$result = $this->db->deleteIp($id);
							if (false === $result) {
								$x = new WP_Ajax_Response(array(
									                          'what'     => 'ipcachelog',
									                          'position' => - 1,
									                          'id'       => new WP_Error('error_deleting',
									                                                     __('Error deleting the IP from the database table.'))
								                          ));
								$x->send();
							}
						} else {
							$x = new WP_Ajax_Response(array(
								                          'what'     => 'ipcachelog',
								                          'position' => - 1,
								                          'id'       => new WP_Error('exists_blacklist',
								                                                     sprintf(__('IP %s already exists in the blacklist.'),
								                                                             $ip))
							                          ));
							$x->send();
						}

						$this->ajaxIpcacheLogResponse($id);
						die('0');
						break;

					case 'dl': // Delete the IP from the Database.
						check_ajax_referer('delete-ip_' . $id);
						$result = $this->db->deleteIp($id);
						if (false === $result) {
							$x = new WP_Ajax_Response(array(
								                          'what' => 'ipcachelog',
								                          'id'   => new WP_Error('error_deleting',
								                                                 __('Error deleting the IP from the database table.'))
							                          ));
							$x->send();
						}
						$this->ajaxIpcacheLogResponse($id);
						die('0');
						break;
				}
		}
		$x = new WP_Ajax_Response(array(
			                          'what'     => 'ipcachelog',
			                          'position' => - 1,
			                          'id'       => new WP_Error('invalid_call', __('Invalid AJAX call'))
		                          ));
		$x->send();
	}

	/**
	 * Checks if the user clicked on the Report & Delete link.
	 *
	 * @WordPress Action wp_ajax_avh-fdas-reportcomment
	 */
	public function actionAjaxReportComment() {
		if ('avh-fdas-reportcomment' == $_REQUEST['action']) {
			$comment_id = absint($_REQUEST['id']);
			check_ajax_referer('report-comment_' . $comment_id);
			if ( ! $comment = get_comment($comment_id)) {
				$this->comment_footer_die(__('Oops, no comment with this ID.') .
				                          sprintf(' <a href="%s">' . __('Go back') . '</a>!',
				                                  'edit-comments.php'));
			}
			if ( ! current_user_can('edit_post', $comment->comment_post_ID)) {
				$this->comment_footer_die(__('You are not allowed to edit comments on this post.'));
			}
			$options = $this->core->getOptions();
			// If we use IP Cache and the Reported IP isn't spam, delete it from the IP cache.
			if (1 == $options['general']['useipcache']) {
				$ip_info = $this->db->getIP($comment->comment_author_IP, OBJECT);
				if (is_object($ip_info) && 0 == $ip_info->spam) {
					$comment_date = get_comment_date('Y-m-d H:i:s', $comment_id);
					$this->db->updateIpCache(array(
						                         'ip'       => $comment->comment_author_IP,
						                         'spam'     => 1,
						                         'lastseen' => $comment_date
					                         ));
				}
			}
			if ($options['sfs']['sfsapikey'] != '' && ( ! empty($comment->comment_author_email))) {
				$this->handleReportSpammer($comment->comment_author,
				                           $comment->comment_author_email,
				                           $comment->comment_author_IP);
			}
			if (1 == $options['general']['addblacklist']) {
				$blacklist = $this->core->getDataElement('lists', 'blacklist');
				if ( ! empty($blacklist)) {
					$b = explode("\r\n", $blacklist);
				} else {
					$b = array();
				}
				if ( ! (in_array($comment->comment_author_IP, $b))) {
					array_push($b, $comment->comment_author_IP);
					$this->setBlacklistOption($b);
				}
			}
			// Delete the comment
			$r = wp_delete_comment($comment->comment_ID);
			die($r ? '1' : '0');
		}
	}

	/**
	 * Handles the admin_action_blacklist call
	 *
	 * @WordPress Action admin_action_blacklist
	 */
	public function actionHandleBlacklistUrl() {
		if ( ! (isset($_REQUEST['action']) && 'blacklist' == $_REQUEST['action'])) {
			return;
		}
		$ip = $_REQUEST['i'];
		if ( ! (false === AVH_Security::verifyNonce($_REQUEST['_avhnonce'], $ip))) {
			$blacklist = $this->core->getDataElement('lists', 'blacklist');
			if ( ! empty($blacklist)) {
				$b = explode("\r\n", $blacklist);
			} else {
				$b = array();
			}
			if ( ! (in_array($ip, $b))) {
				array_push($b, $ip);
				$this->setBlacklistOption($b);
				wp_redirect(admin_url('admin.php?page=' .
				                      AVH_FDAS_Define::MENU_SLUG_GENERAL .
				                      '&m=' .
				                      AVH_FDAS_Define::ADDED_BLACKLIST .
				                      '&i=' .
				                      $ip));
			} else {
				wp_redirect(admin_url('admin.php?page=' .
				                      AVH_FDAS_Define::MENU_SLUG_GENERAL .
				                      '&m=' .
				                      AVH_FDAS_Define::ERROR_EXISTS_IN_BLACKLIST .
				                      '&i=' .
				                      $ip));
			}
		} else {
			wp_redirect(admin_url('admin.php?page=' .
			                      AVH_FDAS_Define::MENU_SLUG_GENERAL .
			                      '&m=' .
			                      AVH_FDAS_Define::ERROR_INVALID_REQUEST));
		}
	}

	/**
	 * Handles the admin_action emailreportspammer call.
	 *
	 * @WordPress Action admin_action_emailreportspammer
	 *
	 * @since     1.2
	 *
	 */
	public function actionHandleEmailReportingUrl() {
		if ( ! (isset($_REQUEST['action']) && 'emailreportspammer' == $_REQUEST['action'])) {
			return;
		}
		$a     = esc_html($_REQUEST['a']);
		$e     = esc_html($_REQUEST['e']);
		$i     = esc_html($_REQUEST['i']);
		$extra = '&m=' . AVH_FDAS_Define::ERROR_INVALID_REQUEST . '&i=' . $i;
		if ( ! (false === AVH_Security::verifyNonce($_REQUEST['_avhnonce'], $a . $e . $i))) {
			$all   = get_option($this->core->getDbNonces());
			$extra = '&m=' . AVH_FDAS_Define::ERROR_NOT_REPORTED . '&i=' . $i;
			if (isset($all[ $_REQUEST['_avhnonce'] ])) {
				$this->handleReportSpammer($a, $e, $i);
				unset($all[ $_REQUEST['_avhnonce'] ]);
				update_option($this->core->db_nonce, $all);
				$extra = '&m=' . AVH_FDAS_Define::REPORTED . '&i=' . $i;
			}
			unset($all);
		}
		wp_redirect(admin_url('admin.php?page=' . AVH_FDAS_Define::MENU_SLUG_GENERAL . $extra));
	}

	/**
	 * Setup Roles
	 *
	 * @WordPress Action init
	 */
	public function actionInitRoles() {
		$role = get_role('administrator');
		if ($role != null && ! $role->has_cap('avh_fdas_admin')) {
			$role->add_cap('avh_fdas_admin');
		}
		// Clean var
		unset($role);
	}

	/**
	 * Setup everything needed for the 3rd party menu
	 */
	public function actionLoadPagehook3rdParty() {
		add_meta_box('avhfdasBoxSFS',
		             'Stop Forum Spam',
		             array($this, 'metaboxMenu3rdParty_SFS'),
		             $this->hooks['avhfdas_menu_3rd_party'],
		             'normal',
		             'core');
		add_meta_box('avhfdasBoxPHP',
		             'Project Honey Pot',
		             array($this, 'metaboxMenu3rdParty_PHP'),
		             $this->hooks['avhfdas_menu_3rd_party'],
		             'side',
		             'core');
		add_meta_box('avhfdasBoxSH',
		             'Spamhaus',
		             array($this, 'metaboxMenu3rdParty_SH'),
		             $this->hooks['avhfdas_menu_3rd_party'],
		             'normal',
		             'core');
		add_screen_option('layout_columns', array('max' => 2, 'default' => 2));

		// WordPress core Styles and Scripts
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		wp_enqueue_style('css/dashboard');
		// Plugin Style and Scripts
		wp_enqueue_script('avhfdas-admin-js');
		wp_enqueue_style('avhfdas-admin-css');
	}

	/**
	 * Setup everything needed for the FAQ page
	 */
	public function actionLoadPagehookFaq() {
		add_meta_box('avhfdasBoxFAQ',
		             __('F.A.Q.', 'avh-fdas'),
		             array($this, 'metaboxFAQ'),
		             $this->hooks['avhfdas_menu_faq'],
		             'normal',
		             'core');
		add_screen_option('layout_columns', array('max' => 2, 'default' => 2));

		// WordPress core Styles and Scripts
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		wp_enqueue_style('css/dashboard');
		// Plugin Style and Scripts
		wp_enqueue_script('avhfdas-admin-js');
		wp_enqueue_style('avhfdas-admin-css');
	}

	/**
	 * Setup the General page
	 */
	public function actionLoadPagehookGeneral() {
		add_meta_box('avhfdasBoxGeneral',
		             'General',
		             array($this, 'metaboxGeneral'),
		             $this->hooks['avhfdas_menu_general'],
		             'normal',
		             'core');
		add_meta_box('avhfdasBoxIPCache',
		             'IP Caching',
		             array($this, 'metaboxIPCache'),
		             $this->hooks['avhfdas_menu_general'],
		             'normal',
		             'core');
		add_meta_box('avhfdasBoxCron',
		             'Cron',
		             array($this, 'metaboxCron'),
		             $this->hooks['avhfdas_menu_general'],
		             'normal',
		             'core');
		add_meta_box('avhfdasBoxBlackList',
		             'Blacklist',
		             array($this, 'metaboxBlackList'),
		             $this->hooks['avhfdas_menu_general'],
		             'side',
		             'core');
		add_meta_box('avhfdasBoxWhiteList',
		             'Whitelist',
		             array($this, 'metaboxWhiteList'),
		             $this->hooks['avhfdas_menu_general'],
		             'side',
		             'core');
		add_screen_option('layout_columns', array('max' => 2, 'default' => 2));

		// WordPress core Styles and Scripts
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		wp_enqueue_style('css/dashboard');
		// Plugin Style and Scripts
		wp_enqueue_script('avhfdas-admin-js');
		wp_enqueue_style('avhfdas-admin-css');
	}

	/**
	 * Handles the Get and Post after a submit on the IP cache Log page
	 *
	 * @WordPress Action load-$page_hook
	 */
	public function actionLoadPagehookHandlePostGetIpCacheLog() {
		$this->ip_cache_list = $this->classes->load_class('IPCacheList', 'plugin', true);

		$pagenum  = $this->ip_cache_list->get_pagenum();
		$doaction = $this->ip_cache_list->current_action();

		if ($doaction) {
			switch ($doaction) {

				case 'delete':
				case 'blacklist':
				case 'ham':
				case 'spam':
					// These are the BULK actions
					check_admin_referer('bulk-ips');

					if (isset($_REQUEST['delete_ips'])) {
						$ips = $_REQUEST['delete_ips'];
					} elseif (wp_get_referer()) {
						wp_redirect(wp_get_referer());
						exit();
					}
					$redirect_to = remove_query_arg(array($doaction), wp_get_referer());
					$redirect_to = add_query_arg('paged', $pagenum, $redirect_to);
					switch ($doaction) {
						case 'delete':
							$deleted = 0;
							foreach ($ips as $ip) {
								$this->db->deleteIp($ip);
								$deleted ++;
							}

							if ($deleted) {
								$redirect_to = add_query_arg(array('deleted' => $deleted), $redirect_to);
							}

							wp_redirect($redirect_to);
							exit();
							break;
						case 'blacklist':
							$blacklisted = 0;
							$blacklist   = $this->core->getDataElement('lists', 'blacklist');
							if ( ! empty($blacklist)) {
								$b = explode("\r\n", $blacklist);
							} else {
								$b = array();
							}
							foreach ($ips as $ip) {

								$ip = long2ip($ip);
								if ( ! (in_array($ip, $b))) {
									array_push($b, $ip);
									$this->db->deleteIp($ip);
									$blacklisted ++;
								}
							}
							if ($blacklisted) {
								$this->setBlacklistOption($b);
								$redirect_to = add_query_arg(array('blacklisted' => $blacklisted), $redirect_to);
							}

							wp_redirect($redirect_to);
							exit();
							break;
						case 'ham':
						case 'spam':
							$hamspammed = 0;
							$new_status = ($doaction == 'ham' ? 0 : 1);
							foreach ($ips as $ip) {

								$result = $this->db->updateIpCache(array('ip' => $ip, 'spam' => $new_status));
								if ($result !== false) {
									$hamspammed ++;
								}
							}
							if ($hamspammed) {
								$arg         = ($doaction == 'ham' ? 'hammed' : 'spammed');
								$redirect_to = add_query_arg(array($arg => $hamspammed), $redirect_to);
							}

							wp_redirect($redirect_to);
							exit();
							break;
					}
					break;

				case 'spamip':
				case 'hamip':
				case 'blacklistip':
				case 'deleteip':
					// These are the ROW actions
					$redirect_to = remove_query_arg(array($doaction), wp_get_referer());
					$redirect_to = add_query_arg('paged', $pagenum, $redirect_to);
					$ip          = absint($_GET['i']);
					switch ($doaction) {
						case 'spamip':
						case 'hamip':
							check_admin_referer('hamspam-ip_' . $ip);
							$new_status = ($doaction == 'hamip' ? 0 : 1);
							$result     = $this->db->updateIpCache(array('ip' => $ip, 'spam' => $new_status));
							if ($result) {
								$arg         = ($doaction == 'hamip' ? 'hammed' : 'spammed');
								$redirect_to = add_query_arg(array($arg => 1), $redirect_to);
							}
							wp_redirect($redirect_to);
							exit();
							break;
						case 'blacklistip':
							$blacklist = $this->core->getDataElement('lists', 'blacklist');
							if ( ! empty($blacklist)) {
								$b = explode("\r\n", $blacklist);
							} else {
								$b = array();
							}
							$ip_human = long2ip($ip);
							if ( ! (in_array($ip_human, $b))) {
								array_push($b, $ip_human);
								$this->db->deleteIp($ip);
								$this->setBlacklistOption($b);
								$redirect_to = add_query_arg(array('blacklisted' => 1), $redirect_to);
							}
							wp_redirect($redirect_to);
							exit();
							break;
						case 'deleteip':
							$result = $this->db->deleteIp($ip);
							if ($result) {
								$redirect_to = add_query_arg(array('deleted' => 1), $redirect_to);
							}
							$redirect_to = add_query_arg(array('deleted' => $deleted), $redirect_to);
					}
					break;
			}
		} elseif ( ! empty($_GET['_wp_http_referer'])) {
			wp_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce'), stripslashes($_SERVER['REQUEST_URI'])));
			exit();
		}

		if (isset($_REQUEST['deleted']) ||
		    isset($_REQUEST['blacklisted']) ||
		    isset($_REQUEST['hammed']) ||
		    isset($_REQUEST['spammed'])
		) {
			$deleted     = isset($_REQUEST['deleted']) ? (int) $_REQUEST['deleted'] : 0;
			$blacklisted = isset($_REQUEST['blacklisted']) ? (int) $_REQUEST['blacklisted'] : 0;
			$hammed      = isset($_REQUEST['hammed']) ? (int) $_REQUEST['hammed'] : 0;
			$spammed     = isset($_REQUEST['spammed']) ? (int) $_REQUEST['spammed'] : 0;

			if ($deleted > 0) {
				$this->ip_cache_list->messages[] = sprintf(_n('%s IP permanently deleted',
				                                              '%s IP\'s deleted',
				                                              $deleted),
				                                           $deleted);
			}

			if ($blacklisted > 0) {
				$this->ip_cache_list->messages[] = sprintf(_n('%s IP added to the blacklist',
				                                              '%s IP\'s added to the blacklist',
				                                              $blacklisted),
				                                           $blacklisted);
			}

			if ($hammed > 0) {
				$this->ip_cache_list->messages[] = sprintf(_n('%s IP marked as ham',
				                                              '%s IP\'s marked as ham',
				                                              $hammed),
				                                           $hammed);
			}

			if ($spammed > 0) {
				$this->ip_cache_list->messages[] = sprintf(_n('%s IP marked as spam',
				                                              '%s IP\'s marked as spam',
				                                              $spammed),
				                                           $spammed);
			}
		}
		$this->ip_cache_list->prepare_items();

		$total_pages = $this->ip_cache_list->get_pagination_arg('total_pages');
		if ($pagenum > $total_pages && $total_pages > 0) {
			wp_redirect(add_query_arg('paged', $total_pages));
			exit();
		}
	}

	/**
	 * Setup everything needed for the IP Cache page
	 */
	public function actionLoadPagehookIpCacheLog() {

		$this->ip_cache_list = $this->classes->load_class('IPCacheList', 'plugin', true);
		add_filter('screen_layout_columns', array($this, 'filterScreenLayoutColumns'), 10, 2);
		// WordPress core Styles and Scripts
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		wp_enqueue_style('css/dashboard');
		// Plugin Style and Scripts
		wp_enqueue_script('avhfdas-admin-js');
		wp_enqueue_script('avhfdas-ipcachelog-js');

		wp_enqueue_style('avhfdas-admin-css');

		$current_screen = get_current_screen();
		$current_screen->add_option('per_page',
		                            array(
			                            'label'   => _x('IP\'s', 'ip\'s per page (screen options)'),
			                            'default' => 20,
			                            'option'  => 'ipcachelog_per_page'
		                            ));
		$current_screen->add_help_tab(array(
			                              'id'      => 'avh_fdas_ipcachelog',
			                              'title'   => 'IP Cache Log Help',
			                              'content' => '<p>' .
			                                           __('You can manage IP\'s added to the IP cache Log. This screen is customizable in the same ways as other management screens, and you can act on IP\'s using the on-hover action links or the Bulk Actions.') .
			                                           '</p>'
		                              ));
	}

	/**
	 * Setup everything needed for the Overview page
	 */
	public function actionLoadPagehookOverview() {
		add_meta_box('avhfdasBoxStats',
		             __('Statistics', 'avh-fdas'),
		             array($this, 'metaboxMenuOverview'),
		             $this->hooks['avhfdas_menu_overview'],
		             'normal',
		             'core');
		add_screen_option('layout_columns', array('max' => 2, 'default' => 2));

		// WordPress core Styles and Scripts
		wp_enqueue_script('common');
		wp_enqueue_script('wp-lists');
		wp_enqueue_script('postbox');
		wp_enqueue_style('css/dashboard');
		// Plugin Style and Scripts
		wp_enqueue_script('avhfdas-admin-js');
		wp_enqueue_style('avhfdas-admin-css');
	}

	/**
	 * Called on deactivation of the plugin.
	 */
	public function deactivatePlugin() {
		// Remove the administrative capilities
		$role = get_role('administrator');
		if ($role != null && $role->has_cap('avh_fdas_admin')) {
			$role->remove_cap('avh_fdas_admin');
		}
		// Deactivate the cron action as the the plugin is deactivated.
		wp_clear_scheduled_hook('avhfdas_clean_nonce');
		wp_clear_scheduled_hook('avhfdas_clean_ipcache');
	}

	/**
	 * Adds an extra option on the comment row
	 *
	 * @WordPress Filter comment_row_actions
	 *
	 * @param $actions array
	 * @param $comment object
	 *
	 * @return array
	 * @since     1.0
	 */
	public function filterCommentRowActions($actions, $comment) {
		$apikey           = $this->core->getOptionElement('sfs', 'sfsapikey');
		$add_to_blacklist = $this->core->getOptionElement('general', 'addblacklist');

		$link_text = '';
		if ((isset($comment->comment_approved) && 'spam' == $comment->comment_approved) &&
		    (('' != $apikey && ! empty($comment->comment_author_email)) || 1 == $add_to_blacklist)
		) {
			if ('' != $apikey && ! empty($comment->comment_author_email)) {
				$link_text .= __('Report', 'avhfdas');
			}
			if (1 == $add_to_blacklist) {
				if ( ! empty($link_text)) {
					$link_text .= ', ';
				}
				$link_text .= __('Blacklist', 'avh-fdas');
			}
			$link_text .= __(' & Delete', 'avhfdas');
			$report_url = esc_url(wp_nonce_url("admin.php?avhfdas_ajax_action=avh-fdas-reportcomment&id=$comment->comment_ID",
			                                   "report-comment_$comment->comment_ID"));

			$actions['report'] = '<a data-wp-lists=\'delete:the-comment-list:comment-' .
			                     $comment->comment_ID .
			                     ':e7e7d3:action=avh-fdas-reportcomment\' class="delete vim-d vim-destructive" href="' .
			                     $report_url .
			                     '">' .
			                     $link_text .
			                     '</a>';
		}

		return $actions;
	}

	/**
	 * This function allows the upgrade notice not to appear
	 *
	 * @param $option
	 *
	 * @return mixed
	 */
	public function filterDisableUpgrade($option) {
		$this_plugin = $this->settings->plugin_basename;
		// Allow upgrade for version 2
		if (version_compare($option->response[ $this_plugin ]->new_version, '4', '>=')) {
			return $option;
		}
		if (isset($option->response[ $this_plugin ])) {
			// Clear its download link:
			$option->response[ $this_plugin ]->package = '';
			// Add a notice message
			if ($this->add_disabled_notice == false) {
				add_action("in_plugin_update_message-$this->plugin_name",
				           create_function('',
				                           'echo \'<br /><span style="color:red">' .
				                           __('You need to have PHP 5.6 or higher for the new version !!!',
				                              'avh-fdas') .
				                           '</span>\';'));
				$this->add_disabled_notice = true;
			}
		}

		return $option;
	}

	/**
	 * Adds Settings next to the plugin actions
	 *
	 * @WordPress Filter plugin_action_links_avh-first-defense-against-spam/avh-fdas.php
	 *
	 * @param $links array
	 *
	 * @return array
	 *
	 * @since     1.0
	 */
	public function filterPluginActions($links) {
		$folder        = AVH_Common::getBaseDirectory($this->settings->plugin_basename);
		$settings_link = '<a href="admin.php?page=' . $folder . '">' . __('Settings', 'avh-fdas') . '</a>';
		array_unshift($links, $settings_link); // before other links
		return $links;
	}

	/**
	 * Sets the amount of columns wanted for a particuler screen
	 *
	 * @WordPress filter screen_meta_screen
	 *
	 * @param $columns
	 * @param $screen
	 *
	 * @return array
	 */
	public function filterScreenLayoutColumns($columns, $screen) {
		switch ($screen) {
			case $this->hooks['avhfdas_menu_overview']:
				$columns[ $this->hooks['avhfdas_menu_overview'] ] = 2;
				break;
			case $this->hooks['avhfdas_menu_general']:
				$columns[ $this->hooks['avhfdas_menu_general'] ] = 2;
				break;
			case $this->hooks['avhfdas_menu_3rd_party']:
				$columns[ $this->hooks['avhfdas_menu_3rd_party'] ] = 2;
				break;
			case $this->hooks['avhfdas_menu_faq']:
				$columns[ $this->hooks['avhfdas_menu_faq'] ] = 2;
				break;
		}

		return $columns;
	}

	/**
	 * Used when we set our own screen options.
	 *
	 * The filter needs to be set during construct otherwise it's not regonized.
	 *
	 * @param int    $error_value
	 * @param string $option
	 * @param int    $value
	 *
	 * @return int
	 */
	public function filterSetScreenOption($error_value, $option, $value) {
		switch ($option) {
			case 'ipcachelog_per_page':
				$value  = (int) $value;
				$return = $value;
				if ($value < 1 || $value > 999) {
					$return = $error_value;
				}
				break;
			default:
				$return = $error_value;
				break;
		}

		return $return;
	}

	public function handleActionInit() {
		// Initialize the plugin
		$this->core = $this->classes->load_class('Core', 'plugin', true);
		$this->db   = $this->classes->load_class('DB', 'plugin', true);
		// Admin URL and Pagination
		$this->core->admin_base_url = $this->settings->siteurl . '/wp-admin/admin.php?page=';
		if (isset($_GET['pagination'])) {
			$this->core->actual_page = (int) $_GET['pagination'];
		}
		$this->installPlugin();
		// Admin Capabilities
		$this->actionInitRoles();

		// Admin menu
		add_action('admin_menu', array($this, 'actionAdminMenu'));
		// Add the ajax action
		add_action('wp_ajax_avh-fdas-reportcomment', array($this, 'actionAjaxReportComment'));
		add_action('wp_ajax_dim-ipcachelog', array($this, 'actionAjaxIpcacheLog'));
		add_action('wp_ajax_delete-ipcachelog', array($this, 'actionAjaxIpcacheLog'));

		/**
		 * Admin actions
		 */
		add_action('admin_action_blacklist', array($this, 'actionHandleBlacklistUrl'));
		add_action('admin_action_emailreportspammer', array($this, 'actionHandleEmailReportingUrl'));
		/**
		 * Admin Filters
		 */
		add_filter('comment_row_actions', array($this, 'filterCommentRowActions'), 10, 2);
		add_filter('plugin_action_links_' . AVH_FDAS_Define::PLUGIN_FILE, array($this, 'filterPluginActions'), 10, 2);
		// If the version compare fails do not display the Upgrade notice.
		if (version_compare(PHP_VERSION, '5.6', '<')) {
			add_filter('site_transient_update_plugins', array($this, 'filterDisableUpgrade'));
		}
		add_filter('set-screen-option', array($this, 'filterSetScreenOption'), 10, 3);
	}

	/**
	 * Called on activation of the plugin.
	 */
	public function installPlugin() {
		global $wpdb;
		// Add Cron Job, the action is added in the Public class.
		if ( ! wp_next_scheduled('avhfdas_clean_nonce')) {
			wp_schedule_event(time(), 'daily', 'avhfdas_clean_nonce');
		}
		// Setup nonces db in options
		if ( ! (get_option($this->core->getDbNonces()))) {
			update_option($this->core->getDbNonces(), $this->core->getDefaultNonces());
			wp_cache_flush(); // Delete cache
		}
		// Setup the DB Tables
		$charset_collate = '';
		if (version_compare($wpdb->db_version(), '4.1.0', '>=')) {
			if ( ! empty($wpdb->charset)) {
				$charset_collate = 'DEFAULT CHARACTER SET ' . $wpdb->charset;
			}
			if ( ! empty($wpdb->collate)) {
				$charset_collate .= ' COLLATE ' . $wpdb->collate;
			}
		}
		if ($wpdb->get_var('show tables like \'' . $wpdb->avhfdasipcache . '\'') === null) {
			$sql = 'CREATE TABLE `' . $wpdb->avhfdasipcache . '` (
  					`ip` int(10) unsigned NOT null,
  					`added` datetime NOT null DEFAULT \'0000-00-00 00:00:00\',
  					`lastseen` datetime NOT null DEFAULT \'0000-00-00 00:00:00\',
  					`spam` tinyint(1) NOT null,
  					PRIMARY KEY (`ip`),
  					KEY `added` (`added`),
  					KEY `lastseen` (`lastseen`)
					) ' . $charset_collate . ';';
			$wpdb->query($sql);
		}
	}

	/**
	 * Menu Page Third Party Options
	 *
	 */
	public function menu3rdPartyOptions() {
		global $screen_layout_columns;
		$options_sfs[] = array(
			'avhfdas[general][use_sfs]',
			__('Check with Stop Forum Spam', 'avh-fdas'),
			'checkbox',
			1,
			__('If checked, the visitor\'s IP will be checked with Stop Forum Spam', 'avh-fdas')
		);
		$options_sfs[] = array(
			'avhfdas[sfs][whentoemail]',
			__('Email threshold', 'avh-fdas'),
			'text',
			3,
			__('When the frequency of the IP address of the spammer in the stopforumspam database equals or exceeds this threshold an email is send.<BR />A negative number means an email will never be send.',
			   'avh-fdas')
		);
		$options_sfs[] = array(
			'avhfdas[sfs][whentodie]',
			__('IP termination threshold', 'avh-fdas'),
			'text',
			3,
			__('When the frequency of the IP address of the spammer in the stopforumspam database equals or exceeds this threshold the connection is terminated.<BR />A negative number means the connection will never be terminated.',
			   'avh-fdas')
		);
		$options_sfs[] = array(
			'avhfdas[sfs][whentodie_email]',
			__('E-Mail termination threshold', 'avh-fdas'),
			'text',
			3,
			__('When the frequency of the e-mail address of the spammer in the stopforumspam database equals or exceeds this threshold the connection is terminated.<BR />A negative number means the connection will never be terminated.',
			   'avh-fdas')
		);
		$options_sfs[] = array(
			'avhfdas[sfs][sfsapikey]',
			__('API Key', 'avh-fdas'),
			'text',
			15,
			__('You need a Stop Forum Spam API key to report spam.', 'avh-fdas')
		);
		$options_sfs[] = array(
			'avhfdas[sfs][error]',
			__('Email error', 'avh-fdas'),
			'checkbox',
			1,
			__('Receive an email when the call to Stop Forum Spam Fails', 'avh-fdas')
		);
		$options_php[] = array(
			'avhfdas[general][use_php]',
			__('Check with Honey Pot Project', 'avh-fdas'),
			'checkbox',
			1,
			__('If checked, the visitor\'s IP will be checked with Honey Pot Project', 'avh-fdas')
		);
		$options_php[] = array(
			'avhfdas[php][phpapikey]',
			__('API Key:', 'avh-fdas'),
			'text',
			15,
			__('You need a Project Honey Pot API key to check the Honey Pot Project database.', 'avh-fdas')
		);
		$options_php[] = array(
			'avhfdas[php][whentoemailtype]',
			__('Email type threshold:', 'avh-fdas'),
			'dropdown',
			'0/1/2/3/4/5/6/7',
			'Search Engine/Suspicious/Harvester/Suspicious & Harvester/Comment Spammer/Suspicious & Comment Spammer/Harvester & Comment Spammer/Suspicious & Harvester & Comment Spammer',
			__('When the type of the spammer in the Project Honey Pot database equals or exceeds this threshold an email is send.<BR />Both the type threshold and the score threshold have to be reached in order to receive an email.',
			   'avh-fdas')
		);
		$options_php[] = array(
			'avhfdas[php][whentoemail]',
			__('Email score threshold', 'avh-fdas'),
			'text',
			3,
			__('When the score of the spammer in the Project Honey Pot database equals or exceeds this threshold an email is send.<BR />A negative number means an email will never be send.',
			   'avh-fdas')
		);
		$options_php[] = array(
			'avhfdas[php][whentodietype]',
			__('Termination type threshold', 'avh-fdas'),
			'dropdown',
			'-1/0/1/2/3/4/5/6/7',
			'Never/Search Engine/Suspicious/Harvester/Suspicious & Harvester/Comment Spammer/Suspicious & Comment Spammer/Harvester & Comment Spammer/Suspicious & Harvester & Comment Spammer',
			__('When the type of the spammer in the Project Honey Pot database equals or exceeds this threshold and the score threshold, the connection is terminated.',
			   'avh-fdas')
		);
		$options_php[] = array(
			'avhfdas[php][whentodie]',
			__('Termination score threshold', 'avh-fdas'),
			'text',
			3,
			__('When the score of the spammer in the Project Honey Pot database equals or exceeds this threshold and the type threshold is reached, the connection terminated.<BR />A negative number means the connection will never be terminated.<BR /><strong>This option will always be the last one checked.</strong>',
			   'avh-fdas')
		);
		$options_php[] = array(
			'avhfdas[php][usehoneypot]',
			__('Use Honey Pot', 'avh-fdas'),
			'checkbox',
			1,
			__('If you have set up a Honey Pot you can select to have the URL below to be added to the message when terminating the connection.<BR />You have to select <em>Show Message</em> in the General Options for this to work.',
			   'avh-fdas')
		);
		$options_php[] = array(
			'avhfdas[php][honeypoturl]',
			__('Honey Pot URL', 'avh-fdas'),
			'text',
			30,
			__('The link to the Honey Pot as suggested by Project Honey Pot.', 'avh-fdas')
		);
		$options_sh[]  = array(
			'avhfdas[general][use_sh]',
			__('Check with Spamhaus', 'avh-fdas'),
			'checkbox',
			1,
			__('If checked, the visitor\'s IP will be checked at Spamhaus', 'avh-fdas')
		);
		$options_sh[]  = array(
			'avhfdas[spamhaus][email]',
			__('Email', 'avh-fdas'),
			'checkbox',
			1,
			__('Send an email when a connection is terminated based on the IP found at Spamhaus', 'avh-fdas')
		);

		if (isset($_POST['updateoptions'])) {
			check_admin_referer('avh_fdas_options');
			$formoptions = $_POST['avhfdas'];
			$options     = $this->core->getOptions();
			$all_data    = array_merge($options_sfs, $options_php, $options_sh);
			foreach ($all_data as $option) {
				$section       = substr($option[0], strpos($option[0], '[') + 1);
				$section       = substr($section, 0, strpos($section, ']['));
				$option_key    = rtrim($option[0], ']');
				$option_key    = substr($option_key, strpos($option_key, '][') + 2);
				$current_value = $options[ $section ][ $option_key ];
				// Every field in a form is set except unchecked checkboxes. Set an unchecked checkbox to 0.
				$newval = (isset($formoptions[ $section ][ $option_key ]) ? esc_attr($formoptions[ $section ][ $option_key ]) : 0);
				if ('sfs' == $section &&
				    ('whentoemail' == $option_key || 'whentodie' == $option_key || 'whentodie_email' == $option_key)
				) {
					$newval = (int) $newval;
				}
				if ('php' == $section && ('whentoemail' == $option_key || 'whentodie' == $option_key)) {
					$newval = (int) $newval;
				}
				if ($newval != $current_value) { // Only process changed fields
					$options[ $section ][ $option_key ] = $newval;
				}
			}
			$note = '';
			if (('' === trim($options['php']['phpapikey'])) && 1 == $options['general']['use_php']) {
				$options['general']['use_php'] = 0;
				$note                          = '<br \><br \>' .
				                                 __('You can not use Project Honey Pot without an API key. Use of Project Honey Pot has been disabled',
				                                    'avh-fdas');
			}
			$this->core->saveOptions($options);
			$this->message = __('Options saved', 'avh-fdas');
			$this->message .= $note;
			$this->status = 'updated fade';
			$this->displayMessage();
		}
		$actual_options = array_merge($this->core->getOptions(), $this->core->getData());
		$hide2          = '';
		switch ($screen_layout_columns) {
			case 2:
				$width = 'width:49%;';
				break;
			default:
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}
		$data['options_sfs']    = $options_sfs;
		$data['options_php']    = $options_php;
		$data['options_sh']     = $options_sh;
		$data['actual_options'] = $actual_options;

		echo '<div class="wrap avhfdas-wrap">';
		echo $this->displayIcon('options-general');
		echo '<h2>AVH First Defense Against Spam: ' . __('3rd Party Options', 'avh-fdas') . '</h2>';
		echo '<form name="avhfdas-options" id="avhfdas-options" method="POST" action="admin.php?page=' .
		     AVH_FDAS_Define::MENU_SLUG_3RD_PARTY .
		     '" accept-charset="utf-8" >';
		wp_nonce_field('avh_fdas_options');
		wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
		wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';
		echo '			<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes($this->hooks['avhfdas_menu_3rd_party'], 'normal', $data);
		echo '			</div>';
		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes($this->hooks['avhfdas_menu_3rd_party'], 'side', $data);
		echo '			</div>';
		echo '		</div>';
		echo '	</div>'; // dashboard-widgets-wrap
		echo '<p class="submit"><input class="button-primary" type="submit" name="updateoptions" value="' .
		     __('Save Changes',
		        'avh-fdas') .
		     '" /></p>';
		echo '</form>';
		$this->printAdminFooter();
		echo '</div>';
	}

	/**
	 * Menu Page FAQ
	 *
	 */
	public function menuFaq() {
		global $screen_layout_columns;
		// This box can't be unselectd in the the Screen Options
		add_meta_box('avhfdasBoxDonations',
		             __('Donations', 'avh-fdas'),
		             array($this, 'metaboxDonations'),
		             $this->hooks['avhfdas_menu_faq'],
		             'side',
		             'core');
		$hide2 = '';
		switch ($screen_layout_columns) {
			case 2:
				$width = 'width:49%;';
				break;
			default:
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}
		echo '<div class="wrap avhfdas-wrap">';
		echo $this->displayIcon('index');
		echo '<h2>AVH First Defense Against Spam: ' . __('Spam Overview', 'avh-fdas') . '</h2>';
		echo '<form method="get" action="">';
		wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
		wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
		echo '</form>';
		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';
		echo '			<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes($this->hooks['avhfdas_menu_faq'], 'normal', '');
		echo '			</div>';
		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes($this->hooks['avhfdas_menu_faq'], 'side', '');
		echo '			</div>';
		echo '		</div>';
		echo ' </div>'; // dashboard-widgets-wrap

		$this->printAdminFooter();
		echo '</div>';
	}

	/**
	 * Menu Page general options
	 *
	 */
	public function menuGeneralOptions() {
		global $screen_layout_columns;
		$options_general[]   = array(
			'avhfdas[general][diewithmessage]',
			__('Show message', 'avh-fdas'),
			'checkbox',
			1,
			__('Show a message when the connection has been terminated.', 'avh-fdas')
		);
		$options_general[]   = array(
			'avhfdas[general][emailsecuritycheck]',
			__('Email on failed security check:', 'avh-fdas'),
			'checkbox',
			1,
			__('Receive an email when a comment is posted and the security check failed.', 'avh-fdas')
		);
		$options_general[]   = array(
			'avhfdas[general][commentnonce]',
			__('Use comment nonce:', 'avh-fdas'),
			'checkbox',
			1,
			__('Block spammers that access wp-comments-post.php directly by using a comment security check. An email can be send when the check fails.',
			   'avh-fdas')
		);
		$options_cron[]      = array(
			'avhfdas[general][cron_nonces_email]',
			__('Email result of nonces clean up', 'avh-fdas'),
			'checkbox',
			1,
			__('Receive an email with the total number of nonces that are deleted. The nonces are used to secure the links found in the emails.',
			   'avh-fdas')
		);
		$options_cron[]      = array(
			'avhfdas[general][cron_ipcache_email]',
			__('Email result of IP cache clean up', 'avh-fdas'),
			'checkbox',
			1,
			__('Receive an email with the total number of IP\'s that are deleted from the IP caching system.',
			   'avh-fdas')
		);
		$options_blacklist[] = array(
			'avhfdas[general][useblacklist]',
			__('Use internal blacklist', 'avh-fdas'),
			'checkbox',
			1,
			__('Check the internal blacklist first. If the IP is found terminate the connection, even when the Termination threshold is a negative number.',
			   'avh-fdas')
		);
		$options_blacklist[] = array(
			'avhfdas[general][addblacklist]',
			__('Add to blacklist link', 'avh-fdas'),
			'checkbox',
			1,
			__('Adds ability to add IP\'s from comments marked as spam', 'avh-fdas')
		);
		$options_blacklist[] = array(
			'avhfdas[lists][blacklist]',
			__('Blacklist IP\'s:', 'avh-fdas'),
			'textarea',
			15,
			__('Each IP should be on a separate line<br />Ranges can be defines as well in the following two formats<br />IP to IP. i.e. 192.168.1.100-192.168.1.105<br />Network in CIDR format. i.e. 192.168.1.0/24',
			   'avh-fdas'),
			15
		);
		$options_whitelist[] = array(
			'avhfdas[general][usewhitelist]',
			__('Use internal whitelist', 'avh-fdas'),
			'checkbox',
			1,
			__('Check the internal whitelist first. If the IP is found don\t do any further checking.', 'avh-fdas')
		);
		$options_whitelist[] = array(
			'avhfdas[lists][whitelist]',
			__('Whitelist IP\'s', 'avh-fdas'),
			'textarea',
			15,
			__('Each IP should be on a seperate line<br />Ranges can be defines as well in the following two formats<br />IP to IP. i.e. 192.168.1.100-192.168.1.105<br />Network in CIDR format. i.e. 192.168.1.0/24',
			   'avh-fdas'),
			15
		);
		$options_ipcache[]   = array(
			'avhfdas[general][useipcache]',
			__('Use IP Caching', 'avh-fdas'),
			'checkbox',
			1,
			__('Cache the IP\'s that meet the 3rd party termination threshold and the IP\'s that are not detected by the 3rd party. The connection will be terminated if an IP is found in the cache that was perviously determined to be a spammer',
			   'avh-fdas')
		);
		$options_ipcache[]   = array(
			'avhfdas[ipcache][email]',
			__('Email', 'avh-fdas'),
			'checkbox',
			1,
			__('Send an email when a connection is terminate based on the IP found in the cache', 'avh-fdas')
		);
		$options_ipcache[]   = array(
			'avhfdas[ipcache][daystokeep]',
			__('Days to keep in cache', 'avh-fdas'),
			'text',
			3,
			__('Keep the IP in cache for the selected days.', 'avh-fdas')
		);
		if (isset($_POST['updateoptions'])) {
			check_admin_referer('avh_fdas_generaloptions');
			$formoptions = $_POST['avhfdas'];
			$options     = $this->core->getOptions();
			$data        = $this->core->getData();
			$all_data    = array_merge($options_general,
			                           $options_blacklist,
			                           $options_whitelist,
			                           $options_ipcache,
			                           $options_cron);
			foreach ($all_data as $option) {
				$section    = substr($option[0], strpos($option[0], '[') + 1);
				$section    = substr($section, 0, strpos($section, ']['));
				$option_key = rtrim($option[0], ']');
				$option_key = substr($option_key, strpos($option_key, '][') + 2);
				switch ($section) {
					case 'general':
					case 'ipcache':
						$current_value = $options[ $section ][ $option_key ];
						break;
					case 'lists':
						$current_value = $data[ $section ][ $option_key ];
						break;
				}
				// Every field in a form is set except unchecked checkboxes. Set an unchecked checkbox to 0.
				$newval = (isset($formoptions[ $section ][ $option_key ]) ? esc_attr($formoptions[ $section ][ $option_key ]) : 0);
				if ($newval != $current_value) { // Only process changed fields.
					// Sort the lists
					if ('blacklist' == $option_key || 'whitelist' == $option_key) {
						$b = explode("\r\n", $newval);
						natsort($b);
						$newval = implode("\r\n", $b);
						unset($b);
					}
					switch ($section) {
						case 'general':
						case 'ipcache':
							$options[ $section ][ $option_key ] = $newval;
							break;
						case 'lists':
							$data[ $section ][ $option_key ] = $newval;
							break;
					}
				}
			}
			// Add or remove the Cron Job: avhfdas_clean_ipcache - defined in Public Class
			if ($options['general']['useipcache']) {
				// Add Cron Job if it's not scheduled
				if ( ! wp_next_scheduled('avhfdas_clean_ipcache')) {
					wp_schedule_event(time(), 'daily', 'avhfdas_clean_ipcache');
				}
			} else {
				// Remove Cron Job if it's scheduled
				if (wp_next_scheduled('avhfdas_clean_ipcache')) {
					wp_clear_scheduled_hook('avhfdas_clean_ipcache');
				}
			}
			$this->core->saveOptions($options);
			$this->core->saveData($data);
			$this->message = __('Options saved', 'avh-fdas');
			$this->status  = 'updated fade';
		}
		// Show messages if needed.
		if (isset($_REQUEST['m'])) {
			switch ($_REQUEST['m']) {
				case AVH_FDAS_Define::REPORTED_DELETED:
					$this->status  = 'updated fade';
					$this->message = sprintf(__('IP [%s] Reported and deleted', 'avh-fdas'), esc_attr($_REQUEST['i']));
					break;
				case AVH_FDAS_Define::ADDED_BLACKLIST:
					$this->status  = 'updated fade';
					$this->message = sprintf(__('IP [%s] has been added to the blacklist', 'avh-fdas'),
					                         esc_attr($_REQUEST['i']));
					break;
				case AVH_FDAS_Define::REPORTED:
					$this->status  = 'updated fade';
					$this->message = sprintf(__('IP [%s] reported.', 'avh-fdas'), esc_attr($_REQUEST['i']));
					break;
				case AVH_FDAS_Define::ERROR_INVALID_REQUEST:
					$this->status  = 'error';
					$this->message = sprintf(__('Invalid request.', 'avh-fdas'));
					break;
				case AVH_FDAS_Define::ERROR_NOT_REPORTED:
					$this->status  = 'error';
					$this->message = sprintf(__('IP [%s] not reported. Probably already processed.', 'avh-fdas'),
					                         esc_attr($_REQUEST['i']));
					break;
				case AVH_FDAS_Define::ERROR_EXISTS_IN_BLACKLIST:
					$this->status  = 'error';
					$this->message = sprintf(__('IP [%s] already exists in the blacklist.', 'avh-fdas'),
					                         esc_attr($_REQUEST['i']));
					break;
				default:
					$this->status  = 'error';
					$this->message = 'Unknown message request';
			}
		}
		$this->displayMessage();
		$actual_options = array_merge($this->core->getOptions(), $this->core->getData());
		$hide2          = '';
		switch ($screen_layout_columns) {
			case 2:
				$width = 'width:49%;';
				break;
			default:
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}
		$data['options_general']   = $options_general;
		$data['options_cron']      = $options_cron;
		$data['options_blacklist'] = $options_blacklist;
		$data['options_whitelist'] = $options_whitelist;
		$data['options_ipcache']   = $options_ipcache;
		$data['actual_options']    = $actual_options;
		echo '<div class="wrap avhfdas-wrap">';
		echo '<div class="wrap">';
		echo $this->displayIcon('options-general');
		echo '<h2>AVH First Defense Against Spam: ' . __('General Options', 'avh-fdas') . '</h2>';
		echo '<form name="avhfdas-generaloptions" id="avhfdas-generaloptions" method="POST" action="admin.php?page=' .
		     AVH_FDAS_Define::MENU_SLUG_GENERAL .
		     '" accept-charset="utf-8" >';
		wp_nonce_field('avh_fdas_generaloptions');
		wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
		wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';
		echo '			<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes($this->hooks['avhfdas_menu_general'], 'normal', $data);
		echo '			</div>';
		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes($this->hooks['avhfdas_menu_general'], 'side', $data);
		echo '			</div>';
		echo '		</div>';
		echo '<br class="clear"/>';
		echo '	</div>'; // dashboard-widgets-wrap
		echo '<p class="submit"><input class="button-primary"	type="submit" name="updateoptions" value="' .
		     __('Save Changes',
		        'avh-fdas') .
		     '" /></p>';
		echo '</form>';
		echo '</div>';
		$this->printAdminFooter();
		echo '</div>';
	}

	/**
	 * Displays the IP cache Log
	 */
	public function menuIpCacheLog() {
		global $ip_status;
		if ( ! empty($this->ip_cache_list->messages)) {
			echo '<div id="moderated" class="updated"><p>' . implode("<br/>\n",
			                                                         $this->ip_cache_list->messages) . '</p></div>';
		}
		$_SERVER['REQUEST_URI'] = remove_query_arg(array('error', 'deleted', '_error_nonce'), $_SERVER['REQUEST_URI']);
		$this->ip_cache_list->prepare_items();

		$total_pages = $this->ip_cache_list->get_pagination_arg('total_pages');
		$pagenum     = $this->ip_cache_list->get_pagenum();
		if ($pagenum > $total_pages && $total_pages > 0) {
			wp_redirect(add_query_arg('paged', $total_pages));
			exit();
		}

		echo '<div class="wrap avhfdas-wrap">';
		echo $this->displayIcon('index');
		echo '<h2>AVH First Defense Against Spam: ' . __('IP Cache Log', 'avh-fdas');

		if (isset($_REQUEST['s']) && $_REQUEST['s']) {
			printf('<span class="subtitle">' .
			       sprintf(__('Search results for &#8220;%s&#8221;'),
			               wp_html_excerpt(esc_html(stripslashes($_REQUEST['s'])), 50)) .
			       '</span>');
		}
		echo '</h2>';

		$this->ip_cache_list->views();
		echo '<form id="ipcachelist-form" action="" method="get">';
		echo '<input type="hidden" name="page" value="' . AVH_FDAS_Define::MENU_SLUG_IP_CACHE . '"';
		echo '<input type="hidden" name="ip_status" value="' . esc_attr($ip_status) . '" />';
		echo '<input type="hidden" name="pagegen_timestamp" value="' . esc_attr(current_time('mysql', 1)) . '" />';

		echo '<input type="hidden" name="_total" value="' .
		     esc_attr($this->ip_cache_list->get_pagination_arg('total_items')) .
		     '" />';
		echo '<input type="hidden" name="_per_page" value="' .
		     esc_attr($this->ip_cache_list->get_pagination_arg('per_page')) .
		     '" />';
		echo '<input type="hidden" name="_page" value="' .
		     esc_attr($this->ip_cache_list->get_pagination_arg('page')) .
		     '" />';

		if (isset($_REQUEST['paged'])) {
			echo '<input type="hidden" name="paged"	value="' . esc_attr(absint($_REQUEST['paged'])) . '" />';
		}
		$this->ip_cache_list->search_box(__('Find IP', 'avh-fdas'), 'find_ip');
		$this->ip_cache_list->display();
		echo '</form>';

		echo '<div id="ajax-response"></div>';
		$this->printAdminFooter();
		echo '</div>';
	}

	/**
	 * Menu Page Overview
	 *
	 */
	public function menuOverview() {
		global $screen_layout_columns;
		// This box can't be unselectd in the the Screen Options
		add_meta_box('avhfdasBoxDonations',
		             __('Donations', 'avh-fdas'),
		             array($this, 'metaboxDonations'),
		             $this->hooks['avhfdas_menu_overview'],
		             'side',
		             'core');
		$hide2 = '';
		switch ($screen_layout_columns) {
			case 2:
				$width = 'width:49%;';
				break;
			default:
				$width = 'width:98%;';
				$hide2 = 'display:none;';
		}
		echo '<div class="wrap avhfdas-wrap">';
		echo $this->displayIcon('index');
		echo '<h2>AVH First Defense Against Spam: ' . __('Overview', 'avh-fdas') . '</h2>';
		echo '<form method="get" action="">';
		wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
		wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
		echo '</form>';
		echo '	<div id="dashboard-widgets-wrap">';
		echo '		<div id="dashboard-widgets" class="metabox-holder">';
		echo '			<div class="postbox-container" style="' . $width . '">' . "\n";
		do_meta_boxes($this->hooks['avhfdas_menu_overview'], 'normal', '');
		echo '			</div>';
		echo '			<div class="postbox-container" style="' . $hide2 . $width . '">' . "\n";
		do_meta_boxes($this->hooks['avhfdas_menu_overview'], 'side', '');
		echo '			</div>';
		echo '		</div>';
		echo '</div>';
		$this->printAdminFooter();
		echo '</div>';
	}

	/**
	 * Metabox Display the Blacklist Options
	 *
	 * @param $data array The data is filled menu setup
	 *
	 */
	public function metaboxBlackList($data) {
		echo $this->printOptions($data['options_blacklist'], $data['actual_options']);
	}

	/**
	 * Metabox Display the cron Options
	 *
	 * @param $data array The data is filled menu setup
	 *
	 */
	public function metaboxCron($data) {
		echo '<p>' .
		     __('Once a day cron jobs of this plugin run. You can select to receive an email with additional information about the jobs that ran.',
		        'avh-fdas');
		echo $this->printOptions($data['options_cron'], $data['actual_options']);
	}

	/**
	 * Donation Metabox
	 *
	 */
	public function metaboxDonations() {
		echo '<p>If you enjoy this plug-in please consider a donation. There are several ways you can show your appreciation</p>';
		echo '<p>';
		echo '<span class="b">Amazon</span><br />';
		echo 'If you decide to buy something from Amazon click the button.<br />';
		echo '<a href="https://www.amazon.com/?tag=petervanderdoes-20" target="_blank" title="Amazon Homepage"><img alt="Amazon Button" src="' .
		     $this->settings->graphics_url .
		     '/us_banner_logow_120x60.gif" /></a></p>';
		echo '<p>';
		echo 'You can send me something from my <a href="http://www.amazon.com/registry/wishlist/1U3DTWZ72PI7W?tag=petervanderdoes-20">Amazon Wish List</a>';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">Through Paypal.</span><br />';
		echo 'Click on the Donate button and you will be directed to Paypal where you can make your donation and you don\'t need to have a Paypal account to make a donation.<br />';
		echo '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=S85FXJ9EBHAF2&lc=US&item_name=AVH%20Plugins&item_number=fdas&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted" target="_blank" title="Donate">';
		echo '<img src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" alt="Donate"/></a>';
		echo '</p>';
	}

	/**
	 * Metabox Display the FAQ
	 *
	 */
	public function metaboxFAQ() {
		echo '<p>';
		echo '<span class="b">Usage terms of the 3rd Parties</span><br />';
		echo 'Please read the usage terms of the 3rd party you are activating.<br />';
		echo '<ul>';
		echo '<li><a href="http://www.stopforumspam.com/license" target="_blank">Stop Forum Spam.</a>';
		echo '<li><a href="http://www.projecthoneypot.org/terms_of_service_use.php" target="_blank">Project Honey Pot.</a>';
		echo '<li><a href="http://www.spamhaus.org/organization/dnsblusage.html" target="_blank">Spamhaus.</a>';
		echo '</ul>';
		echo '<p>';
		echo '<span class="b">What is ham?</span><br />';
		echo 'Ham is the opposite of spam';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">Why is there an IP caching system?</span><br />';
		echo 'Stop Forum spam has set a limit on the amount of API calls you can make a day, currently it iset at 5000 calls a day.<br />';
		echo 'This means that if you don\'t use the Blacklist and/or Whitelist you are limited to 5000 visits/day on your site. To overcome this possible problem I wrote an IP caching system.<br />';
		echo '</p>';
		echo '<p>';
		echo 'The following IP\'s are cached locally:<br />';
		echo '<ul>';
		echo '<li>Every IP identified as spam and triggering the terminate-the-connection threshold.</li>';
		echo '<li>Every clean IP.</li>';
		echo '</ul>';
		echo '</p>';
		echo '<p>';
		echo 'Only returning IP\'s that were previously identified as spammer and who\'s connection was terminated will update their last seen date in the caching system.<br />';
		echo 'Every day, once a day, a routine runs to remove the IP\'s who\'s last seen date is X amount of days older than the date the routine runs. You can set the days in the adminstration section of the plugin.<br />';
		echo 'You can check the statistics to see how many IP\'s are in the database. If you have a busy site, with a lot of unique visitors, you might have to play with the "Days to keep in cache" setting to keep the size under control.<br />';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">In what order is an IP checked and what action is taken?</span><br />';
		echo 'The plugin checks the visiting IP in the following order, only if that feature is enabled of course.<br />';
		echo '<ul>';
		echo '<li>Whitelist - If found skip the rest of the checks.</li>';
		echo '<li>Blacklist - If found terminate the connection.</li>';
		echo '<li>IP Caching - If found and spam terminate connection, if found and clean skip the rest of the checks.</li>';
		echo '<li>3rd Parties - If found determine action based on result.</li>';
		echo '</ul>';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">Is this plugin enough to block all spam?</span><br />';
		echo 'Unfortunately not.<br />';
		echo 'I don\'t believe there is one solution to block all spam. Personally I have great success with the plugin in combination with Akismet.<br />';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">Does it conflicts with other spam solutions?</span><br />';
		echo 'I\'m currently not aware of any conflicts with other anti-spam solutions.<br />';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">How do I define a range in the blacklist or white list?</span><br />';
		echo 'You can define two sorts of ranges:';
		echo '<ul>';
		echo '<li>From IP to IP. i.e. 192.168.1.100-192.168.1.105</li>';
		echo '<li>A network in CIDR format. i.e. 192.168.1.0/24</li>';
		echo '</ul>';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">How do I report a spammer to Stop Forum Spam?</span><br />';
		echo 'You need to have an API key from Stop Forum Spam. If you do on the Edit Comments pages there is an extra option called, Report & Delete, in the messages identified as spam.<br />';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">How do I get a Stop Forum Spam API key?</span><br />';
		echo 'You will have to sign up on their site, <a href="http://www.stopforumspam.com/signup" target="_blank">http://www.stopforumspam.com/signup</a>.<br />';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">How do I get a Project Honey Pot API key?</span><br />';
		echo 'You will have to sign up on their site, <a href="http://www.projecthoneypot.org/create_account.php" target="_blank">http://www.projecthoneypot.org/create_account.php</a>.<br />';
		echo '</p>';
		echo '<p>';
		echo '<span class="b">What are some score examples for Project Honey Pot?</span><br />';
		echo 'The Threat Rating is a logarithmic score -- much like the Richter\'s scale for measuring earthquakes.';
		echo 'A Threat Rating of 25 can be interpreted as the equivalent of sending 100 spam messages to a honey pot trap.<br />';
		echo '<div class="table">';
		echo '<table>';
		echo '<tbody>';
		echo '<tr class="first">';
		echo '<th>Threat Rating</th><th>IP that is as threatening as one that has sent</th>';
		echo '</tr>';
		echo '<tr>';
		echo '<td class="first">25</td><td class="t">100 spam messages</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td class="first">50</td><td class="t">10,000 spam messages</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td class="first">75</td><td class="t">1,000,000 spam messages</td>';
		echo '</tr>';
		echo '</tbody></table></div>';
		echo '</p>';
	}

	/**
	 * Metabox Display the General Options
	 *
	 * @param $data array The data is filled menu setup
	 *
	 */
	public function metaboxGeneral($data) {
		echo $this->printOptions($data['options_general'], $data['actual_options']);
	}

	/**
	 * Metabox Display the IP cache Options
	 *
	 * @param $data array The data is filled menu setup
	 *
	 */
	public function metaboxIPCache($data) {
		echo '<p>' .
		     __('To use IP caching you must enable it below and set the options. IP\'s are stored in the database so if you have a high traffic website the database can grow quickly',
		        'avh-fdas');
		echo $this->printOptions($data['options_ipcache'], $data['actual_options']);
	}

	/**
	 * Metabox Display the 3rd Party Project Honey Pot Options
	 *
	 * @param $data array The data is filled menu setup
	 *
	 */
	public function metaboxMenu3rdParty_PHP($data) {
		echo '<p>' .
		     __('To check a visitor at Project Honey Pot you must enable it below, you must also have an API key. You can get an API key by signing up for free at the <a href="http://www.projecthoneypot.org/create_account.php" target="_blank">Honey Pot Project</a>. Set the options to your own liking.',
		        'avh-fdas');
		echo $this->printOptions($data['options_php'], $data['actual_options']);
	}

	/**
	 * Metabox Display the 3rd Party Stop Forum Spam Options
	 *
	 * @param $data array The data is filled menu setup
	 *
	 */
	public function metaboxMenu3rdParty_SFS($data) {
		echo '<p>' .
		     __('To check a visitor at Stop Forum Spam you must enable it below. Set the options to your own liking.',
		        'avh-fdas');
		echo $this->printOptions($data['options_sfs'], $data['actual_options']);

		// echo '<p>' . __( 'Currently the plugin can not check with Stop Forum Spam untill a better solution has been coded.' );
		// echo __( 'I apologize for this and will be looking for a solutin in the short run.' ).'</p>';
	}

	/**
	 * Metabox Display the 3rd Party Project Honey Pot Options
	 *
	 * @param $data array The data is filled menu setup
	 *
	 */
	public function metaboxMenu3rdParty_SH($data) {
		// echo '<p>' . __('To check a visitor at Spamhaus you must enable it below.', 'avh-fdas');
		echo $this->printOptions($data['options_sh'], $data['actual_options']);
	}

	/**
	 * Metabox Overview of settings
	 */
	public function metaboxMenuOverview() {
		global $wpdb;
		echo '<p class="sub">';
		_e('Spam Statistics for the last 12 months', 'avh-fdas');
		echo '</p>';
		echo '<div class="table">';
		echo '<table>';
		echo '<tbody>';
		echo '<tr class="first">';
		$data       = $this->core->getData();
		$spam_count = $data['counters'];
		krsort($spam_count);
		$have_spam_count_data = false;
		$output               = '';
		$counter              = 0;
		foreach ($spam_count as $key => $value) {

			if ('190001' == $key || $counter >= 12) {
				continue;
			}

			$have_spam_count_data = true;
			$date                 = date_i18n('Y - F', mktime(0, 0, 0, substr($key, 4, 2), 1, substr($key, 0, 4)));
			$output .= '<tr>';
			$output .= '<td class="first b">' . $value . '</td>';
			$output .= '<td class="t">' . sprintf(__('Spam stopped in %s', 'avh-fdas'), $date) . '</td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';

			$counter ++;
		}
		if ( ! $have_spam_count_data) {
			$output .= '<tr>';
			$output .= '<td class="first b">' . __('No statistics yet', 'avh-fdas') . '</td>';
			$output .= '<td class="t"></td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';
		}
		echo $output;
		echo '</tbody></table></div>';
		echo '<div class="versions">';
		echo '<p>';
		$use_sfs = $this->core->getOptionElement('general', 'use_sfs');
		$use_php = $this->core->getOptionElement('general', 'use_php');
		$use_sh  = $this->core->getOptionElement('general', 'use_sh');
		if ($use_sfs || $use_php || $use_sh) {
			echo __('Checking with ', 'avh-fdas');
			echo($use_sfs ? '<span class="b">Stop Forum Spam</span>' : '');
			if ($use_php) {
				if ($use_sfs) {
					echo($use_sh ? ', ' : __(' and ', 'avh-fdas'));
				}
				echo '<span class="b">Project Honey Pot</span>';
			}
			if ($use_sh) {
				if ($use_php || $use_sfs) {
					echo __(' and ', 'avh-fdas');
				} else {
					echo ' ';
				}
				echo '<span class="b">Spamhaus</span>';
			}
		}
		echo '</p></div>';
		echo '<p class="sub">';
		_e('IP Cache Statistics', 'avh-fdas');
		echo '</p>';
		echo '<br/>';
		echo '<div class="versions">';
		echo '<p>';
		echo 'IP caching is ';
		if (0 == $this->core->getOptionElement('general', 'useipcache')) {
			echo '<span class="b">disabled</span>';
			echo '</p></div>';
		} else {
			echo '<span class="b">enabled</span>';
			echo '</p></div>';
			$count       = $wpdb->get_var("SELECT COUNT(ip) from $wpdb->avhfdasipcache");
			$count_clean = $wpdb->get_var("SELECT COUNT(ip) from $wpdb->avhfdasipcache WHERE spam=0");
			$count_spam  = $wpdb->get_var("SELECT COUNT(ip) from $wpdb->avhfdasipcache WHERE spam=1");
			if (false === $count) {
				$count = 0;
			}
			if (false === $count_clean) {
				$count_clean = 0;
			}
			if (false === $count_spam) {
				$count_spam = 0;
			}
			$output = '';
			echo '<div class="table">';
			echo '<table>';
			echo '<tbody>';
			echo '<tr class="first">';
			$output .= '<td class="first b">' . $count . '</td>';
			$text = _n('IP', 'IP\'s', $count, 'avh-fdas');
			$output .= '<td class="t">' . sprintf(__('Total of %s in the cache', 'avh-fdas'), $text) . ' </td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';
			$output .= '<td class="first b">' . $count_clean . '</td>';
			$text = _n('IP', 'IP\'s', $count_clean, 'avh-fdas');
			$output .= '<td class="t">' . sprintf(__('Total of %s classified as ham', 'avh-fdas'), $text) . '</td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';
			$output .= '<td class="first b">' . $count_spam . '</td>';
			$text = _n('IP', 'IP\'s', $count_spam, 'avh-fdas');
			$output .= '<td class="t">' . sprintf(__('Total of %s classified as spam', 'avh-fdas'), $text) . '</td>';
			$output .= '<td class="b"></td>';
			$output .= '<td class="last"></td>';
			$output .= '</tr>';
			$output .= '</tbody></table></div>';
			echo $output;
		}
	}

	/**
	 * Metabox Display the Whitelist Options
	 *
	 * @param $data array The data is filled menu setup
	 *
	 */
	public function metaboxWhiteList($data) {
		echo $this->printOptions($data['options_whitelist'], $data['actual_options']);
	}

	/**
	 * Sends back current IP cache total total and new page links if they need to be updated.
	 *
	 * Contrary to normal success AJAX response ("1"), die with time() on success.
	 *
	 * @param     $ip
	 * @param int $delta
	 *
	 */
	private function ajaxIpcacheLogResponse($ip, $delta = - 1) {
		$total    = array_key_exists("_total", $_POST) ? (int) $_POST['_total'] : false;
		$per_page = array_key_exists("_per_page", $_POST) ? (int) $_POST['_per_page'] : false;
		$page     = array_key_exists("_page", $_POST) ? (int) $_POST['_page'] : false;
		$url      = array_key_exists("_url", $_POST) ? esc_url_raw($_POST['_url']) : false;
		// JS didn't send us everything we need to know. Just die with success message
		if ( ! $total || ! $per_page || ! $page || ! $url) {
			die((string) time());
		}

		$total += $delta;
		if ($total < 0) {
			$total = 0;
		}

		$time = time(); // The time since the last comment count

		$x = new WP_Ajax_Response(array(
			                          'what'         => 'ipcachelog',
			                          'id'           => $ip,
			                          'supplemental' => array(
				                          'total_items_i18n' => sprintf(_n('1 item', '%s items', $total),
				                                                        number_format_i18n($total)),
				                          'total_pages'      => ceil($total / $per_page),
				                          'total_pages_i18n' => number_format_i18n(ceil($total / $per_page)),
				                          'total'            => $total,
				                          'time'             => $time
			                          )
		                          ));
		$x->send();
	}

	/**
	 * Display error message at bottom of comments.
	 *
	 * @param $msg string Error Message. Assumed to contain HTML and be sanitized.
	 */
	private function comment_footer_die($msg) {
		echo "<div class='wrap'><p>$msg</p></div>";
		die();
	}

	/**
	 * Displays the icon needed.
	 * Using this instead of core in case we ever want to show our own icons
	 *
	 * @param string $icon
	 *
	 * @return string
	 */
	private function displayIcon($icon) {
		return ('<div class="icon32" id="icon-' . $icon . '"><br/></div>');
	}

	/**
	 * Display WP alert
	 */
	private function displayMessage() {
		if ($this->message != '') {
			$message       = $this->message;
			$status        = $this->status;
			$this->message = $this->status = ''; // Reset
		}
		if (isset($message)) {
			$status = ($status != '') ? $status : 'updated fade';
			echo '<div id="message"	class="' . $status . '">';
			echo '<p><strong>' . $message . '</strong></p></div>';
		}
	}

	// ############ Admin WP Helper ##############

	/**
	 * Do the HTTP call to and report the spammer
	 *
	 * @param string $username
	 * @param string $email
	 * @param string $ip_addr
	 */
	private function handleReportSpammer($username, $email, $ip_addr) {
		if ( ! empty($email)) {
			$url  = 'http://www.stopforumspam.com/add.php';
			$call = wp_remote_post($url,
			                       array(
				                       'user-agent' => 'WordPress/AVH ' .
				                                       AVH_FDAS_Define::PLUGIN_VERSION .
				                                       '; ' .
				                                       get_bloginfo('url'),
				                       'body'       => array(
					                       'username' => $username,
					                       'ip_addr'  => $ip_addr,
					                       'email'    => $email,
					                       'api_key'  => $this->core->getOptionElement('sfs', 'sfsapikey')
				                       )
			                       ));
			if (is_wp_error($call) || 200 != $call['response']['code']) {
				$to      = get_option('admin_email');
				$subject = sprintf('[%s] AVH First Defense Against Spam - ' . __('Error reporting spammer', 'avh-fdas'),
				                   wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));
				if (is_wp_error($call)) {
					$message = $call->get_error_messages();
				} else {
					$message[] = $call['body'];
				}
				AVH_Common::sendMail($to, $subject, $message, $this->settings->getSetting('mail_footer'));
			}
		}
	}

	/**
	 * Display plugin Copyright
	 */
	private function printAdminFooter() {
		echo '<br class="clear" />';
		echo '<p class="footer_avhfdas">';
		printf('&copy; Copyright 2010-2013 <a href="http://blog.avirtualhome.com/" title="My Thoughts">Peter van der Does</a> | AVH First Defense Against Spam version %s',
		       AVH_FDAS_Define::PLUGIN_VERSION);
		echo '</p>';
		echo '<br class="clear" />';
	}

	/**
	 * Ouput formatted options
	 *
	 * @param $option_data array
	 * @param $option_actual
	 *
	 * @return string
	 */
	private function printOptions($option_data, $option_actual) {
		// Generate output
		$output = '';
		$output .= "\n" . '<table class="form-table avhfdas-options">' . "\n";
		foreach ($option_data as $option) {
			$section    = substr($option[0], strpos($option[0], '[') + 1);
			$section    = substr($section, 0, strpos($section, ']['));
			$option_key = rtrim($option[0], ']');
			$option_key = substr($option_key, strpos($option_key, '][') + 2);
			// Helper
			if ($option[2] == 'helper') {
				$output .= '<tr style="vertical-align: top;"><td class="helper" colspan="2">' .
				           $option[4] .
				           '</td></tr>' .
				           "\n";
				continue;
			}
			switch ($option[2]) {
				case 'checkbox':
					$input_type  = '<input type="checkbox" id="' .
					               $option[0] .
					               '" name="' .
					               $option[0] .
					               '" value="' .
					               esc_attr($option[3]) .
					               '" ' .
					               checked('1', $option_actual[ $section ][ $option_key ], false) .
					               ' />' .
					               "\n";
					$explanation = $option[4];
					break;
				case 'dropdown':
					$selvalue = explode('/', $option[3]);
					$seltext  = explode('/', $option[4]);
					$seldata  = '';
					foreach ((array) $selvalue as $key => $sel) {
						$seldata .= '<option value="' .
						            $sel .
						            '" ' .
						            selected($sel,
						                     $option_actual[ $section ][ $option_key ],
						                     false) .
						            ' >' .
						            ucfirst($seltext[ $key ]) .
						            '</option>' .
						            "\n";
					}
					$input_type  = '<select id="' .
					               $option[0] .
					               '" name="' .
					               $option[0] .
					               '">' .
					               $seldata .
					               '</select>' .
					               "\n";
					$explanation = $option[5];
					break;
				case 'text-color':
					$input_type  = '<input type="text" ' .
					               (($option[3] > 50) ? ' style="width: 95%" ' : '') .
					               'id="' .
					               $option[0] .
					               '" name="' .
					               $option[0] .
					               '" value="' .
					               esc_attr(stripcslashes($option_actual[ $section ][ $option_key ])) .
					               '" size="' .
					               $option[3] .
					               '" /><div class="box_color ' .
					               $option[0] .
					               '"></div>' .
					               "\n";
					$explanation = $option[4];
					break;
				case 'textarea':
					$input_type  = '<textarea rows="' .
					               $option[5] .
					               '" ' .
					               (($option[3] > 50) ? ' style="width: 95%" ' : '') .
					               'id="' .
					               $option[0] .
					               '" name="' .
					               $option[0] .
					               '" size="' .
					               $option[3] .
					               '" />' .
					               esc_attr(stripcslashes($option_actual[ $section ][ $option_key ])) .
					               '</textarea>';
					$explanation = $option[4];
					break;
				case 'text':
				default:
					$input_type  = '<input type="text" ' .
					               (($option[3] > 50) ? ' style="width: 95%" ' : '') .
					               'id="' .
					               $option[0] .
					               '" name="' .
					               $option[0] .
					               '" value="' .
					               esc_attr(stripcslashes($option_actual[ $section ][ $option_key ])) .
					               '" size="' .
					               $option[3] .
					               '" />' .
					               "\n";
					$explanation = $option[4];
					break;
			}
			// Additional Information
			$extra = '';
			if ($explanation) {
				$extra = '<br /><span class="description">' . __($explanation) . '</span>' . "\n";
			}
			// Output
			$output .= '<tr style="vertical-align: top;"><th align="left" scope="row"><label for="' .
			           $option[0] .
			           '">' .
			           __($option[1]) .
			           '</label></th><td>' .
			           $input_type .
			           '	' .
			           $extra .
			           '</td></tr>' .
			           "\n";
		}
		$output .= '</table>' . "\n";

		return $output;
	}

	/**
	 * Update the blacklist in the proper format
	 *
	 * @param array $blacklist
	 *
	 */
	private function setBlacklistOption($blacklist) {
		$data = $this->core->getData();
		natsort($blacklist);
		$blacklist_formatted        = implode("\r\n", $blacklist);
		$data['lists']['blacklist'] = $blacklist_formatted;
		$this->core->saveData($data);
	}
}
