=== Smart Throttle ===
Contributors: mohanjith
Tags: throttle, comment, spam
Donate link: http://mohanjith.com/wordpress.html
Requires at least: 2.7
Tested up to: 3.0.0
Stable tag: trunk

Smart Throttle plugin dynamically throttles comment flood.

== Description ==
Smart Throttle plugin dynamically adjusts the time out between comments. Time out is decided by the rate of comments in the last hour.

== Installation ==

1. Upload `smart-throttle` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= How do you decide on the time out? =

We use the rate of comments in the last hour. See bellow for a
break down.

 * Upto 5 comments/h - 15s
 * From 6-14 comments/h 5s increment
 * Every comment/h there after 60s increment.

= Can I change the time out? =

Yes. Go to Settings -> Smart Throttle in wp-admin and change the time out.
We believe our time out break down is well balanced ;)

== Screenshots ==

1. Smart Throttle time out definition page

== PHP Version ==

PHP 5+

== ChangeLog ==

**Version 1.0.2**

* Compatible with Wordpress 3.0

**Version 1.0.1**

* Few improvements

**Version 1.0.0**

* Initial release

