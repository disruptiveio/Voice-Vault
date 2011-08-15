=== Voice Vault ===
Contributors: disruptive.io, michaelmackus
Donate link: http://disruptive.io/
Tags: widget, plugin, social, audio, free, phono, tropo
Requires at least: 3.0.x
Tested up to: 3.2.3
Stable tag: 1.0

Adds voice verification features to your Wordpress site. 
	When a user requests a new password, the system calls the user and 
	asks them to "verify" their account using their voice. 
	Once verified, it gives them a new password to login to their account.

== Description ==

Adds voice verification features to your Wordpress site. 
	When a user requests a new password, the system calls the user and 
	asks them to "verify" their account using their voice. 
	Once verified, it gives them a new password to login to their account.

For more information check out 
	http://disruptive.io/?p=729

== Installation ==

1. Download the Plugin
2. Install the Plugin by copying it to your wp-content/plugins directory
3. Using the Plugins page in the WordPress Admin Area activate the plugin
4. Configure the Plugin by going to Settings -> Voice Vault
5. Set up a Tropo account, and assign it at least 1 phone number. 
6. Set up the configuration settings in "wp-content/plugins/voicevault/ivr/protected/config/main.php" (around line 85 is the "params" key with the configuration settings for the wordpress DB and the voicevault API).
7. Set your Tropo outbound call token in "wp-content/plugins/voicevault/voicevault.php".
8. Done!

Detailed installation instructions at
	http://disruptive.io/?p=729

== Frequently Asked Questions ==

= What are Voice Biometrics? =

Voice Vault uses Voice Biometrics to identify a user based on the uniqueness of their voice.
	This has several advantages over conventional text based passwords or pin codes.

== Screenshots ==

== Changelog ==

= 1.0 =
* Initial commit.

== Upgrade Notice ==

= 1.0 =
* Initial commit.
