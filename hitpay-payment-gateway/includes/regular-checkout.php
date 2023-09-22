<?php
/**
 * WC_HitPay Class

 * @author: <a href="https://www.hitpayapp.com>HitPay Payment Solutions Pte Ltd</a>   
 * @package: HitPay Payment Gateway
 * @since: 1.0.0
*/

use HitPay\Client;
use HitPay\Request\CreatePayment;
use HitPay\Response\PaymentStatus;

class WC_HitPay extends WC_Payment_Gateway {

    public $domain;

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->domain = 'hitpay';

        $this->supports = array(
            'products',
            'refunds'
        );

        $this->id = 'hitpay';
        $this->icon = HITPAY_PLUGIN_URL . 'assets/images/logo.png';
        $this->has_fields = true;
        $this->method_title = __('HitPay Payment Gateway', $this->domain);

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->mode = $this->get_option('mode');
        $this->debug = $this->get_option('debug');
        $this->api_key = $this->get_option('api_key');
        $this->salt = $this->get_option('salt');
        $this->payments = $this->get_option('payments');
        $this->order_status = $this->get_option('order_status');
        $this->expires_after_status = $this->get_option('expires_after_status');
        $this->expires_after = $this->get_option('expires_after');
        $this->drop_in = $this->get_option('drop_in');

        if (!$this->option_exists("woocommerce_hitpay_customize")) {
            $this->customize = 1;
        } else {
            $this->customize = get_option('woocommerce_hitpay_customize');
        }

        if (!$this->option_exists("woocommerce_hitpay_style")) {
            $this->style = 'width: 100%; margin-bottom: 1rem; background: #212b5f; padding: 20px; color: #fff; font-size: 22px;';
        } else {
            $this->style = get_option('woocommerce_hitpay_style');
        }

        $this->enable_pos = get_option('woocommerce_hitpay_enable_pos');

        if (!$this->option_exists("woocommerce_hitpay_terminal_ids")) {
            $this->terminal_ids = array();
        } else {
            $this->terminal_ids = get_option('woocommerce_hitpay_terminal_ids');
            if (!empty($this->terminal_ids)) {
                $this->terminal_ids = json_decode($this->terminal_ids, true);
            } else {
                $this->terminal_ids = array();
            }
        }

        if (!$this->option_exists("woocommerce_hitpay_cashier_emails")) {
            $this->cashier_emails = array();
        } else {
            $this->cashier_emails = get_option('woocommerce_hitpay_cashier_emails');
            if (!empty($this->cashier_emails)) {
                $this->cashier_emails = json_decode($this->cashier_emails, true);
            } else {
                $this->cashier_emails = array();
            }
        }


        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_api_'. strtolower("WC_HitPay"), array( $this, 'check_ipn_response' ) );
        add_filter('woocommerce_gateway_icon', array($this, 'custom_payment_gateway_icons'), 10, 2 );
        add_action('woocommerce_admin_order_totals_after_total', array($this, 'admin_order_totals'), 10, 1);
        add_action('admin_footer', array( $this, 'hitpay_admin_footer'), 10, 3 );
        add_action('wp_enqueue_scripts',  array( $this, 'hitpay_load_front_assets') );
    }

    public function hitpay_load_front_assets() {
        if ( is_checkout() ) {         
            wp_enqueue_style( 'hitpay-css', HITPAY_PLUGIN_URL.'/assets/css/front.css', array(),HITPAY_VERSION,'all' );
            if ($this->drop_in == 'yes') {
                $dropin_js = 'https://sandbox.hit-pay.com/hitpay.js';
                if ($this->mode == 'yes') {
                    $dropin_js = 'https://hit-pay.com/hitpay.js';
                }
                wp_enqueue_script( 'hitpay_js', $dropin_js );
                wp_enqueue_script( 'hitpay_dropin_js', HITPAY_PLUGIN_URL.'/assets/js/dropin.js' );
            }
        }  
    }

    public function admin_order_totals( $order_id ){
        $order = new WC_Order($order_id);
        if ($order->get_payment_method() == $this->id) {
            $order_id = $order->get_id();
            $payment_method = '';
            $payment_request_id = get_post_meta( $order_id, 'HitPay_payment_request_id', true );

            if (!empty($payment_request_id)) {
                $payment_method = get_post_meta( $order_id, 'HitPay_payment_method', true );
                $fees = get_post_meta( $order_id, 'HitPay_fees', true );
                if (empty($payment_method) || empty($fees)) {
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
                                $fees = $payment->fees;
                                $order->add_meta_data('HitPay_fees', $fees);
                                $order->save_meta_data();
                            }
                        }
                    } catch (\Exception $e) {
                        $payment_method = $e->getMessage();
                    }
                }
            }

            if (!empty($payment_method)) {
                $HitPay_currency = get_post_meta( $order_id, 'HitPay_currency', true );
        ?>
                <table class="wc-order-totals" style="border-top: 1px solid #999; margin-top:12px; padding-top:12px">
                    <tbody>
                        <tr>
                            <td class="label"><?php echo __('HitPay Payment Type', $this->domain) ?>:</td>
                            <td width="1%"></td>
                            <td class="total">
                                <span class="woocommerce-Price-amount amount"><bdi><?php echo ucwords(str_replace("_", " ", $payment_method)) ?></bdi></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="label"><?php echo __('HitPay Fee', $this->domain) ?>:</td>
                            <td width="1%"></td>
                            <td class="total">
                                <span class="woocommerce-Price-amount amount">
                                    <bdi>
                                    <?php echo wc_price($fees, array('currency' => $HitPay_currency)); ?>
                                    </bdi>
                                </span>
                            </td>
                        </tr>

                    </tbody>
                </table>
        <?php
            }
        }
    }

    public function custom_payment_gateway_icons( $icon, $gateway_id ){
        global $wp;
        $icons = $this->getPaymentIcons();

        $customiseIcon = true;
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'wc_pos_payload') {
            $customiseIcon = false;
        }

        if($customiseIcon && $gateway_id == 'hitpay') {
            $icon = '';
            ?><?php
            if ($this->payments) {
            ?>
                <div class="form-row hitpay-payment-gateway-form">
            <?php      
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
                foreach ($this->payments as $payment) {
                    $extn = 'svg';
                    if (in_array($payment, $pngs)) {
                        $extn = 'png';
                    }
                    ?>
                    <div class="payment-labels-container">
                        <div class="payment-labels hitpay-<?php echo $payment?>">
                            <label class="hitpay-<?php echo $payment?>">
                                <img src="<?php echo HITPAY_PLUGIN_URL. '/assets/images/'.$payment.'.'.$extn; ?>" alt="<?php echo esc_attr( $icons[$payment] )?>">
                            </label>
                        </div>
                    </div>
                    <?php
                }
            ?>
                </div>
            <?php        
            }
            ?><?php
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
                'default' => 'no',
                'description'=> __( '(Enable Checkbox to enable payments in live mode)', $this->domain )
            ),
            'api_key' => array(
                'title' => __('Api Key', $this->domain),
                'type' => 'text',
                'description' => __('(Copy/Paste values from HitPay Dashboard under Payment Gateway > API Keys)', $this->domain),
                'default' => '',
            ),
            'salt' => array(
                'title' => __('Salt', $this->domain),
                'type' => 'text',
                'description' => __('(Copy/Paste values from HitPay Dashboard under Payment Gateway > API Keys)', $this->domain),
                'default' => '',
            ),
            'drop_in' => array(
                'title' => __('Checkout UI Option', $this->domain),
                'type' => 'checkbox',
                'label' => __('Enable Drop-In (Popup)', $this->domain),
                'default' => 'no',
                'description'=> __( 'The drop-in is embedded into your webpage so your customer will never have to leave your site.', $this->domain).' <br/>'.__('Redirect: Navigate your user to the hitpay checkout url, and hitpay will take care of the rest of the flow', $this->domain),
            ),
            'payments' => array(
                'title' => __('Payment Logos', $this->domain),
                'type' => 'multiselect',
                'description' => __('Activate payment methods in the HitPay dashboard under Settings > Payment Gateway > Integrations.', $this->domain),
                'css' => 'height: 10rem;',
                'options' => $this->getPaymentIcons(),
                'class' => 'wc-enhanced-select',
            ),
            'order_status' => array(
                'title' => __('Order Status', $this->domain),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('Set your desired order status upon successful payment. ', $this->domain),
                'options' => $this->getOrderStatuses(),
                'default' => 'wc-processing'
            ),
            'debug' => array(
                'title' => __('Debug', $this->domain),
                'type' => 'checkbox',
                'label' => __(' ', $this->domain),
                'default' => 'no'
            ),
            'expires_after_status' => array(
                'title' => __('Expire the payment link?', $this->domain),
                'type' => 'checkbox',
                'label' => __(' ', $this->domain),
                'default' => 'no'
            ),
            'expires_after' => array(
                'title' => __('Expire after [x] mins', $this->domain),
                'type' => 'text',
                'description' => __('Minimum value is 5. Maximum is 1000', $this->domain),
                'default' => '5',
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
            $noerror = true;
            if (isset($post_data['woocommerce_hitpay_expires_after_status'])) {
                if (empty($post_data['woocommerce_hitpay_expires_after'])) {
                    $noerror = false;
                    WC_Admin_Settings::add_error(__('Please enter expiry after mins', $this->domain));
                } elseif ($post_data['woocommerce_hitpay_expires_after'] < 5) {
                    $noerror = false;
                    WC_Admin_Settings::add_error(__('Expiry after minimum mins should be 5', $this->domain));
                } elseif ($post_data['woocommerce_hitpay_expires_after'] > 1000) {
                    $noerror = false;
                    WC_Admin_Settings::add_error(__('Expiry after maximum mins should be 1000', $this->domain));
                }
            }

            if ($noerror) {
                foreach ( $this->get_form_fields() as $key => $field ) {
                    $setting_value = $this->get_field_value( $key, $field, $post_data );
                    $this->settings[ $key ] = $setting_value;
                }

                if (isset($post_data['woocommerce_hitpay_customize'])) {
                    update_option('woocommerce_hitpay_customize', 1);
                } else {
                    update_option('woocommerce_hitpay_customize', 0);
                }
                $style = $post_data['woocommerce_hitpay_style'];
                $style = sanitize_text_field($style);
                update_option('woocommerce_hitpay_style', $style);

                $this->customize = get_option('woocommerce_hitpay_customize');
                $this->style = get_option('woocommerce_hitpay_style');

                if (isset($post_data['woocommerce_hitpay_enable_pos'])) {
                    update_option('woocommerce_hitpay_enable_pos', 1);
                } else {
                    update_option('woocommerce_hitpay_enable_pos', 0);
                }
                $this->enable_pos = get_option('woocommerce_hitpay_enable_pos');

                if ($this->enable_pos) {
                    if (isset($post_data['woocommerce_hitpay_terminal_ids'])) {
                        $terminal_ids = $post_data['woocommerce_hitpay_terminal_ids'];
                        $cashier_emails = $post_data['woocommerce_hitpay_cashier_emails'];

                        $terminal_ids_sanitized = array();
                        $cashier_emails_sanitized = array();
                        foreach ($terminal_ids as $key => $val) {
                            if (!empty($val)) {
                                $terminal_ids_sanitized[] = sanitize_text_field($val);
                                $cashier_emails_sanitized[] = sanitize_text_field($cashier_emails[$key]);
                            }
                        }
                        $this->terminal_ids = $terminal_ids_sanitized;
                        $this->cashier_emails = $cashier_emails_sanitized;
                        if (count($terminal_ids_sanitized) > 0) {
                            update_option('woocommerce_hitpay_terminal_ids', json_encode($terminal_ids_sanitized));
                            update_option('woocommerce_hitpay_cashier_emails', json_encode($cashier_emails_sanitized));
                        } else {
                            delete_option('woocommerce_hitpay_terminal_ids');
                            delete_option('woocommerce_hitpay_cashier_emails');
                        }
                     } else {
                        delete_option('woocommerce_hitpay_terminal_ids');
                        delete_option('woocommerce_hitpay_cashier_emails');
                        $this->terminal_ids = array();
                        $this->cashier_emails = array();
                    }
                } else {
                    delete_option('woocommerce_hitpay_terminal_ids');
                    delete_option('woocommerce_hitpay_cashier_emails');
                    $this->terminal_ids = array();
                    $this->cashier_emails = array();
                }

                return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ) );
            }
        }
    }

    public function hitpay_admin_footer() {
        $this->expires_after_status = $this->get_option('expires_after_status');
        $this->expires_after = $this->get_option('expires_after');
        if (isset($_GET['page']) && sanitize_text_field($_GET['page']) == 'wc-settings' && isset($_GET['section']) && sanitize_text_field($_GET['section']) == 'hitpay') { 
            $hitpaytab = 'settings';
            if(isset($_GET['hitpaytab'])) {
                $hitpaytab = sanitize_text_field($_GET['hitpaytab']);
            }
        ?>
        <nav id="hitpay-tabs" class="nav-tab-wrapper" style="display: none">
            <a id="hitpay-setting-tab" href="?page=wc-settings&section=hitpay&tab=checkout&hitpaytab=settings" class="nav-tab <?php echo (($hitpaytab == 'settings') ? 'nav-tab-active':'')?>">Settings</a>
            <a id="hitpay-customize-tab" href="?page=wc-settings&section=hitpay&hitpaytab=customize&tab=checkout" class="nav-tab <?php echo (($hitpaytab == 'customize') ? 'nav-tab-active':'')?>">Customization</a>
            <a id="hitpay-pos-settings-tab" href="?page=wc-settings&section=hitpay&hitpaytab=pos-settings&tab=checkout" class="nav-tab <?php echo (($hitpaytab == 'pos-settings') ? 'nav-tab-active':'')?>">POS Payments</a>
        </nav>
        <div class="tab-content" id="hitpay-tab-content-customize" style="display: none">
            <table class="form-table">
                <tbody>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="woocommerce_hitpay_customize">Enable Status Display </label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Enable Status Display</span></legend>
                                <label for="woocommerce_hitpay_customize">
                                    <input class="" type="checkbox" name="woocommerce_hitpay_customize" id="woocommerce_hitpay_customize" style="" value="1" 
                                        <?php echo $this->customize ? 'checked="checked"':''?> >
                                </label>
                                <br>
                                <span class="woocommerce-help-tip2">
                                    If enabled, payment status will be retrieved and displayed on the Order Confirmation Page.
                                </span>
                            </fieldset>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                           <label for="woocommerce_hitpay_style">Style</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Style</span></legend>
                                <textarea rows="3" cols="20" class="input-text wide-input " type="textarea" name="woocommerce_hitpay_style" id="woocommerce_hitpay_style"><?php echo $this->style?></textarea>
                                <br/>
                                <span class="woocommerce-help-tip2">
                                   Here you can update CSS styles for HitPay Payment status display container.
                               </span>
                            </fieldset>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="tab-content" id="hitpay-tab-content-pos-settings" style="display: none">
            <table class="form-table">
                <tbody>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="woocommerce_hitpay_enable_pos">Enable POS Payments</label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Enable POS Payments</span></legend>
                                <label for="woocommerce_hitpay_enable_pos">
                                    <input class="" type="checkbox" name="woocommerce_hitpay_enable_pos" id="woocommerce_hitpay_enable_pos" style="" value="1" 
                                        <?php echo $this->enable_pos ? 'checked="checked"':''?> >
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr valign="top">
                        <td class="forminp" colspan="2" id="terminal_id_settings">
                            <div>Enter Terminal Reader Information:</div>
                            <div class="field_wrapper">
                                <?php 
                                if (count($this->terminal_ids) > 0) {
                                    $i = 0;
                                    foreach($this->terminal_ids as $key => $val) {
                                        $i++;
                                ?>
                                <table style="border-bottom: 1px solid #ccc; margin-bottom: 10px" <?php if ($i > 1) {?>class="dynamic-field-table"<?php } ?>>
                                    <tr valign="top">
                                        <th scope="row" class="titledesc">
                                            <label for="woocommerce_hitpay_enable_pos">Terminal Reader ID</label>
                                        </th>
                                        <td class="forminp">
                                            <input type="text" name="woocommerce_hitpay_terminal_ids[]" value="<?php echo $val?>"/>
                                        </td>
                                        <td>
                                            <?php if ($i > 1) {?>
                                            <a href="javascript:void(0);" class="btn button-secondary remove_button" title="Remove field">Remove</a>
                                            <?php } else { ?>
                                            &nbsp;
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row" class="titledesc">
                                            <label for="woocommerce_hitpay_enable_pos">Cashier E-mail (Optional)</label>
                                        </th>
                                        <td class="forminp">
                                            <input type="email" name="woocommerce_hitpay_cashier_emails[]" value="<?php echo (isset($this->cashier_emails[$key]) ? $this->cashier_emails[$key] : '') ?>"/>
                                        </td>
                                        <td>&nbsp;</td>
                                    </tr>
                                </table>
                                <?php 
                                    }
                                } else {
                                ?>
                                <table style="border-bottom: 1px solid #ccc; margin-bottom: 10px">
                                    <tr valign="top">
                                        <th scope="row" class="titledesc">
                                            <label for="woocommerce_hitpay_enable_pos">Terminal Reader ID</label>
                                        </th>
                                        <td class="forminp">
                                            <input type="text" name="woocommerce_hitpay_terminal_ids[]" value=""/>
                                        </td>
                                        <td>
                                              &nbsp;
                                         </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row" class="titledesc">
                                            <label for="woocommerce_hitpay_enable_pos">Cashier E-mail (Optional)</label>
                                        </th>
                                        <td class="forminp">
                                            <input type="text" name="woocommerce_hitpay_cashier_emails[]" value=""/>
                                        </td>
                                        <td>&nbsp;</td>
                                    </tr>
                                </table>
                                <?php
                                }
                                ?>
                            </div>
                            <div>
                                <a href="javascript:void(0);" class="btn button-secondary add_button" title="Add field">Add New</a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <script type="text/javascript">
            var hitpaytab = '<?php echo $hitpaytab?>';
            jQuery(document).ready(function(){
                var maxField = 5;
                var addButton = jQuery('.add_button');
                var wrapper = jQuery('.field_wrapper');
                var fieldHTML = '<table class="dynamic-field-table" style="border-bottom: 1px solid #ccc; margin-bottom: 10px"><tr valign="top"><th scope="row" class="titledesc"><label for="woocommerce_hitpay_enable_pos">Terminal Reader ID</label></th><td class="forminp"><input type="text" name="woocommerce_hitpay_terminal_ids[]" value=""/></td><td><a href="javascript:void(0);" class="btn button-secondary remove_button" title="Remove field">Remove</a></td></tr><tr valign="top"><th scope="row" class="titledesc"><label for="woocommerce_hitpay_enable_pos">Cashier E-mail (Optional)</label></th><td class="forminp"><input type="text" name="woocommerce_hitpay_cashier_emails[]" value=""/></td></tr></table>';
                var x = parseInt('<?php echo (count($this->terminal_ids) == 0) ? 1 : count($this->terminal_ids)?>');

                jQuery('.wc-admin-breadcrumb').parent().after(jQuery('#hitpay-tabs'));
                jQuery('#hitpay-tabs').after(jQuery('#hitpay-tab-content-customize'));
                jQuery('#hitpay-tab-content-customize').after(jQuery('#hitpay-tab-content-pos-settings'));
                jQuery('#hitpay-tabs').show();
                if (hitpaytab == 'settings') {
                    jQuery('#woocommerce_hitpay_enabled').closest('.form-table').show();
                    jQuery('#hitpay-tab-content-customize').hide();
                    jQuery('#hitpay-tab-content-pos-settings').hide();
                } else if (hitpaytab == 'customize') {
                    jQuery('#woocommerce_hitpay_enabled').closest('.form-table').hide();
                    jQuery('#hitpay-tab-content-customize').show();
                } else if (hitpaytab == 'pos-settings') {
                    jQuery('#woocommerce_hitpay_enabled').closest('.form-table').hide();
                    jQuery('#hitpay-tab-content-customize').hide();
                    jQuery('#hitpay-tab-content-pos-settings').show();
                } 

                if (jQuery('#woocommerce_hitpay_expires_after_status').is(':checked')) {
                    jQuery('#woocommerce_hitpay_expires_after').parent().parent().parent().show();
                } else {
                    jQuery('#woocommerce_hitpay_expires_after').parent().parent().parent().hide();
                }

                jQuery('#woocommerce_hitpay_expires_after_status').click(function(){
                    if (jQuery('#woocommerce_hitpay_expires_after_status').is(':checked')) {
                        jQuery('#woocommerce_hitpay_expires_after').parent().parent().parent().show();
                    } else {
                        jQuery('#woocommerce_hitpay_expires_after').parent().parent().parent().hide();
                    }
                });

                jQuery('#woocommerce_hitpay_payments').select2({
                    maximumSelectionLength: 10,
                });

                if (jQuery('#woocommerce_hitpay_enable_pos').is(':checked')) {
                    jQuery('#terminal_id_settings').show();
                } else {
                    jQuery('#terminal_id_settings').hide();
                }

                jQuery('#woocommerce_hitpay_enable_pos').click(function(){
                    if (jQuery('#woocommerce_hitpay_enable_pos').is(':checked')) {
                        jQuery('#terminal_id_settings').show();
                    } else {
                        jQuery('#terminal_id_settings').hide();
                    }
                });

                jQuery(addButton).click(function(){
                    if(x < maxField){ 
                        x++;
                        jQuery(wrapper).append(fieldHTML);
                    } else {
                        alert('Allowed to add maximum: '+maxField);
                    }
                });

                jQuery(wrapper).on('click', '.remove_button', function(e){
                    e.preventDefault();
                    jQuery(this).parents('.dynamic-field-table').remove();
                    x--; 
                });
            });
        </script>  
        <?php
        }
    }

    public function option_exists($name, $site_wide=false)
    {
        global $wpdb; 
        return $wpdb->query("SELECT * FROM ". ($site_wide ? $wpdb->base_prefix : $wpdb->prefix). "options WHERE option_name ='$name' LIMIT 1");
    }

    function payment_fields()
    { 
        ?>
        <div class="form-row form-row-wide">
            <?php if (!empty($this->description)) {?>
            <p><?php echo $this->description; ?></p>
            <?php } ?>
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
            <?php
            if ($this->enable_pos && count($this->terminal_ids) > 0) {
                $filter_terminal_ids = $this->filterTerminalIds();
            ?>
            <div class="hitpay-payment-selection">
                <label class="woocommerce-form__label woocommerce-form__label-for-radio radio" for="hitpay_payment_option-0" <?php if (count($filter_terminal_ids) == 1) {?>style="float:left"<?php }?>>
                    <input id="hitpay_payment_option-0"
                           class="woocommerce-form__input woocommerce-form__input-radio input-radio"
                           type="radio"
                           name="hitpay_payment_option" 
                           value="onlinepayment" checked="checked"> 
                    <p style="display: inline">Online Payments</p>
                </label>

                <?php if (count($filter_terminal_ids) == 1) {?>
                <label class="woocommerce-form__label woocommerce-form__label-for-radio radio"  for="hitpay_payment_option-1">
                    <input id="hitpay_payment_option-1"
                           class="woocommerce-form__input woocommerce-form__input-radio input-radio"
                           type="radio"
                           name="hitpay_payment_option" 
                           value="<?php echo $filter_terminal_ids[0]?>"> 
                    <p style="display: inline">Card Reader</p>
                </label>
                <?php 
                    } else {
                        foreach ($filter_terminal_ids as $key => $val) {
                ?>
                        <label class="woocommerce-form__label woocommerce-form__label-for-radio radio"  for="hitpay_payment_option-<?php echo ($key+2)?>">
                            <input id="hitpay_payment_option-<?php echo ($key+2)?>"
                                   class="woocommerce-form__input woocommerce-form__input-radio input-radio"
                                   type="radio"
                                   name="hitpay_payment_option" 
                                   value="<?php echo $val?>"> 
                            <p style="display: inline">Card Reader - Terminal ID: <?php echo $val?></p>
                        </label>
                <?php
                        }
                    }
                ?>
            </div>
             <?php
            }
            ?>
            <?php if ($this->drop_in) {?>
            <div id="hitpay_background_layer"></div>
            <?php }?>
        </div>
        <?php
    }

    public function filterTerminalIds()
    {
        $filtered_terminal_ids = array();
        $user = wp_get_current_user();
        $email = $user->user_email;

        if (!empty($email)) {
            $i = 0;
            foreach ($this->terminal_ids as $key => $val) {
                $cashier_email = $this->cashier_emails[$key];
                if ($email == $cashier_email) {
                    $filtered_terminal_ids[$i++] = $val;
                }
            }
        }

        if (count($filtered_terminal_ids) == 0) {
            $filtered_terminal_ids = array_values($this->terminal_ids);
        }
        return $filtered_terminal_ids;
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page($order_id) {
        if ($this->customize) {
            $order = new WC_Order($order_id);

            $thankyoupage_ui_enabled = 1;
            $style = $this->style;

            if (isset($_GET['status'])) {
                $status = sanitize_text_field($_GET['status']);

                if ($status == 'canceled') {
                    $status = $order->get_status();
                    if ($status == 'processing' || $status == 'completed') {
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
                        jQuery('.payment-panel-'+type).eq(0).show();
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
        if (!isset($_GET['hitpay_order_id'])) {
            $this->log('return_from_hitpay order_id check failed');
            exit;
        }

        $order_id = (int)sanitize_text_field($_GET['hitpay_order_id']);
        $order = new WC_Order($order_id);

        if (isset($_GET['status'])) {
            $status = sanitize_text_field($_GET['status']);
            $reference = sanitize_text_field($_GET['reference']);

            if ($status == 'canceled') {
                $status = $order->get_status();
                if ($status == 'processing' || $status == 'completed') {
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

        if (!isset($_GET['hitpay_order_id']) || !isset($_POST['hmac'])) {
            $this->log('order_id + hmac check failed');
            exit;
        }

        $order_id = (int)sanitize_text_field($_GET['hitpay_order_id']);

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
                        //&& $order_id == $_POST['reference_number']
                        && $order_data['currency'] == $_POST['currency']
                    ) {
                        $payment_id = sanitize_text_field($_POST['payment_id']);
                        $payment_request_id = sanitize_text_field($_POST['payment_request_id']);
                        $hitpay_currency = sanitize_text_field($_POST['currency']);
                        $hitpay_amount = sanitize_text_field($_POST['amount']);

                        if (empty($this->order_status)) {
                            $order->update_status('processing', __('Payment successful. Transaction Id: '.$payment_id, $this->domain));
                        } elseif ($this->order_status == 'wc-pending') {
                            $order->update_status('pending', __('Payment successful. Transaction Id: '.$payment_id, $this->domain));
                        } elseif ($this->order_status == 'wc-processing') {
                            $order->update_status('processing', __('Payment successful. Transaction Id: '.$payment_id, $this->domain));
                        } elseif ($this->order_status == 'wc-completed') {
                            $order->update_status('completed', __('Payment successful. Transaction Id: '.$payment_id, $this->domain));
                        }

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

    public function process_refund($orderId, $amount = NULL, $reason = '') {
        $order = wc_get_order($orderId);
        $amount = (float)strip_tags(trim($amount));
        $amountValue = number_format($amount, 2);

        try {
            $HitPay_transaction_id = get_post_meta( $orderId, 'HitPay_transaction_id', true );
            $HitPay_is_refunded = get_post_meta( $orderId, 'HitPay_is_refunded', true );
            if ($HitPay_is_refunded == 1) {
                throw new Exception(__('Only one refund allowed per transaction by HitPay Gateway.',  $this->domain));
            }

            $order_total_paid = $order->get_total();

            if ($amountValue <=0 ) {
                throw new Exception(__('Refund amount shoule be greater than 0.',  $this->domain));
            }

            if ($amountValue > $order_total_paid) {
                throw new Exception(__('Refund amount shoule be less than or equal to order paid total.',  $this->domain));
            }

            $hitpayClient = new Client(
                $this->api_key,
                $this->getMode()
            );

            $result = $hitpayClient->refund($HitPay_transaction_id, $amountValue);

            $order->add_meta_data('HitPay_is_refunded', 1);
            $order->add_meta_data('HitPay_refund_id', $result->getId());
            $order->add_meta_data('HitPay_refund_amount_refunded', $result->getAmountRefunded());
            $order->add_meta_data('HitPay_refund_created_at', $result->getCreatedAt());
            $order->save_meta_data();

            $message = __('Refund successful. Refund Reference Id: '.$result->getId().', '
                . 'Payment Id: '.$HitPay_transaction_id.', Amount Refunded: '.$result->getAmountRefunded().', '
                . 'Payment Method: '.$result->getPaymentMethod().', Created At: '.$result->getCreatedAt(), $this->domain);

            $total_refunded = $result->getAmountRefunded();
            if ($total_refunded >= $order_total_paid) {
                $order->update_status('refunded', $message);
            } else {
                $order->add_order_note( $message );
            }

            return true;
        } catch (\Exception $e) {
            return new WP_Error(400, $e->getMessage());
        }
    }

    public function check_ipn_response() {
        global $woocommerce;
        if (isset($_GET['get_order_status'])) {
            $this->get_payment_staus();
        } else if (isset($_GET['hitpayreturn'])) {
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
            $redirect_url = site_url().'/?wc-api=wc_hitpay&hitpayreturn=1&hitpay_order_id='.$order_id;
            $webhook = site_url().'/?wc-api=wc_hitpay&hitpay_order_id='.$order_id;

            $create_payment_request = new CreatePayment();
            $create_payment_request->setAmount($order_total)
                ->setCurrency($order_data['currency'])
                ->setReferenceNumber($order->get_order_number())
                ->setWebhook($webhook)
                ->setRedirectUrl($redirect_url)
                ->setChannel('api_woocomm');

            $create_payment_request->setName($order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name']);
            $create_payment_request->setEmail($order_data['billing']['email']);

            $create_payment_request->setPurpose($this->getSiteName());

            if ($this->expires_after_status == 'yes') {
                if (empty($this->expires_after)) {
                    $this->expires_after = '6';
                }
                 $create_payment_request->setExpiresAfter($this->expires_after.' mins');
            }

            if ($this->enable_pos && count($this->terminal_ids) > 0) {
                if (isset($_POST['hitpay_payment_option']) && ($_POST['hitpay_payment_option'] != 'onlinepayment')) {
                    $terminal_id = sanitize_text_field($_POST['hitpay_payment_option']);
                    $create_payment_request->setPaymentMethod('wifi_card_reader');
                    $create_payment_request->setWifiTerminalId($terminal_id);
                }
            }

            $this->log('Request:');
            $this->log((array)$create_payment_request);

            $result = $hitpay_client->createPayment($create_payment_request);

            $this->log('Response:');
            $this->log((array)$result);

            $order->delete_meta_data('HitPay_payment_id');
            $order->add_meta_data('HitPay_payment_id', $result->getId());

            $order->save_meta_data();

            $hitpayDomain = 'sandbox.hit-pay.com';
            if ($this->mode == 'yes') {
                $hitpayDomain = 'hit-pay.com';
            }

            if ($result->getStatus() == 'pending') {
                return array(
                    'result' => 'success',
                    'redirect' => $result->getUrl(),
                    'domain' => $hitpayDomain,
                    'apiDomain' => $hitpayDomain,
                    'payment_request_id' => $result->getId(),
                    'redirect_url' => $redirect_url
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
                'redirect' => wc_get_checkout_url()
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

    public function getPaymentIcons()
    {
        $methods = [
            'paynow' => __('PayNow QR', $this->domain),
            'visa' => __('Visa', $this->domain),
            'master' => __('Mastercard', $this->domain),
            'american_express' => __('American Express', $this->domain),
            'apple_pay' => __('Apple Pay', $this->domain),
            'google_pay' => __('Google Pay', $this->domain),
            'grabpay' => __('GrabPay', $this->domain),
            'wechatpay' => __('WeChatPay', $this->domain),
            'alipay' => __('AliPay', $this->domain),
            'shopeepay'        => __( 'ShopeePay', $this->domain ),
            'fpx'        => __( 'FPX', $this->domain ),
            'zip'        => __( 'Zip', $this->domain ),
            'atomeplus' => __('ATome+'),
            'unionbank' => __('Unionbank Online'),
            'qrph' => __('Instapay QR PH'),
            'pesonet' => __('PESONet'),
            'billease' => __('Billease BNPL'),
            'gcash' => __('GCash'),
            'eftpos' => __('eftpos'),
            'maestro' => __('maestro'),
            'alfamart' => __('Alfamart'),
            'indomaret' => __('Indomaret'),
            'dana' => __('DANA'),
            'gopay' => __('gopay'),
            'linkaja' => __('Link Aja!'),
            'ovo' => __('OVO'),
            'qris' => __('QRIS'),
            'danamononline' => __('Bank Danamon'),
            'permata' => __('PermataBank'),
            'bsi' => __('Bank Syariah Indonesia'),
            'bca' => __('BCA'),
            'bni' => __('BNI'),
            'bri' => __('BRI'),
            'cimb' => __('CIMB Niaga'),
            'doku' => __('DOKU'),
            'mandiri' => __('Mandiri'),
            'akulaku' => __('AkuLaku BNPL'),
            'kredivo' => __('Kredivo BNPL'),
            'philtrustbank' => __('PHILTRUST BANK'),
            'allbank' => __('AllBank'),
            'aub' => __('ASIA UNITED BANK'),
            'chinabank' => __('CHINABANK'),
            'instapay' => __('instaPay'),
            'landbank' => __('LANDBANK'),
            'metrobank' => __('Metrobank'),
            'pnb' => __('PNB'),
            'queenbank' => __('QUEENBANK'),
            'rcbc' => __('RCBC'),
            'tayocash' => __('TayoCash'),
            'ussc' => __('USSC'),
            'bayad' => __('bayad'),
            'cebuanalhuillier' => __('CEBUANA LHUILLIER'),
            'ecpay' => __('ecPay'),
            'palawan' => __('PALAWAN PAWNSHOP'),
            'bpi' => __('BPI'),
            'psbank' => __('PSBank'),
            'robinsonsbank' => __('Robinsons Bank'),
            'diners_club' => __('Diners Club'),
            'discover' => __('Discover'),
            'doku_wallet' => __('DOKU Wallet'),
            'grab_paylater' => __('PayLater by Grab'),
            'favepay' => __('FavePay'),
            'shopback_paylater' => __('ShopBack PayLater'),
        ];

        return $methods;
    }

    public function log($content)
    {
        $debug = $this->debug;
        if ($debug == 'yes') {
            $file = HITPAY_PLUGIN_PATH.'debug.log';
            try {
                $fp = fopen($file, 'a+');
                if ($fp) {
                    fwrite($fp, "\n");
                    fwrite($fp, date("Y-m-d H:i:s").": ");
                    fwrite($fp, print_r($content, true));
                    fclose($fp);
                }
            } catch (\Exception $e) {}
        }
    }

    public function getOrderStatuses()
    {
        $statuses = wc_get_order_statuses();
        unset($statuses['wc-cancelled']);
        unset($statuses['wc-refunded']);
        unset($statuses['wc-failed']);
        unset($statuses['wc-on-hold']);
        return $statuses;
    }
}