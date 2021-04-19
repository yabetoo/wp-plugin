<?php

/**
 * Yabetoo Mobile Payments Gateway.
 *
 * Provides a Yabetoo Mobile Payments Payment Gateway.
 *
 * @class       WC_Gateway_Yabetoo
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce/Classes/Payment
 */


class WC_Gateway_Yabetoo extends WC_Payment_Gateway
{

    // Logging
    public static $log_enabled = false;
    public static $log = false;

    protected $msg = array();
    private $auth_transaction;
    private $oauth_server_sandbox;
    private $payment_server_sandbox;
    private $product_server_sandbox;
    /**
     * @var string
     */
    private $checkout_url_sandbox;

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {

        // Setup general properties.
        $this->setup_properties();

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Get settings.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $this->public_key = trim($this->get_option('public_key'));
        $this->secret_key = trim($this->get_option('secret_key'));

        $this->pay_method = $this->get_option('pay_method');
        $this->xchange_rate = $this->get_option('xchange_rate');
        $this->demo = $this->get_option('sandbox');


        $this->debug = $this->get_option('debug');
        $this->enable_for_methods = $this->get_option('enable_for_methods', array());
        $this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes';

        self::$log_enabled = $this->debug;
        $this->log = $this->settings['log'];

        $this->msg['message'] = "";
        $this->msg['class'] = "";


        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_filter('woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3);

        // Customer Emails.
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);


        // Payment listener/API hook
        add_action('woocommerce_api_wc_gateway_nm_yabetoopay', array($this, 'twocheckout_response'));
    }

    /**
     * Logging method
     * @param string $message
     */
    public static function log($message)
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = new WC_Logger();
            }

            $message = is_array($message) ? json_encode($message) : $message;
            self::$log->add('yabetoopay', $message);
        }
    }

    /**
     * @param $message
     */
    public function addLog($message)
    {

        //You can find this log on the path (wp-content\uploads\wc-logs)

        if ($this->log == "yes") {
            $log = new WC_Logger();
            $log->add('paykun-payment', $message);
        }
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties()
    {
        $this->id = 'yabetoopay';
        $this->icon = apply_filters('woocommerce_yabetoo_icon', plugins_url('../assets/icon.png', __FILE__));
        $this->method_title = __('YabetooPay Mobile Payments', 'yabetoo-payments-woo');
        $this->public_key = __('Add public key', 'yabetoo-payments-woo');
        $this->secret_key = __('Add secret key', 'yabetoo-payments-woo');
        $this->method_description = __('Have your customers pay with YabetooPay Mobile Payments.', 'yabetoo-payments-woo');
        $this->has_fields = false;

        //sandbox

        $this->checkout_url_sandbox = 'https://checkout.sandbox-yabetoopay.com';
        $this->oauth_server_sandbox = 'https://auth.sandbox-yabetoopay.com/api';
        $this->payment_server_sandbox = 'https://payment.sandbox-yabetoopay.com/api';
        $this->product_server_sandbox = 'https://product.sandbox-yabetoopay.com';
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'yabetoo-payments-woo'),
                'label' => __('Enable YabetooPay Mobile Payments', 'yabetoo-payments-woo'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'yabetoo-payments-woo'),
                'type' => 'text',
                'description' => __('YabetooPay Mobile Payment method description that the customer will see on your checkout.', 'yabetoo-payments-woo'),
                'default' => __('YabetooPay', 'yabetoo-payments-woo'),
                'desc_tip' => true,
            ),
            /*  'xchange_rate' => array(
                  'title' => __('Currency Converter', 'woocommerce'),
                  'type' => 'checkbox',
                  'label' => __('Yes - It is PRO Feature get <a href="#" target="_blank">Pro Version</a>', 'woocommerce'),
                  'default' => 'no',
                  'description' => __('If your currency is not supported by 2CO, then use this option. It will automatically convert your currency to USD using Yahoo Finance API', 'woocommerce'),
                  'desc_tip' => false,
              ),*/
            'sandbox' => array(
                'title' => __('Enable sandbox', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Yes', 'woocommerce'),
                'default' => 'no'
            ),
            'debug' => array(
                'title' => __('Debug Log', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable logging', 'woocommerce'),
                'default' => 'no',
                'description' => sprintf(__('Debug Information <em>%s</em>', 'woocommerce'), wc_get_log_file_path('yabetoopay'))
            ),
            'api_details' => array(
                'title' => __('API credentials', 'woocommerce'),
                'type' => 'title',
                /* translators: %s: URL */
                'description' => sprintf(__('Enter your YabetooPay API credentials to process payment via YabetooPay. Learn how to access your <a href="%s">YabetooPay API Credentials</a>.', 'woocommerce'), 'http://api.yabetoopay.com'),
            ),
            'details' => array(
                'title' => __('', 'woocommerce'),
                'type' => 'title',
                /* translators: %s: URL */
                'description' => sprintf(__('If sandbox is enable you should put your <strong>Sandbox credentials</strong> otherwise your <strong> Live credentials</strong>.', 'woocommerce')),
            ),
            'public_key' => array(
                'title' => __('Public key', 'yabetoo-payments-woo'),
                'type' => 'text',
                'description' => __('Add your Yabetoo public key.', 'yabetoo-payments-woo'),
                'desc_tip' => true,
            ),
            'secret_key' => array(
                'title' => __('Secret key', 'yabetoo-payments-woo'),
                'type' => 'text',
                'description' => __('Add your Yabetoo secret key .', 'yabetoo-payments-woo'),
                'desc_tip' => true,
            ),

            /*  'description' => array(
                  'title' => __('Description', 'yabetoo-payments-woo'),
                  'type' => 'textarea',
                  'description' => __('YabetooPay Mobile Payment method description that the customer will see on your website.', 'yabetoo-payments-woo'),
                  'default' => __('YabetooPay Mobile Payments before delivery.', 'yabetoo-payments-woo'),
                  'desc_tip' => true,
              ),
              'instructions' => array(
                  'title' => __('Instructions', 'yabetoo-payments-woo'),
                  'type' => 'textarea',
                  'description' => __('Instructions that will be added to the thank you page.', 'yabetoo-payments-woo'),
                  'default' => __('YabetooPay Mobile Payments before delivery.', 'yabetoo-payments-woo'),
                  'desc_tip' => true,
              ),*/
            /*   'enable_for_methods' => array(
                   'title' => __('Enable for shipping methods', 'yabetoo-payments-woo'),
                   'type' => 'multiselect',
                   'class' => 'wc-enhanced-select',
                   'css' => 'width: 400px;',
                   'default' => '',
                   'description' => __('If YabetooPay is only available for certain methods, set it up here. Leave blank to enable for all methods.', 'yabetoo-payments-woo'),
                   'options' => $this->load_shipping_method_options(),
                   'desc_tip' => true,
                   'custom_attributes' => array(
                       'data-placeholder' => __('Select shipping methods', 'yabetoo-payments-woo'),
                   ),
               ),
               'enable_for_virtual' => array(
                   'title' => __('Accept for virtual orders', 'yabetoo-payments-woo'),
                   'label' => __('Accept YabetooPay if the order is virtual', 'yabetoo-payments-woo'),
                   'type' => 'checkbox',
                   'default' => 'yes',
               ),*/
        );
    }

    /**
     * Check If The Gateway Is Available For Use.
     *
     * @return bool
     */
    public function is_available()
    {
        $order = null;
        $needs_shipping = false;

        // Test if shipping is needed first.
        if (WC()->cart && WC()->cart->needs_shipping()) {
            $needs_shipping = true;
        } elseif (is_page(wc_get_page_id('checkout')) && 0 < get_query_var('order-pay')) {
            $order_id = absint(get_query_var('order-pay'));
            $order = wc_get_order($order_id);

            // Test if order needs shipping.
            if (0 < count($order->get_items())) {
                foreach ($order->get_items() as $item) {
                    $_product = $item->get_product();
                    if ($_product && $_product->needs_shipping()) {
                        $needs_shipping = true;
                        break;
                    }
                }
            }
        }

        $needs_shipping = apply_filters('woocommerce_cart_needs_shipping', $needs_shipping);

        // Virtual order, with virtual disabled.
        if (!$this->enable_for_virtual && !$needs_shipping) {
            return false;
        }

        // Only apply if all packages are being shipped via chosen method, or order is virtual.
        if (!empty($this->enable_for_methods) && $needs_shipping) {
            $order_shipping_items = is_object($order) ? $order->get_shipping_methods() : false;
            $chosen_shipping_methods_session = WC()->session->get('chosen_shipping_methods');

            if ($order_shipping_items) {
                $canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids($order_shipping_items);
            } else {
                $canonical_rate_ids = $this->get_canonical_package_rate_ids($chosen_shipping_methods_session);
            }

            if (!count($this->get_matching_rates($canonical_rate_ids))) {
                return false;
            }
        }

        return parent::is_available();
    }

    /**
     * Checks to see whether or not the admin settings are being accessed by the current request.
     *
     * @return bool
     */
    private function is_accessing_settings()
    {
        if (is_admin()) {
            // phpcs:disable WordPress.Security.NonceVerification
            if (!isset($_REQUEST['page']) || 'wc-settings' !== $_REQUEST['page']) {
                return false;
            }
            if (!isset($_REQUEST['tab']) || 'checkout' !== $_REQUEST['tab']) {
                return false;
            }
            if (!isset($_REQUEST['section']) || 'yabetoopay' !== $_REQUEST['section']) {
                return false;
            }
            // phpcs:enable WordPress.Security.NonceVerification

            return true;
        }

        return false;
    }

    /**
     * Loads all of the shipping method options for the enable_for_methods field.
     *
     * @return array
     */
    private function load_shipping_method_options()
    {
        // Since this is expensive, we only want to do it if we're actually on the settings page.
        if (!$this->is_accessing_settings()) {
            return array();
        }

        $data_store = WC_Data_Store::load('shipping-zone');
        $raw_zones = $data_store->get_zones();

        foreach ($raw_zones as $raw_zone) {
            $zones[] = new WC_Shipping_Zone($raw_zone);
        }

        $zones[] = new WC_Shipping_Zone(0);

        $options = array();
        foreach (WC()->shipping()->load_shipping_methods() as $method) {

            $options[$method->get_method_title()] = array();

            // Translators: %1$s shipping method name.
            $options[$method->get_method_title()][$method->id] = sprintf(__('Any &quot;%1$s&quot; method', 'yabetoo-payments-woo'), $method->get_method_title());

            foreach ($zones as $zone) {

                $shipping_method_instances = $zone->get_shipping_methods();

                foreach ($shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance) {

                    if ($shipping_method_instance->id !== $method->id) {
                        continue;
                    }

                    $option_id = $shipping_method_instance->get_rate_id();

                    // Translators: %1$s shipping method title, %2$s shipping method id.
                    $option_instance_title = sprintf(__('%1$s (#%2$s)', 'yabetoo-payments-woo'), $shipping_method_instance->get_title(), $shipping_method_instance_id);

                    // Translators: %1$s zone name, %2$s shipping method instance name.
                    $option_title = sprintf(__('%1$s &ndash; %2$s', 'yabetoo-payments-woo'), $zone->get_id() ? $zone->get_zone_name() : __('Other locations', 'yabetoo-payments-woo'), $option_instance_title);

                    $options[$method->get_method_title()][$option_id] = $option_title;
                }
            }
        }

        return $options;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @param array $order_shipping_items Array of WC_Order_Item_Shipping objects.
     * @return array $canonical_rate_ids    Rate IDs in a canonical format.
     * @since  3.4.0
     *
     */
    private function get_canonical_order_shipping_item_rate_ids($order_shipping_items)
    {

        $canonical_rate_ids = array();

        foreach ($order_shipping_items as $order_shipping_item) {
            $canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
        }

        return $canonical_rate_ids;
    }

    /**
     * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
     *
     * @param array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
     * @return array $canonical_rate_ids  Rate IDs in a canonical format.
     * @since  3.4.0
     *
     */
    private function get_canonical_package_rate_ids($chosen_package_rate_ids)
    {

        $shipping_packages = WC()->shipping()->get_packages();
        $canonical_rate_ids = array();

        if (!empty($chosen_package_rate_ids) && is_array($chosen_package_rate_ids)) {
            foreach ($chosen_package_rate_ids as $package_key => $chosen_package_rate_id) {
                if (!empty($shipping_packages[$package_key]['rates'][$chosen_package_rate_id])) {
                    $chosen_rate = $shipping_packages[$package_key]['rates'][$chosen_package_rate_id];
                    $canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
                }
            }
        }

        return $canonical_rate_ids;
    }

    /**
     * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
     *
     * @param array $rate_ids Rate ids to check.
     * @return boolean
     * @since  3.4.0
     *
     */
    private function get_matching_rates($rate_ids)
    {
        // First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
        return array_unique(array_merge(array_intersect($this->enable_for_methods, $rate_ids), array_intersect($this->enable_for_methods, array_unique(array_map('wc_get_string_before_colon', $rate_ids)))));
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        $twoco_args = $this->get_twoco_args($order);


        $twoco_args = http_build_query($twoco_args, '', '&');
        $this->log("========== Payment Procesing Started: args =========");
        $this->log($twoco_args);


        //if demo is enabled
        $checkout_url = '';

        if ($this->demo == 'yes') {
            $checkout_url = $this->checkout_url_sandbox;
        } else {
            $checkout_url = 'https://checkout.yabetoopay.com';
        }

        var_dump($checkout_url . '?' . $twoco_args);
        //var_dump($this->auth_transaction);

        return array(
            'result' => 'success',
            'redirect' => $checkout_url . '?' . $twoco_args
        );
    }

    /**
     * Check for 2Checkout IPN Response
     *
     * @access public
     * @return void
     */
    function twocheckout_response()
    {


        global $woocommerce;

        // twoco_log($_REQUEST);

        $mode = $this->demo ? 'Demo' : 'Live';
        $this->log(__("== INS Response Received - Standard Checkout Method ({$mode}) == ", "2checkout"));
        $this->log($_REQUEST);

        global $woocommerce;
        $orderDetail = null;
        $paymentId = sanitize_text_field($_REQUEST['paymentId']);

        //print_r($paymentId);

        if (trim($paymentId) && strlen(trim($paymentId)) > 0) {

            $response = $this->getTransactionInfo($paymentId);

            if (isset($response['status'])) {
                $payment_status = $response['status'];

                $order_id = $response['order_id'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    $order = new WC_Order($order_id);
                } else {
                    $order = new woocommerce_order($order_id);
                }

                $orderDetail = $order;
                //$this->addLog("Response Code = " . $payment_status);

                if ($payment_status === "SUCCESSFUL") { //Transaction is success
                    //if(1) { //Transaction is success

                    $resAmout = $response['amount'];

                    //$this->addLog("amount matching responseAmount=".$resAmout." And  Order Amount = ".$order->order_total);
                    //if((intval($order->order_total)	== intval($resAmout)))

                    {

                        //$this->addLog("amount matched");

                        if ($order->status !== 'completed') {
                            $this->addLog("SUCCESS. Order Id => $order_id, Payment Id => $paymentId");

                            $this->msg['message'] = "Thank you for your order . 
                                Your transaction has been successful.  
                                Your  Order Id is => " . $order_id . " And Yabetoopay Transaction Id => " . $response['transaction_id'];
                            $this->msg['class'] = 'success';

                            $order->add_order_note($this->msg['message']);

                            $order->update_status('processing');

                            $this->addLog("Paid successfully with the order status 'processing' for order id $order_id");

                            $woocommerce->cart->empty_cart();
                            $url = $this->get_return_url($order);
                            wp_redirect($url);
                            /*if($order ->status == 'processing'){
                                //Process code for 'processing status'
                            }*/
                        }
                    }
                } else { //Transaction failed
                    $resAmout = $response['data']['transaction']['order']['gross_amount'];
                    $this->addLog("Transaction Failed:=> amount matching responseAmount=" . $resAmout . " And  Order Amount = " . $order->order_total);
                    $this->addLog($this->msg['message']);
                    $order->update_status('failed');
                    $order->add_order_note('Failed');
                    $order->add_order_note("With Payment Id => " . $paymentId);
                    $order->add_order_note($this->msg['message']);
                }
            }
        }


        $wc_order_id = '';
        if (!isset($_REQUEST['merchant_order_id'])) {
            if (!isset($_REQUEST['vendor_order_id'])) {
                $this->log('===== NO ORDER NUMBER FOUND =====');
                exit;
            } else {
                $wc_order_id = $_REQUEST['vendor_order_id'];
            }
        } else {

            $wc_order_id = $_REQUEST['merchant_order_id'];
        }

        $this->log(" ==== ORDER -> {$wc_order_id} ====");

        // echo $wc_order_id;
        $wc_order_id = apply_filters('twoco_order_no_received', $wc_order_id, $_REQUEST);

        $wc_order = new WC_Order(absint($wc_order_id));

        $this->verify_order_by_hash($wc_order_id);

        $order_redirect = add_query_arg('twoco', 'processed', $this->get_return_url($wc_order));
        wp_redirect($order_redirect);
        exit;
    }

    /**
     * @param $iTransactionId
     * @return mixed
     */
    private function getTransactionInfo($iTransactionId)
    {

        try {

            $apiUrl = ($this->demo == 'yes') ? $this->payment_server_sandbox . "/transaction/" : 'http://api.yabetoopay.com/api/transactions/';
            $token = $this->requestToken();
            $oauth = "Bearer " . str_replace('"', "", json_decode($token)->token);
            $request = wp_remote_get($apiUrl . $iTransactionId . '/', array(
                'headers' => array(
                    'Authorization' => $oauth,
                ),
            ));


            //print_r($request);

            $body = wp_remote_retrieve_body($request);
            $res = json_decode($body, true);
            return $res;
        } catch (Exception $e) {

            $this->addLog("Server couldn't respond, " . $e->getMessage());
            throw new ValidationException("Server couldn't respond, " . $e->getMessage(), $e->getCode(), null);
        }
    }

    /**
     * @param $order
     * @return array|string
     */
    private function yabetoo_payment_processing($order)
    {
        //request token
        $token = $this->requestToken();

        /* if ($token) {
         }*/

        $twoco_args = $this->get_twoco_args($order);


        /*if ($data){
            print_r($data);
        } else {
            echo "err";
        }*/


        /*$total = intval( $order->get_total() );
        var_dump($total);

        $phone = esc_attr( $_POST['payment_number'] );
        $network_id = '1'; // mtn
        $reason = 'Test';

        $url = 'https://e.patasente.com/phantom-api/pay-with-patasente/' . $this->api_key . '/' . $this->widget_id . '?phone=' . $phone . '&amount=' . $total . '&mobile_money_company_id=' . $network_id . '&reason=' . 'Payment for Order: ' .$order_id;

        var_dump($url);*/

        //$response = wp_remote_post($url, array('timeout' => 45));

        /* if ( is_wp_error( $response ) ) {
             $error_message = $response->get_error_message();
             return "Something went wrong: $error_message";
         }

         if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
             $order->update_status( apply_filters( 'woocommerce_payleo_process_payment_order_status', $order->has_downloadable_item() ? 'wc-invoiced' : 'processing', $order ), __( 'Payments pending.', 'payleo-payments-woo' ) );
         }

         if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
             $response_body = wp_remote_retrieve_body( $response );
             var_dump($response_body['message']);
             if ( 'Thank you! Your payment was successful' === $response_body['message'] ) {
                 $order->payment_complete();

                 // Remove cart.
                 WC()->cart->empty_cart();

                 // Return thankyou redirect.
                 return array(
                     'result'   => 'success',
                     'redirect' => $this->get_return_url( $order ),
                 );
             }
         }*/

        // pending payment
        // $order->update_status( apply_filters( 'woocommerce_yabetoo_process_payment_order_status', $order->has_downloadable_item() ? 'wc-invoiced' : 'processing', $order ), __( 'Payments pending.', 'yabetoo-payments-woo' ) );

        // // If cleared
        // $order->payment_complete();

    }

    /**
     * @param $token
     * @return mixed
     */
    private function requestTransactionToken($token, $items, $order_id, $total_oder, $currency)
    {
        $endpoint = ($this->demo == 'yes') ? $this->product_server_sandbox . "/products" :
            'http://api.yabetoopay.com/api/transaction/transaction-token/' . $this->public_key;

        $body = [
            "total" => $total_oder,
            "currency" => $currency,
            "order_id" => $order_id,
            "store_id" => $token->store_id,
            "products" => $items,
        ];
        $body = wp_json_encode($body);

        $oauth = "Bearer " . str_replace('"', "", $token->token);

        $options = [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $oauth,
            ],
            'timeout' => 60,
            'redirection' => 5,
            "redirectTarget" => "TOP",
            'blocking' => true,
            'httpversion' => '1.0',
            'sslverify' => false,
            'data_format' => 'body',
        ];

        $response = wp_remote_post($endpoint, $options);

        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 200) {
            $data = wp_remote_retrieve_body($response);
            return json_decode($data, true);

        }
        return false;

    }

    /**
     * @return bool
     */
    private function requestToken()
    {

        $endpoint = ($this->demo == 'yes') ? $this->oauth_server_sandbox . '/oauth2/token' : 'http://api.yabetoopay.com/api/token/';


        $body = [
            'secret_key' => $this->secret_key,
            'public_key' => $this->public_key
        ];

        $body = wp_json_encode($body);

        $options = [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'timeout' => 60,
            'redirection' => 5,
            "redirectTarget" => "TOP",
            'blocking' => true,
            'httpversion' => '1.0',
            'sslverify' => false,
            'data_format' => 'body',
        ];

        $response = wp_remote_post($endpoint, $options);


        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code === 200) {
            $data = wp_remote_retrieve_body($response);
            return $data;

        }
        return false;
    }

    /**
     * @param $order
     * @return array
     */
    function get_twoco_args($order)
    {
        global $woocommerce;

        $order_id = $order->get_id();
        $redirect_url = get_site_url() . '/wc-api/wc_gateway_nm_yabetoopay';
        $token = json_decode($this->requestToken());
        $order_items = array();

        foreach ($order->get_items() as $item_id => $item) {
            array_push($order_items, [
                "product_id" => $item->get_product_id(),
                //"product" => $item->get_product(),
                "name" => $item->get_name(),
                "quantity" => $item->get_quantity(),
                "subtotal" => $item->get_subtotal(),
                "price" => $item->get_total(),
                "currency" => get_woocommerce_currency(),
                //"allmeta" => $item->get_meta_data(),
                //"somemeta" => $item->get_meta('_whatever', true),
                //"type" => $item->get_type(),
            ]);
        }


        //request token_transaction
        $product_id = $this->requestTransactionToken($token, $order_items, $order_id, $order->get_total(),get_woocommerce_currency() );

        // Yabetoo Args
        $twoco_args = array(
            "callback" => $redirect_url,
            "token" => str_replace('"', "", $token->token),
            "return_callback" => $order->get_cancel_order_url(),
            "environment" => $this->demo =="yes" ? "sandbox" : "production",
            "product_id" => $product_id["product_id"]
        );


        $twoco_args['x_receipt_link_url'] = $this->get_return_url($order);
        $twoco_args['return_url'] = str_replace('https', 'http', $order->get_cancel_order_url());


        //setting payment method
        if ($this->pay_method)
            $twoco_args['pay_method'] = $this->pay_method;


        $item_names = array();

        /* if (sizeof($order->get_items()) > 0) {

             $twoco_product_index = 0;

             foreach ($order->get_items() as $item) {
                 if ($item['qty'])
                     $item_names[] = $item['name'] . ' x ' . $item['qty'];

                 //since version 1.6
                 //adding support for both WC Versions

                 $_sku = '';
                 if (function_exists('get_product')) {

                     // Version 2.0
                     $product = $order->get_product_from_item($item);

                     // Get SKU or product id
                     if ($product->get_sku()) {
                         $_sku = $product->get_sku();
                     } else {
                         $_sku = $product->get_id();
                     }

                 } else {

                     // Version 1.6.6
                     $product = new WC_Product($item['id']);

                     // Get SKU or product id
                     if ($product->get_sku()) {
                         $_sku = $product->get_sku();
                     } else {
                         $_sku = $item['id'];
                     }
                 }

                 $tangible = "N";

                 $item_formatted_name = $item['name'] . ' (Product SKU: ' . $item['product_id'] . ')';

                 $twoco_args['li_' . $twoco_product_index . '_type'] = 'product';
                 $twoco_args['li_' . $twoco_product_index . '_name'] = sprintf(__('Order %s', 'woocommerce'), $order->get_order_number()) . " - " . $item_formatted_name;
                 $twoco_args['li_' . $twoco_product_index . '_quantity'] = $item['qty'];
                 $twoco_args['li_' . $twoco_product_index . '_price'] = $this->get_price($order->get_item_total($item, false));
                 $twoco_args['li_' . $twoco_product_index . '_product_id'] = $_sku;
                 $twoco_args['li_' . $twoco_product_index . '_tangible'] = $tangible;

                 $twoco_product_index++;
             }

             //getting extra fees since version 2.0+
             $extrafee = $order->get_fees();
             if ($extrafee) {


                 $fee_index = 1;
                 foreach ($order->get_fees() as $item) {

                     $twoco_args['li_' . $twoco_product_index . '_type'] = 'product';
                     $twoco_args['li_' . $twoco_product_index . '_name'] = sprintf(__('Other Fee %s', 'woocommerce'), $item['name']);
                     $twoco_args['li_' . $twoco_product_index . '_quantity'] = 1;
                     $twoco_args['li_' . $twoco_product_index . '_price'] = $this->get_price($item['line_total']);

                     $fee_index++;
                     $twoco_product_index++;
                 }
             }

             // Shipping Cost
             if ($order->get_total_shipping() > 0) {


                 $twoco_args['li_' . $twoco_product_index . '_type'] = 'shipping';
                 $twoco_args['li_' . $twoco_product_index . '_name'] = __('Shipping charges', 'woocommerce');
                 $twoco_args['li_' . $twoco_product_index . '_quantity'] = 1;
                 $twoco_args['li_' . $twoco_product_index . '_price'] = $this->get_price($order->get_total_shipping());
                 $twoco_args['li_' . $twoco_product_index . '_tangible'] = 'Y';

                 $twoco_product_index++;
             }

             // Taxes (shipping tax too)
             if ($order->get_total_tax() > 0) {

                 $twoco_args['li_' . $twoco_product_index . '_type'] = 'tax';
                 $twoco_args['li_' . $twoco_product_index . '_name'] = __('Tax', 'woocommerce');
                 $twoco_args['li_' . $twoco_product_index . '_quantity'] = 1;
                 $twoco_args['li_' . $twoco_product_index . '_price'] = $this->get_price($order->get_total_tax());

                 $twoco_product_index++;
             }


         }*/


        $twoco_args = apply_filters('woocommerce_twoco_args', $twoco_args);

        return $twoco_args;
    }


    /**
     * @param $price
     * @return mixed
     */
    function get_price($price)
    {

        $price = wc_format_decimal($price, 2);

        return apply_filters('nm_get_price', $price);
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page()
    {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }
    }

    /**
     * Change payment complete order status to completed for yabetoo orders.
     *
     * @param string $status Current order status.
     * @param int $order_id Order ID.
     * @param WC_Order|false $order Order object.
     * @return string
     * @since  3.1.0
     */
    public function change_payment_complete_order_status($status, $order_id = 0, $order = false)
    {
        if ($order && 'yabetoopay' === $order->get_payment_method()) {
            $status = 'completed';
        }
        return $status;
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order Order object.
     * @param bool $sent_to_admin Sent to admin.
     * @param bool $plain_text Email format: plain text or HTML.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
        }
    }
}
