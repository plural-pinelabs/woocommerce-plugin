=== Pay securely with Pine Labs Payment Gateway ===
Contributors: pine-labs
Tags: woocommerce, payment gateway, pine labs, checkout, secure payment, iframe, redirect
Requires at least: 5.0
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments on your WooCommerce store using the Pay securely with Pine Labs Payment Gateway with both Redirect and Iframe integration options.

== Description ==

**Pay securely with Pine Labs Payment Gateway** allows you to accept online payments on your WooCommerce-powered store using Pine Labs' secure payment infrastructure. Choose between **Redirect** or **Iframe** integration based on your business needs.

## 🎯 Integration Options

### 🔄 Redirect Integration
- Customer is redirected to Pine Labs payment page
- Simple and reliable integration
- Recommended for most use cases

**GitHub Branch**: [`main`](https://github.com/plural-pinelabs/woocommerce-plugin/tree/main)

### 🖼️ Iframe Integration  
- Payment form opens in an iframe on your checkout page
- Customer stays on your website throughout payment process
- Enhanced user experience with seamless integration

**GitHub Branch**: [`iframe`](https://github.com/plural-pinelabs/woocommerce-plugin/tree/iframe)

## ✨ Features

- 🛡️ Secure online payments
- 💳 Supports multiple payment modes (Credit/Debit cards, UPI, Netbanking, Wallets, etc.)
- ⚙️ Seamless integration with WooCommerce checkout
- 🔄 Full refund support
- 🌐 Tested on latest WordPress and WooCommerce versions
- 📱 Mobile-responsive design
- 🔒 PCI DSS compliant
- 🔔 Automatic webhook handling for payment notifications

== Installation ==

### For Redirect Integration:
1. Download from: [GitHub Main Branch](https://github.com/plural-pinelabs/woocommerce-plugin/tree/main)
2. Use the main branch for traditional redirect flow

### For Iframe Integration:
1. Download from: [GitHub Iframe Branch](https://github.com/plural-pinelabs/woocommerce-plugin/tree/iframe)
2. Use the iframe branch for embedded payment experience

### Installation Steps:
1. Download the ZIP file from the appropriate GitHub branch
2. Log in to your WordPress admin dashboard
3. Navigate to **Plugins > Add New**
4. Click **Upload Plugin**, then **Choose File** and select the downloaded ZIP file
5. Click **Install Now** and then **Activate**
6. Go to **WooCommerce > Settings > Payments**
7. Enable **Pay securely with Pine Labs Payment Gateway** and configure your settings

## ⚙️ Configuration

| Field | Description | Required |
|-------|-------------|----------|
| **Enable PinePG Payment** | Enable/disable the payment gateway | Yes |
| **Environment** | Choose between `Sandbox` (testing) and `Production` (live) | Yes |
| **Merchant ID** | Your Pine Labs Merchant ID | Yes |
| **Client ID** | API Client ID provided by Pine Labs | Yes |
| **Client Secret** | API Client Secret provided by Pine Labs | Yes |
| **Enable Down Payment** | Enable part-payment on checkout (optional) | No |

## 🔗 Webhook Configuration

### Webhook URL
The plugin automatically handles webhooks at the following endpoint:
https://yourdomain.com/wc-api/WC_PinePg_webhook/


### Setting up Webhooks in Pine Labs Dashboard

1. **Log in** to your Pine Labs Merchant Dashboard
2. Navigate to **Settings** > **Webhooks**
3. **Add New Webhook** with the following details:
   - **Webhook URL**: `https://yourdomain.com/wc-api/wc_pinepg_webhook`
   - **Events**: Select `ORDER_PROCESSED`
   - **Status**: Enable

### Webhook Events Handled
- `ORDER_PROCESSED`: Automatically updates order status when payment is successful
- Payment failure notifications
- Refund status updates

### Webhook Security
- Signature verification for all incoming webhooks
- Timestamp validation to prevent replay attacks
- Automatic retry mechanism for failed webhooks

**Plugin path after installation:**
`wp-content/plugins/woocommerce-pinepg-gateway/`

## 🔧 Technical Details

### Supported Payment Methods:
- Credit Cards (Visa, MasterCard, Rupay, AMEX)
- Debit Cards
- UPI (All major providers)
- Net Banking (50+ banks)
- Digital Wallets
- EMI Options

### Webhook Support:
- Automatic payment status updates
- Order confirmation handling
- Refund status notifications
- Secure signature verification

== Frequently Asked Questions ==

### = Which integration should I choose? =
- **Redirect**: Better for compatibility and simpler implementation
- **Iframe**: Better for user experience, keeps customers on your site

### = What are the system requirements? =
- ✅ WordPress 6.8.2, 6.7.x, 6.6.x
- ✅ WooCommerce 10.1.1, 10.0.x, 9.9.x
- ✅ PHP 7.4.x, 8.2.12, 8.1.x, 8.0.x

### = How do refunds work? =
Refunds are fully supported through both integration methods. You can process partial or full refunds directly from the WooCommerce order admin panel.

### = Is the plugin PCI DSS compliant? =
Yes, the plugin leverages Pine Labs' PCI DSS Level 1 certified infrastructure for secure payment processing.

### = Can I switch between integration methods? =
Yes, but you'll need to reinstall the appropriate branch and reconfigure the settings.

### = Do I need to configure webhooks manually? =
The plugin automatically provides the webhook URL, but you need to register it in your Pine Labs merchant dashboard for payment status updates.

### = What if my webhook fails? =
The plugin includes retry logic and logs all webhook activities for debugging. Failed webhooks will be retried by Pine Labs.

== Changelog ==

### = 1.0.0 =
* Initial release of the plugin
* Supports both Redirect and Iframe integration
* Full payment and refund functionality
* Compatible with latest WooCommerce and WordPress versions
* Webhook support for payment confirmation with signature verification

== Support ==

For technical support, integration queries, or issues:

📧 **Email**: [anoop.pandey@pinelabs.com](mailto:anoop.pandey@pinelabs.com)  
🐛 **GitHub Issues**: [Report Issues](https://github.com/plural-pinelabs/woocommerce-plugin/issues)

## 🔒 Security Features

- Token-based authentication
- Secure API communication
- No sensitive data stored locally
- Regular security updates
- Webhook signature verification
- Timestamp validation

== Screenshots ==

1. 🛍️ WooCommerce Payment Settings with Pine Labs option
2. 💳 Checkout page with Pine Labs selected (both Redirect and Iframe views)
3. 🧾 Payment confirmation and order completion
4. 🔄 Refund management in WooCommerce admin
5. ⚙️ Plugin configuration settings
6. 🔔 Webhook configuration in Pine Labs dashboard

## 📚 Additional Resources

- [Pine Labs Documentation](https://developer.pinelabsonline.com/docs/checkout-infinity)
- [WooCommerce Integration Guide](https://docs.woocommerce.com)
- [Troubleshooting Guide](https://github.com/plural-pinelabs/woocommerce-plugin)
- [Plugin Setup Guide](https://developer.pinelabsonline.com/docs/woocommerce)

## 🚀 Quick Start Guide

1. **Choose Integration**: Decide between Redirect or Iframe
2. **Install Plugin**: Download and install from appropriate GitHub branch
3. **Configure Settings**: Enter your Merchant ID, Client ID, and Client Secret
4. **Setup Webhook**: Register the webhook URL in Pine Labs dashboard
5. **Test in Sandbox**: Verify everything works in test environment
6. **Go Live**: Switch to production environment

### Webhook Testing
- Test webhooks using Pine Labs sandbox environment
- Monitor webhook logs in `wp-content/pinepg-logs/`
- Verify order status updates after successful payments

---

**Note**: Always test in Sandbox environment before going live with production payments. Ensure webhooks are properly configured for automatic order status updates.