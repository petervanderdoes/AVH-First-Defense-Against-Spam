=== AVH First Defense Against Spam ===
Contributors: petervanderdoes
Donate link: http://blog.avirtualhome.com/wordpress-plugins/
Tags: spam, block
Requires at least: 2.7
Tested up to: 2.7.1
Stable tag: 1.0

The AVH First Defense Against Spam plugin gives you the ability to block spammers before any content is served.

== Description ==

The AVH First Defense Against Spam plugin gives you the ability to block spammers before any content is served.
Spammers are identified by checking if the visitors IP exists in a database served by stopforumspam.com.


= Features =
* Based on the frequency a spammer has been reported at stopforumspam.com, a separate threshold can be set for the following features:
	* Send an email to the board administrator with information about the spammer.
	* Block the spammer before content is server. 
* When an IP is blocked a message can be displayed to the visitor with the reason why access was blocked and a link to stopforumspam.com if they want to resolve the issue.

Blocking a potential spammer before content is served has the following advantages:
* Save bandwidth.
* Save CPU cycles. The spammer is actually checked and blocked before WordPress starts building the page.
* If you keep track of how many visitors your site has, either by using Google's Analytics, WP-Stats or any other one, it will give you a cleaner statistic of visits your site receives. 

This plugin is fully compatible with other anti-spam plugins, I have tested it with WP-Spamfree and Akismet.

The plugin also gives you some extra tips and tricks to stop spam by editing your htaccess file. To access them go to the settings of the plugin and click Tips and Tricks

== Installation ==

The AVH First Defense Against Spam plugin can be installed in 3 easy steps:

1. Unzip the "avh-fdas" archive and put the directory "avh-fdas" into your "plugins" folder (wp-content/plugins).
1. Activate the plugin.

== Frequently Asked Questions ==


== Screenshots ==

None

== Arbitrary section ==
* Version 1.0
	* Initial version