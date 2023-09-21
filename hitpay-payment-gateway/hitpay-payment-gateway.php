<?php
/*
Plugin Name: HitPay Payment Gateway
Description: HitPay Payment Gateway Plugin allows HitPay merchants to accept PayNow QR, Cards, Apple Pay, Google Pay, WeChatPay, AliPay and GrabPay Payments. You will need a HitPay account, contact support@hitpay.zendesk.com.
Version: 4.0.9
Requires at least: 4.0
Tested up to: 6.2.2
WC requires at least: 2.4
WC tested up to: 7.8.0
Requires PHP: 5.5
Author: <a href="https://www.hitpayapp.com>HitPay Payment Solutions Pte Ltd</a>   
Author URI: https://www.hitpayapp.com
License: MIT
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('HITPAY_VERSION', '4.0.9');
define('HITPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HITPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once HITPAY_PLUGIN_PATH . 'vendor/softbuild/hitpay-sdk/src/CurlEmulator.php';

require_once HITPAY_PLUGIN_PATH . 'vendor/autoload.php';

/**
 * Initiate HitPay Mobile Payment once plugin is ready
 */
add_action('plugins_loaded', 'woocommerce_hitpay_init');

function woocommerce_hitpay_init() {
    if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . '/wp-admin/includes/plugin.php';
    }
    if (!is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            return;
    }
    
    require_once HITPAY_PLUGIN_PATH. 'includes/regular-checkout.php';
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

add_action( 'woocommerce_blocks_loaded', 'woocommerce_hitpay_blocks_support' );
function woocommerce_hitpay_blocks_support() {
    if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
        require_once HITPAY_PLUGIN_PATH. 'includes/blocks-checkout.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                $payment_method_registry->register( new WC_HitPay_Blocks_Support );
            }
        );
    }
}