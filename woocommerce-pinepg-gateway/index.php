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

add_filter( 'woocommerce_payment_gateways', 'pinepg_add_gateway_class' );
function pinepg_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_PinePg';
    return $gateways;
}

add_action( 'plugins_loaded', 'pinepg_init_gateway_class' );
function pinepg_init_gateway_class() {


    load_plugin_textdomain('wc-edgepg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
    class WC_PinePg extends WC_Payment_Gateway {

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
            $this->return_url = $this->get_option( 'return_url' );
            $this->environment = $this->get_option( 'environment' );
            $this->msg['message'] = "";
			$this->msg['class'] = ""; 

            // Save settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Callback for payment verification
            add_action( 'woocommerce_api_wc_pinepg', array( $this, 'handle_pinepg_callback' ) );
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
                'return_url' => array(
                'title'       => 'Return URL',
                'type'        => 'text',
                'default'     => '',
                'description' => 'Enter the URL to which customers will be redirected after payment',
                )

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
            $onlyNumbers = preg_replace('/\D/', '', $telephone) ?: '9999999999';
        
            // Function to sanitize address fields
            $sanitize_address = function ($address) {
                return [
                    'address1' => isset($address['address1']) ? substr($address['address1'], 0, 95) : '',
                    'pincode' => $address['pincode'] ?? '',
                    'city' => $address['city'] ?? '',
                    'state' => $address['state'] ?? '',
                    'country' => $address['country'] ?? '',
                ];
            };
        
            // Get billing and shipping addresses
            $billing_address = [
                'address1' => $order->get_billing_address_1(),
                'pincode' => $order->get_billing_postcode(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'country' => $order->get_billing_country(),
            ];
        
            $shipping_address = [
                'address1' => $order->get_shipping_address_1(),
                'pincode' => $order->get_shipping_postcode(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'country' => $order->get_shipping_country(),
            ];
        
            // Check if shipping address is empty
            if (empty($shipping_address['address1']) && empty($shipping_address['pincode'])) {
                $shipping_address = $billing_address;
            }
        
            // Check if billing address is empty
            if (empty($billing_address['address1']) && empty($billing_address['pincode'])) {
                $billing_address = $shipping_address;
            }
        
            // Sanitize addresses
            $billing_address = $sanitize_address($billing_address);
            $shipping_address = $sanitize_address($shipping_address);
        
             // Get ordered products
             $products = [];
             $invalid_sku_found = false;
             
             foreach ($order->get_items() as $item) {
                 $product = $item->get_product();
                 $sku = $product->get_sku();
             
                 // If SKU is null or empty string, stop processing and clear $products
                 if (empty($sku)) {
                     $invalid_sku_found = true;
                     break;
                 }
             
                 $quantity = $item->get_quantity();
                 $product_price = (int) round($item->get_total() * 100 / $quantity);
             
                 for ($i = 0; $i < $quantity; $i++) {
                     $products[] = [
                         'product_code' => $sku,
                         'product_amount' => [
                             'value' => $product_price,
                             'currency' => 'INR',
                         ],
                     ];
                 }
             }
             
             if ($invalid_sku_found) {
                 $products = []; // make sure it's reset after the loop
             }
        
            // Get cart discount
            $cart_discount = 0;
            if (method_exists($order, 'get_total_discount')) {
                $discount = $order->get_total_discount();
                if (is_numeric($discount)) {
                    $cart_discount = (int) round($discount * 100);
                }
            }
        
            // Prepare purchase details
            $purchase_details = [
                'customer' => [
                    'email_id' => $order->get_billing_email(),
                    'first_name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'mobile_number' => $onlyNumbers,
                    'billing_address' => $billing_address,
                    'shipping_address' => $shipping_address,
                ]
            ];

            if (!empty($products)) {
                $purchase_details['products'] = $products;
            }
        
                       // Prepare request body
                       $body = [
                        'merchant_order_reference' => $order->get_order_number() . '_' . gmdate("ymdHis"),
                        'order_amount' => [
                            'value' => (int) round($order->get_total() * 100),
                            'currency' => 'INR',
                        ],
                        'callback_url' => $callback_url,
                        'pre_auth' => false,
                        'integration_mode'=> "REDIRECT",
                        "plugin_data"=> [
                            "plugin_type" => "WooCommerce",
                            "plugin_version" => "V3"
                        ],
                        'purchase_details' => $purchase_details,
                    ];
        
                    // Add cart discount if applicable
                    if ($cart_discount > 0) {
                        $body['cart_coupon_discount_amount'] = [
                            'value' => (int) round($cart_discount * 100), // Ensure consistency in amount format
                            'currency' => 'INR',
                        ];
                    }
        
                    // Encode the body after modifying the array
                    $body = wp_json_encode($body);
        
            // Set headers
            $headers = [
                'Merchant-ID' => $this->merchant_id,
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
            ];
        
            // Send request
            $response = wp_remote_post($url, [
                'method' => 'POST',
                'body' => $body,
                'headers' => $headers,
            ]);
        
            // Handle errors
            if (is_wp_error($response)) {
                return [
                    'response_code' => 500,
                    'response_message' => 'Internal Server Error',
                ];
            }
        
            // Return decoded response
            return json_decode(wp_remote_retrieve_body($response), true);
        }

        public function handle_pinepg_callback() {
            
        
           
                // Verify nonce before processing data
                if (sanitize_text_field(wp_unslash(isset( $_POST['order_id'] )))) {
                    
                    // Sanitize the order_id
                    $order_id_from_pg = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
                    
                    // Process the order ID here
                }

                $status=$_POST['status'];
            
            

            if ($order_id_from_pg != '') {
                // Construct the cookie name
                $cookie_name = 'order_' . $order_id_from_pg;
                
                
                //get order 
                $woocommerce_order_id = 'woocommerce_' . $order_id_from_pg;

                if (isset($_COOKIE[$woocommerce_order_id])) {
                    $woocommerce_order_id = sanitize_text_field(wp_unslash($_COOKIE[$woocommerce_order_id]));
                    $parts = explode('_', $woocommerce_order_id);
                    $actual_order_id = (int)$parts[0];
                }

                

                
        
                // Check if the cookie exists
                if (isset($_COOKIE[$cookie_name])) {
                    $token = sanitize_text_field(wp_unslash($_COOKIE[$cookie_name]));
                   
        
                    // Call the API to get the status of the payment
                    $status = $this->call_enquiry_api($order_id_from_pg);
        
                    
        
                    // Check payment status
                    if ($status === 'PROCESSED') {

                         // Payment succeeded, complete the order
                         $order = new WC_Order($actual_order_id);
         
                         // Update order status
                         $order->payment_complete();
                         $order->add_order_note('Payment success via Edge by Pine Labs.Status:'.$status.' Pinelabs order id: ' . $order_id_from_pg.' and woocommerce order id :' . $actual_order_id);
                         
         
                         // Redirect to thank you page
                         wp_redirect($order->get_checkout_order_received_url());
                         exit;

                        
                    } else {

                        // Payment failed, add an error notice
                        wc_add_notice(__('Payment failed. Please try again.', 'pinelabs-pinepg-gateway'), 'error');
        
                        // Update order status to failed
                        $order = new WC_Order($actual_order_id);
                        $order->update_status('failed', 'Payment failed via Edge by Pine Labs.');
                        $completeText = 'Payment failed via Edge by Pine Labs. Please try again. Edge order id: ' . $order_id_from_pg . ' and WooCommerce order id: ' . $actual_order_id;
                        $order->add_order_note($completeText);


        
                        // Redirect to cart
                        wp_redirect(wc_get_cart_url());
                        exit;
                       
                    }
                } else {
                    // Handle case where cookie is not found
                    
                    wc_add_notice(__('Error processing payment. Cookie not found.', 'pinelabs-pinepg-gateway'), 'error');
                    wp_redirect(wc_get_cart_url());
                    exit;
                }
            } else {
                // Handle case where order ID is not provided
                
                wc_add_notice(__('Error processing payment. Invalid order ID.', 'pinelabs-pinepg-gateway'), 'error');
                wp_redirect(wc_get_cart_url());
                exit;
            }
        
            // End the function with a JSON success response for logging purposes (if needed)
            wp_send_json_success();
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

    }
}
