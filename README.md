=== Pay Securely with Pine Labs ===
Contributors: anooppandey
Tags: WooCommerce, payment gateway, Pine Labs, Plural, secure payment
Requires at least: 5.0
Tested up to: 6.3
Requires PHP: 7.4
Tested up to PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WooCommerce payment gateway integration for Pine Labs, providing a seamless and secure checkout experience.

== Description ==

Pay Securely with Pine Labs is a payment gateway plugin for WooCommerce that integrates with Pine Labs to provide a reliable and secure payment experience. With features like real-time payment processing, refunds, and sandbox support, this plugin ensures smooth payment management for your online store.

**Features:**
– Supports both Sandbox and Production environments.
– Processes payments securely using Pine Labs' Plural API.
– Enables refunds directly from WooCommerce.
– Adds order notes for transaction tracking.

For more details, visit the [plugin documentation](https://github.com/plural-pinelabs/woocommerce-plugin/).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/pay-securely-pine-labs` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to WooCommerce > Settings > Payments.
4. Enable the "Pay Securely with Pine Labs" payment method and configure the required fields:
   – Merchant ID
   – Client ID
   – Client Secret
   – Return URL
   – Environment (Sandbox/Production)

== Frequently Asked Questions ==

= How do I test the payment gateway? =
Set the environment to "Sandbox" in the plugin settings and use the provided test credentials from Pine Labs to simulate transactions.

= How do I process refunds? =
Refunds can be initiated directly from the WooCommerce order details page. The plugin communicates with Pine Labs' API to process refunds in real-time.

= Where can I find more documentation? =
Visit the [GitHub repository](https://github.com/plural-pinelabs/woocommerce-plugin/) for detailed setup and usage instructions.

== Screenshots ==

1. **Payment Gateway Settings**: Configure Merchant ID, Client ID, and other credentials.
2. **Checkout Page**: Customers can select "Pay Securely with Pine Labs" as their payment option.
3. **Order Details**: View transaction details and initiate refunds.

== Changelog ==

= 1.0.0 =
* Initial release.
* Supports payment and refund functionality.

== Upgrade Notice ==

= 1.0.0 =
This is the initial version of the plugin. Ensure your WooCommerce version is up-to-date for compatibility.
