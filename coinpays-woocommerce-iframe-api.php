<?php
/**
 * Plugin Name: CoinPays WooCommerce - iFrame API
 * Plugin URI: https://wordpress.org/plugins/coinpays-woocommerce-iframe-api/
 * Description: The infrastructure required to receive payments through WooCommerce with your CoinPays membership.
 * Version: 1.0.0
 * Author: CoinPays Payment Gateway
 * Author URI: http://coinpays.io/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:coinpays-woocommerce-iframe-api
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
};

define('COINPAYSSPI_PLUGIN_URL_2', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));

require_once plugin_dir_path(__FILE__) . 'includes/CoinPaysCoreClass.php';

function woocommerce_coinpays_payment_gateway()
{
    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    require_once plugin_dir_path(__FILE__) . 'includes/class-coinpays-payment-gateway-iframe.php';

    function add_custom_gateway_class_coinpays($methods) {
        $methods[] = 'CoinPays_Payment_Gateway';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_custom_gateway_class_coinpays');

    add_action( 'woocommerce_blocks_loaded', function (){
        // Check if the required class exists
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            return;
        }

        // Include Blocks Checkout class
        require_once plugin_dir_path(__FILE__) . 'class-block.php';

        // Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
                $payment_method_registry->register( new CoinPays_Gateway_Blocks );
            }
        );
    });

    add_action('before_woocommerce_init', function() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil'))
        {
            Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    });
}

add_action('plugins_loaded', 'woocommerce_coinpays_payment_gateway', 0);
