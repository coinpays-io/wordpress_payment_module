<?php

class CoinPays_Payment_Gateway extends WC_Payment_Gateway
{
    private CoinPaysCoreClass $core;

    public function __construct()
    {
        $this->id = 'coinpays_payment_gateway';
        $this->has_fields = true;
        $this->method_title = __('CoinPays Payment Gateway WooCommerce - iFrame API', 'coinpays-sanal-pos-woocommerce-iframe-api');
        $this->method_description = __('Accept payments through CoinPays Payment Gateway', 'coinpays-sanal-pos-woocommerce-iframe-api');
        $this->supports = array(
            'products',
        );
        $this->core = new CoinPaysCoreClass();
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'coinpays_receipt_page'));
        add_action('woocommerce_api_wc_gateway_coinpayscheckout', array($this, 'coinpays_checkout_response'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(
            $this,
            'plugin_action_links'
        ));
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
        $get_pspi_options = get_option('woocommerce_coinpays_payment_gateway_settings');

        if ($get_pspi_options != '' && $get_pspi_options['logo'] === 'yes') {
            add_action('wp_enqueue_scripts', array($this, 'add_coinpays_payment_style'));
        }
    }

    public function plugin_action_links($links)
    {
        $plugin_links = array('<a href="admin.php?page=wc-settings&tab=checkout&section=coinpays_payment_gateway">Settings</a>');

        return array_merge($plugin_links, $links);
    }

    public function plugin_row_meta($links, $file)
    {
        if (plugin_basename(__FILE__) === $file) {
            $row_meta = array(
                'support' => '<a href="' . esc_url(apply_filters('coinpaysspi_support_url', 'https://coinpays.io/contact-us')) . '" target="_blank">Support</a>'
            );

            return array_merge($links, $row_meta);
        }

        return (array)$links;
    }

    public function add_coinpays_payment_style()
    {
        wp_register_style('coinpays-payment-gateway', COINPAYSSPI_PLUGIN_URL_2 . '/assets/css/coinpays-sanal-pos-iframe-style.css');
        wp_enqueue_style('coinpays-payment-gateway');
    }

    function init_form_fields()
    {
        $this->form_fields = array(
            'callback' => array(
                'title' => __('Callback URL', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'type' => 'title',
                'description' => sprintf(__('You must add the following callback url <strong>%s</strong> to your <a href="https://app.coinpays.io/manage/virtual-pos/settings" target="_blank">Callback URL Settings.</a>'), get_home_url() . '/index.php?wc-api=wc_gateway_coinpayscheckout')
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'label' => __('Enable CoinPays iFrame API', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'test' => array(
                'title' => __('Test Mode', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'label' => __('Test Mode', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'type' => 'text',
                'description' => __('The title your customers will see during checkout.', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'default' => __('Crypto (CoinPays)'),
                'desc_tip' => true,
                'required' => true,
            ),
            'description' => array(
                'title' => __('Description', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'type' => 'textarea',
                'description' => __('The description your customers will see during checkout.', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'default' => __("When you choose this payment method, crypto payments are available on most networks..", 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'desc_tip' => true
            ),
            'logo' => array(
                'title' => __('Logo', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'label' => __('Enable/Disable', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'coinpays_merchant_id' => array(
                'title' => __('Merchant ID', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'type' => 'text',
                'description' => __('You will find this value under the CoinPays Merchant Panel > Information Tab.', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'desc_tip' => true,
                'required' => true,
            ),
            'coinpays_merchant_key' => array(
                'title' => __('Merchant Key', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'type' => 'text',
                'description' => __('You will find this value under the CoinPays Merchant Panel > Information Tab.', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'desc_tip' => true,
                'required' => true,
            ),
            'coinpays_merchant_salt' => array(
                'title' => __('Merchant Salt', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'type' => 'text',
                'description' => __('You will find this value under the CoinPays Merchant Panel > Information Tab.', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'desc_tip' => true,
                'required' => true,
            ),
            'coinpays_order_status' => array(
                'title' => __('Order Status', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'type' => 'select',
                'description' => __('Order status when payment is successful. Recommended processing.', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'desc_tip' => true,
                'default' => 'wc-processing',
                'options' => wc_get_order_statuses(),

            ),
            'coinpays_lang' => array(
                'title' => __('Language', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                'type' => 'select',
                'default' => '0',
                'options' => array(
                    '0' => __('Automatic', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                    '1' => __('Turkish', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                    '2' => __('English', 'coinpays-sanal-pos-woocommerce-iframe-api'),
                ),
            ),
        );
    }

    public function coinpays_receipt_page($order)
    {
        $this->core->receiptPage($order, $this->settings);
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        );
    }

    function coinpays_checkout_response()
    {
        if (empty($_POST)) {
            die('no post data');
        }
        require_once plugin_dir_path(__FILE__) . '/class-coinpaysspi-callback-iframe.php';
        CoinPaysCheckoutCallbackIframe::callback_iframe($_POST);
    }
}
