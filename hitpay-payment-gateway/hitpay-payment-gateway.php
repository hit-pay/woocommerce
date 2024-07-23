<?php
/*
Plugin Name: HitPay Payment Gateway
Description: HitPay Payment Gateway Plugin allows HitPay merchants to accept PayNow QR, Cards, Apple Pay, Google Pay, WeChatPay, AliPay and GrabPay Payments. You will need a HitPay account, contact support@hitpay.zendesk.com.
Version: 4.1.5
Requires at least: 4.0
Tested up to: 6.6
WC requires at least: 2.4
WC tested up to: 9.1.2
Requires PHP: 5.5
Author: <a href="https://www.hitpayapp.com>HitPay Payment Solutions Pte Ltd</a>   
Author URI: https://www.hitpayapp.com
License: MIT
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('HITPAY_VERSION', '4.1.5');
define('HITPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HITPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));

require_once HITPAY_PLUGIN_PATH . 'vendor/softbuild/hitpay-sdk/src/CurlEmulator.php';

if (!class_exists('\HitPay\Client')) {
	require_once HITPAY_PLUGIN_PATH . 'vendor/autoload.php';
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

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

add_filter('woocommerce_order_button_html', 'custom_order_button_html', 10, 5 );
function custom_order_button_html( $order_button_html ) {
    $chosen_payment_method = WC()->session->get('chosen_payment_method');
    if( $chosen_payment_method == 'hitpay'){
		$payment_button = get_option('woocommerce_hitpay_payment_button');
		if($payment_button == 1) {
			$place_order_text = get_option('woocommerce_hitpay_place_order_text');
			if (empty($place_order_text)) {
				$place_order_text = 'Complete Payment';
			}
			$order_button_html = '<button type="submit" class="hitpay-own-payment-button button alt wp-element-button" name="woocommerce_checkout_place_order" id="place_order" value="'.esc_attr($place_order_text).'" data-value="'.esc_attr($place_order_text).'">'.esc_attr($place_order_text).'</button>';
		}
    }
	
	// jQuery code: Make dynamic text button "on change" event ?>
    <script type="text/javascript">
    (function($){
        $('form.checkout').on( 'change', 'input[name^="payment_method"]', function() {
            var t = { updateTimer: !1,  dirtyInput: !1,
                reset_update_checkout_timer: function() {
                    clearTimeout(t.updateTimer)
                },  trigger_update_checkout: function() {
                    t.reset_update_checkout_timer(), t.dirtyInput = !1,
                    $(document.body).trigger("update_checkout")
                }
            };
            t.trigger_update_checkout();
        });
    })(jQuery);
    </script><?php
    return $order_button_html;
}