=== Shipment Tracking DDT for WooCommercee ===
Tags: woocommerce, tracking, shipment, ddt
Requires at least: 6.0.1
Tested up to: 6.6.2
Stable tag: 1.5.2
WC requires at least: 9.0
WC tested up to: 9.3.3
Requires PHP: 8.0
Contributors: giangel84
Donate link: https://www.paypal.com/donate?hosted_button_id=DEFQGNU2RNQ4Y
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Manage the default chosen Payment method on checkout, easily! 

== Description ==

Add your Tracking code to WooCommerce orders and attach the DDT!


== Installation ==

* Install the Plugin and Activate it.
* Go to WooCommerce Email settings and adjust subject as you need.
* Go to WooCommerce->Settings->Tracking and set your desidered options.
* Open an order and add the tracking informations (Code and/or Tracking link) and upload your DDT (only PDF accepted, max 5MB file size), then set order as shipped, or send email with manual actions.
* That's all! Enjoy.

== Donate ==
If you like this plugin and want to support my work, you can also make a donation at this address: https://www.paypal.com/donate?hosted_button_id=DEFQGNU2RNQ4Y - Thank you very much!

== Changelog == 
2024-10-22 - version 1.5.2

* Fixed PHP Warning:  Undefined variable $tracking_info in /wc-frontend-functions.php on line 32
* Substitution of $_SESSION with transient for notices after email sent (this fix PHP Warning headers already sent in some conditions).