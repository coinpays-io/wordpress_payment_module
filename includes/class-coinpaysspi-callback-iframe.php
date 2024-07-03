<?php
if (!defined('ABSPATH')) {
    exit;
};

class CoinPaysCheckoutCallbackIframe
{
    public static function callback_iframe($post)
    {
        $options = get_option('woocommerce_coinpays_payment_gateway_settings');

        $hash = base64_encode(hash_hmac('sha256', sanitize_text_field($post['merchant_oid']) . $options['coinpays_merchant_salt'] . sanitize_text_field($post['status']) . sanitize_text_field($post['total_amount']), $options['coinpays_merchant_key'], true));

        if ($hash != sanitize_text_field($post['hash'])) {
            die('COINPAYS notification failed: bad hash');
        }

        $order_id = explode('COINPAYSWOO', sanitize_text_field($post['merchant_oid']));
        $order = wc_get_order($order_id[1]);

        if ($order->get_status() == 'pending' or $order->get_status() == 'failed') {
            if (sanitize_text_field($post['status']) == 'success') {
                // Reduce Stock Levels
                wc_reduce_stock_levels($order_id[1]);

                $total_amount = round(sanitize_text_field($post['total_amount']) / 100, 2);

                // Note Start
                $note = __('COINPAYS NOTIFICATION - Payment Accepted', 'coinpays-payment-gateway') . "\n";
                $note .= __('Total Paid', 'coinpays-payment-gateway') . ': ' . sanitize_text_field(wc_price($total_amount, array('currency' => $order->get_currency()))) . "\n";

                $note .= __('CoinPays Order ID', 'coinpays-payment-gateway') . ': <a href="https://app.coinpays.io/manage/virtual-pos/transactions/detail/' . sanitize_text_field($post['merchant_oid']) . '" target="_blank">' . sanitize_text_field($post['merchant_oid']) . '</a>';
                // Note End
                $order->add_order_note(nl2br($note));
                $order->update_status($options['coinpays_order_status']);
                $order->save();
            } else {
                // Note Start
                $note = __('COINPAYS NOTIFICATION - Payment Failed', 'coinpays-payment-gateway') . "\n";
                $note .= __('Error', 'coinpays-payment-gateway') . ': ' . sanitize_text_field($post['failed_reason_code']) . ' - ' . sanitize_text_field($post['failed_reason_msg']) . "\n";
                $note .= __('CoinPays Order ID', 'coinpays-payment-gateway') . ': <a href="https://app.coinpays.io/manage/virtual-pos/transactions/detail/' . sanitize_text_field($post['merchant_oid']) . '" target="_blank">' . sanitize_text_field($post['merchant_oid']) . '</a>';
                $order->add_order_note(nl2br($note));
                $order->update_status('failed');
                $order->save();
            }
        }

        echo 'OK';
        exit;
    }
}
