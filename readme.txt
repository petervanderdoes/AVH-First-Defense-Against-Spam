=== AVH First Defense Against Spam ===
Contributors: petervanderdoes
Donate link: http://blog.avirtualhome.com/wordpress-plugins/
Tags: spam, block, blacklist, whitelist, comment, anti-spam, comments
Requires at least: 2.8
Tested up to: 4.0
Stable tag: 3.7.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html

The AVH First Defense Against Spam plugin gives you the ability to block spammers before any content is served.

== Description ==

The AVH First Defense Against Spam plugin gives you the ability to block spammers before any content is served by identifying them at the Project Honey Pot, a local blacklist or the local IP cache.
Visitors trying to post a comment are checked at stopforumspam.com. Stop Forum Spam is not used to check before content is served due to the amount of requests, which overloads their infrastructure.


= Features =
* PHP 5 is required.
* The visitor's IP can be checked at the following third parties:
	* [Stop Forum Spam](http://www.stopforumspam.com).
	* [Project Honey Pot](http://www.projecthoneypot.org). An API key is needed to check the IP at this party. The key is free.
	* [Spamhaus](http://www.spamhaus.org). IP's are checked with the lists SBL and XBL.
* Spammers can be blocked based on the information supplied by the third party or by using a local blacklist.
* Comments made without a HTTP referrer can be blocked.
* Separate thresholds can be set for the following features:
	* Send an email to the board administrator with information supplied by the third party about the spammer.
	* Block the spammer before content is served.
* Bypass the checks for the IP at the third parties and the local blacklist, based on IP in the local whitelist.
* Ability to add single IP's and/or IP ranges to the blacklist and whitelist.
* When an IP is blocked a message can be displayed to the visitor with the reason why access was blocked.
* Report a spammer to Stop Forum Spam. A valid API key from Stop Forum Spam is necessary.
* Add a spammer to the local blacklist by clicking a link in the received email.
* IP Caching system.
* Use a honey pot from Project Honey Pot.
* Option to block spammers that access wp-comments-post.php directly by using a comment security check. An email can be send when the check fails.

Blocking a potential spammer before content is served has the following advantages:

1. It saves bandwidth.
1. It saves CPU cycles. The spammer is actually checked and blocked before WordPress starts building the page.
1. If you keep track of how many visitors your site has, either by using Google's Analytics, WP-Stats or any other one, it will give you a cleaner statistic of visits your site receives.

= Usage terms of the 3rd Parties =
Please read the usage terms of the 3rd party you are activating.
* [Stop Forum Spam](http://www.stopforumspam.com/license).
* [Project Honey Pot](http://www.projecthoneypot.org/terms_of_service_use.php).
* [Spamhaus](http://www.spamhaus.org/organization/dnsblusage.html).

= The IP Caching system. =
Stop Forum spam has set a limit on the amount of API calls you can make a day, currently it is set at 20,000 calls a day.
This means that if you don't use the Blacklist and/or Whitelist you are limited to 20,000 visits/day on your site. To overcome this possible problem I wrote an IP caching system.
If you use the caching system you still have a limit with Stop Forum Spam , but the limit is set to 20,000 unique visits/day.

The following IP's are cached locally:

1. Every IP identified by a 3rd party as spam and triggering the terminate-the-connection threshold.
1. Every clean IP.

Only returning IP's that were previously identified as spammer and who's connection was terminated will update their last seen date in the caching system.
Every day, once a day, a routine runs to remove the IP's who's last seen date is X amount of days older than the date the routine runs. You can set the days in the administration section of the plugin.
You can check the statistics to see how many IP's are in the database. If you have a busy site, with a lot of unique visitors, you might have to play with the "Days to keep in cache" setting to keep the size under control.

= Checking Order and Actions =
The plugin checks the visiting IP in the following order, only if that feature is enabled of course.

1. Whitelist - If found in the list skip the rest of the checks.
1. Blacklist - If found in the list terminate the connection.
1. IP Caching - If found in the list and it's spam terminate connection otherwise if it's clean skip the rest of the checks except when posting a comment then a Stop Forum Spam check will be done.
1. Stop Forum Spam - Only when the visiting IP is posting a comment. If found and it's spam terminate connection.
1. Project Honey Pot - If found and it's spam terminate connection, if found and it's set to be a search engine no further checks will be performed.
1. Spamhaus- If found and it's spam terminate connection.

To my knowledge this plugin is fully compatible with other anti-spam plugins, I have tested it with WP-Spamfree and Akismet.

== Installation ==

The AVH First Defense Against Spam plugin can be installed in 3 easy steps:

1. Unzip the "avh-first-defense-against-spam" archive and put the directory "avh-first-defense-against-spam" into your "plugins" folder (wp-content/plugins).
1. Activate the plugin.

== Frequently Asked Questions ==
= How can I translate the plugin in my language? =
I'm in the process of setting up a new site for translating all my plugins. Please keep an eye on my website for news on when it goes live.

= Is this plugin enough to block all spam? =
Unfortunately not.
I don't believe there is one solution to block all spam. Personally I have great success with the plugin in combination with Akismet.

= Does it conflicts with other spam solutions? =
I'm currently not aware of any conflicts with other anti-spam solutions.

= What does "Stop comments made without a referrer." mean =
On the Internet when you click on a link, directing you to another page a HTTP referrer is set. If you type in an address or select a bookmark from your browser this referrer is not set.
When somebody posts a comment on your blog, they click the submit button and are redirected to another page which handles the submission. At the moment the HTTP referrer is set.
The plugin checks if there is a referrer, if there's not the connection is terminated. For a more detailed write you can read an [article](http://blog.avirtualhome.com/2012/02/29/investigation-in-a-big-spammer/) I wrote on my site

= How do I define a range in the blacklist or white list? =
You can define two sorts of ranges:
From IP to IP. i.e. 192.168.1.100-192.168.1.105
A network in CIDR format. i.e. 192.168.1.0/24

= How do I report a spammer to Stop Forum Spam? =
You need to have an API key from Stop Forum Spam. If you do on the Edit Comments pages there is an extra option called, Report & Delete, in the messages identified as spam.

= How do I get a Stop Forum Spam API key? =
You will have to sign up on their site, [http://www.stopforumspam.com/signup](http://www.stopforumspam.com/signup).

= How do I get a Project Honey Pot API key? =
You will have to sign up on their site, [http://www.projecthoneypot.org/create_account.php](http://www.projecthoneypot.org/create_account.php) .

== Screenshots ==

1. This message is shown when you select the option to show a message and the visitors IP is found in the Stop Forum Spam database.

2. This message is shown when you select the option to show a message and the visitors IP is blacklisted.

3. The option Report & Delete

== Upgrade Notice ==
Starting with version 3.0 this plugin is for PHP5 only.

== Changelog ==
= Version 3.7.1 =
* PHP 5.3 is dead, long live PHP 5.3
  Forgot people still run 5.3 which doesn't support shorthand for arrays.

= Version 3.7.0 =
* Bugfix: Error on IP Cache log page

= Version 3.6.9 =
* The API URL changed for Stop Forum Spam.

= Version 3.6.8 =
* Drop the use of Spamhaus checking the CBL.

= Version 3.6.7 =
* Better check for supported MySQL version. (Props: doublesharp)

= Version 3.6.5 =
* "Display" Honeypot URL on the login and register screen.
* Randomize the way the Honeyput URL is displayed.

= Version 3.6.4 =
* Bugfix: Fix comment reply bug when replying with official WordPress App.

= Version 3.6.3 =
* Updated German language.

= Version 3.6.2 =
* Bugfix: Warning given during MU regsitration process.

= Version 3.6.1 =
* Add German translation
* Bugfix: Database error Duplicate entry.
  Sometimes this error occurs when MySQL hasn't written to the database yet and the spammer is back on the site already. It causes the logfile to be flooded with this error message.

= Version 3.6.0 =
* Changed behavior when spammer is detected during registration process.
  The connection will no longer be terminated, but instead it will return an error to the login process. This works better when a plugin uses AJAX for their registration process.
* Add compatibility with the plugin [Events Manager](http://wordpress.org/extend/plugins/events-manager/).

= Version 3.5.3 =
* Detecting remote IP returned wrong information when a loopback address was found.

= Version 3.5.2 =
* $wpdb->prepare fixes as per WordPress 3.5

= Version 3.5.1 =
* Reporting spam doesn't work in WordPress 3.5

= Version 3.5.0 =
* Do check during WordPress MU user validation
* Fixes Stop Forum Spam error message reporting.

= Version 3.4.2 =
* When call to Stop Forum Spam fails on during the check, no error message is reported.

= Version 3.4.1 =
* Bugfix: If WordPress registration page is embedded in a theme page, an error could possibly occur.

= Version 3.4 =
* Block comments made without a referrer.
* When using the plugin Hyper Cache, pages shown to blocked visitors will not be cached. The caching caused to show the blocked page to legit visitors.
* When no email is provided in a spam comment, you can't report it to Stop Forum Spam as this is against their policy.

= Version 3.3.1 =
* Bugfix: Problem with accessing the options pages.

= Version 3.3 =
* RFC: Add abillity to also check on email.
* Bugfix: Enqueue certain CSS files instead of loading them directly.
* Updated the language file.

= Version 3.2.6 =
* Bugfix: Correctly load the text domain file.

= Version 3.2.5 =
* Bugfix: HTML changes in the admin section to fit in with WordPress 3.3
* Bugfix: The columns in the admin section don't save, making certain columns disappear.
* Change the name of the Role and removed one Role.

= Version 3.2.4 =
* Bugfix: The nonce function used by WordPress is sometime valid for less than 24 hours causing a problem with adding IP's to the blacklist.

= Version 3.2.3 =
* Bugfix: Adding IP's to the blacklist by clicking on the link in the email fails.

= Version 3.2.2 =
* Bugfix: Get the correct visiting IP when using CloudFlare

= Version 3.2.1 =
* Bugfix: Fixes undefined method after WordPress 3.2 upgrade.

= Version 3.2 =
* Adds Immediate Actions (on-hover links) (Ham, Spam, Blacklist and Delete) to the IP Cache Log list below the IP.
* Adds bulk actions Ham, Spam and Blacklist to the IP Cache Log list.
* Adds the ability to check with Spamhaus.org
* When a spam check results in a termination of the connection, no more checks will be performed. This removes the option to receive Project Honey Pot information even when Stop Forum Spam determined the connection can be terminated.
* When Project Honey Pot determines the visiting IP is a known search engine no further checking is done.
* Removes the Screen layout option on the IP Cache Log page
* RFC: Option to add IP's to blacklist from the comments page.
* Bugfix: Report&Delete doesn't work on certain server configurations.
* Bugfix: Admin javascript does not load, causing the inability to close the metaboxes.


= Version 3.1.2 =
* An email is send when the report to Stop Forum Spam fails.
* Bugfix: IP is added to the cache even when the use of the IP cache is disabled.

= Version 3.1.1 =
* Bugfix: Can not add a site in WordPress Network setup when the plugin is active.

= Version 3.1 =
* New menu page: IP Cache Log. This gives the ability to manage the IP cache. This only works in WordPress 3.1 and higher.
* Improvement on checking for spam when a comment is posted.
* Reporting email will now say which post the spammer was trying to comment on.
* When reporting a spammer also set the offending IP as spammer in cache.
* Bugfix: When the visiting IP is not a public IP all the configured spam checks are performed. Private IP's can be assumed to be safe.
* Bugfix: The check for Stop Forum Spam is always performed even if set not to check with Stop Forum Spam.
* Bugfix: The results of a call to Stop Forum Spam are not evaluated.

= Version 3.0.5 =
* Bugfix: When saving options the options would be erased.
* Bugfix: Don't show the Project Honey Pot API key message when the option to check with Project Honey Pot is disabled.

= Version 3.0.4 =
* Bugfix: When the IP cache was disabled the cache would still be checked.
* Bugfix: Under certain server configurations, when Project Honey Pot could not be reached the IP to be checked was incorrect.
* Use minified CSS and Javascript except when WordPress is set to debugging.
* Adds option for localization. Translation are done through in [Launchpad](https://translations.launchpad.net/avhfirstdefenseagainstspam).

= Version 3.0.3 =
* Bugfix: With PHP 5.3 and up there was a problem with getting the visitors IP.

= Version 3.0.2 =
* The comment nonce can be activated on the General Options page in the administration section of the plugin.
* Only show the last 12 months of spam statistics on the Overview page.

= Version 3.0.1 =
* Pages shown to blocked visitors will not be cached. This is compatible with caching plugins W3 Total cache and WP Super Cache. The caching caused to show the blocked page to legit visitors.
* Removed comment nonce check due to reported problems.

= Version 3.0 =
* Plugin is for PHP5 only
* RFC: Spam check is performed when a user registers.
* Important!: When using a Honey Pot URL, change the option to be a URL only, the plugin will add the necessary HTML by default.
* Bugfix: On pages the nonce check would fail.
* Bugfix: Typo in window title for menu option overview
* Bugfix: Blogname would show up as html safe text
* Bugfix: Checking for spammers when a comment is posted did not utilize the IP cache.
* Plugin is refactored
* When an IP is reported to Stop Forum Spam, using Report & Delete, and IP caching is used, the IP is deleted from the cache as the IP is marked as ham in the cache.
* When an update is available it will show the changelog on the plugin screen of WordPress

= Version 2.3.2 =
* Bugfix: Commenting didn't work anymore.

= Version 2.3.1 =
* Stop Forum Spam is only checked when a comment is posted. All other checks are still done before content is served. The reason for this change is the amount of traffic this plugin caused at the server of Stop Forum Spam.

= Version 2.3 =
* Better error messages when a problem occurs with a call to Stop Forum Spam.
* Disabled Stop Forum Spam until a better solution is created.

= Version 2.2 =
* Changed initial settings for email to not send E-Mail. This is better for busy sites.
* Option for using a honey pot page by Project Honey Pot.
* Change in IP caching system. Added the field lastseen. This field will be updated if an IP returns which was previously identified as spam. The daily cleaning of the IP cache database will use this field to determine if the record will be deleted.
* Bugfix: Database version was never saved.
* Bugfix: When HTP connection failed, IP was added as no-spam in cache when cache is active.
* Bugfix: Uninstall didn't work.
* Bugfix: Validate admin fields.

= Version 2.1.2 =
* Bugfix: Settings link on plugin page was incorrect.

= Version 2.1.1 =
* Bugfix: Menu Option FAQ threw an error.

= Version 2.1 =
* Added an IP caching system.
* Administrative layout changes.
* Optional email can be send with information about the cron jobs of the plugin.
* Bugfix: The default setting to terminate the connection for Project Honey Pot was unrealistic.

= Version 2.0.1 =
* Bugfix: The function comment_footer_die was undefined.

= Version 2.0 =
* RFC: Optionally check the visitor at Project Honey Pot.
* RFC: Optionally receive error emails for failed calls to Stop Forum Spam. Error mails were always received.
* The plugin has a separate menu page.
* Added very simple statistics.
* Bugfix: Check Trackbacks/Pingbacks for spammers as well.
* Bugfix: Reporting a spammer without an email address failed. Stop Forum Spam changed their policy about reporting spammers without an email.

= Version 1.3 =
* Updated determination of users IP. Now also detects right IP if the server is running Apache with nginx proxy.

= Version 1.2.3 =
* Bugfix: HTTP Error messages didn't work properly
* Refactoring of some of the code.

= Version 1.2.2 =
* Bugfix: Trackback and Pingback comments were blocked as well

= Version 1.2.1 =
* Better implementation for getting the remote IP.

= Version 1.2 =
 * Added security to protect against spammers directly posting comments by accessing wp-comments-post.php.
 * An email can be received of a spammer trying posting directly. The email holds a link to report the spammer at Stop Forum Spam ( an API key is required).
 * The black and white list can now hold ranges besides single IP addresses.
 * Some small improvements and bug fixes.

= Version 1.1 =
* Ability to report a spammer to Stop Forum Spam if you sign up on their website and get an API key (it's free).
* Added a link in the emails to add an IP to the local blacklist.
* Bugfix: Uninstall did not work.
* RFC: A white list was added.

= Version 1.0 =
* Initial version

= Glossary =
* RFC: Request For Change. This indicates a new or improved function requested by one or more users.
