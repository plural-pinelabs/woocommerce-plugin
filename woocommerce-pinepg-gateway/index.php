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
                            'Payment refund success via Edge by Pine Labs. Status: %s Edge order id: %s and WooCommerce order id: %d and refund id: %s and amount is %d',
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

        public function send_pinepg_payment_request( $order ) {
            $url = $this->environment === 'production'
                ? 'https://api.pluralpay.in/api/checkout/v1/orders'
                : 'https://pluraluat.v2.pinepg.in/api/checkout/v1/orders';


                
        $access_token = $this->get_access_token();
        if (!$access_token) {
            return array(
                'response_code' => 500,
                'response_message' => 'Failed to retrieve access token',
            );
        }
    

            $body = wp_json_encode( array(
                'merchant_order_reference' => $order->get_order_number() . '_' . gmdate("ymdHis"),
                'order_amount' => array(
                    'value' => (int) $order->get_total() * 100,
                    'currency' => 'INR',
                ),
                'pre_auth' => false,
                'purchase_details' => array(
                    'customer' => array(
                        'email_id' => $order->get_billing_email(),
                        'first_name' => $order->get_billing_first_name(),
                        'last_name' => $order->get_billing_last_name(),
                        //'customer_id' => $order->get_customer_id(),
                        'mobile_number' => $order->get_billing_phone(),
                    ),
                ),
            ) );

           

            $headers = array(
                'Merchant-ID' => $this->merchant_id,
                'Content-Type' => 'application/json',
            );
           


            if (!empty($access_token)) {
                    $headers['Authorization'] = 'Bearer ' . $access_token;
                }


            $response = wp_remote_post( $url, array(
                'method'    => 'POST',
                'body'      => $body,
                'headers'   => $headers,
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

        public function handle_pinepg_callback() {
            
        
           
                // Verify nonce before processing data
                if (sanitize_text_field(wp_unslash(isset( $_POST['order_id'] )))) {
                    
                    // Sanitize the order_id
                    $order_id_from_pg = sanitize_text_field( wp_unslash( $_POST['order_id'] ) );
                    
                    // Process the order ID here
                }
            
            

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
                    plugin_log(__LINE__ . ' Token from cookie: ' . $token);
        
                    // Call the API to get the status of the payment
                    $status_data = $this->call_enquiry_api($token);
        
                    // Log the status data received from the API
                    plugin_log(__LINE__ . ' Payment status data: ' . wp_json_encode($status_data));
        
                    // Check payment status
                    if ($status_data['status'] === 'FAILED') {
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
                    } else {
                        // Payment succeeded, complete the order
                        $order = new WC_Order($actual_order_id);
                        $status=$status_data['status'];
        
                        // Update order status
                        $order->payment_complete();
                        $order->add_order_note('Payment success via Edge by Pine Labs.Status:'.$status.' Edge order id: ' . $order_id_from_pg.' and woocommerce order id :' . $actual_order_id);
                        
        
                        // Redirect to thank you page
                        wp_redirect($order->get_checkout_order_received_url());
                        exit;
                    }
                } else {
                    // Handle case where cookie is not found
                    plugin_log(__LINE__ . ' Cookie not found for order ID: ' . $actual_order_id);
                    wc_add_notice(__('Error processing payment. Cookie not found.', 'pinelabs-pinepg-gateway'), 'error');
                    wp_redirect(wc_get_cart_url());
                    exit;
                }
            } else {
                // Handle case where order ID is not provided
                plugin_log(__LINE__ . ' Order ID not found in callback data.');
                wc_add_notice(__('Error processing payment. Invalid order ID.', 'pinelabs-pinepg-gateway'), 'error');
                wp_redirect(wc_get_cart_url());
                exit;
            }
        
            // End the function with a JSON success response for logging purposes (if needed)
            wp_send_json_success();
        }
        


        private function call_enquiry_api($token) {

            // Set the URL based on the environment
           $url = $this->environment === 'production'
            ? 'https://api.pluralonline.com/api/v3/checkout-bff/inquiry?token=' . $token
            : 'https://api-staging.pluralonline.com/api/v3/checkout-bff/inquiry?token=' . $token;
        
            $response = wp_remote_get($url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
            ));
        
            if (is_wp_error($response)) {
                //error_log('Error calling external API: ' . $response->get_error_message());
                return; // Exit if there was an error
            }
        
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
        
            // Check if 'data' exists in the response
            if (isset($response_data['data'])) {
                $order_id = $response_data['data']['order_id'] ?? 'N/A'; // Use 'N/A' if not set
                $status = $response_data['data']['status'] ?? 'N/A'; // Use 'N/A' if not set
        
               

                return array('order_id' => $order_id, 'status' => $status);
                
                
            } else {
                //error_log('Unexpected response structure: ' . print_r($response_data, true));
            }
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
