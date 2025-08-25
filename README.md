=== Pay securely with Pine Labs Payment Gateway ===
Contributors: pine-labs
Tags: woocommerce, payment gateway, pine labs, checkout, secure payment
Requires at least: 5.0
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments on your WooCommerce store using the Pay securely with Pine Labs Payment Gateway.

== Description ==

**Pay securely with Pine Labs Payment Gateway** allows you to accept online payments on your WooCommerce-powered store using Pine Labs’ secure payment infrastructure.

Features:
- 🛡️ Secure online payments
- 💳 Supports multiple payment modes (Credit/Debit cards, UPI, Netbanking, Wallets, etc.)
- ⚙️ Seamless integration with WooCommerce checkout
- 🔄 Refund support
- 🌐 Tested on latest WordPress and WooCommerce versions

== Installation ==

1. Download the ZIP file from [GitHub Repository](https://github.com/plural-pinelabs/woocommerce-plugin).
2. Log in to your WordPress admin dashboard.
3. Navigate to **Plugins > Add New**.
4. Click **Upload Plugin**, then **Choose File** and select the downloaded `woocommerce-pinepg-gateway.zip`.
5. Click **Install Now** and then **Activate**.
6. Go to **WooCommerce > Settings > Payments**.
7. Enable **Pay securely with Pine Labs Payment Gateway** and configure your Merchant ID, credentials, and environment settings.

| Field | Description |
|-------|-------------|
| **Enable PinePG Payment** | Enable/disable the payment gateway |
| **Environment** | Choose between `Sandbox` and `Production` |
| **Merchant ID** | Your Pine Labs Merchant ID |
| **Client ID** | API Client ID provided by Pine Labs |
| **Client Secret** | API Client Secret |
| **Enable Down Payment** | Enable part-payment on checkout (optional) |

Plugin path after installation:
\wp-content\plugins\woocommerce-pinepg-gateway\index.php


== Frequently Asked Questions ==

= What is the plugin used for? =
This plugin integrates Pine Labs payment gateway with WooCommerce for accepting secure payments.

= Which WordPress and WooCommerce versions are supported? =
- ✅ WordPress 6.8.2, 6.7.x, 6.6.x
- ✅ WooCommerce 10.1.1, 10.0.x, 9.9.x
- ✅ PHP 7.4.x,8.2.12, 8.1.x, 8.0.x

== Changelog ==

= 1.0.0 =
* Initial release of the plugin.
* Supports payments and refunds via Pine Labs.
* Compatible with latest WooCommerce and WordPress versions.

== Support ==

For queries, issues or support requests, please contact:

📧 Email: [anoop.pandey@pinelabs.com](mailto:anoop.pandey@pinelabs.com)

== Screenshots ==

1. 🛍️ WooCommerce Payment Settings with Pine Labs option
2. 💳 Checkout page with Pine Labs selected
3. 🧾 Payment confirmation

