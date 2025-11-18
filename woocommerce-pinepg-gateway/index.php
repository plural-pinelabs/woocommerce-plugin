<?php
/*
Plugin Name: Pay Securely with Pine Labs
Plugin URI: https://github.com/plural-pinelabs/woocommerce-plugin/
Description: A WooCommerce payment gateway integration for Pine Labs.
Version: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: anoop.pandey@pinelabs.com
Author URI: https://www.pinelabs.com/
*/

if (!defined('PINEPG_LOG_DIR')) {
    define('PINEPG_LOG_DIR', WP_CONTENT_DIR . '/pinepg-logs/');
}

add_filter( 'woocommerce_payment_gateways', 'pinepg_add_gateway_class' );
function pinepg_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_PinePg';
    return $gateways;
}

add_action( 'plugins_loaded', 'pinepg_init_gateway_class' );
function pinepg_init_gateway_class() {


    load_plugin_textdomain('wc-edgepg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
    class WC_PinePg extends WC_Payment_Gateway {


        private function log_api_data($type, $data) {
            // Ensure log directory exists
            if (!file_exists(PINEPG_LOG_DIR)) {
                wp_mkdir_p(PINEPG_LOG_DIR);
            }
            
            $log_file = PINEPG_LOG_DIR . 'pinepg-' . date('Y-m-d') . '.log';
            $timestamp = current_time('mysql');
            $message = "[" . $timestamp . "] " . strtoupper($type) . ": " . print_r($data, true) . "\n";
            
            // Write to log file
            file_put_contents($log_file, $message, FILE_APPEND | LOCK_EX);
        }

  private function is_part_payment_enabled() {
    return $this->get_option('enable_down_payment') === 'yes';
}


        public function __construct() {
            $this->id = 'pinepg';
            $this->has_fields = true;
            $this->method_title = 'Pay securely with Pine Labs';
            $this->method_description = 'Pay securely with Pine Labs';
            $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/edgepg.png';

             // Enable refund support
                $this->supports = array(
                    'products',
                    'refunds'  // Add this to support WooCommerce refunds
                );

                    // Hardcode title and description
            $this->title = 'Pay securely with Pine Labs';
            $this->description = 'Pay securely with Pine Labs';

            // Load settings
            $this->init_form_fields();
            $this->init_settings();

            // Get user settings
            $this->enabled = $this->get_option( 'enabled' );
            $this->merchant_id = $this->get_option( 'merchant_id' );
            $this->cookie = $this->get_option( 'cookie' );
            $this->client_id = $this->get_option( 'client_id' );
            $this->client_secret = $this->get_option( 'client_secret' );
            $this->environment = $this->get_option( 'environment' );
            $this->msg['message'] = "";
			$this->msg['class'] = ""; 

            // Save settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Callback for payment verification
            add_action( 'woocommerce_api_wc_pinepg', array( $this, 'handle_pinepg_callback' ) );
            add_action( 'woocommerce_api_wc_pinepg_webhook', array( $this, 'handle_pinepg_webhook' ) );
            add_action('wp_footer', array($this, 'inject_pinelabs_script'));
        }

        public function init_form_fields() {
    $this->form_fields = array(
        'enabled' => array(
            'title'       => 'Enable/Disable',
            'label'       => 'Enable PinePG Payment',
            'type'        => 'checkbox',
            'default'     => 'yes',
        ),
        'environment' => array(
            'title'       => 'Environment',
            'type'        => 'select',
            'options'     => array(
                'sandbox'    => 'Sandbox',
                'production' => 'Production',
            ),
            'default'     => 'sandbox',
            'description' => 'Select the environment to use (Sandbox or Production)',
        ),
        'merchant_id' => array(
            'title'       => 'Merchant ID',
            'type'        => 'text',
            'default'     => '',
            'description' => 'Enter your PinePG Merchant ID',
        ),
        'client_id' => array(
            'title'       => 'Client ID',
            'type'        => 'text',
            'default'     => '',
            'description' => 'Enter your PinePG Client ID',
        ),
        'client_secret' => array(
            'title'       => 'Client Secret',
            'type'        => 'text',
            'default'     => '',
            'description' => 'Enter your PinePG Client Secret',
        ),
        'enable_down_payment' => array(
            'title'       => 'Enable Down Payment',
            'label'       => 'Enable part payment on checkout',
            'type'        => 'checkbox',
            'default'     => 'no',
            'description' => 'Check this to enable part payment option via Pine Labs.',
        ),
    );
}



// Implement the refund functionality
public function process_refund( $order_id, $amount = null, $reason = '' ) {
    $this->log_api_data('refund_start', [
        'order_id' => $order_id,
        'amount' => $amount,
        'reason' => $reason
    ]);

    $order = wc_get_order( $order_id );
    $woocommerce_order_id = $order_id;

    // Log order details
    $this->log_api_data('refund_order_details', [
        'order_id' => $order_id,
        'order_status' => $order->get_status(),
        'order_total' => $order->get_total(),
        'payment_method' => $order->get_payment_method()
    ]);

    // Retrieve the Edge order ID from the order metadata
    $edge_order_id = $order->get_meta('edge_order_id');
    
    // Log all metadata to see what's stored
    $all_meta = $order->get_meta_data();
    $meta_data = [];
    foreach ($all_meta as $meta) {
        $meta_data[$meta->key] = $meta->value;
    }
    
    $this->log_api_data('refund_metadata', [
        'edge_order_id' => $edge_order_id,
        'all_metadata' => $meta_data
    ]);

    if ( empty( $edge_order_id ) ) {
        $this->log_api_data('refund_error', 'Edge order ID not found for this order');
        return new WP_Error( 'error', 'Edge order ID not found for this order.' );
    }

    $this->log_api_data('refund_processing', [
        'edge_order_id' => $edge_order_id,
        'amount' => $amount
    ]);

    // Call the refund API
    $response = $this->send_pinepg_refund_request( $edge_order_id, $amount, $reason );

    $this->log_api_data('refund_api_response', $response);

    // Extract relevant data
    if (isset($response['data'])) {
        $order_id = $response['data']['parent_order_id'] ?? 'unknown';
        $refund_id = $response['data']['order_id'] ?? 'unknown';
        $refund_status = $response['data']['status'] ?? 'unknown';
        $amount = $response['data']['order_amount']['value'] ?? 0;
        $showAmount = $amount / 100;
    } else {
        $this->log_api_data('refund_api_error', 'No data in API response');
        return new WP_Error( 'error', 'Refund failed: No response data from Pine Labs API' );
    }

    if ($refund_status === 'PROCESSED') {
        // Save refund ID in order metadata
        update_post_meta($woocommerce_order_id, '_refund_id', $refund_id);

        // Add a note to the order
        $order_note = sprintf(
            'Payment refund success via Edge by Pine Labs. Status: %s Pinelabs order id: %s and WooCommerce order id: %d and Pinelabs refund id: %s and amount is %d',
            $refund_status,
            $order_id,
            $woocommerce_order_id,
            $refund_id,
            $showAmount
        );
        
        // Get the WooCommerce order object
        $order = wc_get_order($woocommerce_order_id);
        
        // Add the note
        $order->add_order_note($order_note);

        // Optional: Mark the order as refunded
        $order->update_status('refunded');

        $this->log_api_data('refund_success', [
            'order_id' => $woocommerce_order_id,
            'refund_id' => $refund_id,
            'amount' => $showAmount,
            'status' => $refund_status
        ]);

        return 'Refund success: ' . $order_note;

    } else {
        $this->log_api_data('refund_failed', [
            'refund_status' => $refund_status,
            'error_message' => $response['message'] ?? 'Unknown error'
        ]);
        return new WP_Error( 'error', 'Refund failed: ' . ($response['message'] ?? 'Unknown error') );
    }
}

// Method to send the refund request to PinePG
public function send_pinepg_refund_request( $edge_order_id, $amount, $reason ) {
    $this->log_api_data('refund_request_start', [
        'edge_order_id' => $edge_order_id,
        'amount' => $amount,
        'reason' => $reason
    ]);

    $url = $this->environment === 'production'
        ? 'https://api.pluralpay.in/api/pay/v1/refunds/' . $edge_order_id
        : 'https://pluraluat.v2.pinepg.in/api/pay/v1/refunds/' . $edge_order_id;

    $this->log_api_data('refund_request_url', $url);

    $body = wp_json_encode( array(
        'merchant_order_reference' => uniqid(),
        'refund_amount' => array(
            'value' => (int) $amount * 100, // Assuming WooCommerce amount is in decimal
            'currency' => 'INR',
        ),
        'merchant_metadata' => array(
            'key1' => 'DD',
            'key_2' => 'XOF',
        ),
        'refund_reason' => $reason,
    ) );

    $this->log_api_data('refund_request_body', $body);

    $headers = array(
        'Merchant-ID' => $this->merchant_id,
        'Content-Type' => 'application/json',
    );

    $access_token = $this->get_access_token();
    if ( ! empty( $access_token ) ) {
        $headers['Authorization'] = 'Bearer ' . $access_token;
    } else {
        $this->log_api_data('refund_token_error', 'No access token available');
        return array(
            'response_code' => 500,
            'response_message' => 'Failed to get access token',
        );
    }

    $this->log_api_data('refund_request_headers', $headers);

    $response = wp_remote_post( $url, array(
        'method'  => 'POST',
        'body'    => $body,
        'headers' => $headers,
        'timeout' => 30,
    ) );

    if ( is_wp_error( $response ) ) {
        $this->log_api_data('refund_request_wp_error', [
            'error' => $response->get_error_message(),
            'code' => $response->get_error_code()
        ]);
        return array(
            'response_code' => 500,
            'response_message' => 'Internal Server Error: ' . $response->get_error_message(),
        );
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    
    $this->log_api_data('refund_response_full', [
        'response_code' => $response_code,
        'response_body' => $response_body,
        'response_headers' => wp_remote_retrieve_headers( $response )
    ]);

    $response_data = json_decode( $response_body, true );
    $this->log_api_data('refund_response_decoded', $response_data);
    
    return $response_data;
}
    


       public function process_payment($order_id) {
    $order = wc_get_order($order_id);
    $response = $this->send_pinepg_payment_request($order);

    if (isset($response['response_code']) && $response['response_code'] == 200) {
        // Save the Edge order ID
        $order_id_pg = $response['order_id'];
        $order->update_meta_data('edge_order_id', $order_id_pg);
        $order->save();

        // Store the redirect URL in session
        WC()->session->set('pinepg_redirect_url', $response['redirect_url']);
        WC()->session->set('pinepg_order_id', $order_id);

        // Return the redirect URL in the response for immediate use
        return array(
            'result'   => 'success',
            'redirect' => wc_get_checkout_url(), // Stay on checkout page
            'pinepg_redirect_url' => $response['redirect_url'], // Add this line
            'pinepg_order_id' => $order_id // Add this line
        );
    } else {
        wc_add_notice('Payment error: ' . $response['message'], 'error');
        return array(
            'result'   => 'failure',
            'redirect' => '',
        );
    }
}


        public function getCallbackUrl() {
            // Check if running on localhost
            if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
                // Local environment
                return 'http://localhost/wordpress/wc-api/WC_PinePg';
            }
        
            // Production or staging environment
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $domain = $protocol . $_SERVER['HTTP_HOST'];
            
            return $domain . '/wc-api/WC_PinePg';
        }



       



    public function send_pinepg_payment_request($order)
{
    $url = $this->environment === 'production'
        ? 'https://api.pluralpay.in/api/checkout/v1/orders'
        : 'https://pluraluat.v2.pinepg.in/api/checkout/v1/orders';

    $access_token = $this->get_access_token();
    if (!$access_token) {
        return [
            'response_code' => 500,
            'response_message' => 'Failed to retrieve access token',
        ];
    }

    $callback_url = $this->getCallbackUrl();
    
    // Process phone number - remove hardcoded default
    $telephone = $order->get_billing_phone();
    $onlyNumbers = preg_replace('/\D/', '', $telephone);
    
    // Remove country codes for India
    $countryCodes = ['91', '+91'];
    foreach ($countryCodes as $code) {
        $cleanCode = preg_replace('/\D/', '', $code);
        if (strpos($onlyNumbers, $cleanCode) === 0) {
            $onlyNumbers = substr($onlyNumbers, strlen($cleanCode));
            break;
        }
    }
    
    // Ensure we have exactly 10 digits, otherwise use empty (no hardcoded default)
    if (strlen($onlyNumbers) > 10) {
        $onlyNumbers = substr($onlyNumbers, -10);
    } elseif (strlen($onlyNumbers) < 10) {
        $onlyNumbers = ''; // Don't use hardcoded number
    }

    // Prepare addresses
    $billing_address_raw = [
        'address1' => $order->get_billing_address_1(),
        'pincode' => $order->get_billing_postcode(),
        'city' => $order->get_billing_city(),
        'state' => $order->get_billing_state(),
        'country' => $order->get_billing_country(),
    ];

    $shipping_address_raw = [
        'address1' => $order->get_shipping_address_1(),
        'pincode' => $order->get_shipping_postcode(),
        'city' => $order->get_shipping_city(),
        'state' => $order->get_shipping_state(),
        'country' => $order->get_shipping_country(),
    ];

    // Use whichever address is available
    $billing_address = array_filter($billing_address_raw) ?: $shipping_address_raw;
    $shipping_address = array_filter($shipping_address_raw) ?: $billing_address_raw;

    $sanitize_address = function ($address) {
        return [
            'address1' => isset($address['address1']) ? substr($address['address1'], 0, 95) : '',
            'pincode'  => $address['pincode'] ?? '',
            'city'     => $address['city'] ?? '',
            'state'    => $address['state'] ?? '',
            'country'  => $address['country'] ?? '',
        ];
    };

    $billing_address = $sanitize_address($billing_address);
    $shipping_address = $sanitize_address($shipping_address);

    $grand_total_paise = (int) round($order->get_total() * 100);
    $shipping_amount_paise = (int) round($order->get_shipping_total() * 100);
    $discount_total_paise = abs((int) round($order->get_discount_total() * 100));

    // Calculate total value of all items before discounts (including tax)
    $total_before_discount_paise = 0;
    foreach ($order->get_items() as $item) {
        $qty = $item->get_quantity();
        if ($qty <= 0) continue;

        $product = $item->get_product();
        if (!$product || !$product->exists()) continue;

        $item_total_before_discount = floatval($item->get_subtotal() + $item->get_subtotal_tax());
        $total_before_discount_paise += (int) round($item_total_before_discount * 100);
    }

    // Add shipping to total before discount
    $total_before_discount_paise += $shipping_amount_paise;

    // Prepare products array with distributed discounts
    $products = [];
    $total_product_value = 0;

    // Process regular items with distributed discounts
    foreach ($order->get_items() as $item) {
        $qty = $item->get_quantity();
        if ($qty <= 0) continue;

        $product = $item->get_product();
        if (!$product || !$product->exists()) continue;

        // Calculate item values
        $item_total_before_discount = floatval($item->get_subtotal() + $item->get_subtotal_tax());
        $item_total_after_discount = floatval($item->get_total() + $item->get_total_tax());
        
        // Calculate discount share for this item
        $item_share_of_total = $item_total_before_discount * 100 / ($total_before_discount_paise / 100);
        $item_discount_share_paise = (int) round($discount_total_paise * $item_share_of_total / 100);
        
        // Calculate final price per unit with distributed discount
        $unit_price_before_discount = $item_total_before_discount / $qty;
        $unit_discount_share = $item_discount_share_paise / 100 / $qty;
        $unit_price_after_discount = $unit_price_before_discount - $unit_discount_share;
        $unit_price_paise = (int) round($unit_price_after_discount * 100);

        if ($unit_price_paise <= 0) continue;

        $sku = $product->get_sku();
        if (empty($sku)) {
            $sku = 'ITEM_' . $item->get_id();
        }

        // Add product for each quantity
        for ($i = 0; $i < $qty; $i++) {
            $products[] = [
                "product_code" => $sku,
                "product_amount" => [
                    "value" => $unit_price_paise,
                    "currency" => "INR"
                ]
            ];
            $total_product_value += $unit_price_paise;
        }
    }

    // Add shipping as product if applicable (with its share of discount)
    if ($shipping_amount_paise > 0) {
        $shipping_share_of_total = ($shipping_amount_paise / 100) / ($total_before_discount_paise / 100) * 100;
        $shipping_discount_share_paise = (int) round($discount_total_paise * $shipping_share_of_total / 100);
        $shipping_final_paise = $shipping_amount_paise - $shipping_discount_share_paise;

        $products[] = [
            "product_code" => "shipping_charge",
            "product_amount" => [
                "value" => $shipping_final_paise,
                "currency" => "INR"
            ]
        ];
        $total_product_value += $shipping_final_paise;
    }

    // Check for rounding differences and add adjustment if needed
    $rounding_adjustment = $grand_total_paise - $total_product_value;
    if (abs($rounding_adjustment) > 0) {
        $products[] = [
            "product_code" => "rounding_adjustment",
            "product_amount" => [
                "value" => $rounding_adjustment,
                "currency" => "INR"
            ]
        ];
        $total_product_value += $rounding_adjustment;
    }

    // Prepare cart items with correct pricing
    $cartItems = [];
    foreach ($order->get_items() as $item) {
        $qty = $item->get_quantity();
        if ($qty <= 0) continue;

        $product = $item->get_product();
        if (!$product || !$product->exists()) continue;

        // Calculate correct prices including tax
        $item_total_before_discount = floatval($item->get_subtotal() + $item->get_subtotal_tax());
        $item_total_after_discount = floatval($item->get_total() + $item->get_total_tax());
        $unit_price_before_discount = $item_total_before_discount / $qty;
        $unit_price_after_discount = $item_total_after_discount / $qty;

        // Truncate item name to 100 characters
        $item_name = $product->get_name();
        if (strlen($item_name) > 100) {
            $item_name = substr($item_name, 0, 97) . '...';
        }

        // Truncate description to 200 characters and remove HTML tags
        $item_description = $product->get_short_description() ?: $product->get_name();
        $item_description = wp_strip_all_tags($item_description); // Remove HTML tags
        if (strlen($item_description) > 200) {
            $item_description = substr($item_description, 0, 197) . '...';
        }

        $cartItems[] = [
            "item_id" => (string) $item->get_id(),
            "item_name" => $item_name,
            "item_description" => $item_description,
            "item_details_url" => $product->get_permalink(),
            "item_image_url" => wp_get_attachment_url($product->get_image_id()) ?: '',
            "item_original_unit_price" => strval($unit_price_before_discount)*100, // Price before discounts
            "item_discounted_unit_price" => strval($unit_price_after_discount)*100, // Final price after discounts
            "item_quantity" => strval($qty),
            "item_currency" => "INR"
        ];
    }

    // Prepare customer data
    $customer_data = [
        "email_id" => $order->get_billing_email(),
        "first_name" => $order->get_billing_first_name(),
        "last_name" => $order->get_billing_last_name(),
        "customer_id" => (string) ($order->get_customer_id() ?: "0"),
        "is_edit_customer_details_allowed" => true
    ];

    // Add mobile number only if available
    if (!empty($onlyNumbers)) {
        $customer_data["mobile_number"] = $onlyNumbers;
    }

    // Add addresses if available
    if (!empty($billing_address['pincode'])) {
        $customer_data['billing_address'] = $billing_address;
        $customer_data['shipping_address'] = $shipping_address;
    }

    // Build the complete payload with Shopify structure
    $payload = [
        "merchant_order_reference" => $order->get_order_number() . '_' . gmdate("ymdHis"),
        "order_amount" => [
            "value" => $grand_total_paise,
            "currency" => "INR"
        ],
        "callback_url" => $callback_url,
        "integration_mode" => "IFRAME",
        "pre_auth" => false,
        "plugin_data" => [
            "plugin_type" => "WooCommerce",
            "plugin_version" => "V3"
        ],
        "purchase_details" => [
            "customer" => $customer_data,
            "products" => $products,
            "merchant_metadata" => [
                "express_checkout_allowed_action" => "checkoutCollectAddress, checkoutCollectMobile"
            ],
            "cart_details" => [
                "cart_items" => $cartItems
            ]
        ]
    ];

    // Optional: part payment toggle
    if ($this->is_part_payment_enabled()) {
        $payload['part_payment'] = true;
    }

    // Log the calculated totals for debugging
    $this->log_api_data('price_calculation', [
        'order_total_paise' => $grand_total_paise,
        'calculated_total_paise' => $total_product_value,
        'discount_total_paise' => $discount_total_paise,
        'difference' => $grand_total_paise - $total_product_value,
        'item_count' => count($order->get_items()),
        'product_count' => count($products)
    ]);

    $body = wp_json_encode($payload);

    $this->log_api_data('request', $body);

    $headers = [
        'Merchant-ID' => $this->merchant_id,
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $access_token,
    ];

    $response = wp_remote_post($url, [
        'method' => 'POST',
        'body' => $body,
        'headers' => $headers,
    ]);

    $this->log_api_data('response', $response);

    if (is_wp_error($response)) {
        return [
            'response_code' => 500,
            'response_message' => 'API error: ' . $response->get_error_message(),
        ];
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}


       public function handle_pinepg_callback() {
    // Get order_id and status from POST (iframe success) or from redirect flow
    $order_id_from_pg = sanitize_text_field(wp_unslash($_POST['order_id'] ?? ''));
    $status = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));
    
    // If no POST data, try GET (redirect flow)
    if (empty($order_id_from_pg)) {
        $order_id_from_pg = sanitize_text_field(wp_unslash($_GET['order_id'] ?? ''));
        $status = sanitize_text_field(wp_unslash($_GET['status'] ?? ''));
    }

    if (empty($order_id_from_pg)) {
        wc_add_notice(__('Error processing payment. Invalid order ID.', 'pinelabs-pinepg-gateway'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }

    // Get WooCommerce order ID from session (iframe flow) or cookie (redirect flow)
    $woocommerce_order_id = WC()->session->get('pinepg_order_id');
    
    if (empty($woocommerce_order_id)) {
        // Fallback to cookie method for redirect flow
        $cookie_name = 'woocommerce_' . $order_id_from_pg;
        if (isset($_COOKIE[$cookie_name])) {
            $woocommerce_order_id = sanitize_text_field(wp_unslash($_COOKIE[$cookie_name]));
        }
    }

    $actual_order_id = (int)$woocommerce_order_id;
    
    if (!$actual_order_id) {
        wc_add_notice(__('Order not found.', 'pinelabs-pinepg-gateway'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }

    $order = wc_get_order($actual_order_id);
    
    if (!$order) {
        wc_add_notice(__('Order not found.', 'pinelabs-pinepg-gateway'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }

    $this->log_api_data('callback_processing', [
        'pine_order_id' => $order_id_from_pg,
        'woocommerce_order_id' => $actual_order_id,
        'current_order_status' => $order->get_status(),
        'callback_status' => $status,
        'source' => !empty($_POST['order_id']) ? 'iframe' : 'redirect'
    ]);

    // Check if order is already paid
    if ($order->is_paid()) {
        $this->log_api_data('callback_order_already_paid', [
            'order_id' => $actual_order_id,
            'status' => $order->get_status()
        ]);
        
        // Clear session data
        WC()->session->__unset('pinepg_redirect_url');
        WC()->session->__unset('pinepg_order_id');
        
        wp_redirect($order->get_checkout_order_received_url());
        exit;
    }

    // Verify payment status with Pine Labs API
    $api_status = $this->call_enquiry_api($order_id_from_pg);

    $this->log_api_data('callback_api_status', [
        'order_id' => $actual_order_id,
        'api_status' => $api_status
    ]);

    // Check payment status
    if ($api_status === 'PROCESSED') {
        // Payment succeeded, complete the order
        $order->payment_complete();
        $order->add_order_note('Payment success via Edge by Pine Labs. Status: ' . $api_status . ' Pinelabs order id: ' . $order_id_from_pg . ' and woocommerce order id: ' . $actual_order_id);
        
        // ✅ EMPTY THE CART - Add this line
        WC()->cart->empty_cart();
        
        // Clear session data
        WC()->session->__unset('pinepg_redirect_url');
        WC()->session->__unset('pinepg_order_id');
        
        $this->log_api_data('callback_payment_success', [
            'order_id' => $actual_order_id,
            'pine_order_id' => $order_id_from_pg,
            'cart_emptied' => true
        ]);

        // If this is an AJAX call from iframe, return success
        if (wp_doing_ajax() || !empty($_POST['order_id'])) {
            echo 'SUCCESS';
            exit;
        }
        
        // Redirect to thank you page for redirect flow
        wp_redirect($order->get_checkout_order_received_url());
        exit;
    } else {
        // Payment failed
        wc_add_notice(__('Payment failed. Please try again.', 'pinelabs-pinepg-gateway'), 'error');
        $order->update_status('failed', 'Payment failed via Edge by Pine Labs.');
        $order->add_order_note('Payment failed via Edge by Pine Labs. Edge order id: ' . $order_id_from_pg);

        $this->log_api_data('callback_payment_failed', [
            'order_id' => $actual_order_id,
            'pine_order_id' => $order_id_from_pg,
            'status' => $api_status
        ]);

        wp_redirect(wc_get_cart_url());
        exit;
    }
}


// Add this to your class
public function __destruct() {
    // Clean up session data if order is completed
    if (is_order_received_page()) {
        WC()->session->__unset('pinepg_redirect_url');
        WC()->session->__unset('pinepg_order_id');
    }
}


public function inject_pinelabs_script() {
    if (!is_checkout()) {
        return;
    }
    ?>
    <style>
        .pinepg-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            flex-direction: column;
        }
        .pinepg-loader .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        .pinepg-loader .loading-text {
            font-size: 16px;
            color: #333;
            font-weight: bold;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .pinepg-payment-status {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 30px;
            border-radius: 10px;
            text-align: center;
            z-index: 10000;
            display: none;
            min-width: 300px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        .pinepg-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        .pinepg-failure {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        .pinepg-status-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }
        .pinepg-status-message {
            font-size: 18px;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        .pinepg-status-button {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 5px;
            transition: all 0.3s ease;
        }
        .pinepg-continue-btn {
            background: #28a745;
            color: white;
        }
        .pinepg-continue-btn:hover {
            background: #218838;
        }
        .pinepg-retry-btn {
            background: #007bff;
            color: white;
        }
        .pinepg-retry-btn:hover {
            background: #0056b3;
        }
        .pinepg-cart-btn {
            background: #6c757d;
            color: white;
        }
        .pinepg-cart-btn:hover {
            background: #545b62;
        }
    </style>

    <script src="https://checkout-staging.pluralonline.com/v3/web-sdk-checkout.js"></script>
    <script>
    // Loader functions
    function showLoader(message = 'Processing payment...') {
        const loader = document.getElementById('pinepg-loader');
        const loadingText = document.getElementById('pinepg-loading-text');
        loadingText.textContent = message;
        loader.style.display = 'flex';
    }

    function hideLoader() {
        const loader = document.getElementById('pinepg-loader');
        loader.style.display = 'none';
    }

    // Payment status functions
    function showPaymentStatus(isSuccess, message, orderId = null) {
        const statusDiv = document.getElementById('pinepg-payment-status');
        const statusIcon = document.getElementById('pinepg-status-icon');
        const statusMessage = document.getElementById('pinepg-status-message');
        const statusButtons = document.getElementById('pinepg-status-buttons');
        
        // Set status type
        statusDiv.className = isSuccess ? 'pinepg-payment-status pinepg-success' : 'pinepg-payment-status pinepg-failure';
        
        // Set icon
        statusIcon.innerHTML = isSuccess ? '✅' : '❌';
        
        // Set message
        statusMessage.innerHTML = message;
        
        // Set buttons
        if (isSuccess) {
            statusButtons.innerHTML = `
                <button class="pinepg-status-button pinepg-continue-btn" onclick="redirectToThankYou('${orderId}')">
                    Continue to Order Details
                </button>
            `;
        } else {
            statusButtons.innerHTML = `
                <button class="pinepg-status-button pinepg-retry-btn" onclick="retryPayment()">
                    Try Again
                </button>
                <button class="pinepg-status-button pinepg-cart-btn" onclick="redirectToCart()">
                    Return to Cart
                </button>
            `;
        }
        
        // Hide loader and show status
        hideLoader();
        statusDiv.style.display = 'block';
    }

    // Navigation functions
    function redirectToThankYou(orderId) {
        if (orderId && orderId !== '') {
            window.location.href = '<?php echo esc_url(wc_get_checkout_url()); ?>?order-received=' + orderId + '&key=wc_order_' + orderId;
        } else {
            // Fallback to generic thank you page
            window.location.href = '<?php echo esc_url(wc_get_checkout_url()); ?>';
        }
    }

    function redirectToCart() {
        window.location.href = '<?php echo esc_url(wc_get_cart_url()); ?>';
    }

    function retryPayment() {
        // Hide status and reload page to restart payment process
        document.getElementById('pinepg-payment-status').style.display = 'none';
        window.location.reload();
    }

    // Main payment functions
    function handleCheckout(redirectUrl, orderId) {
        console.log('Opening Pine Labs iframe with URL:', redirectUrl);
        
        // Show loader before iframe opens
        showLoader('Opening secure payment gateway...');
        
        const options = {
            redirectUrl,
            successHandler: async function (response) {
                console.log('Payment successful:', response);
                showLoader('Verifying payment...');
                
                try {
                    // Mark order as successful in WordPress
                    await markOrderAsSuccess(response, orderId);
                } catch (error) {
                    console.error('Error in success handler:', error);
                    showPaymentStatus(false, 'Payment verification failed. Please contact support.');
                }
            },
            failedHandler: async function (response) {
                console.log('Payment failed:', response);
                showPaymentStatus(false, 'Payment failed. Please try again with a different payment method.');
            },
        };

        const plural = new Plural(options);
        plural.open(options);
    }

    // Function to mark order as successful
    async function markOrderAsSuccess(paymentResponse, orderId) {
        try {
            console.log('Marking order as successful...', paymentResponse);
            showLoader('Finalizing payment...');
            
            const response = await fetch('<?php echo home_url("/wc-api/WC_PinePg"); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'order_id': paymentResponse.order_id,
                    'status': 'PROCESSED'
                })
            });
            
            const result = await response.text();
            console.log('Order status update response:', result);
            
            // Show success message
            showPaymentStatus(
                true, 
                'Payment Successful!<br>Your order has been confirmed.',
                orderId
            );
            
        } catch (error) {
            console.error('Error updating order status:', error);
            showPaymentStatus(false, 'Payment completed but verification failed. Please contact support with your order details.');
        }
    }

    jQuery(document).ready(function($) {
        // Store the current order ID to handle retries
        let currentOrderId = null;
        
        $(document).on('click', '#place_order', function(e) {
            if ($('#payment_method_pinepg').is(':checked')) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                console.log('Pine Labs payment selected, getting redirect URL...');
                showLoader('Processing your order...');
                
                // Show loading state on button
                var $button = $('#place_order');
                var originalText = $button.text();
                $button.prop('disabled', true).text('Processing...');
                
                // Get all form data
                var formData = $('form.checkout').serialize();
                
                // Submit via AJAX but stay on same page
                $.ajax({
                    type: 'POST',
                    url: wc_checkout_params.checkout_url,
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        console.log('AJAX Response:', response);
                        
                        if (response.result === 'success') {
                            console.log('Checkout processed successfully');
                            
                            // Method 1: Check if redirect URL is in the AJAX response (first click)
                            if (response.pinepg_redirect_url && response.pinepg_order_id) {
                                console.log('Using redirect URL from AJAX response');
                                currentOrderId = response.pinepg_order_id;
                                handleCheckout(response.pinepg_redirect_url, response.pinepg_order_id);
                            } 
                            // Method 2: Check if we have a stored order ID from previous attempt
                            else if (currentOrderId) {
                                console.log('Using stored order ID for retry:', currentOrderId);
                                <?php 
                                $redirect_url = WC()->session->get('pinepg_redirect_url');
                                if ($redirect_url): ?>
                                    var redirectUrl = "<?php echo esc_js($redirect_url); ?>";
                                    console.log('Using redirect URL from session for retry');
                                    handleCheckout(redirectUrl, currentOrderId);
                                <?php else: ?>
                                    console.log('No redirect URL found in session for retry');
                                    showPaymentStatus(false, 'Unable to initialize payment. Please refresh the page and try again.');
                                <?php endif; ?>
                            }
                            // Method 3: Check session data with delay (fallback)
                            else {
                                console.log('No redirect URL in AJAX response, checking session...');
                                
                                // Small delay to ensure session is properly set
                                setTimeout(function() {
                                    <?php 
                                    $redirect_url = WC()->session->get('pinepg_redirect_url');
                                    $order_id = WC()->session->get('pinepg_order_id');
                                    if ($redirect_url && $order_id): ?>
                                        var redirectUrl = "<?php echo esc_js($redirect_url); ?>";
                                        var sessionOrderId = "<?php echo esc_js($order_id); ?>";
                                        console.log('Using redirect URL from session after delay');
                                        currentOrderId = sessionOrderId;
                                        handleCheckout(redirectUrl, sessionOrderId);
                                    <?php else: ?>
                                        console.log('No redirect URL found in session after delay');
                                        showPaymentStatus(false, 'Payment initialization failed. Please try again.');
                                    <?php endif; ?>
                                }, 300);
                            }
                        } else {
                            console.log('Checkout failed in AJAX response');
                            showPaymentStatus(false, 'Order processing failed: ' + (response.messages || 'Please try again.'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log('AJAX error:', error);
                        showPaymentStatus(false, 'Network error. Please check your connection and try again.');
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(originalText);
                    }
                });
                
                return false;
            }
        });
        
        // Handle page refresh/retry scenarios
        $(document).on('pagehide beforeunload', function() {
            // Clear stored data on page navigation
            currentOrderId = null;
        });
    });
    </script>

    <!-- Loader HTML -->
    <div id="pinepg-loader" class="pinepg-loader">
        <div class="spinner"></div>
        <div id="pinepg-loading-text" class="loading-text">Processing payment...</div>
    </div>

    <!-- Payment Status HTML -->
    <div id="pinepg-payment-status" class="pinepg-payment-status">
        <div id="pinepg-status-icon" class="pinepg-status-icon"></div>
        <div id="pinepg-status-message" class="pinepg-status-message"></div>
        <div id="pinepg-status-buttons" class="pinepg-status-buttons"></div>
    </div>
    <?php
}




        private function call_enquiry_api($order_id_from_pg) { 
            $url = $this->environment === 'production'
                ? 'https://api.pluralpay.in/api/pay/v1/orders/' . $order_id_from_pg
                : 'https://pluraluat.v2.pinepg.in/api/pay/v1/orders/' . $order_id_from_pg;
        
            $access_token = $this->get_access_token();
            error_log('Access Token: ' . $access_token);
        
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . trim($access_token),
                    'Content-Type' => 'application/json',
                )
            ));

            
        
            if (is_wp_error($response)) {
                error_log('API Error: ' . $response->get_error_message());
                return;
            }
        
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
        
            error_log('API Response: ' . print_r($response_data, true));
        
            if (isset($response_data['data'])) {
                return $response_data['data']['status'];
            }
        
            return null;
        }
        


        // Function to retrieve the access token from the auth API
            private function get_access_token() {
                
                $url = $this->environment === 'production'
            ? 'https://api.pluralpay.in/api/auth/v1/token'
            : 'https://pluraluat.v2.pinepg.in/api/auth/v1/token';

                // Prepare the request body
                $body = wp_json_encode(array(
                    'client_id' => $this->client_id,
                    'client_secret' => $this->client_secret,
                    'grant_type' => 'client_credentials',
                ));

                // Make the API request to get the token
                $response = wp_remote_post( $url, array(
                    'method'    => 'POST',
                    'body'      => $body,
                    'headers'   => array(
                        'Content-Type' => 'application/json',
                    ),
                ));

                if (is_wp_error($response)) {
                    //error_log('Error retrieving access token: ' . $response->get_error_message());
                    return false;
                }

                $response_body = wp_remote_retrieve_body($response);
                $response_data = json_decode($response_body, true);
                

                // Return the access token if available
                if (isset($response_data['access_token'])) {
                    return $response_data['access_token'];
                } else {
                    //error_log('Error retrieving access token: ' . print_r($response_data, true));
                    return false;
                }
            }


            public function handle_pinepg_webhook() {
    // Get the raw input data
    $raw_data = file_get_contents('php://input');
    $headers = $this->get_all_headers();
    
    // Log webhook received
    $this->log_api_data('webhook_received', [
        'headers' => $headers,
        'raw_data' => $raw_data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    try {
        // Verify webhook signature first
        $signature_verification = $this->verify_webhook_signature($headers, $raw_data);
        if (!$signature_verification['valid']) {
            $this->log_api_data('webhook_signature_failed', [
                'error' => $signature_verification['error'],
                'headers' => $headers
            ]);
            
            wp_send_json_error('Invalid webhook signature', 401);
            return;
        }

        $this->log_api_data('webhook_signature_success', 'Signature verified successfully');

        $data = json_decode($raw_data, true);
        
        // Log webhook data
        $this->log_api_data('webhook_data_decoded', ['data' => $data]);

        if (($data['event_type'] ?? '') !== 'ORDER_PROCESSED') {
            $this->log_api_data('webhook_non_processed_event', [
                'event_type' => $data['event_type'] ?? 'UNKNOWN'
            ]);
            
            wp_send_json_success('Webhook received but not processed (not ORDER_PROCESSED)');
            return;
        }

        $order_id = $data['data']['order_id'] ?? null;
        $status = $data['data']['status'] ?? null;
        $merchant_order_reference = $data['data']['merchant_order_reference'] ?? null;

        if (!$order_id || !$status || !$merchant_order_reference) {
            $this->log_api_data('webhook_missing_fields', ['data' => $data]);
            throw new Exception('Missing order_id, status or merchant_order_reference in webhook data');
        }

        $this->log_api_data('webhook_processing', [
            'order_id' => $order_id,
            'status' => $status,
            'merchant_order_reference' => $merchant_order_reference
        ]);

        if ($status === 'PROCESSED') {
            $this->process_successful_webhook($order_id, $status, $merchant_order_reference);
        } else {
            $this->log_api_data('webhook_non_processed_status', [
                'order_id' => $order_id,
                'status' => $status
            ]);
        }

        $this->log_api_data('webhook_processing_completed', 'Webhook processed successfully');
        wp_send_json_success('Webhook processed successfully');

    } catch (Exception $e) {
        $this->log_api_data('webhook_processing_failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'order_id' => $order_id ?? 'unknown',
            'merchant_order_reference' => $merchant_order_reference ?? 'unknown'
        ]);
        
        wp_send_json_error($e->getMessage(), 500);
    }
}


private function verify_webhook_signature($headers, $raw_data) {
    try {
        // Convert all header keys to lowercase for consistent access
        $headers = array_change_key_case($headers, CASE_LOWER);
        
        $webhook_id = $headers['webhook-id'] ?? '';
        $webhook_timestamp = $headers['webhook-timestamp'] ?? '';
        $webhook_signature = $headers['webhook-signature'] ?? '';

        $this->log_api_data('webhook_signature_start', [
            'webhook_id' => $webhook_id,
            'webhook_timestamp' => $webhook_timestamp,
            'webhook_signature' => $webhook_signature,
            'body_length' => strlen($raw_data),
            'all_headers' => $headers // Log all headers for debugging
        ]);

        // Check if required headers are present
        if (empty($webhook_id) || empty($webhook_timestamp) || empty($webhook_signature)) {
            throw new Exception('Missing required webhook headers: webhook-id, webhook-timestamp, webhook-signature');
        }

        // Rest of your existing signature verification code remains the same...
        // Validate timestamp (prevent replay attacks)
        $current_timestamp = time();
        $timestamp = (int) $webhook_timestamp;
        $max_age = 300; // 5 minutes in seconds

        if ($timestamp < ($current_timestamp - $max_age)) {
            throw new Exception('Webhook timestamp is too old');
        }

        if ($timestamp > ($current_timestamp + $max_age)) {
            throw new Exception('Webhook timestamp is in the future');
        }

        // Continue with your existing signature verification logic...
        $secret_key = $this->client_secret;
        
        if (empty($secret_key)) {
            throw new Exception('Client secret is not configured in plugin settings');
        }

        $this->log_api_data('webhook_secret_retrieved', [
            'secret_key_length' => strlen($secret_key),
            'environment' => $this->environment
        ]);

        // Base64 encode the secret key as required by Pine Labs
        $base64_secret = base64_encode($secret_key);
        
        $this->log_api_data('webhook_secret_prepared', [
            'base64_secret_length' => strlen($base64_secret)
        ]);

        // Generate the signature to compare
        $signed_content = $webhook_id . '.' . $webhook_timestamp . '.' . $raw_data;
        
        // Base64 decode the secret (as per Pine Labs documentation)
        $secret_bytes = base64_decode($base64_secret);
        
        if ($secret_bytes === false) {
            throw new Exception('Failed to base64 decode the secret key');
        }

        // Generate HMAC SHA-256 signature
        $mac = hash_hmac('sha256', $signed_content, $secret_bytes, true);
        $expected_signature = base64_encode($mac);
        
        $this->log_api_data('webhook_signature_generated', [
            'signed_content_length' => strlen($signed_content),
            'expected_signature' => $expected_signature
        ]);

        // Extract the actual signature from the header (remove 'v1,' prefix if present)
        $actual_signature = str_replace('v1,', '', $webhook_signature);
        
        $this->log_api_data('webhook_signature_comparison', [
            'expected' => $expected_signature,
            'actual' => $actual_signature
        ]);

        // Use constant-time comparison to prevent timing attacks
        $signature_valid = hash_equals($expected_signature, $actual_signature);
        
        if (!$signature_valid) {
            throw new Exception('Signature mismatch - possible tampering detected');
        }

        $this->log_api_data('webhook_signature_valid', 'Signature validation successful');
        
        return [
            'valid' => true,
            'error' => null
        ];

    } catch (Exception $e) {
        $this->log_api_data('webhook_signature_error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return [
            'valid' => false,
            'error' => $e->getMessage()
        ];
    }
}


/**
 * Process successful webhook payment
 */
private function process_successful_webhook($pine_order_id, $status, $merchant_order_reference) {
    // Extract WooCommerce order ID from merchant_order_reference
    // Format: order_number_timestamp (e.g., 69_251104104402)
    $parts = explode('_', $merchant_order_reference);
    
    if (count($parts) < 2) {
        throw new Exception("Invalid merchant_order_reference format: " . $merchant_order_reference);
    }
    
    $woocommerce_order_number = $parts[0] ?? null;
    
    if (!$woocommerce_order_number) {
        throw new Exception("Could not extract WooCommerce order number from: " . $merchant_order_reference);
    }

    $this->log_api_data('webhook_order_extraction', [
        'merchant_order_reference' => $merchant_order_reference,
        'extracted_order_number' => $woocommerce_order_number,
        'parts' => $parts
    ]);

    // Find order by order number
    $query = new WC_Order_Query(array(
        'limit' => 1,
        'return' => 'ids',
        'order_number' => $woocommerce_order_number,
    ));
    
    $orders = $query->get_orders();
    
    if (empty($orders)) {
        // Alternative: try to find by order ID directly
        $order = wc_get_order($woocommerce_order_number);
    } else {
        $order_id = $orders[0];
        $order = wc_get_order($order_id);
    }
    
    if (!$order) {
        throw new Exception("WooCommerce order not found for number: " . $woocommerce_order_number);
    }

    $woocommerce_order_id = $order->get_id();

    $this->log_api_data('webhook_order_found', [
        'woocommerce_order_id' => $woocommerce_order_id,
        'order_number' => $woocommerce_order_number,
        'order_status' => $order->get_status()
    ]);

    // Check if order is already paid
    if ($order->is_paid()) {
        $this->log_api_data('webhook_order_already_paid', [
            'order_id' => $woocommerce_order_id,
            'pine_order_id' => $pine_order_id
        ]);
        return;
    }

    // Update order status
    $order->payment_complete();
    
    // Update pine labs order ID if not set
    $current_pine_order_id = $order->get_meta('edge_order_id');
    if (empty($current_pine_order_id)) {
        $order->update_meta_data('edge_order_id', $pine_order_id);
    }
    
    // Add order note
    $order_note = sprintf(
        'Payment confirmed via Pine Labs webhook. Status: %s. Pine Labs Order ID: %s. Merchant Reference: %s',
        $status,
        $pine_order_id,
        $merchant_order_reference
    );
    
    $order->add_order_note($order_note);
    $order->save();

     $cookie_name = 'woocommerce_' . $pine_order_id;
    if (isset($_COOKIE[$cookie_name])) {
        setcookie($cookie_name, '', time() - 3600, "/"); // Expire the cookie
    }

    $this->log_api_data('webhook_order_updated', [
        'woocommerce_order_id' => $woocommerce_order_id,
        'pine_order_id' => $pine_order_id,
        'status' => $status,
        'merchant_reference' => $merchant_order_reference,
        'new_status' => $order->get_status()
    ]);
}


/**
 * Get all headers with proper case handling
 */
private function get_all_headers() {
    $headers = [];
    
    // If getallheaders() is available, use it and normalize keys
    if (function_exists('getallheaders')) {
        $all_headers = getallheaders();
        foreach ($all_headers as $key => $value) {
            $headers[strtolower($key)] = $value;
        }
        return $headers;
    }
    
    // Fallback: parse from $_SERVER
    foreach ($_SERVER as $name => $value) {
        if (substr($name, 0, 5) == 'HTTP_') {
            $header_key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
            $headers[strtolower($header_key)] = $value;
        }
    }
    
    // Also check for specific headers that might not start with HTTP_
    $special_headers = [
        'CONTENT_TYPE' => 'content-type',
        'CONTENT_LENGTH' => 'content-length',
    ];
    
    foreach ($special_headers as $server_key => $header_key) {
        if (isset($_SERVER[$server_key])) {
            $headers[$header_key] = $_SERVER[$server_key];
        }
    }
    
    return $headers;
}


    }
}
