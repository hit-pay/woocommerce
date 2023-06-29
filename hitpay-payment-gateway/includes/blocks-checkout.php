<?php
/**
 * WC_HitPay_Blocks_Support Class
 * Dummy Payments Blocks integration
 * @author: <a href="https://www.hitpayapp.com>HitPay Payment Solutions Pte Ltd</a>   
 * @package: HitPay Payment Gateway
 * @since: 4.0.6
*/

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentResult;
use Automattic\WooCommerce\Blocks\Payments\PaymentContext;

final class WC_Hitpay_Blocks_Support extends AbstractPaymentMethodType {
    /**
     * The gateway instance.
     *
     * @var WC_Gateway_Dummy
     */
    private $gateway;

    /**
     * Payment method name/id/slug.
     *
     * @var string
     */
    protected $name = 'hitpay';
    
    /**
     * Initializes the payment method type.
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_hitpay_settings', [] );
        
        $payment_gateways_class   = WC()->payment_gateways();
        $payment_gateways         = $payment_gateways_class->payment_gateways();

        $this->gateway  = $payment_gateways['hitpay'];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return boolean
     */
    public function is_active() {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $asset_path   = HITPAY_PLUGIN_PATH . 'build/frontend/blocks.asset.php';
        $version      = HITPAY_VERSION;
        $dependencies = [];
        if ( file_exists( $asset_path ) ) {
            $asset        = require $asset_path;
            $version      = is_array( $asset ) && isset( $asset['version'] )
                    ? $asset['version']
                    : $version;
            $dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
                    ? $asset['dependencies']
                    : $dependencies;
        }
        wp_register_script(
                'wc-hitpay-blocks-integration',
                HITPAY_PLUGIN_URL . 'build/frontend/blocks.js',
                $dependencies,
                $version,
                true
        );
        
        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wc-hitpay-blocks-integration', 'hitpay', HITPAY_PLUGIN_PATH . 'languages/' );
        }

        return [ 'wc-hitpay-blocks-integration' ];
    }

    /**
     * Returns an array of key=>value pairs of data made available to the payment methods script.
     *
     * @return array
     */
    public function get_payment_method_data() {
        return [
            'title'       => $this->get_setting( 'title' ),
            'description' => $this->get_setting( 'description' ),
            'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
            'logo_url'    => HITPAY_PLUGIN_URL . '/assets/images/logo.png',
            'icons'       => $this->get_icons(),
            'drop_in_enabled' => $this->isDropInEnabled(),
            'pos_enabled' => $this->isPosEnabled(),
            'terminal_ids' => $this->gateway->terminal_ids,
            'total_terminals' => count($this->gateway->terminal_ids),
        ];
    }
    
    private function isDropInEnabled() {
        $status = false;
        if ($this->gateway->drop_in == 'yes') {
            $status = true;
        } 
        return $status;
    }
    
    private function isPosEnabled() {
        $status = false;
        if ($this->gateway->enable_pos) {
            $status = true;
        } 
        return $status;
    }
    
    /**
     * Return the icons urls.
     *
     * @return array Arrays of icons metadata.
     */
    private function get_icons() {
        $icons = $this->gateway->getPaymentIcons();

        $icons_src = [];

        if ($this->gateway->payments) {
            $pngs = array(
                'pesonet',
                'eftpos',
                'doku',
                'philtrustbank',
                'allbank',
                'aub',
                'chinabank',
                'instapay',
                'landbank',
                'metrobank',
                'pnb',
                'queenbank',
                'ussc',
                'bayad',
                'cebuanalhuillier',
                'psbank',
                'robinsonsbank',
                'doku_wallet',
                'favepay',
                'shopback_paylater'
            );
            foreach ($this->gateway->payments as $payment) {
                $extn = 'svg';
                if (in_array($payment, $pngs)) {
                    $extn = 'png';
                }
                $icons_src[$payment] = [
                    'src' => HITPAY_PLUGIN_URL. '/assets/images/'.$payment.'.'.$extn,
                    'alt' => esc_attr( $icons[$payment] ),
                ];
            }
        }
        
        return $icons_src;
    }
}