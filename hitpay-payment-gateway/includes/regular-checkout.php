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
	
	public $terminal_ids;
	public $enable_pos;
	public $place_order_text;
	public $payment_button;
	public $style;
	public $customize;
	public $drop_in;
	public $expires_after;
	public $expires_after_status;
	public $order_status;
	public $payments;
	public $salt;
	public $api_key;
	public $debug;
	public $mode;
	public $cashier_emails;
	
    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        
        $this->testConnection();

        $this->domain = 'hitpay-payment-gateway';

        $this->supports = array(
            'products',
            'refunds'
        );

        $this->id = 'hitpay';
        $this->icon = HITPAY_PLUGIN_URL . 'assets/images/logo.png';
        $this->has_fields = true;
        $this->method_title = __('HitPay Payment Gateway', 'hitpay-payment-gateway');

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
		
		if (!$this->option_exists("woocommerce_hitpay_payment_button")) {
            $this->payment_button = 1;
        } else {
            $this->payment_button = get_option('woocommerce_hitpay_payment_button');
        }

        if (!$this->option_exists("woocommerce_hitpay_place_order_text")) {
            $this->place_order_text = 'Complete Payment';
        } else {
            $this->place_order_text = get_option('woocommerce_hitpay_place_order_text');
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

    public function getHostName()
    {
        $domainIp = site_url();
        $url = parse_url($domainIp);
        if (isset($url['host'])) {
            $domainIp = gethostbyname($url['host']);
        } else {
            $domainIp = 'IP Not Found';
        }
        return $domainIp;
    }

    public function testConnection()
    {
        if (isset($_GET['hitpaytestnonce']) && !wp_verify_nonce(sanitize_key($_GET['hitpaytestnonce']), 'hitpay-settings')) {
			exit;
		}
        if (isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) == 'wc-settings' && isset($_GET['section']) && sanitize_text_field(wp_unslash($_GET['section'])) == 'hitpay') { 
            if(isset($_GET['hitpaytab']) && sanitize_text_field(wp_unslash($_GET['hitpaytab'])) == 'testconnection') {

                $mode = '';
                if (isset($_POST['mode'])) {
                    $mode = sanitize_text_field(wp_unslash($_POST['mode']));
                }

                $api_key = '';
                if (isset($_POST['api_key'])) {
                    $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));
                }

                $response = array();
                $response['my_ip_address'] = $this->getHostName();

                try {
                    if (empty($api_key)) {
                        $response['request_sent'] = '<p>Request not sent yet, initial check failed.</p>';
                        $response['response_from_hitpay'] = '<p>N/A</p>';
                        
                        throw new Exception('Enter your API key.');
                    }

                    if (!extension_loaded('curl')) {
                        $response['request_sent'] = '<p>Request not sent yet, initial check failed.</p>';
                        $response['response_from_hitpay'] = '<p>N/A</p>';

                        throw new Exception('CURL extension is not enabled on your server.');
                    }

                    $hitpay_client = new Client(
                        $api_key,
                        $this->getTestConnectionMode($mode)
                    );
                    
                    $create_payment_request = new CreatePayment();
                    $create_payment_request->setAmount(10)
                        ->setCurrency(get_woocommerce_currency())
                        ->setReferenceNumber('Test Connection - '.HITPAY_VERSION);

                    $payload = array (
                        'amount' => $create_payment_request->getAmount(),
                        'currency' => $create_payment_request->getCurrency(),
                        'reference_number' => $create_payment_request->getReferenceNumber()
                    );

                    $result = $hitpay_client->createPaymentTest($create_payment_request);
        
                    if ($result['status'] == 'success') {
                        $response['status'] = 'success';
                        $response['message'] = 'Your store connection with HitPay is successful.';
                    } else {
                        $response['status'] = 'error';
                        $response['message'] = $result['httpCode']. ': Failed to connect to HitPay Gateway Server.';
                        $response['response_from_hitpay'] = '<p>'.$result['content'].'</p>';
                        $request_sent = '<p><strong>End Point: </strong>'.$result['endpoint'].'</p>';
                        // @codingStandardsIgnoreStart
                        $request_sent .= '<p><strong>Headers: </strong>'.print_r($result['headers'], true).'</p>';
                        $request_sent .= '<p><strong>Pay Load: </strong>'.print_r($payload, true).'</p>';
                        // @codingStandardsIgnoreEnd
                        $response['request_sent'] = $request_sent;
                    }

                } catch (\Exception $e) {
                    $message = $e->getMessage();
                    $response['status'] = 'error';
                    $response['message'] = $message;
                }

                echo json_encode($response);
                exit;
            }
        }
    }

    public function hitpay_load_front_assets() {
        if ( is_checkout() ) {         
            wp_enqueue_style( 'hitpay-css', HITPAY_PLUGIN_URL.'/assets/css/front.css', array(),HITPAY_VERSION,'all' );
            if ($this->drop_in == 'yes') {
                $dropin_js = 'https://sandbox.hit-pay.com/hitpay.js';
                if ($this->mode == 'yes') {
                    $dropin_js = 'https://hit-pay.com/hitpay.js';
                }
                wp_enqueue_script(
					'hitpay_js', 
					$dropin_js, 
					array(), 
					HITPAY_VERSION,
					array(
						'in_footer'  => 'true',
					)
				);
                wp_enqueue_script(
					'hitpay_dropin_js',
					HITPAY_PLUGIN_URL.'/assets/js/dropin.js',
					array(),
					HITPAY_VERSION,
					array(
						'in_footer'  => 'true',
					)
				);
            }
        }  
    }
	
	public function isHPOSEnabled() {
		$status = false;
		
		if ( 
			class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && 
			Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
		) {
			$status = true;
		}
		
		return $status;
	}
	
	public function getOrder($order_id) {
		if ($this->isHPOSEnabled()) {
			$order = wc_get_order( $order_id );
		} else {
			$order = new WC_Order($order_id);
		}
		
		return $order;
	}
	
	public function getOrderMetaData($order, $order_id, $key, $single) {
		if ($this->isHPOSEnabled()) {
			return $order->get_meta( $key, $single );
		} else {
			return get_post_meta( $order_id, $key, $single );
		}
	}

    public function admin_order_totals( $order_id ){
		$order = $this->getOrder($order_id);
        
        if ($order->get_payment_method() == $this->id) {
            $order_id = $order->get_id();
            $payment_method = '';
            $payment_request_id = $this->getOrderMetaData($order, $order_id, 'HitPay_payment_request_id', true );

            if (!empty($payment_request_id)) {
                $payment_method = $this->getOrderMetaData($order, $order_id, 'HitPay_payment_method', true );
                $fees = $this->getOrderMetaData($order, $order_id, 'HitPay_fees', true );
				$fees_currency = $this->getOrderMetaData($order, $order_id, 'HitPay_fees_currency', true );
                if (empty($payment_method) || empty($fees) || empty($fees_currency)) {
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
								$fees_currency = $payment->fees_currency;
								$order->add_meta_data('HitPay_fees_currency', $fees_currency);
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
                <table class="wc-order-totals" style="border-top: 1px solid #999; margin-top:12px; padding-top:12px">
                    <tbody>
                        <tr>
                            <td class="label"><?php esc_html_e('HitPay Payment Type', 'hitpay-payment-gateway') ?>:</td>
                            <td width="1%"></td>
                            <td class="total">
                                <span class="woocommerce-Price-amount amount"><bdi><?php echo esc_html(ucwords(str_replace("_", " ", $payment_method))) ?></bdi></span>
                            </td>
                        </tr>
                        <tr>
                            <td class="label"><?php esc_html_e('HitPay Fee', 'hitpay-payment-gateway') ?>:</td>
                            <td width="1%"></td>
                            <td class="total">
                                <span class="woocommerce-Price-amount amount">
                                    <bdi>
                                    <?php echo esc_html($fees); ?>
									<?php echo esc_html( strtoupper($fees_currency))?>
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
		
		// @codingStandardsIgnoreStart
		/*
		This is not form submitted data.
		And we can not control this URL.
		Here we're just checking whether current page is specific pos plugin checkout page.
		If do not display the custom icons on that specific pos plugin checkout page
		*/
        if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'wc_pos_payload') {
            $customiseIcon = false;
        }
		// @codingStandardsIgnoreEnd

        if($customiseIcon && $gateway_id == 'hitpay') {
            $icon = '';
            if ($this->payments) {
				$icon .= '<div class="form-row hitpay-payment-gateway-form">';
     
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
                        // @codingStandardsIgnoreStart
						$icon .= '
						<div class="payment-labels-container">
							<div class="payment-labels hitpay-'.esc_attr($payment).'">
								<label class="hitpay-'.esc_attr($payment).'">
									<img src="'.HITPAY_PLUGIN_URL. '/assets/images/'.esc_attr($payment).'.'.esc_attr($extn).'" alt="'.esc_attr( $icons[$payment] ).'">
                                </label>
							</div>
						</div>
						';
                        // @codingStandardsIgnoreEnd
					}
				$icon .= '</div>';
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
                'title' => __('Active', 'hitpay-payment-gateway'),
                'type' => 'checkbox',
                'label' => ' ',
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'hitpay-payment-gateway'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'hitpay-payment-gateway'),
                'default' => $this->method_title,
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'hitpay-payment-gateway'),
                'type' => 'textarea',
                'description' => __('Instructions that the customer will see on your checkout.', 'hitpay-payment-gateway'),
                'default' => $this->method_description,
                'desc_tip' => true,
            ),
            'mode' => array(
                'title' => __('Live Mode', 'hitpay-payment-gateway'),
                'type' => 'checkbox',
                'label' => ' ',
                'default' => 'no',
                'description'=> __( '(Enable Checkbox to enable payments in live mode)', 'hitpay-payment-gateway' )
            ),
            'api_key' => array(
                'title' => __('Api Key', 'hitpay-payment-gateway'),
                'type' => 'text',
                'description' => __('(Copy/Paste values from HitPay Dashboard under Payment Gateway > API Keys)', 'hitpay-payment-gateway'),
                'default' => '',
            ),
            'salt' => array(
                'title' => __('Salt', 'hitpay-payment-gateway'),
                'type' => 'text',
                'description' => __('(Copy/Paste values from HitPay Dashboard under Payment Gateway > API Keys)', 'hitpay-payment-gateway'),
                'default' => '',
            ),
            'drop_in' => array(
                'title' => __('Checkout UI Option', 'hitpay-payment-gateway'),
                'type' => 'checkbox',
                'label' => __('Enable Drop-In (Popup)', 'hitpay-payment-gateway'),
                'default' => 'no',
                'description'=> __( 'The drop-in is embedded into your webpage so your customer will never have to leave your site.', 'hitpay-payment-gateway').' <br/>'.__('Redirect: Navigate your user to the hitpay checkout url, and hitpay will take care of the rest of the flow', 'hitpay-payment-gateway'),
            ),
            'payments' => array(
                'title' => __('Payment Logos', 'hitpay-payment-gateway'),
                'type' => 'multiselect',
                'description' => __('Activate payment methods in the HitPay dashboard under Settings > Payment Gateway > Integrations.', 'hitpay-payment-gateway'),
                'css' => 'height: 10rem;',
                'options' => $this->getPaymentIcons(),
                'class' => 'wc-enhanced-select',
            ),
            'order_status' => array(
                'title' => __('Order Status', 'hitpay-payment-gateway'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'description' => __('Set your desired order status upon successful payment. ', 'hitpay-payment-gateway'),
                'options' => $this->getOrderStatuses(),
                'default' => 'wc-processing'
            ),
            'debug' => array(
                'title' => __('Debug', 'hitpay-payment-gateway'),
                'type' => 'checkbox',
                'label' => ' ',
                'default' => 'no'
            ),
            'expires_after_status' => array(
                'title' => __('Expire the payment link?', 'hitpay-payment-gateway'),
                'type' => 'checkbox',
                'label' => ' ',
                'default' => 'no'
            ),
            'expires_after' => array(
                'title' => __('Expire after [x] mins', 'hitpay-payment-gateway'),
                'type' => 'text',
                'description' => __('Minimum value is 5. Maximum is 1000', 'hitpay-payment-gateway'),
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
            WC_Admin_Settings::add_error(__('Please enter HitPay API Key', 'hitpay-payment-gateway'));
        } elseif (empty($post_data['woocommerce_hitpay_salt'])) {
            WC_Admin_Settings::add_error(__('Please enter HitPay API Salt', 'hitpay-payment-gateway'));
        } else {
            $noerror = true;
            if (isset($post_data['woocommerce_hitpay_expires_after_status'])) {
                if (empty($post_data['woocommerce_hitpay_expires_after'])) {
                    $noerror = false;
                    WC_Admin_Settings::add_error(__('Please enter expiry after mins', 'hitpay-payment-gateway'));
                } elseif ($post_data['woocommerce_hitpay_expires_after'] < 5) {
                    $noerror = false;
                    WC_Admin_Settings::add_error(__('Expiry after minimum mins should be 5', 'hitpay-payment-gateway'));
                } elseif ($post_data['woocommerce_hitpay_expires_after'] > 1000) {
                    $noerror = false;
                    WC_Admin_Settings::add_error(__('Expiry after maximum mins should be 1000', 'hitpay-payment-gateway'));
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
                    update_option('woocommerce_hitpay_customize', -1);
                }
                $style = $post_data['woocommerce_hitpay_style'];
                $style = sanitize_text_field($style);
                update_option('woocommerce_hitpay_style', $style);

                $this->customize = get_option('woocommerce_hitpay_customize');
                $this->style = get_option('woocommerce_hitpay_style');
				
				if (isset($post_data['woocommerce_hitpay_payment_button'])) {
                    update_option('woocommerce_hitpay_payment_button', 1);
                } else {
                    update_option('woocommerce_hitpay_payment_button', -1);
                }
                $place_order_text = $post_data['woocommerce_hitpay_place_order_text'];
                $place_order_text = sanitize_text_field($place_order_text);
                update_option('woocommerce_hitpay_place_order_text', $place_order_text);

                $this->payment_button = get_option('woocommerce_hitpay_payment_button');
                $this->place_order_text = get_option('woocommerce_hitpay_place_order_text');

                if (isset($post_data['woocommerce_hitpay_enable_pos'])) {
                    update_option('woocommerce_hitpay_enable_pos', 1);
                } else {
                    update_option('woocommerce_hitpay_enable_pos', -1);
                }
                $this->enable_pos = get_option('woocommerce_hitpay_enable_pos');

                if ($this->enable_pos == 1) {
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
        wp_enqueue_script( 'jquery-ui-dialog' );
        wp_enqueue_style( 'wp-jquery-ui-dialog' );
        $this->expires_after_status = $this->get_option('expires_after_status');
        $this->expires_after = $this->get_option('expires_after');

		if (isset($_GET['hitpaynonce']) && !wp_verify_nonce(sanitize_key($_GET['hitpaynonce']), 'hitpay-settings')) {
			exit;
		}
        if (isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) == 'wc-settings' && isset($_GET['section']) && sanitize_text_field(wp_unslash($_GET['section'])) == 'hitpay') { 
            $hitpaytab = 'settings';
            if(isset($_GET['hitpaytab'])) {
                $hitpaytab = sanitize_text_field(wp_unslash($_GET['hitpaytab']));
            }
			
			$hitpaynonce = wp_create_nonce( 'hitpay-settings' );
        ?>
        <nav id="hitpay-tabs" class="nav-tab-wrapper" style="display: none">
            <a id="hitpay-setting-tab" href="?page=wc-settings&section=hitpay&tab=checkout&hitpaytab=settings&hitpaynonce=<?php echo esc_html($hitpaynonce)?>" class="nav-tab <?php echo (($hitpaytab == 'settings') ? 'nav-tab-active':'')?>"><?php esc_html_e('Settings', 'hitpay-payment-gateway')?></a>
            <a id="hitpay-customize-tab" href="?page=wc-settings&section=hitpay&hitpaytab=customize&tab=checkout&hitpaynonce=<?php echo esc_html($hitpaynonce)?>" class="nav-tab <?php echo (($hitpaytab == 'customize') ? 'nav-tab-active':'')?>"><?php esc_html_e('Customization', 'hitpay-payment-gateway')?></a>
            <a id="hitpay-pos-settings-tab" href="?page=wc-settings&section=hitpay&hitpaytab=pos-settings&tab=checkout&hitpaynonce=<?php echo esc_html($hitpaynonce)?>" class="nav-tab <?php echo (($hitpaytab == 'pos-settings') ? 'nav-tab-active':'')?>"><?php esc_html_e('POS Payments', 'hitpay-payment-gateway')?></a>
        </nav>
        <div class="tab-content" id="hitpay-tab-content-customize" style="display: none">
            <table class="form-table">
                <tbody>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="woocommerce_hitpay_customize"><?php esc_html_e('Enable Status Display', 'hitpay-payment-gateway')?> </label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php esc_html_e('Enable Status Display', 'hitpay-payment-gateway')?></span></legend>
                                <label for="woocommerce_hitpay_customize">
                                    <input class="" type="checkbox" name="woocommerce_hitpay_customize" id="woocommerce_hitpay_customize" style="" value="1" 
                                        <?php echo ($this->customize == 1) ? 'checked="checked"':''?> >
                                </label>
                                <br>
                                <span class="woocommerce-help-tip2">
                                    <?php esc_html_e('If enabled, payment status will be retrieved and displayed on the Order Confirmation Page.', 'hitpay-payment-gateway')?>
                                </span>
                            </fieldset>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                           <label for="woocommerce_hitpay_style"><?php esc_html_e('Style', 'hitpay-payment-gateway')?></label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php esc_html_e('Style', 'hitpay-payment-gateway')?></span></legend>
                                <textarea rows="3" cols="20" class="input-text wide-input " type="textarea" name="woocommerce_hitpay_style" id="woocommerce_hitpay_style"><?php echo esc_attr($this->style)?></textarea>
                                <br/>
                                <span class="woocommerce-help-tip2">
                                   <?php esc_html_e('Here you can update CSS styles for HitPay Payment status display container.', 'hitpay-payment-gateway')?>
                               </span>
                            </fieldset>
                        </td>
                    </tr>
					<tr valign="top">
                        <th scope="row" class="titledesc">
                            <label for="woocommerce_hitpay_payment_button"><?php esc_html_e('Enable HitPay Place Order Button', 'hitpay-payment-gateway')?> </label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php esc_html_e('Enable HitPay Place Order Button', 'hitpay-payment-gateway')?></span></legend>
                                <label for="woocommerce_hitpay_payment_button">
                                    <input class="" type="checkbox" name="woocommerce_hitpay_payment_button" id="woocommerce_hitpay_payment_button" style="" value="1" 
                                        <?php echo esc_html(($this->payment_button == 1) ? 'checked="checked"':'')?> >
                                </label>
                                <br>
                                <span class="woocommerce-help-tip2">
                                    <?php esc_html_e('If enabled, HitPay Payment Gateway branding place button will be displayed when selecting this payment option in the checkout.', 'hitpay-payment-gateway')?>
                                </span>
                            </fieldset>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row" class="titledesc">
                           <label for="woocommerce_hitpay_place_order_text"><?php esc_html_e('Place Order Button Text', 'hitpay-payment-gateway')?></label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php esc_html_e('Place Order Button Text', 'hitpay-payment-gateway')?></span></legend>
                                <input type="text" class="input-text regular-input " name="woocommerce_hitpay_place_order_text" id="woocommerce_hitpay_place_order_text" value="<?php echo esc_attr($this->place_order_text)?>" />
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
                            <label for="woocommerce_hitpay_enable_pos"><?php esc_html_e('Enable POS Payments', 'hitpay-payment-gateway')?></label>
                        </th>
                        <td class="forminp">
                            <fieldset>
                                <legend class="screen-reader-text"><span><?php esc_html_e('Enable POS Payments', 'hitpay-payment-gateway')?></span></legend>
                                <label for="woocommerce_hitpay_enable_pos">
                                    <input class="" type="checkbox" name="woocommerce_hitpay_enable_pos" id="woocommerce_hitpay_enable_pos" style="" value="1" 
                                        <?php echo esc_html(($this->enable_pos == 1) ? 'checked="checked"':'')?> >
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr valign="top">
                        <td class="forminp" colspan="2" id="terminal_id_settings">
                            <div><?php esc_html_e('Enter Terminal Reader Information:', 'hitpay-payment-gateway')?></div>
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
                                            <label for="woocommerce_hitpay_enable_pos"><?php esc_html_e('Terminal Reader ID', 'hitpay-payment-gateway')?></label>
                                        </th>
                                        <td class="forminp">
                                            <input type="text" name="woocommerce_hitpay_terminal_ids[]" value="<?php echo esc_attr($val)?>"/>
                                        </td>
                                        <td>
                                            <?php if ($i > 1) {?>
                                            <a href="javascript:void(0);" class="btn button-secondary remove_button" title="Remove field"><?php esc_html_e('Remove', 'hitpay-payment-gateway')?></a>
                                            <?php } else { ?>
                                            &nbsp;
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row" class="titledesc">
                                            <label for="woocommerce_hitpay_enable_pos"><?php esc_html_e('Cashier E-mail (Optional)', 'hitpay-payment-gateway')?></label>
                                        </th>
                                        <td class="forminp">
                                            <input type="email" name="woocommerce_hitpay_cashier_emails[]" value="<?php echo esc_html((isset($this->cashier_emails[$key]) ? esc_attr($this->cashier_emails[$key]) : '')) ?>"/>
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
                                            <label for="woocommerce_hitpay_enable_pos"><?php esc_html_e('Terminal Reader ID', 'hitpay-payment-gateway')?></label>
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
                                            <label for="woocommerce_hitpay_enable_pos"><?php esc_html_e('Cashier E-mail (Optional)', 'hitpay-payment-gateway')?></label>
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
                                <a href="javascript:void(0);" class="btn button-secondary add_button" title="Add field"><?php esc_html_e('Add New', 'hitpay-payment-gateway')?></a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="test-connection-content" style="display: none">
            <button style="margin-left:15px" name="test_connection" id="test_connection" class="woocommerce-save-button components-button is-primary" type="button" value="Test Connection">Test Connection</button>
            <span id="test_connection_success" style="
                font-weight: bold;
                color: green;
                background: white;
                padding: 8px 20px;
                margin-left: 10px;
                border:  1px solid darkgreen;
                border-radius: 3px;
                display: none;
            "></span>
            <span id="test_connection_error" style="
                font-weight: bold;
                color: red;
                background: white;
                padding: 8px 20px;
                margin-left: 10px;
                border:  1px solid darkred;
                border-radius: 3px;
                display: none;
            "></span>
            <button style="margin-left:15px; display: none" name="test_connection_details" id="test_connection_details" class="woocommerce-save-button components-button is-primary" type="button" value="Test Connection">View Details</button>
            <div id="test_connection_details_popup">
                
            </div>                   
        </div>

        <div id="test_connection-dialog" class="hidden" style="max-width:800px; float:left">
            <h3>Request Sent</h3>
            <div id="request_sent"></div>
            <h3>Response From HitPay</h3>
            <div id="response_from_hitpay"></div>
            <h3>My IP Address</h3>
            <div id="my_ip_address"></div>
        </div>

<style>
.test_connection-dialog .ui-widget-header{
    border: none !important;
}
.test_connection-dialog .ui-dialog-titlebar {
    border-bottom: 1px solid #dcdcde !important;
}
.test_connection-dialog .ui-dialog-titlebar-close .ui-icon-closethick {
    display: none  !important;
}
.test_connection-dialog .ui-dialog .ui-dialog-content {
    float: left !important;
}
</style>

        <script type="text/javascript">
            var hitpaytab = '<?php echo esc_attr($hitpaytab)?>';
            var testConnectionAjaxUrl = "?page=wc-settings&section=hitpay&tab=checkout&hitpaytab=testconnection&hitpaytestnonce=<?php echo esc_html($hitpaynonce)?>";
            jQuery(document).ready(function(){

                jQuery('#test_connection-dialog').dialog({
                    title: 'Test Connection Details',
                    dialogClass: 'wp-dialog test_connection-dialog',
                    autoOpen: false,
                    draggable: false,
                    width: 'auto',
                    modal: true,
                    resizable: false,
                    closeOnEscape: true,
                    position: {
                    my: "center",
                    at: "center",
                    of: window
                    },
                    open: function () {
                        jQuery('.ui-widget-overlay').bind('click', function(){
                            jQuery('#test_connection-dialog').dialog('close');
                        })
                    },
                    create: function () {
                        jQuery('.ui-dialog-titlebar-close').addClass('ui-button');
                        jQuery('.ui-dialog-titlebar-close').html('');
                    },
                    close: function () {
                        jQuery('#test_connection_details').removeClass('is-busy');
                    },
                });

                jQuery('#test_connection_details').click(function(e) {
                    e.preventDefault();
                    jQuery('#test_connection-dialog').dialog('open');
                });

                var maxField = 30;
                var addButton = jQuery('.add_button');
                var wrapper = jQuery('.field_wrapper');
                var fieldHTML = '<table class="dynamic-field-table" style="border-bottom: 1px solid #ccc; margin-bottom: 10px"><tr valign="top"><th scope="row" class="titledesc"><label for="woocommerce_hitpay_enable_pos">Terminal Reader ID</label></th><td class="forminp"><input type="text" name="woocommerce_hitpay_terminal_ids[]" value=""/></td><td><a href="javascript:void(0);" class="btn button-secondary remove_button" title="Remove field">Remove</a></td></tr><tr valign="top"><th scope="row" class="titledesc"><label for="woocommerce_hitpay_enable_pos">Cashier E-mail (Optional)</label></th><td class="forminp"><input type="text" name="woocommerce_hitpay_cashier_emails[]" value=""/></td></tr></table>';
                var x = parseInt('<?php echo (count($this->terminal_ids) == 0) ? 1 : count($this->terminal_ids)?>');

                jQuery('.wc-admin-breadcrumb').parent().after(jQuery('#hitpay-tabs'));
                jQuery('#hitpay-tabs').after(jQuery('#hitpay-tab-content-customize'));
                jQuery('#hitpay-tab-content-customize').after(jQuery('#hitpay-tab-content-pos-settings'));
                jQuery('#hitpay-tabs').show();
                jQuery('p.submit button').after(jQuery('#test_connection'));
                jQuery('#test_connection').after(jQuery('#test_connection_success'));
                jQuery('#test_connection').after(jQuery('#test_connection_error'));
                jQuery('#test_connection_success').after(jQuery('#test_connection_details'));

                if (hitpaytab == 'settings') {
                    jQuery('#woocommerce_hitpay_enabled').closest('.form-table').show();
                    jQuery('#hitpay-tab-content-customize').hide();
                    jQuery('#hitpay-tab-content-pos-settings').hide();
                } else if (hitpaytab == 'customize') {
                    jQuery('#woocommerce_hitpay_enabled').closest('.form-table').hide();
                    jQuery('#hitpay-tab-content-customize').show();
                    jQuery('#test_connection').hide();
                } else if (hitpaytab == 'pos-settings') {
                    jQuery('#woocommerce_hitpay_enabled').closest('.form-table').hide();
                    jQuery('#hitpay-tab-content-customize').hide();
                    jQuery('#hitpay-tab-content-pos-settings').show();
                    jQuery('#test_connection').hide();
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

                jQuery('#test_connection').click(function(){
                    jQuery('#test_connection').html('Testing... Hold on, please');
                    jQuery('#test_connection').prop('disabled', true);

                    jQuery('#test_connection_success').hide();
                    jQuery('#test_connection_error').hide();
                    jQuery('#test_connection_details').hide();

                    var mode = 'sandbox';
                    if (jQuery('#woocommerce_hitpay_mode').is(':checked')) {
                        var mode = 'live';
                    }
                    var api_key = jQuery('#woocommerce_hitpay_api_key').val();

                    jQuery.ajax({
                        type: 'POST',
                        url: testConnectionAjaxUrl, 
                        data: {'mode' : mode,'api_key':api_key}, 

                        success: function (response) {
                            var output = JSON.parse(response);
                            if (output.status == 'success') {
                                jQuery('#test_connection_success').show();
                                jQuery('#test_connection_success').html(output.message);
                            } else {
                                jQuery('#test_connection_details').show();
                                jQuery('#test_connection_error').show();
                                jQuery('#test_connection_error').html(output.message);

                                jQuery('#request_sent').html(output.request_sent);
                                jQuery('#response_from_hitpay').html(output.response_from_hitpay);
                                jQuery('#my_ip_address').html(output.my_ip_address);
                            }
                            jQuery('#test_connection').html('Test Connection');
                            jQuery('#test_connection').prop('disabled', false);
                        }
                    });
                });
            });
        </script>  
        <?php
        }
    }
	
	public function option_exists($option_name) 
	{
		$value = get_option($option_name);
		return $value;
	}

    function payment_fields()
    { 
		if (isset($_GET['hitpaynonce']) && !wp_verify_nonce(sanitize_key($_GET['hitpaynonce']), 'hitpay-payment-fields')) {
			exit;
		}
        ?>
		<?php
		   if (isset($_REQUEST['cancelled'] ))  {
		?>
		<script>
			let message = '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"><div class="woocommerce-error"><?php esc_html_e('Payment canceled by customer', 'hitpay-payment-gateway')?></div></div>';
			jQuery(document).ready(function(){
				jQuery('.woocommerce-notices-wrapper:first').html(message);
			});
		</script>
		<?php
		   }
		?>
		
		<?php if (!empty($this->description) || ( ($this->enable_pos == 1) && count($this->terminal_ids) > 0 ) ) {?>
        <div class="form-row form-row-wide payment_method_hitpay_custom_box">
            <?php if (!empty($this->description)) {?>
            <p><?php echo esc_html($this->description); ?></p>
            <?php } ?>
            <?php
            if (($this->enable_pos == 1) && count($this->terminal_ids) > 0) {
                $filter_terminal_ids = $this->filterTerminalIds();
            ?>
            <div class="hitpay-payment-selection">
                <label class="woocommerce-form__label woocommerce-form__label-for-radio radio" for="hitpay_payment_option-0" <?php if (count($filter_terminal_ids) == 1) {?>style="float:left"<?php }?>>
                    <input id="hitpay_payment_option-0"
                           class="woocommerce-form__input woocommerce-form__input-radio input-radio"
                           type="radio"
                           name="hitpay_payment_option" 
                           value="onlinepayment" checked="checked"> 
                    <p style="display: inline"><?php esc_html_e('Online Payments', 'hitpay-payment-gateway')?></p>
                </label>

                <?php if (count($filter_terminal_ids) == 1) {?>
                <label class="woocommerce-form__label woocommerce-form__label-for-radio radio"  for="hitpay_payment_option-1">
                    <input id="hitpay_payment_option-1"
                           class="woocommerce-form__input woocommerce-form__input-radio input-radio"
                           type="radio"
                           name="hitpay_payment_option" 
                           value="<?php echo esc_attr($filter_terminal_ids[0])?>"> 
                    <p style="display: inline"><?php esc_html_e('Card Reader', 'hitpay-payment-gateway')?></p>
                </label>
                <?php 
                    } else {
                        foreach ($filter_terminal_ids as $key => $val) {
                ?>
                        <label class="woocommerce-form__label woocommerce-form__label-for-radio radio"  for="hitpay_payment_option-<?php echo esc_attr ($key+2)?>">
                            <input id="hitpay_payment_option-<?php echo esc_attr ($key+2)?>"
                                   class="woocommerce-form__input woocommerce-form__input-radio input-radio"
                                   type="radio"
                                   name="hitpay_payment_option" 
                                   value="<?php echo esc_attr($val)?>"> 
                            <p style="display: inline"><?php esc_html_e('Card Reader - Terminal ID:', 'hitpay-payment-gateway')?> <?php echo esc_attr($val)?></p>
                        </label>
                <?php
                        }
                    }
                ?>
            </div>
             <?php
            }
            ?>
        </div>
		<?php } ?>
		
		<?php if ($this->drop_in) {?>
		<div id="hitpay_background_layer"></div>
		<?php }?>
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
        if ($this->customize == 1) {
            $order = $this->getOrder($order_id);

            $thankyoupage_ui_enabled = 1;
            $style = $this->style;
			
			if (isset($_GET['hitpaynonce']) && !wp_verify_nonce(sanitize_key($_GET['hitpaynonce']), 'hitpay-thankyou-page')) {
				exit;
			}

            if (isset($_GET['status'])) {
                $status = sanitize_text_field(wp_unslash($_GET['status']));

                if ($status == 'canceled') {
                    $status = $order->get_status();
                    if ($status == 'processing' || $status == 'completed') {
                        $status = 'completed';
                    } else {
                        $reference = 'NONEXIST';
                        if (isset($_GET['reference'])) {
                            $reference = sanitize_text_field(wp_unslash($_GET['reference']));
                        }

                        $status_message = __('Order cancelled by HitPay.', 'hitpay-payment-gateway').($reference ? ' Reference: '.$reference:'');
                        $order->update_status('cancelled', $status_message);

                        $order->add_meta_data('HitPay_reference', $reference);
                        $order->save_meta_data();
                    }
                }

                if ($status == 'completed') {
                    $status = 'wait';
                }
            }

            if ($status !== 'wait') {
                $status = $order->get_status();
            }
			
			$hitpaynonce = wp_create_nonce('hitpay-get-payment-staus');
?>
            <script>
                let is_status_received = false;
                let hitpay_status_ajax_url = '<?php echo esc_url(site_url())."/?wc-api=wc_hitpay&get_order_status=1&hitpaynonce=".esc_attr($hitpaynonce)?>';
                jQuery(document).ready(function(){
                    jQuery('.entry-header .entry-title').html('<?php esc_html_e('Order Status', 'hitpay-payment-gateway')?>');
                    jQuery('.woocommerce-thankyou-order-received').hide();
                    jQuery('.woocommerce-thankyou-order-details').hide();
                    jQuery('.woocommerce-order-details').hide();
                    jQuery('.woocommerce-customer-details').hide();
                    show_hitpay_status();
                });
                function show_hitpay_status(type='') {
                    jQuery('.payment-panel-wait').hide();
                    <?php if ($status == 'completed' || $status == 'pending') {?>
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
            <?php if ($status == 'wait') {?>
            <style>.payment-panel-wait .img-container {text-align: center;}.payment-panel-wait .img-container img{display: inline-block !important;}</style>
            <script>
                jQuery(document).ready(function(){
                    check_hitpay_payment_status();
                    function check_hitpay_payment_status() {
                         function hitpay_status_loop() {
                             if (is_status_received) {
                                 return;
                             }
                             if (typeof(hitpay_status_ajax_url) !== "undefined") {
                                 jQuery.getJSON(hitpay_status_ajax_url, {'order_id' : <?php echo esc_html($order_id)?>}, function (data) {
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
                <h3><?php esc_html_e('We are retrieving your payment status from HitPay, please wait...', 'hitpay-payment-gateway') ?></h3>
                <?php // @codingStandardsIgnoreStart ?>
                <div class="img-container"><img src="<?php echo esc_html( HITPAY_PLUGIN_URL)?>assets/images/loader.gif" /></div>
                <?php // @codingStandardsIgnoreEnd ?>
            </div>
            <?php } ?>

            <div class="payment-panel-pending" style="<?php echo esc_attr( ($status == 'pending' ? 'display: block':'display: none'))?>">
                <div style="<?php echo esc_attr($style)?>">
                <?php esc_html_e('Your payment status is pending, we will update the status as soon as we receive notification from HitPay.', 'hitpay-payment-gateway') ?>
                </div>
            </div>
            <div class="payment-panel-completed" style="<?php echo esc_attr( ($status == 'completed' ? 'display: block':'display: none'))?>">
                <div style="<?php echo esc_attr($style)?>">
                <?php esc_html_e('Your payment is successful with HitPay.', 'hitpay-payment-gateway') ?>
                <?php // @codingStandardsIgnoreStart ?>
                    <img style="width:100px" src="<?php echo esc_html( HITPAY_PLUGIN_URL)?>assets/images/check.png"  />
                    <?php // @codingStandardsIgnoreEnd ?>
                </div>
            </div>
             <div class="payment-panel-failed" style="<?php echo esc_attr( ($status == 'failed' ? 'display: block':'display: none'))?>">
                <div style="<?php echo esc_attr($style)?>">
                <?php esc_html_e('Your payment is failed with HitPay.', 'hitpay-payment-gateway') ?>
                </div>
            </div>
             <div class="payment-panel-cancelled" style="<?php echo esc_attr( ($status == 'cancelled' ? 'display: block':'display: none'))?>">
                <div style="<?php echo esc_attr($style)?>">
                <?php 
                if (isset($status_message) && !empty($status_message)) {
                    echo esc_html($status_message);
                } else {
                    esc_html_e('Your order is cancelled.', 'hitpay-payment-gateway');
                }
                ?>
                </div>
            </div>  
            <div class="payment-panel-error" style="display: none">
                <div class="message-holder">
                    <?php esc_html_e('Something went wrong, please contact the merchant.', 'hitpay-payment-gateway') ?>
                </div>
            </div>
        <?php
        }
    }

    public function get_payment_staus() {
        $status = 'wait';
        $message = '';

        try {
			
			if (isset($_GET['hitpaynonce']) && !wp_verify_nonce(sanitize_key($_GET['hitpaynonce']), 'hitpay-get-payment-staus')) {
				throw new \Exception( esc_html_e('Nonce verification failed.', 'hitpay-payment-gateway'));
			}

            $order_id = 0;
            if (isset($_GET['order_id'])) {
                $order_id = (int)sanitize_text_field(wp_unslash($_GET['order_id']));
            }
            if ($order_id == 0) {
                throw new \Exception( esc_html_e('Order not found.', 'hitpay-payment-gateway'));
            }
			
			$order = $this->getOrder($order_id);

            $payment_status = $this->getOrderMetaData($order, $order_id, 'HitPay_WHS', true );
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
		
		if (isset($_GET['hitpaynonce']) && !wp_verify_nonce(sanitize_key($_GET['hitpaynonce']), 'return-from-hitpay')) {
			exit;
		}
		
        if (!isset($_GET['hitpay_order_id'])) {
            $this->log('return_from_hitpay order_id check failed');
            exit;
        }

        $order_id = (int)sanitize_text_field(wp_unslash($_GET['hitpay_order_id']));
        $order = $this->getOrder($order_id);

        if (isset($_GET['status'])) {
            $status = sanitize_text_field(wp_unslash($_GET['status']));

            $reference = 'NONEXIST';
            if (isset($_GET['reference'])) {
                $reference = sanitize_text_field(wp_unslash($_GET['reference']));
            }

            if ($status == 'canceled') {
                $status = $order->get_status();
                if ($status == 'processing' || $status == 'completed') {
                    $status = 'completed';
                } else {
                    $status_message = __('Order cancelled by HitPay.', 'hitpay-payment-gateway').($reference ? ' Reference: '.$reference:'');
                    $order->update_status('cancelled', $status_message);

                    $order->add_meta_data('HitPay_reference', $reference);
                    $order->save_meta_data();
					
					$hitpaynonce = wp_create_nonce( 'hitpay-payment-fields' );

                    wp_redirect( 
						add_query_arg(
							array(
								'cancelled'=>'true',
								'hitpaynonce'=>$hitpaynonce,
							),
							wc_get_checkout_url()
						)
					);
					exit;
                }
            }

            if ($status == 'completed') {
				$hitpaynonce = wp_create_nonce('hitpay-thankyou-page');
                wp_redirect(
					add_query_arg(
						array(
							'status' => $status,
							'hitpaynonce' => $hitpaynonce
						),
						$this->get_return_url( $order )
					)
				);
				exit;
            }
        }
    }

    public function web_hook_handler() {
        global $woocommerce;
        $this->log('Webhook Triggered');
		
		if (isset($_GET['hitpaynonce']) && !wp_verify_nonce(sanitize_key($_GET['hitpaynonce']), 'hitpay-web_hook_handler')) {
			exit;
		}

        if (!isset($_GET['hitpay_order_id']) || !isset($_POST['hmac'])) {
            $this->log('order_id + hmac check failed');
            exit;
        }

        $post_payment_id = '';
        if (isset($_POST['payment_id'])) {
            $post_payment_id = sanitize_text_field(wp_unslash($_POST['payment_id']));
        }
        $post_status = '';
        if (isset($_POST['status'])) {
            $post_status = sanitize_text_field(wp_unslash($_POST['status']));
        }
        $post_reference_number = '';
        if (isset($_POST['reference_number'])) {
            $post_reference_number = sanitize_text_field(wp_unslash($_POST['reference_number']));
        }
		
		$this->log('Payment_id: '.$post_payment_id);
        $this->log('Payment_status: '.$post_status);
		$this->log('Payment_reference_number: '.$post_reference_number);

        $order_id = (int)sanitize_text_field(wp_unslash($_GET['hitpay_order_id']));
		
		$order = $this->getOrder($order_id);

        if ($order_id > 0) {
            $HitPay_webhook_triggered = (int)$this->getOrderMetaData($order, $order_id, 'HitPay_webhook_triggered', true);
            if ($HitPay_webhook_triggered == 1) {
                exit;
            }
        }
        
        $order_data = $order->get_data();

        $order->add_meta_data('HitPay_webhook_triggered', 1);
        $order->save_meta_data();

        try {
            $data = $_POST;
            unset($data['hmac']);

            $salt = $this->salt;

            $post_hmac = '';
            if (isset($_POST['hmac'])) {
                $post_hmac = sanitize_text_field(wp_unslash($_POST['hmac']));
            }

            $post_amount = '';
            if (isset($_POST['amount'])) {
                $post_amount = sanitize_text_field(wp_unslash($_POST['amount']));
            }

            $post_currency = '';
            if (isset($_POST['currency'])) {
                $post_currency = sanitize_text_field(wp_unslash($_POST['currency']));
            }

            $post_payment_request_id = '';
            if (isset($_POST['payment_request_id'])) {
                $post_payment_request_id = sanitize_text_field(wp_unslash($_POST['payment_request_id']));
            }

            if (Client::generateSignatureArray($salt, $data) == $post_hmac) {
                $this->log('hmac check passed');

                $HitPay_payment_id = $this->getOrderMetaData($order, $order_id, 'HitPay_payment_id', true );

                if (!$HitPay_payment_id || empty($HitPay_payment_id)) {
                    $this->log('saved payment not valid');
                }

                $HitPay_is_paid = $this->getOrderMetaData($order, $order_id, 'HitPay_is_paid', true );

                if (!$HitPay_is_paid) {
                    $status = sanitize_text_field(wp_unslash($_POST['status']));

                    if ($status == 'completed'
                        && $order->get_total() == $post_amount
                        && $order_data['currency'] == $post_currency
                    ) {
                        $payment_id = sanitize_text_field(wp_unslash($_POST['payment_id']));
                        $payment_request_id = sanitize_text_field(wp_unslash($_POST['payment_request_id']));
                        $hitpay_currency = sanitize_text_field(wp_unslash($_POST['currency']));
                        $hitpay_amount = sanitize_text_field(wp_unslash($_POST['amount']));

                        if (empty($this->order_status)) {
							$status_message = __('Payment successful. Transaction Id: ', 'hitpay-payment-gateway').$payment_id;
                            $order->update_status('processing', $status_message);
                        } elseif ($this->order_status == 'wc-pending') {
							$status_message = __('Payment successful. Transaction Id: ', 'hitpay-payment-gateway').$payment_id;
                            $order->update_status('pending', $status_message);
                        } elseif ($this->order_status == 'wc-processing') {
							$status_message = __('Payment successful. Transaction Id: ', 'hitpay-payment-gateway').$payment_id;
                            $order->update_status('processing', $status_message);
                        } elseif ($this->order_status == 'wc-completed') {
							$status_message = __('Payment successful. Transaction Id: ', 'hitpay-payment-gateway').$payment_id;
                            $order->update_status('completed', $status_message);
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
                        $payment_id = sanitize_text_field(wp_unslash($_POST['payment_id']));
                        $hitpay_currency = sanitize_text_field(wp_unslash($_POST['currency']));
                        $hitpay_amount = sanitize_text_field(wp_unslash($_POST['amount']));

						$status_message = __('Payment Failed. Transaction Id: ', 'hitpay-payment-gateway').$payment_id;
                        $order->update_status('failed', $status_message);

                        $order->add_meta_data('HitPay_transaction_id', $payment_id);
                        $order->add_meta_data('HitPay_is_paid', 0);
                        $order->add_meta_data('HitPay_currency', $hitpay_currency);
                        $order->add_meta_data('HitPay_amount', $hitpay_amount);
                        $order->add_meta_data('HitPay_WHS', $status);
                        $order->save_meta_data();

                        $woocommerce->cart->empty_cart();
                    } elseif ($status == 'pending') {
                        $payment_id = sanitize_text_field(wp_unslash($_POST['payment_id']));
                        $hitpay_currency = sanitize_text_field(wp_unslash($_POST['currency']));
                        $hitpay_amount = sanitize_text_field(wp_unslash($_POST['amount']));
						
						$status_message = __('Payment is pending. Transaction Id: ', 'hitpay-payment-gateway').$payment_id;
                        $order->update_status('failed', $status_message);

                        $order->add_meta_data('HitPay_transaction_id', $payment_id);
                        $order->add_meta_data('HitPay_is_paid', 0);
                        $order->add_meta_data('HitPay_currency', $hitpay_currency);
                        $order->add_meta_data('HitPay_amount', $hitpay_amount);
                        $order->add_meta_data('HitPay_WHS', $status);
                        $order->save_meta_data();

                        $woocommerce->cart->empty_cart();
                    } else {
                        $payment_id = sanitize_text_field(wp_unslash($_POST['payment_id']));
                        $hitpay_currency = sanitize_text_field(wp_unslash($_POST['currency']));
                        $hitpay_amount = sanitize_text_field(wp_unslash($_POST['amount']));
						
						$status_message = __('Payment returned unknown status. Transaction Id: ', 'hitpay-payment-gateway').$payment_id;
                        $order->update_status('failed', $status_message);

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
        $order = $this->getOrder($orderId);
        $amount = (float)wp_strip_all_tags(trim($amount));
        $amountValue = number_format($amount, 2, '.', '');

        try {
            $HitPay_transaction_id = $this->getOrderMetaData($order, $orderId, 'HitPay_transaction_id', true );
            $HitPay_is_refunded = $this->getOrderMetaData($order, $orderId, 'HitPay_is_refunded', true );
            if ($HitPay_is_refunded == 1) {
                throw new Exception(__('Only one refund allowed per transaction by HitPay Gateway.',  'hitpay-payment-gateway'));
            }

            $order_total_paid = $order->get_total();

            if ($amountValue <=0 ) {
                throw new Exception(__('Refund amount shoule be greater than 0.',  'hitpay-payment-gateway'));
            }

            if ($amountValue > $order_total_paid) {
                throw new Exception(__('Refund amount shoule be less than or equal to order paid total.',  'hitpay-payment-gateway'));
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

            $message = __('Refund successful. Refund Reference Id: ', 'hitpay-payment-gateway');
			$message .= $result->getId().', ';
			$message .= __('Payment Id: ', 'hitpay-payment-gateway');
			$message .= $HitPay_transaction_id.', ';
			$message .= __('Amount Refunded: ', 'hitpay-payment-gateway');
			$message .= $result->getAmountRefunded().', ';
            $message .= __('Payment Method: ', 'hitpay-payment-gateway');
			$message .= $result->getPaymentMethod().', ';
			$message .= __('Created At: ', 'hitpay-payment-gateway');
			$message .= $result->getCreatedAt();

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
		
		if (isset($_GET['hitpaynonce']) && !wp_verify_nonce(sanitize_key($_GET['hitpaynonce']), 'hitpay-get-payment-staus')) {
			exit;
		}
		
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

    public function getTestConnectionMode($modeValue)
    {
        $mode = true;
        if ($modeValue != 'live') {
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

        $order = $this->getOrder($order_id);

        $order_data = $order->get_data();
        $order_total = $order->get_total();

        try {
            $hitpay_client = new Client(
                $this->api_key,
                $this->getMode()
            );

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

            if (($this->enable_pos == 1) && count($this->terminal_ids) > 0) {
				
				if (isset( $_POST['woocommerce-process-checkout-nonce'] ) 
					&& !wp_verify_nonce(sanitize_key($_POST['woocommerce-process-checkout-nonce']), 'woocommerce-process_checkout' ) 
				) {
				   print 'Sorry, your nonce did not verify.';
				   exit;
				}

                $post_hitpay_payment_option = '';
                if (isset($_POST['hitpay_payment_option'])) {
                    $post_hitpay_payment_option = sanitize_text_field(wp_unslash($_POST['hitpay_payment_option']));
                }
				
                if (isset($_POST['hitpay_payment_option']) && ($post_hitpay_payment_option !== 'onlinepayment')) {
                    $terminal_id = sanitize_text_field(wp_unslash($_POST['hitpay_payment_option']));
                    $create_payment_request->setPaymentMethod('wifi_card_reader');
                    $create_payment_request->setWifiTerminalId($terminal_id);
                }
            }

            $this->log('Create Payment Request:');
			$this->log('Payment_request_amount: '.$create_payment_request->getAmount());
			$this->log('Payment_request_currency: '.$create_payment_request->getCurrency());
			$this->log('Payment_request reference_number: '.$create_payment_request->getReferenceNumber());

            $result = $hitpay_client->createPayment($create_payment_request);

            $this->log('Create Payment Response:');
			$this->log('Create Payment_Request_id: '.$result->getId());
			$this->log('Create Payment_status: '.$result->getStatus());

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
                throw new Exception(sprintf('HitPay: sent status is %s', $result->getStatus()));
             }
        } catch (\Exception $e) {
			$this->log('Create Payment Failed:');
            $log_message = $e->getMessage();
            $this->log($log_message);

            $status_message = __('HitPay: Something went wrong, please contact the merchant', 'hitpay-payment-gateway');
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
            'paynow' => __('PayNow QR', 'hitpay-payment-gateway'),
            'visa' => __('Visa', 'hitpay-payment-gateway'),
            'master' => __('Mastercard', 'hitpay-payment-gateway'),
            'american_express' => __('American Express', 'hitpay-payment-gateway'),
            'apple_pay' => __('Apple Pay', 'hitpay-payment-gateway'),
            'google_pay' => __('Google Pay', 'hitpay-payment-gateway'),
            'grabpay' => __('GrabPay', 'hitpay-payment-gateway'),
            'wechatpay' => __('WeChatPay', 'hitpay-payment-gateway'),
            'alipay' => __('AliPay', 'hitpay-payment-gateway'),
            'shopeepay'        => __( 'ShopeePay', 'hitpay-payment-gateway' ),
            'fpx'        => __( 'FPX', 'hitpay-payment-gateway' ),
            'zip'        => __( 'Zip', 'hitpay-payment-gateway' ),
            'atomeplus' => __('ATome+', 'hitpay-payment-gateway' ),
            'unionbank' => __('Unionbank Online', 'hitpay-payment-gateway' ),
            'qrph' => __('Instapay QR PH', 'hitpay-payment-gateway' ),
            'pesonet' => __('PESONet', 'hitpay-payment-gateway' ),
            'billease' => __('Billease BNPL', 'hitpay-payment-gateway' ),
            'gcash' => __('GCash', 'hitpay-payment-gateway' ),
            'eftpos' => __('eftpos', 'hitpay-payment-gateway' ),
            'maestro' => __('maestro', 'hitpay-payment-gateway' ),
            'alfamart' => __('Alfamart', 'hitpay-payment-gateway' ),
            'indomaret' => __('Indomaret', 'hitpay-payment-gateway' ),
            'dana' => __('DANA', 'hitpay-payment-gateway' ),
            'gopay' => __('gopay', 'hitpay-payment-gateway' ),
            'linkaja' => __('Link Aja!', 'hitpay-payment-gateway' ),
            'ovo' => __('OVO', 'hitpay-payment-gateway' ),
            'qris' => __('QRIS', 'hitpay-payment-gateway' ),
            'danamononline' => __('Bank Danamon', 'hitpay-payment-gateway' ),
            'permata' => __('PermataBank', 'hitpay-payment-gateway' ),
            'bsi' => __('Bank Syariah Indonesia', 'hitpay-payment-gateway' ),
            'bca' => __('BCA', 'hitpay-payment-gateway' ),
            'bni' => __('BNI', 'hitpay-payment-gateway' ),
            'bri' => __('BRI', 'hitpay-payment-gateway' ),
            'cimb' => __('CIMB Niaga', 'hitpay-payment-gateway' ),
            'doku' => __('DOKU', 'hitpay-payment-gateway' ),
            'mandiri' => __('Mandiri', 'hitpay-payment-gateway' ),
            'akulaku' => __('AkuLaku BNPL', 'hitpay-payment-gateway' ),
            'kredivo' => __('Kredivo BNPL', 'hitpay-payment-gateway' ),
            'philtrustbank' => __('PHILTRUST BANK', 'hitpay-payment-gateway' ),
            'allbank' => __('AllBank', 'hitpay-payment-gateway' ),
            'aub' => __('ASIA UNITED BANK', 'hitpay-payment-gateway' ),
            'chinabank' => __('CHINABANK', 'hitpay-payment-gateway' ),
            'instapay' => __('instaPay', 'hitpay-payment-gateway' ),
            'landbank' => __('LANDBANK', 'hitpay-payment-gateway' ),
            'metrobank' => __('Metrobank', 'hitpay-payment-gateway' ),
            'pnb' => __('PNB', 'hitpay-payment-gateway' ),
            'queenbank' => __('QUEENBANK', 'hitpay-payment-gateway' ),
            'rcbc' => __('RCBC', 'hitpay-payment-gateway' ),
            'tayocash' => __('TayoCash', 'hitpay-payment-gateway' ),
            'ussc' => __('USSC', 'hitpay-payment-gateway' ),
            'bayad' => __('bayad', 'hitpay-payment-gateway' ),
            'cebuanalhuillier' => __('CEBUANA LHUILLIER', 'hitpay-payment-gateway' ),
            'ecpay' => __('ecPay', 'hitpay-payment-gateway' ),
            'palawan' => __('PALAWAN PAWNSHOP', 'hitpay-payment-gateway' ),
            'bpi' => __('BPI', 'hitpay-payment-gateway' ),
            'psbank' => __('PSBank', 'hitpay-payment-gateway' ),
            'robinsonsbank' => __('Robinsons Bank', 'hitpay-payment-gateway' ),
            'diners_club' => __('Diners Club', 'hitpay-payment-gateway' ),
            'discover' => __('Discover', 'hitpay-payment-gateway' ),
            'doku_wallet' => __('DOKU Wallet', 'hitpay-payment-gateway' ),
            'grab_paylater' => __('PayLater by Grab', 'hitpay-payment-gateway' ),
            'favepay' => __('FavePay', 'hitpay-payment-gateway' ),
            'shopback_paylater' => __('ShopBack PayLater', 'hitpay-payment-gateway' ),
            'duitnow' => __('DuitNow', 'hitpay-payment-gateway' ),
            'touchngo' => __('Touch \'n Go', 'hitpay-payment-gateway' ),
            'boost' => __('Boost', 'hitpay-payment-gateway' ),
        ];

        return $methods;
    }

    public function log($content)
    {
        $debug = $this->debug;
        if ($debug == 'yes') {
			if (!$this->option_exists("woocommerce_hitpay_logfile_prefix")) {
				$logfile_prefix = md5(uniqid(wp_rand(), true));
				update_option('woocommerce_hitpay_logfile_prefix', $logfile_prefix);
			} else {
				$logfile_prefix = get_option('woocommerce_hitpay_logfile_prefix');
				if (empty($logfile_prefix)) {
					$logfile_prefix = md5(uniqid(wp_rand(), true));
					update_option('woocommerce_hitpay_logfile_prefix', $logfile_prefix);
				}
			}
			
			$filename = $logfile_prefix.'_hitpay_debug.log';

            $file = ABSPATH .'wp-content/uploads/wc-logs/'.$filename;
			
            try {
				/*
				if (!defined( 'FS_CHMOD_FILE' ) ) {
                    define('FS_CHMOD_FILE', 0644);
                }				
				
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
				require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
				
				$filesystem = new WP_Filesystem_Direct( false );
				$filesystem->put_contents("\n".gmdate("Y-m-d H:i:s").": ".print_r($content, true));
				*/
				
				// @codingStandardsIgnoreStart
				/*
				We tried to use WP_Filesystem methods, look at the above commented out code block.
				But this put_contents method just writing the code not appending to the file.
				So we have only the last written content in the file.
				Because in the below method fopen initiated with 'wb' mode instead of 'a' or 'a+', otherwise this core method must be modified to able to pass the file open mode from the caller.
				public function put_contents( $file, $contents, $mode = false ) {
				$fp = @fopen( $file, 'wb' );
				*/
				$fp = fopen($file, 'a+');
                if ($fp) {
                    fwrite($fp, "\n".gmdate("Y-m-d H:i:s").": ".print_r($content, true));
                    fclose($fp);
                }
				// @codingStandardsIgnoreEnd
				
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