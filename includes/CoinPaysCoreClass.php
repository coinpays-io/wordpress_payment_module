<?php

class CoinPaysCoreClass
{
    public $coinpays_lang;
    public $url = 'https://app.coinpays.io';
    protected $category_full = array();

    public function receiptPage($order, $settings, $iframe = true)
    {
        $config = get_option('woocommerce_coinpays_payment_gateway_settings');
        $merchant = array();
        $this->categoryParserProd();;
        // Get Order
        $order = wc_get_order($order);
        $country = sanitize_text_field($order->get_billing_country());
        $get_country = sanitize_text_field(WC()->countries->get_states($country)[sanitize_text_field($order->get_billing_state())]);
        $merchant['merchant_oid'] = time() . 'COINPAYSWOO' . $order->get_id();
        $merchant['user_ip'] = $this->GetIP();
        $merchant['test_mode'] = $settings['test'] === 'yes' ? 1 : 0;
        $merchant['email'] = sanitize_email(substr($order->get_billing_email(), 0, 100));
        $merchant['payment_amount'] = $order->get_total() * 100;
        $merchant['user_name'] = sanitize_text_field(substr($order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), 0, 60));
        $merchant['user_address'] = substr($order->get_billing_address_1() . ' ' . $order->get_billing_address_2() . ' ' . $order->get_billing_city() . ' ' . $get_country . ' ' . $order->get_billing_postcode(), 0, 300);
        $merchant['user_phone'] = sanitize_text_field(substr($order->get_billing_phone(), 0, 20));

        // Basket
        $user_basket = array();
        if (sizeof($order->get_items()) > 0) {
            foreach ($order->get_items() as $item) {
                if ($item['qty']) {
                    $product = $item->get_product();
                    $item_name = $item['name'];
                    // WC_Order_Item_Meta is deprecated since WooCommerce version 3.1.0
                    if (defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '3.1.0', '>=')) {
                        $item_name .= wc_display_item_meta($item, array(
                            'before' => '',
                            'after' => '',
                            'separator' => ' | ',
                            'echo' => false,
                            'autop' => false
                        ));
                    } else {
                        $item_meta = new WC_Order_Item_Meta($item['item_meta']);
                        if ($meta = $item_meta->display(true, true)) {
                            $item_name .= ' ( ' . $meta . ' )';
                        }
                    }

                    $item_total_inc_tax = $order->get_item_subtotal($item, true);
                    $sku = '';

                    if ($product->get_sku()) {
                        $sku = '[STK:' . $product->get_sku() . ']';
                    }

                    $user_basket[] = array(
                        str_replace(':', ' = ', $sku) . ' ' . $item_name,
                        $item_total_inc_tax,
                        $item['qty'],
                    );
                }
            }
        }

        $merchant['currency'] = strtoupper(get_woocommerce_currency());
        $merchant['user_basket'] = base64_encode(wp_json_encode($user_basket));
        $hash_str = $config['coinpays_merchant_id'] . $merchant['user_ip'] . $merchant['merchant_oid'] . $merchant['email'] . $merchant['payment_amount'] . $merchant['user_basket'];
        $coinpays_token = base64_encode(hash_hmac('sha256', $hash_str . $config['coinpays_merchant_salt'], $config['coinpays_merchant_key'], true));
        $post_data = array(
            'merchant_id' => $settings['coinpays_merchant_id'],
            'user_ip' => $merchant['user_ip'],
            'currency' => $merchant['currency'],
            'merchant_oid' => $merchant['merchant_oid'],
            'email' => $merchant['email'],
            'payment_amount' => $merchant['payment_amount'],
            'coinpays_token' => $coinpays_token,
            'user_basket' => $merchant['user_basket'],
            'user_name' => $merchant['user_name'],
            'user_address' => $merchant['user_address'],
            'user_phone' => $merchant['user_phone'],
            'test_mode' => $merchant['test_mode'],
            'merchant_pending_url' => $order->get_checkout_order_received_url(),
        );
        if ($this->coinpays_lang == 0) {
            $lang_arr = array(
                'tr',
                'tr-tr',
                'tr_tr',
                'turkish',
                'turk',
                'türkçe',
                'turkce',
                'try',
                'trl',
                'tl'
            );
            $post_data['lang'] = (in_array(strtolower(get_locale()), $lang_arr) ? 'tr' : 'en');
        } else {
            $post_data['lang'] = ($this->coinpays_lang == 1 ? 'tr' : 'en');
        }
        $wpCurlArgs = array(
            'method' => 'POST',
            'body' => $post_data,
            'httpversion' => '1.0',
            'sslverify' => true,
            'timeout' => 90,
        );
        $result = wp_remote_post($this->url . '/api/get-token', $wpCurlArgs);
        $body = wp_remote_retrieve_body($result);
        $response = json_decode($body, 1);
        if ($response['status'] == 'success') {
            $token = $response['token'];
            $order->update_meta_data('coinpays_order_id', $merchant['merchant_oid']);
            $order->update_status('wc-pending');
            $order->save();
        } else {
            wp_die("COINPAYS IFRAME failed. reason:" . $response['reason']);
        }

        wp_enqueue_script('script', COINPAYSSPI_PLUGIN_URL_2 . '/assets/js/CoinPaysiframeResizer.js', false, '2.0', true);

        echo '<iframe src="' . $this->url . '/payment/' . $token . '" id="coinpaysiframe" frameborder="0" style="width: 100%;"></iframe>
            <script type="text/javascript">setInterval(function () {iFrameResize({}, "#coinpaysiframe");}, 1000);</script>';
    }

    public function categoryParserProd()
    {
        $all_cats = get_terms('product_cat', array());
        foreach ($all_cats as $cat) {
            $this->category_full[$cat->term_id] = $cat->parent;
        }
    }

    private function GetIP()
    {
        if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else {
            $ip = $_SERVER["REMOTE_ADDR"];
        }

        return $ip;
    }

    public function parentCategoryParser(&$cats = array(), &$cat_tree = array()): void
    {
        foreach ($cats as $key => $item) {
            if ($item['parent_id'] == $cat_tree['id']) {
                $cat_tree['parent'][$item['id']] = array('id' => $item['id'], 'name' => $item['name']);
                $this->parentCategoryParser($cats, $cat_tree['parent'][$item['id']]);
            }
        }
    }

    public function categoryParserClear($tree, $level = 0, $arr = array(), &$finish_him = array()): void
    {
        foreach ($tree as $id => $item) {
            if ($level == 0) {
                unset($arr);
                $arr = array();
                $arr[] = $item['name'];
            } elseif ($level == 1 or $level == 2) {
                if (count($arr) == ($level + 1)) {
                    $deleted = array_pop($arr);
                }
                $arr[] = $item['name'];
            }

            if ($level < 3) {
                $nav = null;
                foreach ($arr as $key => $val) {
                    $nav .= $val . ($level != 0 ? ' > ' : null);
                }

                $finish_him[$item['id']] = rtrim($nav, ' > ') . '<br>';

                if (!empty($item['parent'])) {
                    $this->categoryParserClear($item['parent'], $level + 1, $arr, $finish_him);
                }
            }
        }
    }

    public function categoryParser()
    {
        $all_cats = get_terms('product_cat', array());
        $cats = array();

        foreach ($all_cats as $cat) {
            $cats[] = array('id' => $cat->term_id, 'parent_id' => $cat->parent, 'name' => $cat->name);
        }

        $cat_tree = array();

        foreach ($cats as $key => $item) {
            if ($item['parent_id'] == 0) {
                $cat_tree[$item['id']] = array('id' => $item['id'], 'name' => $item['name']);
                $this->parentCategoryParser($cats, $cat_tree[$item['id']]);
            }
        }

        return $cat_tree;
    }

    public function catSearchProd($category_id = 0)
    {
        $return = false;
    }
}
