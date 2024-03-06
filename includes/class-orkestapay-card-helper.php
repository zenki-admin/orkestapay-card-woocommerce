<?php
if (!defined('ABSPATH')) {
    exit();
}

/**
 * OrkestaPayCard_Helper class.
 *
 */
class OrkestaPayCard_Helper
{
    const PAYMENT_METHOD_CARD = 'CARD';
    const PAYMENT_METHOD_ALIAS = 'Default card';

    /**
     * Description
     *
     * @return array
     */
    public static function transform_data_4_customers($order)
    {
        $customerData = [
            'external_id' => "{$order->get_user_id()}",
            'name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'country' => $order->get_billing_country(),
        ];

        return $customerData;
    }

    /**
     * Description
     *
     * @return array
     */
    public static function transform_data_4_orders($orkestaCustomerId, $order)
    {
        $products = [];
        foreach ($order->get_items() as $item) {
            $product = wc_get_product($item->get_product_id());
            $name = trim(preg_replace('/[[:^print:]]/', '', strip_tags($product->get_title())));
            $desc = trim(preg_replace('/[[:^print:]]/', '', strip_tags($product->get_description())));
            $thumbnailUrl = wp_get_attachment_image_url($product->get_image_id());

            $products[] = [
                'id' => "{$item->get_product_id()}",
                'name' => $name,
                'description' => substr($desc, 0, 250),
                'quantity' => $item->get_quantity(),
                'unit_price' => $item->get_subtotal(),
                'thumbnail_url' => $thumbnailUrl,
            ];
        }

        $orderData = [
            'merchant_order_id' => "{$order->get_id()}",
            'customer_id' => $orkestaCustomerId,
            'currency' => $order->get_currency(),
            'subtotal_amount' => $order->get_subtotal(),
            'order_country' => $order->get_billing_country(),
            'additional_charges' => [
                'shipment' => $order->get_shipping_total(),
                'taxes' => $order->get_total_tax(),
            ],
            'discounts' => [
                'promo_discount' => $order->get_discount_total(),
            ],
            'total_amount' => $order->get_total(),
            'products' => $products,
        ];

        return $orderData;
    }

    /**
     * Description
     *
     * @return array
     */
    public static function transform_data_4_payment_method($order, $creditCard)
    {
        $paymentMethodData = [
            'type' => self::PAYMENT_METHOD_CARD,
            'card' => [
                'holder_name' => $order->get_billing_first_name(),
                'holder_last_name' => $order->get_billing_last_name(),
                'number' => $creditCard['card_number'],
                'expiration_month' => $creditCard['exp_month'],
                'expiration_year' => $creditCard['exp_year'],
                'cvv' => intval($creditCard['cvv']),
                'one_time_use' => true,
            ],
            'billing_address' => [
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
                'address' => [
                    'line_1' => $order->get_billing_address_1(),
                    'line_2' => $order->get_billing_address_2(),
                    'city' => $order->get_billing_city(),
                    'state' => $order->get_billing_state(),
                    'country' => $order->get_billing_country(),
                    'zip_code' => $order->get_billing_postcode(),
                ],
            ],
        ];

        return $paymentMethodData;
    }

    /**
     * Description
     *
     * @return array
     */
    public static function transform_data_4_payment($orkestaPaymentMethodId, $order, $deviceSessionId, $orkestaCardCvc)
    {
        $paymentData = [
            'payment_method' => $orkestaPaymentMethodId,
            'payment_amount' => [
                'amount' => $order->get_total(),
                'currency' => $order->get_currency(),
            ],
            'device_info' => [
                'device_session_id' => $deviceSessionId,
            ],
            'payment_options' => [
                'capture' => true,
                'cvv' => $orkestaCardCvc,
            ],
            'metadata' => [
                'merchant_order_id' => "{$order->get_id()}",
            ],
        ];

        return $paymentData;
    }

    /**
     * Description
     *
     * @return array
     */
    public static function transform_data_4_checkout($order, $returnUrl)
    {
        $products = [];
        foreach ($order->get_items() as $item) {
            $product = wc_get_product($item->get_product_id());
            $name = trim(preg_replace('/[[:^print:]]/', '', strip_tags($product->get_title())));
            $desc = trim(preg_replace('/[[:^print:]]/', '', strip_tags($product->get_description())));
            $thumbnailUrl = wp_get_attachment_image_url($product->get_image_id());

            $products[] = [
                'id' => "{$item->get_product_id()}",
                'name' => $name,
                'description' => substr($desc, 0, 250),
                'quantity' => $item->get_quantity(),
                'unit_price' => $item->get_subtotal(),
                'thumbnail_url' => $thumbnailUrl,
            ];
        }

        $convertedTime = date('Y-m-d H:i:s', strtotime(' +15 minutes '));
        $expiresAt = (new DateTime($convertedTime))->getTimestamp();

        $checkoutData = [
            // 'expires_at' => $expiresAt,
            'completed_redirect_url' => $returnUrl,
            'canceled_redirect_url' => wc_get_checkout_url(),
            'order' => [
                'merchant_order_id' => "{$order->get_id()}",
                'currency' => $order->get_currency(),
                'locale' => 'es_MX',
                'subtotal_amount' => $order->get_subtotal(),
                'order_country' => $order->get_billing_country(),
                'additional_charges' => [
                    'shipment' => $order->get_shipping_total(),
                    'taxes' => $order->get_total_tax(),
                ],
                'discounts' => [
                    'promo_discount' => $order->get_discount_total(),
                ],
                'total_amount' => $order->get_total(),
                'products' => $products,
                'customer' => [
                    'external_id' => "{$order->get_user_id()}",
                    'name' => $order->get_billing_first_name(),
                    'last_name' => $order->get_billing_last_name(),
                    'email' => $order->get_billing_email(),
                    'phone' => $order->get_billing_phone(),
                    'country' => $order->get_billing_country(),
                ],
                'config' => [
                    'use_3ds' => true,
                ],
            ],
        ];

        return $checkoutData;
    }

    public static function remove_string_spaces($text)
    {
        return preg_replace('/\s+/', '', wc_clean($text));
    }

    public static function get_expiration_month($exp_date)
    {
        $exp_date = self::remove_string_spaces($exp_date);
        $exp_date = explode('/', $exp_date);
        return $exp_date[0];
    }

    public static function get_expiration_year($exp_date)
    {
        $exp_date = self::remove_string_spaces($exp_date);
        $exp_date = explode('/', $exp_date);
        return $exp_date[1];
    }

    public static function is_null_or_empty_string($string)
    {
        return !isset($string) || trim($string) === '';
    }

    public static function get_signature_from_url($url)
    {
        $url_components = explode('&signature=', $url);
        return $url_components[1];
    }
}
