=== AVH First Defense Against Spam ===
Contributors: petervanderdoes
Donate link: http://blog.avirtualhome.com/wordpress-plugins/
Tags: spam, block, blacklist, whitelist, comment
Requires at least: 2.7
Tested up to: 2.8
Stable tag: 1.3

The AVH First Defense Against Spam plugin gives you the ability to block spammers before any content is served.

== Description ==

The AVH First Defense Against Spam plugin gives you the ability to block spammers before any content is served.
Spammers are identified by checking if the visitors IP exists in a database served by stopforumspam.com or by a local blacklist.


= Features =
* Based on the frequency a spammer has been reported at stopforumspam.com, a separate threshold can be set for the following features:
	* Send an email to the board administrator with information about the spammer.
	* Block the spammer before content is server.
* Block spammers that access wp-comments-post.php directly by using a comment security check. An email can be send when the check fails.
* Block visitors based on IP address by using a local blacklist.
* Bypass the checks for the IP at Stop Forum Spam and the local blacklist based on IP in the local whitelist.
* Ability to add single IP's and/or IP ranges to the blacklist and whitelist.
* When an IP is blocked a message can be displayed to the visitor with the reason why access was blocked and a link to stopforumspam.com if they want to resolve the issue.
* Report a spammer to Stop Forum Spam. A valid API key from Stop Forum Spam is necessary.
* Add a spammer to the local blacklist by clicking a link in the received email.

 
Blocking a potential spammer before content is served has the following advantages:

1. It saves bandwidth.
1. It saves CPU cycles. The spammer is actually checked and blocked before WordPress starts building the page.
1. If you keep track of how many visitors your site has, either by using Google's Analytics, WP-Stats or any other one, it will give you a cleaner statistic of visits your site receives. 

To my knowledge this plugin is fully compatible with other anti-spam plugins, I have tested it with WP-Spamfree and Akismet.

The plugin also gives you some extra tips and tricks to stop spam by editing your htaccess file. To access them go to the settings of the plugin and click Tips and Tricks.
If you have more tricks feel free to email them to me and I will add them in the Tips and Tricks section with full credits of course.

== Installation ==

The AVH First Defense Against Spam plugin can be installed in 3 easy steps:

1. Unzip the "avh-first-defense-against-spam" archive and put the directory "avh-first-defense-against-spam" into your "plugins" folder (wp-content/plugins).
1. Activate the plugin.

== Frequently Asked Questions ==
= Is this plugin enough to block all spam? =
Unfortunately not.
I don't believe there is one solution to block all spam. Personally I have great success with the plugin in combination with Akismet.

= Does it conflicts with other spam solutions? =
I'm currently not aware of any conflicts with other anti-spam solutions.

= How do I define a range in the blacklist or white list? =
You can define two sorts of ranges:
From IP to IP. i.e. 192.168.1.100-192.168.1.105
A network in CIDR format. i.e. 192.168.1.0/24

= How do I report a spammer to Stop Forum Spam? =
You need to have an API key from Stop Forum Spam. If you do on the Edit Comments pages there is an extra option called, Report & Delete, in the messages identified as spam.

= How do I get a Stop Forum Spam API key? =
You will have to sign up on their site, http://www.stopforumspam.com/signup.

== Screenshots ==

1. This message is shown when you select the option to show a message and the visitors IP is found in the Stop Forum Spam database. 

2. This message is shown when you select the option to show a message and the visitors IP is blacklisted.

3. The option Report & Delete

== Arbitrary section ==
* Version 1.3
	* Updateded determination of users ip. Now also detects right IP if Apache with nginx proxy is used.
	
* Version 1.2.3
	* Bugfix: HTTP Error messages didn't work properly
	* Refactoring of some of the code.
	
* Version 1.2.2
	* Bugfix: Trackback and Pingback comments were blocked as well
	
* Version 1.2.1
	* Better implementation for getting the remote IP.
	
* Version 1.2
 	* Added security to protect against spammers directly posting comments by accessing wp-comments-post.php.
 	* An email can be received of a spammer trying posting directly. The email holds a link to report the spammer at Stop Forum Spam ( an API key is required).
 	* The black and white list can now hold ranges besides single IP addresses.
 	* Some small improvements and bug fixes.
 
* Version 1.1
	* Ability to report a spammer to Stop Forum Spam if you sign up on their website and get an API key (it's free).
	* Added a link in the emails to add an IP to the local blacklist.
	* Bugfix: Uninstall did not work.
	* RFC: A white list was added.

* Version 1.0
	* Initial version