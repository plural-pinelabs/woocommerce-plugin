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
            $order = wc_get_order( $order_id );
            $woocommerce_order_id = $order_id;
        
            // Retrieve the Edge order ID from the order metadata
            $edge_order_id = $order->get_meta('edge_order_id');
        
            if ( empty( $edge_order_id ) ) {
                return new WP_Error( 'error', 'Edge order ID not found for this order.' );
            }
        
            // Call the refund API
            $response = $this->send_pinepg_refund_request( $edge_order_id, $amount, $reason );
           

            // Extract relevant data
            $order_id = $response['data']['parent_order_id'];
            $refund_id = $response['data']['order_id'];
            $refund_status = $response['data']['status'];
            $amount = $response['data']['order_amount']['value'];
            $showAmount=$amount/100;

            
        
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


                        return 'Refund success: ' . $order_note;


            } else {
                return new WP_Error( 'error', 'Refund failed: ' . $response['message'] );
            }
        }
        




    // Method to send the refund request to PinePG
    public function send_pinepg_refund_request( $edge_order_id, $amount, $reason ) {
        $url = $this->environment === 'production'
            ? 'https://api.pluralpay.in/api/pay/v1/refunds/' . $edge_order_id
            : 'https://pluraluat.v2.pinepg.in/api/pay/v1/refunds/' . $edge_order_id;
    
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
    
        $headers = array(
            'Merchant-ID' => $this->merchant_id,
            'Content-Type' => 'application/json',
        );
    
        $access_token = $this->get_access_token();
        if ( ! empty( $access_token ) ) {
            $headers['Authorization'] = 'Bearer ' . $access_token;
        }
    
        $response = wp_remote_post( $url, array(
            'method'  => 'POST',
            'body'    => $body,
            'headers' => $headers,
        ) );
    
        if ( is_wp_error( $response ) ) {
            return array(
                'response_code' => 500,
                'response_message' => 'Internal Server Error',
            );
        }
    
        $response_body = wp_remote_retrieve_body( $response );
        $this->log_api_data('refund_response', $response_body);
        return json_decode( $response_body, true );
    }
    


        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $response = $this->send_pinepg_payment_request( $order );


            if ( isset( $response['response_code'] ) && $response['response_code'] == 200 ) {
                // Set cookie with order ID as name and token as value
                $order_id_pg=$response['order_id'];

                // Save the Edge order ID in the WooCommerce order metadata
                $order->update_meta_data('edge_order_id', $order_id_pg);
                $order->save();
                // Save the Edge order ID in the WooCommerce order metadata

                //this is for enquiry api token is save correspondant to pg order id
                $cookie_name = 'order_' . $order_id_pg;
                $cookie_value = $response['token'];
                setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/");
                //this is for enquiry api token is save correspondant to pg order id

                //this is to update order status actual woocommerce orderid is save in cookies to update status after returning from pg
                $cookie_name = 'woocommerce_' . $order_id_pg;
                $cookie_value = $order_id;
                setcookie($cookie_name, $cookie_value, time() + (86400 * 30), "/");
                //this is to update order status actual woocommerce orderid is save in cookies to update status after returning from pg



                //error_log('Cookie set: ' . $cookie_name . ' = ' . $cookie_value);
                
                return array(
                    'result'   => 'success',
                    'redirect' => $response['redirect_url'],
                );
            } else {
                wc_add_notice( 'Payment error: ' . $response['message'], 'error' );
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
   $telephone = $order->get_billing_phone();
// Remove all non-digit characters
$onlyNumbers = preg_replace('/\D/', '', $telephone);

// Process the phone number
if (empty($onlyNumbers)) {
    $onlyNumbers = '9999999999';
} else {
    // Remove country codes for India
    $countryCodes = ['91', '+91'];
    foreach ($countryCodes as $code) {
        $cleanCode = preg_replace('/\D/', '', $code);
        if (strpos($onlyNumbers, $cleanCode) === 0) {
            $onlyNumbers = substr($onlyNumbers, strlen($cleanCode));
            break;
        }
    }
    
    // Ensure we have exactly 10 digits
    if (strlen($onlyNumbers) > 10) {
        $onlyNumbers = substr($onlyNumbers, -10); // Take last 10 digits
    } elseif (strlen($onlyNumbers) < 10) {
        $onlyNumbers = '9999999999'; // Default if too short
    }
}

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

    $products = [];
    $total_product_value = 0;
    $total_item_price_incl_tax = 0;
    $coupon_discount = $discount_total_paise / 100.0;

    // Calculate total incl tax value
    foreach ($order->get_items() as $item) {
        $qty = $item->get_quantity();
        if ($qty <= 0) continue;

        $item_total = floatval($item->get_total() + $item->get_total_tax());
        $total_item_price_incl_tax += $item_total;
    }

    foreach ($order->get_items() as $item) {
        $qty = $item->get_quantity();
        if ($qty <= 0) continue;

        $product = $item->get_product();
        if (!$product || !$product->exists()) continue;

        $item_total = floatval($item->get_total() + $item->get_total_tax());
        $item_discount = abs(floatval($item->get_subtotal() - $item->get_total()));
        $sku = $product->get_sku();

        if (empty($sku)) {
            $sku = 'ITEM_' . $item->get_id() . '_' . rand(10000, 99999);
        }

        $cart_discount_share = $total_item_price_incl_tax > 0
            ? ($item_total / $total_item_price_incl_tax) * $coupon_discount
            : 0;

        $total_discount = $item_discount + $cart_discount_share;
        $final_item_price = ($item_total - $total_discount) / $qty;
        $final_item_price = max(0, $final_item_price);
        $final_item_price_paise = (int) round($final_item_price * 100);

        if ($final_item_price_paise <= 0) {
            continue; // Skip 0-value products
        }

        for ($i = 0; $i < $qty; $i++) {
            $products[] = [
                'product_code' => $sku,
                'product_amount' => [
                    'value' => $final_item_price_paise,
                    'currency' => 'INR',
                ],
            ];
            $total_product_value += $final_item_price_paise;
        }
    }

    // Add shipping as product
    if ($shipping_amount_paise > 0) {
        $products[] = [
            'product_code' => 'shipping_charge',
            'product_amount' => [
                'value' => $shipping_amount_paise,
                'currency' => 'INR',
            ],
        ];
        $total_product_value += $shipping_amount_paise;
    }

    // Rounding adjustment
    $rounding_adjustment = $grand_total_paise - $total_product_value;
    if ($rounding_adjustment > 0) {
        $products[] = [
            'product_code' => 'rounding_adjustment',
            'product_amount' => [
                'value' => $rounding_adjustment,
                'currency' => 'INR',
            ],
        ];
        $total_product_value += $rounding_adjustment;
    }

    // Final mismatch check
    if (abs($grand_total_paise - $total_product_value) > 1) {
        $this->log_api_data('error', "Amount mismatch! Order total: $grand_total_paise, Product total: $total_product_value");
        return [
            'response_code' => 500,
            'response_message' => 'Amount mismatch',
        ];
    }

    // Determine if virtual and no address
    $all_virtual = true;
    foreach ($order->get_items() as $item) {
        $product = $item->get_product();
        if ($product && !$product->is_virtual()) {
            $all_virtual = false;
            break;
        }
    }

    $customer = [
        'email_id' => $order->get_billing_email(),
        'first_name' => $order->get_billing_first_name(),
        'last_name' => $order->get_billing_last_name(),
        'mobile_number' => $onlyNumbers,
    ];

    if (!$all_virtual || !empty($billing_address['pincode'])) {
        $customer['billing_address'] = $billing_address;
        $customer['shipping_address'] = $shipping_address;
    }

    $payload = [
        'merchant_order_reference' => $order->get_order_number() . '_' . gmdate("ymdHis"),
        'order_amount' => [
            'value' => $grand_total_paise,
            'currency' => 'INR',
        ],
        'callback_url' => $callback_url,
        'pre_auth' => false,
        'integration_mode' => "REDIRECT",
        "plugin_data" => [
            "plugin_type" => "WooCommerce",
            "plugin_version" => "V3"
        ],
        'purchase_details' => [
            'customer' => $customer,
            'products' => $products,
        ],
    ];

    // Optional: part payment toggle
    if ($this->is_part_payment_enabled()) {
        $payload['part_payment'] = true;
    }

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
    // Verify nonce before processing data
    if (sanitize_text_field(wp_unslash(isset($_POST['order_id'])))) {
        $order_id_from_pg = sanitize_text_field(wp_unslash($_POST['order_id']));
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
    } else {
        wc_add_notice(__('Error processing payment. Invalid order ID.', 'pinelabs-pinepg-gateway'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }

    if ($order_id_from_pg != '') {
        // Construct the cookie name
        $cookie_name = 'woocommerce_' . $order_id_from_pg;
        
        // Get WooCommerce order ID from cookie
        if (isset($_COOKIE[$cookie_name])) {
            $woocommerce_order_id = sanitize_text_field(wp_unslash($_COOKIE[$cookie_name]));
            $actual_order_id = (int)$woocommerce_order_id;
            
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
                'callback_status' => $status
            ]);

            // Check if order is already paid (processed by webhook)
            if ($order->is_paid()) {
                $this->log_api_data('callback_order_already_paid', [
                    'order_id' => $actual_order_id,
                    'status' => $order->get_status()
                ]);
                
                // Redirect to thank you page since order is already paid
                wp_redirect($order->get_checkout_order_received_url());
                exit;
            }

            // If order is not paid, check payment status
            $api_status = $this->call_enquiry_api($order_id_from_pg);

            $this->log_api_data('callback_api_status', [
                'order_id' => $actual_order_id,
                'api_status' => $api_status
            ]);

            // Check payment status
            if ($api_status === 'PROCESSED') {
                // Payment succeeded, complete the order
                $order->payment_complete();
                $order->add_order_note('Payment success via Edge by Pine Labs callback. Status: ' . $api_status . ' Pinelabs order id: ' . $order_id_from_pg . ' and woocommerce order id: ' . $actual_order_id);
                
                $this->log_api_data('callback_payment_success', [
                    'order_id' => $actual_order_id,
                    'pine_order_id' => $order_id_from_pg
                ]);

                // Redirect to thank you page
                wp_redirect($order->get_checkout_order_received_url());
                exit;
            } else {
                // Payment failed, add an error notice
                wc_add_notice(__('Payment failed. Please try again.', 'pinelabs-pinepg-gateway'), 'error');

                // Update order status to failed
                $order->update_status('failed', 'Payment failed via Edge by Pine Labs.');
                $completeText = 'Payment failed via Edge by Pine Labs. Please try again. Edge order id: ' . $order_id_from_pg . ' and WooCommerce order id: ' . $actual_order_id;
                $order->add_order_note($completeText);

                $this->log_api_data('callback_payment_failed', [
                    'order_id' => $actual_order_id,
                    'pine_order_id' => $order_id_from_pg,
                    'status' => $api_status
                ]);

                // Redirect to cart
                wp_redirect(wc_get_cart_url());
                exit;
            }
        } else {
            // Handle case where cookie is not found
            wc_add_notice(__('Error processing payment. Session expired.', 'pinelabs-pinepg-gateway'), 'error');
            
            $this->log_api_data('callback_cookie_missing', [
                'pine_order_id' => $order_id_from_pg,
                'cookie_name' => $cookie_name
            ]);
            
            wp_redirect(wc_get_cart_url());
            exit;
        }
    } else {
        // Handle case where order ID is not provided
        wc_add_notice(__('Error processing payment. Invalid order ID.', 'pinelabs-pinepg-gateway'), 'error');
        wp_redirect(wc_get_cart_url());
        exit;
    }
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
