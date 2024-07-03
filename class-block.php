<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class CoinPays_Gateway_Blocks extends AbstractPaymentMethodType {

    protected $name = 'coinpays_payment_gateway';

    private array $gateways;

    public function initialize()
    {
        $this->settings['iframe'] = get_option( 'woocommerce_coinpays_payment_gateway_settings');
        $this->gateways['coinpays_payment_gateway'] = [
            'title' => $this->settings['iframe']['title'],
            'description' => $this->settings['iframe']['description'],
            'enabled' => $this->settings['iframe']['enabled'],
            'icon' => $this->settings['iframe']['logo'] === 'yes'
                ? plugin_dir_url( __DIR__ ) . '/' . plugin_basename( __DIR__ ) . '/assets/img/logo.webp'
                : null
        ];
    }

    public function is_active()
    {
        return $this->settings['iframe']['enabled'] === 'yes' ? 1 : 0;
    }


    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'coinpays-payment-gateway-blocks-integration',
            plugin_dir_url(__FILE__) . 'checkout.js',
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );
        return [ 'coinpays-payment-gateway-blocks-integration' ];
    }

    public function get_payment_method_data()
    {
        $data = [];
        foreach ($this->gateways as $key => $gateway) {
            if($gateway['enabled'] === 'yes') {
                $data[$key] = [
                    'title' => $gateway['title'],
                    'description' => $gateway['description'],
                    'icon'         => $gateway['icon'],
                ];
            }
        }
        return $data;
    }
}
