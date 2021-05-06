<?php
/*
Plugin Name: HitPay Payment Gateway
Description: HitPay Payment Gateway Plugin allows HitPay merchants to accept PayNow QR, Cards, Apple Pay, Google Pay, WeChatPay, AliPay and GrabPay Payments. You will need a HitPay account, contact support@hitpay.zendesk.com.
Version: 2.7
Requires at least: 4.0
Tested up to: 5.6.2
WC requires at least: 2.4
WC tested up to: 5.0.0
Requires PHP: 5.5
Author: <a href="https://www.hitpayapp.com>HitPay Payment Solutions Pte Ltd</a>   
Author URI: https://www.hitpayapp.com
License: MIT
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('HITPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HITPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once HITPAY_PLUGIN_PATH . 'vendor/softbuild/hitpay-sdk/src/CurlEmulator.php';

require_once HITPAY_PLUGIN_PATH . 'vendor/autoload.php';

use HitPay\Client;
use HitPay\Request\CreatePayment;
use HitPay\Response\PaymentStatus;

/**
 * Initiate HitPay Mobile Payment once plugin is ready
 */
add_action('plugins_loaded', 'woocommerce_hitpay_init');

function woocommerce_hitpay_init() {

    class WC_HitPay extends WC_Payment_Gateway {

        public $domain;

        /**
         * Constructor for the gateway.
         */
        public function __construct() {
            $this->domain = 'hitpay';

            $this->id = 'hitpay';
            $this->icon = HITPAY_PLUGIN_URL . 'assets/images/logo.png';
            $this->has_fields = false;
            $this->method_title = __('HitPay Payment Gateway', $this->domain);
            $this->method_description = __('Allows secure payments PayNow QR, Credit Card, WeChatPay and AliPay payments. You will need an HitPay account, contact support@hitpay.zendesk.com.', $this->domain);
            
            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->mode = $this->get_option('mode');
            $this->debug = $this->get_option('debug');
            $this->api_key = $this->get_option('api_key');
            $this->salt = $this->get_option('salt');
            $this->payments = $this->get_option('payments');

	    // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_api_'. strtolower("WC_HitPay"), array( $this, 'check_ipn_response' ) );
            add_filter('woocommerce_gateway_icon', array($this, 'custom_payment_gateway_icons'), 10, 2 );
            add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'admin_order_details'), 10, 3 );
        }
        
        public function admin_order_details( $order ){
            if ($order->get_payment_method() == $this->id) {
                $order_id = $order->get_id();
                $payment_method = '';
                $payment_request_id = get_post_meta( $order_id, 'HitPay_payment_request_id', true );
                
                if (!empty($payment_request_id)) {
                    $payment_method = get_post_meta( $order_id, 'HitPay_payment_method', true );
                    if (empty($payment_method)) {
                        try {
                            $hitpay_client = new Client(
                                $this->api_key,
                                $this->getMode()
                            );

                            $paymentStatus = $hitpay_client->getPaymentStatus($payment_request_id);
                            if ($paymentStatus) {
                                $payments = $paymentStatus->payments;
                                if (isset($payments[0])) {
                                    $payment = $payments[0];
                                    $payment_method = $payment->payment_type;
                                    $order->add_meta_data('HitPay_payment_method', $payment_method);
                                    $order->save_meta_data();
                                }
                            }
                        } catch (\Exception $e) {
                            $payment_method = $e->getMessage();
                        }
                    }
                }
                
                if (!empty($payment_method)) {
            ?>
                     <p class="form-field form-field-wide wc-hitpay-status" style="padding: 10px; font-size: 16px; color: blue; top: 10px;">
                        <?php echo __('HitPay Payment Method:', $this->domain) ?> <span style="color: blue;"><?php echo ucwords(str_replace("_", " ", $payment_method)) ?></span>
                    </p>
            <?php
                }
            }
        }
        
        public function custom_payment_gateway_icons( $icon, $gateway_id ){
            $icons = $this->getPaymentIcons();
            foreach( WC()->payment_gateways->get_available_payment_gateways() as $gateway ) {
                if( $gateway->id == $gateway_id ){
                    $title = $gateway->get_title();
                    break;
                }
            }
            
            if($gateway_id == 'hitpay') {
                $icon = '';
                if ($this->payments) {
                    foreach ($this->payments as $payment) {
                        $icon .= ' <img src="' . HITPAY_PLUGIN_URL . 'assets/images/'.$payment.'.svg" alt="' . esc_attr( $icons[$payment] ) . '"  title="' . esc_attr( $icons[$payment] ) . '" />';
                    }
                }
            }
            
            return $icon;
        }

        /**
         * Initialize Gateway Settings Form Fields.
         */
        public function init_form_fields() {
            $countries_obj   = new WC_Countries();
            $countries   = $countries_obj->__get('countries');
    
	    $field_arr = array(
                'enabled' => array(
                    'title' => __('Active', $this->domain),
                    'type' => 'checkbox',
                    'label' => __(' ', $this->domain),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', $this->domain),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', $this->domain),
                    'default' => $this->method_title,
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', $this->domain),
                    'type' => 'textarea',
                    'description' => __('Instructions that the customer will see on your checkout.', $this->domain),
                    'default' => $this->method_description,
                    'desc_tip' => true,
                ),
                'mode' => array(
                    'title' => __('Live Mode', $this->domain),
                    'type' => 'checkbox',
                    'label' => __(' ', $this->domain),
                    'default' => 'no'
                ),
                'api_key' => array(
                    'title' => __('Api Key', $this->domain),
                    'type' => 'text',
                    'description' => __('Copy/paste values from HitPay Dashboard under Settings > Payment Gateway > API Keys.', $this->domain),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'salt' => array(
                    'title' => __('Salt', $this->domain),
                    'type' => 'text',
                    'description' => __('Copy/paste values from HitPay Dashboard under Settings > Payment Gateway > API Keys.', $this->domain),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'payments' => array(
                    'title' => __('Payment Logos', $this->domain),
                    'type' => 'multiselect',
                    'default' => __('Activate payment methods in the HitPay dashboard under Settings > Payment Gateway > Integrations.', $this->domain),
                    'css' => 'height: 10rem;',
                    'desc_tip' => true,
                    'options' => $this->getPaymentIcons()
                ),
                'debug' => array(
                    'title' => __('Debug', $this->domain),
                    'type' => 'checkbox',
                    'label' => __(' ', $this->domain),
                    'default' => 'no'
                ),
            );

            $this->form_fields = $field_arr;
        }

        /**
         * Process Gateway Settings Form Fields.
         */
	public function process_admin_options() {
            $this->init_settings();

            $post_data = $this->get_post_data();
            if (empty($post_data['woocommerce_hitpay_api_key'])) {
                WC_Admin_Settings::add_error(__('Please enter HitPay API Key', $this->domain));
            } elseif (empty($post_data['woocommerce_hitpay_salt'])) {
                WC_Admin_Settings::add_error(__('Please enter HitPay API Salt', $this->domain));
            } else {
                foreach ( $this->get_form_fields() as $key => $field ) {
                    $setting_value = $this->get_field_value( $key, $field, $post_data );
                    $this->settings[ $key ] = $setting_value;
                }
                return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ) );
            }
	}
        
        function payment_fields()
        { 
            ?>
            <div class="form-row form-row-wide">
                <p><?php echo $this->description; ?></p>
                <?php
                   if (isset( $_REQUEST['cancelled'] ) )  {
                ?>
                <script>
                    let message = '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div class="woocommerce-error"><?php echo __('Payment canceled by customer', $this->domain)?></div></div>';
                    jQuery(document).ready(function(){
                        jQuery('.woocommerce-notices-wrapper:first').html(message);
                    });
                </script>
                <?php
                   }
                ?>
            </div>
            <?php
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page($order_id) {
            $order = new WC_Order($order_id);
            
            $style = "width: 100%;  margin-bottom: 1rem; background: #212b5f; padding: 20px; color: #fff; font-size: 22px;";
            if (isset($_GET['status'])) {
                $status = sanitize_text_field($_GET['status']);

                if ($status == 'canceled') {
                    $status = $order->get_status();
                    if ($status == 'processing') {
                        $status = 'completed';
                    } else {
                        $reference = sanitize_text_field($_GET['reference']);

                        $status_message = __('Order cancelled by HitPay.', $this->domain).($reference ? ' Reference: '.$reference:'');
                        $order->update_status('cancelled', $status_message);

                        $order->add_meta_data('HitPay_reference', $reference);
                        $order->save_meta_data();
                    }
                }
                
                if ($status == 'completed') {
                    $status = 'wait';
                }
            }
            
            if ($status != 'wait') {
                $status = $order->get_status();
            }
            ?>
            <script>
                let is_status_received = false;
                let hitpay_status_ajax_url = '<?php echo site_url().'/?wc-api=wc_hitpay&get_order_status=1'?>';
                jQuery(document).ready(function(){
                    jQuery('.entry-header .entry-title').html('<?php echo __('Order Status', $this->domain)?>');
                    jQuery('.woocommerce-thankyou-order-received').hide();
                    jQuery('.woocommerce-thankyou-order-details').hide();
                    jQuery('.woocommerce-order-details').hide();
                    jQuery('.woocommerce-customer-details').hide();
                    
                    show_hitpay_status();
                });
                
                function show_hitpay_status(type='') {
                    jQuery('.payment-panel-wait').hide();
                    <?php  if ($status == 'completed' || $status == 'pending') {?>
                    jQuery('.woocommerce-thankyou-order-received').show();
                    jQuery('.woocommerce-thankyou-order-details').show();
                    <?php } ?>
                    jQuery('.woocommerce-order-details').show();
                    jQuery('.woocommerce-customer-details').show();
                    if (type.length > 0) {
                        jQuery('.payment-panel-'+type).show();
                    }
                 }
            </script>
            <?php  if ($status == 'wait') {?>
            <style>
                .payment-panel-wait .img-container {
                    text-align: center;
                }
                .payment-panel-wait .img-container img{
                    display: inline-block !important;
                }
            </style>
            <script>
                jQuery(document).ready(function(){
                    check_hitpay_payment_status();
   
                    function check_hitpay_payment_status() {

                         function hitpay_status_loop() {
                             if (is_status_received) {
                                 return;
                             }

                             if (typeof(hitpay_status_ajax_url) !== "undefined") {
                                 jQuery.getJSON(hitpay_status_ajax_url, {'order_id' : <?php echo $order_id?>}, function (data) {
                                     if (data.status == 'wait') {
                                        setTimeout(hitpay_status_loop, 2000);
                                     } else if (data.status == 'error') {
                                        show_hitpay_status('error');
                                        is_status_received = true;
                                     } else if (data.status == 'pending') {
                                        show_hitpay_status('pending');
                                        is_status_received = true;
                                     } else if (data.status == 'failed') {
                                        show_hitpay_status('failed');
                                        is_status_received = true;
                                     } else if (data.status == 'completed') {
                                        show_hitpay_status('completed');
                                        is_status_received = true;
                                     }
                                });
                             }
                         }
                         hitpay_status_loop();
                     }
                });
            </script>
            <div class="payment-panel-wait">
                <h3><?php echo __('We are retrieving your payment status from HitPay, please wait...', $this->domain) ?></h3>
                <div class="img-container"><img src="<?php echo HITPAY_PLUGIN_URL?>assets/images/loader.gif" /></div>
            </div>
            <?php } ?>

            <div class="payment-panel-pending" style="<?php echo ($status == 'pending' ? 'display: block':'display: none')?>">
                <div style="<?php echo $style?>">
                <?php echo __('Your payment status is pending, we will update the status as soon as we receive notification from HitPay.', $this->domain) ?>
                </div>
            </div>

            <div class="payment-panel-completed" style="<?php echo ($status == 'completed' ? 'display: block':'display: none')?>">
                <div style="<?php echo $style?>">
                <?php echo __('Your payment is successful with HitPay.', $this->domain) ?>
                    <img style="width:100px" src="<?php echo HITPAY_PLUGIN_URL?>assets/images/check.png"  />
                </div>
            </div>

             <div class="payment-panel-failed" style="<?php echo ($status == 'failed' ? 'display: block':'display: none')?>">
                <div style="<?php echo $style?>">
                <?php echo __('Your payment is failed with HitPay.', $this->domain) ?>
                </div>
            </div>

             <div class="payment-panel-cancelled" style="<?php echo ($status == 'cancelled' ? 'display: block':'display: none')?>">
                <div style="<?php echo $style?>">
                <?php 
                if (isset($status_message) && !empty($status_message)) {
                    echo $status_message;
                } else {
                    echo __('Your order is cancelled.', $this->domain);
                }
                ?>
                </div>
            </div>  
            
            <div class="payment-panel-error" style="display: none">
                <div class="message-holder">
                    <?php echo __('Something went wrong, please contact the merchant.', $this->domain) ?>
                </div>
            </div>
            <?php
        }

        public function get_payment_staus() {
            $status = 'wait';
            $message = '';

            try {
                $order_id = (int)sanitize_text_field($_GET['order_id']);
                if ($order_id == 0) {
                    throw new \Exception( __('Order not found.', $this->domain));
                }
                
                $payment_status = get_post_meta( $order_id, 'HitPay_WHS', true );
                if ($payment_status && !empty($payment_status)) {
                    $status = $payment_status;
                }
            } catch (\Exception $e) {
                $status = 'error';
                $message = $e->getMessage();
            }

            $data = [
                'status' => $status,
                'message' => $message
            ];

            echo json_encode($data);
            die();
        }
        
        public function return_from_hitpay() {
            if (!isset($_GET['order_id'])) {
                $this->log('return_from_hitpay order_id check failed');
                exit;
            }

            $order_id = (int)sanitize_text_field($_GET['order_id']);
            $order = new WC_Order($order_id);
            
            if (isset($_GET['status'])) {
                $status = sanitize_text_field($_GET['status']);
                $reference = sanitize_text_field($_GET['reference']);

                if ($status == 'canceled') {
                    $status = $order->get_status();
                    if ($status == 'processing') {
                        $status = 'completed';
                    } else {
                        $status_message = __('Order cancelled by HitPay.', $this->domain).($reference ? ' Reference: '.$reference:'');
                        $order->update_status('cancelled', $status_message);

                        $order->add_meta_data('HitPay_reference', $reference);
                        $order->save_meta_data();

                        wp_redirect( add_query_arg( 'cancelled', 'true', wc_get_checkout_url() ) );exit;
                    }
                }
                
                if ($status == 'completed') {
                    wp_redirect(add_query_arg( 'status', $status, $this->get_return_url( $order )));exit;
                }
            }
        }
        
        public function web_hook_handler() {
            global $woocommerce;
            $this->log('Webhook Triggers');
            $this->log('Post Data:');
            $this->log($_POST);

            if (!isset($_GET['order_id']) || !isset($_POST['hmac'])) {
                $this->log('order_id + hmac check failed');
                exit;
            }

            $order_id = (int)sanitize_text_field($_GET['order_id']);
            
            if ($order_id > 0) {
                $HitPay_webhook_triggered = (int)get_post_meta( $order_id, 'HitPay_webhook_triggered', true);
                if ($HitPay_webhook_triggered == 1) {
                    exit;
                }
            }
            
            $order = new WC_Order($order_id);
            $order_data = $order->get_data();
            
            $order->add_meta_data('HitPay_webhook_triggered', 1);
            $order->save_meta_data();

            try {
                $data = $_POST;
                unset($data['hmac']);

                $salt = $this->salt;
                if (Client::generateSignatureArray($salt, $data) == $_POST['hmac']) {
                    $this->log('hmac check passed');
                    
                    $HitPay_payment_id = get_post_meta( $order_id, 'HitPay_payment_id', true );

                    if (!$HitPay_payment_id || empty($HitPay_payment_id)) {
                        $this->log('saved payment not valid');
                    }
                    
                    $HitPay_is_paid = get_post_meta( $order_id, 'HitPay_is_paid', true );

                    if (!$HitPay_is_paid) {
                        $status = sanitize_text_field($_POST['status']);

                        if ($status == 'completed'
                            && $order_total = $order->get_total() == $_POST['amount']
                            && $order_id == $_POST['reference_number']
                            && $order_data['currency'] == $_POST['currency']
                        ) {
                            $payment_id = sanitize_text_field($_POST['payment_id']);
                            $payment_request_id = sanitize_text_field($_POST['payment_request_id']);
                            $hitpay_currency = sanitize_text_field($_POST['currency']);
                            $hitpay_amount = sanitize_text_field($_POST['amount']);
                            
                            
                            $order->update_status('processing', __('Payment successful. Transaction Id: '.$payment_id, $this->domain));

                            $order->add_meta_data('HitPay_transaction_id', $payment_id);
                            $order->add_meta_data('HitPay_payment_request_id', $payment_request_id);
                            $order->add_meta_data('HitPay_is_paid', 1);
                            $order->add_meta_data('HitPay_currency', $hitpay_currency);
                            $order->add_meta_data('HitPay_amount', $hitpay_amount);
                            $order->add_meta_data('HitPay_WHS', $status);
                            $order->save_meta_data();

                            $woocommerce->cart->empty_cart();
                        } elseif ($status == 'failed') {
                            $payment_id = sanitize_text_field($_POST['payment_id']);
                            $hitpay_currency = sanitize_text_field($_POST['currency']);
                            $hitpay_amount = sanitize_text_field($_POST['amount']);
                            
                            $order->update_status('failed', __('Payment Failed. Transaction Id: '.$payment_id, $this->domain));

                            $order->add_meta_data('HitPay_transaction_id', $payment_id);
                            $order->add_meta_data('HitPay_is_paid', 0);
                            $order->add_meta_data('HitPay_currency', $hitpay_currency);
                            $order->add_meta_data('HitPay_amount', $hitpay_amount);
                            $order->add_meta_data('HitPay_WHS', $status);
                            $order->save_meta_data();

                            $woocommerce->cart->empty_cart();
                        } elseif ($status == 'pending') {
                            $payment_id = sanitize_text_field($_POST['payment_id']);
                            $hitpay_currency = sanitize_text_field($_POST['currency']);
                            $hitpay_amount = sanitize_text_field($_POST['amount']);
                            
                            $order->update_status('failed', __('Payment is pending. Transaction Id: '.$payment_id, $this->domain));

                            $order->add_meta_data('HitPay_transaction_id', $payment_id);
                            $order->add_meta_data('HitPay_is_paid', 0);
                            $order->add_meta_data('HitPay_currency', $hitpay_currency);
                            $order->add_meta_data('HitPay_amount', $hitpay_amount);
                            $order->add_meta_data('HitPay_WHS', $status);
                            $order->save_meta_data();

                            $woocommerce->cart->empty_cart();
                        } else {
                            $payment_id = sanitize_text_field($_POST['payment_id']);
                            $hitpay_currency = sanitize_text_field($_POST['currency']);
                            $hitpay_amount = sanitize_text_field($_POST['amount']);
                            
                            $order->update_status('failed', __('Payment returned unknown status. Transaction Id: '.$payment_id, $this->domain));

                            $order->add_meta_data('HitPay_transaction_id', $payment_id);
                            $order->add_meta_data('HitPay_is_paid', 0);
                            $order->add_meta_data('HitPay_currency', $hitpay_currency);
                            $order->add_meta_data('HitPay_amount', $hitpay_amount);
                            $order->add_meta_data('HitPay_WHS', $status);
                            $order->save_meta_data();

                            $woocommerce->cart->empty_cart();
                        }
                    }
                } else {
                    throw new \Exception('HitPay: hmac is not the same like generated');
                }
            } catch (\Exception $e) {
                $this->log('Webhook Catch');
                $this->log('Exception:'.$e->getMessage());
                
                $order->update_status('failed', 'Error :'.$e->getMessage());
                $order->add_meta_data('HitPay_WHS', 'failed');
                $woocommerce->cart->empty_cart();
            }
            exit;
        }


        public function check_ipn_response() {
            global $woocommerce;
            if (isset($_GET['get_order_status'])) {
                $this->get_payment_staus();
            } else if (isset($_GET['return'])) {
                $this->return_from_hitpay();
            } else {
                $this->web_hook_handler();
            }
            exit;
        }
        
        public function getMode()
        {
            $mode = true;
            if ($this->mode == 'no') {
                $mode = false;
            }
            return $mode;
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id) {
            global $woocommerce;

            $order = wc_get_order($order_id);
            
            $order_data = $order->get_data();
            $order_total = $order->get_total();
            
            try {
                $hitpay_client = new Client(
                    $this->api_key,
                    $this->getMode()
                );

                //$redirect_url = $this->get_return_url( $order );
                $redirect_url = site_url().'/?wc-api=wc_hitpay&return=1&order_id='.$order_id;
                $webhook = site_url().'/?wc-api=wc_hitpay&order_id='.$order_id;
                
                $create_payment_request = new CreatePayment();
                $create_payment_request->setAmount($order_total)
                    ->setCurrency($order_data['currency'])
                    ->setReferenceNumber($order_id)
                    ->setWebhook($webhook)
                    ->setRedirectUrl($redirect_url)
                    ->setChannel('api_woocomm');
                
                $create_payment_request->setName($order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name']);
                $create_payment_request->setEmail($order_data['billing']['email']);
                
                $create_payment_request->setPurpose($this->getSiteName());
                
                $this->log('Request:');
                $this->log((array)$create_payment_request);

                $result = $hitpay_client->createPayment($create_payment_request);

                $order->delete_meta_data('HitPay_payment_id');
                $order->add_meta_data('HitPay_payment_id', $result->getId());
                
                $order->save_meta_data();

                if ($result->getStatus() == 'pending') {
                    return array(
                        'result' => 'success',
                        'redirect' => $result->getUrl()
                    );
                } else {
                    throw new Exception(sprintf(__('HitPay: sent status is %s', $this->domain), $result->getStatus()));
                 }
            } catch (\Exception $e) {
                $log_message = $e->getMessage();
                $this->log($log_message);

                $status_message = __('HitPay: Something went wrong, please contact the merchant', $this->domain);
                WC()->session->set('refresh_totals', true);
                wc_add_notice($status_message, $notice_type = 'error');
                return array(
                    'result' => 'failure',
                    'redirect' => WC_Cart::get_checkout_url()
                );
            }
        }
        
        public function getSiteName()
        {   global $blog_id;

            if (is_multisite()) {
                $path = get_blog_option($blog_id, 'blogname');
            } else{
              $path = get_option('blogname');
            }
            return $path;
        }
        
        public function getPaymentMethods()
        {
            $methods = [
                'paynow_online' => __('PayNow QR', $this->domain),
                'card' => __('Credit cards', $this->domain),
                'wechat' => __('WeChatPay and AliPay', $this->domain)
            ];
            
            return $methods;
        }
        
        public function getPaymentIcons()
        {
            $methods = [
                'paynow' => __('PayNow QR', $this->domain),
                'visa' => __('Visa', $this->domain),
                'master' => __('Mastercard', $this->domain),
                'american_express' => __('American Express', $this->domain),
                'grabpay' => __('GrabPay', $this->domain),
                'wechatpay' => __('WeChatPay', $this->domain),
                'alipay' => __('AliPay', $this->domain),
            ];
            
            return $methods;
        }
        
        public function log($content) {
            $debug = $this->debug;
            if ($debug == true) {
                $file = HITPAY_PLUGIN_PATH.'debug.log';
                $fp = fopen($file, 'a+');
                fwrite($fp, "\n");
                fwrite($fp, date("Y-m-d H:i:s").": ");
                fwrite($fp, print_r($content, true));
                fclose($fp);
            }
        }
    }
}

add_filter('woocommerce_payment_gateways', 'add_hitpay_gateway_class');
function add_hitpay_gateway_class($methods) {
    $methods[] = 'WC_Hitpay';
    return $methods;
}

add_filter( 'woocommerce_available_payment_gateways', 'enable_hitpay_gateway' );
function enable_hitpay_gateway( $available_gateways ) {
    if ( is_admin() ) return $available_gateways;

    if ( isset( $available_gateways['hitpay'] )) {
        $settings = get_option('woocommerce_hitpay_settings');
        
        if(empty($settings['salt'])) {
            unset( $available_gateways['hitpay'] );
        } elseif(empty($settings['api_key'])) {
            unset( $available_gateways['hitpay'] );
        }
    } 
    return $available_gateways;
}
