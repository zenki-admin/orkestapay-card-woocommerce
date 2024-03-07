<?php

/**
 * OrkestaPayCard_Gateway class.
 *
 * @extends WC_Payment_Gateway
 */

if (!defined('ABSPATH')) {
    exit();
}

class OrkestaPayCard_Gateway extends WC_Payment_Gateway
{
    const PAYMENT_ACTION_REQUIRED = 'PAYMENT_ACTION_REQUIRED';

    protected $test_mode = true;
    protected $merchant_id;
    protected $client_id;
    protected $client_secret;
    protected $device_key;
    protected $whsec;
    protected $plugin_version = '0.1.0';

    public function __construct()
    {
        $this->id = 'orkestapay-card'; // Payment gateway plugin ID
        $this->method_title = __('OrkestaPay Card', 'orkestapay-card');
        $this->method_description = __('Orchestrate multiple payment gateways for a frictionless, reliable, and secure checkout experience.', 'orkestapay-card');
        $this->has_fields = true;
        $this->supports = ['products', 'refunds', 'tokenization', 'add_payment_method'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];

        $this->enabled = $this->settings['enabled'];
        $this->test_mode = strcmp($this->settings['test_mode'], 'yes') == 0;
        $this->merchant_id = $this->settings['merchant_id'];
        $this->client_id = $this->settings['client_id'];
        $this->client_secret = $this->settings['client_secret'];
        $this->device_key = $this->settings['device_key'];
        $this->whsec = $this->settings['whsec'];

        OrkestaPayCard_API::set_client_id($this->client_id);
        OrkestaPayCard_API::set_client_secret($this->client_secret);

        if ($this->test_mode) {
            $this->description .= __('TEST MODE ENABLED. In test mode, you can use the card number 4242424242424242 with any CVC and a valid expiration date.', 'orkestapay-card');
        }

        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('woocommerce_api_orkesta_get_access_token', [$this, 'orkesta_get_access_token']);

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * Plugin options
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable OrkestaPay', 'orkestapay-card'),
                'label' => __('Enable', 'orkestapay-card'),
                'type' => 'checkbox',
                'default' => 'no',
                'description' => __('Check the box to enable Orkesta as a payment method.', 'orkestapay-card'),
            ],
            'test_mode' => [
                'title' => __('Enable test mode', 'orkestapay-card'),
                'label' => __('Enable', 'orkestapay-card'),
                'type' => 'checkbox',
                'default' => 'yes',
                'description' => __('Check the box to make test payments.', 'orkestapay-card'),
            ],
            'title' => [
                'title' => __('Title', 'orkestapay-card'),
                'type' => 'text',
                'default' => __('Credit Card', 'orkestapay-card'),
                'description' => __('Payment method title that the customer will see on your checkout.', 'orkestapay-card'),
            ],
            'description' => [
                'title' => __('Description', 'orkestapay-card'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your website.', 'orkestapay-card'),
                'default' => __('Pay with your credit or debit card.', 'orkestapay-card'),
            ],
            'merchant_id' => [
                'title' => __('Merchant ID', 'orkestapay-card'),
                'type' => 'text',
                'default' => '',
                'custom_attributes' => [
                    'autocomplete' => 'off',
                    'aria-autocomplete' => 'none',
                ],
            ],
            'client_id' => [
                'title' => __('Access Key', 'orkestapay-card'),
                'type' => 'text',
                'default' => '',
                'custom_attributes' => [
                    'autocomplete' => 'off',
                    'aria-autocomplete' => 'none',
                    'role' => 'presentation',
                ],
            ],
            'client_secret' => [
                'title' => __('Secret Key', 'orkestapay-card'),
                'type' => 'password',
                'default' => '',
                'custom_attributes' => [
                    'autocomplete' => 'off',
                    'aria-autocomplete' => 'none',
                    'role' => 'presentation',
                ],
            ],
            'device_key' => [
                'title' => __('Device Key', 'orkestapay-card'),
                'type' => 'text',
                'default' => '',
                'custom_attributes' => [
                    'autocomplete' => 'off',
                    'aria-autocomplete' => 'none',
                ],
            ],
            'whsec' => [
                'title' => __('Webhook Signing Secret', 'orkestapay-card'),
                'type' => 'password',
                'default' => '',
                'description' => __('This secret is required to verify payment notifications.', 'orkestapay-card'),
                'custom_attributes' => [
                    'autocomplete' => 'off',
                    'aria-autocomplete' => 'none',
                    'role' => 'presentation',
                ],
            ],
        ];
    }

    /**
     * Handles admin notices
     *
     * @return void
     */
    public function admin_notices()
    {
        if ('no' == $this->enabled) {
            return;
        }

        /**
         * Check if WC is installed and activated
         */
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            // WooCommerce is NOT enabled!
            echo wp_kses_post('<div class="error"><p>');
            echo esc_html_e('Orkesta needs WooCommerce plugin is installed and activated to work.', 'orkestapay-card');
            echo wp_kses_post('</p></div>');
            return;
        }
    }

    function admin_options()
    {
        wp_enqueue_style('font_montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600&display=swap', ORKESTAPAY_CARD_WC_PLUGIN_FILE, [], $this->plugin_version);
        wp_enqueue_style('orkesta_admin_style', plugins_url('assets/css/admin-style.css', ORKESTAPAY_CARD_WC_PLUGIN_FILE), [], $this->plugin_version);

        $this->logo = plugins_url('assets/images/orkestapay.svg', ORKESTAPAY_CARD_WC_PLUGIN_FILE);

        include_once dirname(__DIR__) . '/templates/admin.php';
    }

    public function process_admin_options()
    {
        $settings = new WC_Admin_Settings();

        $post_data = $this->get_post_data();
        $client_id = $post_data['woocommerce_' . $this->id . '_client_id'];
        $client_secret = $post_data['woocommerce_' . $this->id . '_client_secret'];
        $merchant_id = $post_data['woocommerce_' . $this->id . '_merchant_id'];
        $device_key = $post_data['woocommerce_' . $this->id . '_device_key'];
        $whsec = $post_data['woocommerce_' . $this->id . '_whsec'];

        $this->settings['title'] = $post_data['woocommerce_' . $this->id . '_title'];
        $this->settings['description'] = $post_data['woocommerce_' . $this->id . '_description'];
        $this->settings['merchant_id'] = $merchant_id;
        $this->settings['device_key'] = $device_key;
        $this->settings['whsec'] = $whsec;
        $this->settings['test_mode'] = $post_data['woocommerce_' . $this->id . '_test_mode'] == '1' ? 'yes' : 'no';
        $this->settings['enabled'] = $post_data['woocommerce_' . $this->id . '_enabled'] == '1' ? 'yes' : 'no';
        $this->test_mode = $post_data['woocommerce_' . $this->id . '_test_mode'] == '1';

        if ($merchant_id == '' || $device_key == '' || $whsec == '') {
            $this->settings['enabled'] = 'no';
            $settings->add_error('You need to enter all your credentials if you want to use this plugin.');
            return;
        }

        if (!$this->validateOrkestaCredentials($client_id, $client_secret)) {
            $this->settings['enabled'] = 'no';
            $this->settings['client_id'] = '';
            $this->settings['client_secret'] = '';

            $settings->add_error(__('Provided credentials are invalid.', 'orkestapay-card'));
        } else {
            $this->settings['client_id'] = $client_id;
            $this->settings['client_secret'] = $client_secret;
            $settings->add_message(__('Configuration completed successfully.', 'orkestapay-card'));
        }

        return update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
    }

    /**
     * Loads (enqueue) static files (js & css) for the checkout page
     *
     * @return void
     */
    public function payment_scripts()
    {
        if (!is_checkout()) {
            return;
        }

        $get_access_token_url = esc_url(WC()->api_request_url('orkesta_get_access_token'));

        $payment_args = [
            'orkestapay_api_url' => $this->getApiHost(),
            'plugin_payment_gateway_id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'device_key' => $this->device_key,
            'get_access_token_url' => $get_access_token_url,
        ];

        $js_url = $this->getJsUrl();

        wp_enqueue_script('orkestapay_card_js_resource', $js_url . '/script/orkestapay.js', [], $this->plugin_version, true);
        wp_enqueue_script('orkestapay_card_payment_js', plugins_url('assets/js/orkesta-payment.js', ORKESTAPAY_CARD_WC_PLUGIN_FILE), ['jquery'], $this->plugin_version, true);
        wp_enqueue_style('orkestapay_card_checkout_style', plugins_url('assets/css/checkout-style.css', ORKESTAPAY_CARD_WC_PLUGIN_FILE), [], $this->plugin_version, 'all');
        wp_localize_script('orkestapay_card_payment_js', 'orkestapay_card_payment_args', $payment_args);
    }

    public function payment_fields()
    {
        wp_enqueue_script('wc-credit-card-form');

        $apiHost = $this->getApiHost();
        $this->brands = OrkestaPayCard_API::retrieve("$apiHost/v1/merchants/providers/brands");

        include_once dirname(__DIR__) . '/templates/payment.php';
    }

    public function validate_fields()
    {
        if (empty($_POST['orkesta_device_session_id']) || empty($_POST['orkesta_customer_id']) || empty($_POST['orkesta_payment_method_id'])) {
            wc_add_notice('Some Orkesta ID is missing.', 'error');
            return false;
        }

        return true;
    }

    /**
     * Process payment at checkout
     *
     * @return int $order_id
     */
    public function process_payment($order_id)
    {
        global $woocommerce;
        $apiHost = $this->getApiHost();
        $deviceSessionId = wc_clean($_POST['orkesta_device_session_id']);
        $paymentMethodId = wc_clean($_POST['orkesta_payment_method_id']);
        $customerId = wc_clean($_POST['orkesta_customer_id']);
        $orkestaCardCvc = wc_clean($_POST['orkesta_card_cvc']);

        $order = wc_get_order($order_id);

        try {
            $orderDTO = OrkestaPayCard_Helper::transform_data_4_orders($customerId, $order);
            $orkestaOrder = OrkestaPayCard_API::request($orderDTO, "$apiHost/v1/orders");

            $paymentDTO = OrkestaPayCard_Helper::transform_data_4_payment($paymentMethodId, $order, $deviceSessionId, $orkestaCardCvc);
            $orkestaPayment = OrkestaPayCard_API::request($paymentDTO, "$apiHost/v1/orders/{$orkestaOrder->order_id}/payments");

            if ($orkestaPayment->status !== 'COMPLETED') {
                throw new Exception(__('Payment Failed.', 'orkestapay-card'));
            }

            // COMPLETED - we received the payment
            $order->payment_complete();
            $order->add_order_note(sprintf("%s payment completed with Transaction Id of '%s'", $this->method_title, $orkestaPayment->id));

            update_post_meta($order->get_id(), '_orkesta_order_id', $orkestaOrder->order_id);
            update_post_meta($order->get_id(), '_orkesta_payment_id', $orkestaPayment->id);

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Redirect to the thank you page
            return [
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
            ];
        } catch (Exception $e) {
            OrkestaPayCard_Logger::error('#process_payment', ['error' => $e->getMessage()]);

            $order->add_order_note(sprintf("%s Credit card payment failed with message: '%s'", $this->method_title, $e->getMessage()));
            $order->update_status('failed');
            $order->save();

            wc_add_notice(__('A transaction error occurred. Your credit card has not been charged.', 'orkestapay-card'), 'error');

            return;
        }
    }

    /**
     * Checks if the Orkesta key is valid
     *
     * @return boolean
     */
    protected function validateOrkestaCredentials($client_id, $client_secret)
    {
        $token_result = OrkestaPayCard_API::get_access_token($client_id, $client_secret);

        if (!array_key_exists('access_token', $token_result)) {
            OrkestaPayCard_Logger::error('#validateOrkestaCredentials', ['error' => 'Error al obtener access_token']);

            return false;
        }

        // Se valida que la respuesta sea un JWT
        $regex = preg_match('/^([a-zA-Z0-9_=]+)\.([a-zA-Z0-9_=]+)\.([a-zA-Z0-9_\-\+\/=]*)/', $token_result['access_token']);
        if ($regex !== 1) {
            return false;
        }

        return true;
    }

    /**
     * AJAX - Get access token function
     *
     * @return string
     */
    public function orkesta_get_access_token()
    {
        $token_result = OrkestaPayCard_API::get_access_token($this->client_id, $this->client_secret);

        if (!array_key_exists('access_token', $token_result)) {
            OrkestaPayCard_Logger::error('#orkesta_get_access_token', ['error' => 'Error al obtener access_token']);

            wp_send_json_error(
                [
                    'result' => 'fail',
                    'message' => __('An error occurred getting access token.', 'orkestapay-card'),
                ],
                400
            );
        }

        wp_send_json_success([
            'result' => 'success',
            'access_token' => $token_result['access_token'],
        ]);

        die();
    }

    public function getApiHost()
    {
        return $this->test_mode ? ORKESTAPAY_CARD_API_SAND_URL : ORKESTAPAY_CARD_API_URL;
    }

    public function getJsUrl()
    {
        return $this->test_mode ? ORKESTAPAY_CARD_JS_SAND_URL : ORKESTAPAY_CARD_JS_URL;
    }
}
?>
