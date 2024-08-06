<?php
/*
 * Plugin Name: OrkestaPay Card
 * Plugin URI: https://wordpress.org/plugins/orkestapay-card
 * Description: Orchestrate multiple payment gateways for a frictionless, reliable, and secure checkout experience.
 * Author: Zenkipay
 * Author URI: https://zenkipay.io
 * Version: 0.3.0
 * Requires at least: 5.8
 * Tested up to: 6.4.1
 * WC requires at least: 6.8
 * WC tested up to: 7.1.1
 * Text Domain: orkestapay-card
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit();
}

define('ORKESTAPAY_CARD_WC_PLUGIN_FILE', __FILE__);
define('ORKESTAPAY_CARD_API_URL', 'https://api.orkestapay.com');
define('ORKESTAPAY_CARD_API_SAND_URL', 'https://api.sand.orkestapay.com');
define('ORKESTAPAY_CARD_JS_URL', 'https://checkout.orkestapay.com');

// Languages traslation
load_plugin_textdomain('orkestapay-card', false, dirname(plugin_basename(__FILE__)) . '/languages/');

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_action('plugins_loaded', 'orkestapay_card_init_gateway_class', 0);
add_action('woocommerce_order_refunded', 'orkestapay_card_woocommerce_order_refunded', 10, 2);

function orkestapay_card_init_gateway_class()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    include_once 'includes/class-orkestapay-card-logger.php';
    include_once 'includes/class-orkestapay-card-api.php';
    include_once 'includes/class-orkestapay-card-helper.php';
    include_once 'includes/class-orkestapay-card.php';

    add_filter('woocommerce_payment_gateways', 'orkestapay_card_add_gateway_class');

    function orkestapay_card_plugin_action_links($links)
    {
        $settings_url = esc_url(get_admin_url(null, 'admin.php?page=wc-settings&tab=checkout&section=orkestapay-card'));
        array_unshift($links, "<a title='OrkestaPay Card Settings Page' href='$settings_url'>" . __('Settings', 'orkestapay-card') . '</a>');

        return $links;
    }

    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'orkestapay_card_plugin_action_links');

    /**
     * Add the Gateway to WooCommerce
     *
     * @return Array Gateway list with our gateway added
     */
    function orkestapay_card_add_gateway_class($gateways)
    {
        $gateways[] = 'OrkestaPayCard_Gateway';
        return $gateways;
    }
}

/**
 * Capture a dispute when a refund was made
 *
 * @param type $order_id
 * @param type $refund_id
 *
 */
function orkestapay_card_woocommerce_order_refunded($order_id, $refund_id)
{
    $order = wc_get_order($order_id);
    $refund = wc_get_order($refund_id);

    OrkestaPayCard_Logger::log('#orkesta_woocommerce_order_refunded', ['order_id' => $order_id, 'refund_id' => $refund_id, 'payment_method' => $order->get_payment_method()]);

    if ($order->get_payment_method() !== 'orkestapay-card') {
        return;
    }

    $orkestaOrderId = get_post_meta($order_id, '_orkestapay_order_id', true);
    $orkestaPaymentId = get_post_meta($order_id, '_orkestapay_payment_id', true);
    OrkestaPayCard_Logger::log('#orkesta_woocommerce_order_refunded', ['orkesta_order_id' => $orkestaOrderId, 'orkesta_payment_id' => $orkestaPaymentId]);

    if (OrkestaPayCard_Helper::is_null_or_empty_string($orkestaOrderId) || OrkestaPayCard_Helper::is_null_or_empty_string($orkestaPaymentId)) {
        return;
    }

    $refundData = ['description' => $refund->get_reason(), 'amount' => floatval($refund->get_amount())];

    try {
        $orkestapay = new OrkestaPayCard_Gateway();
        $apiHost = $orkestapay->getApiHost();

        OrkestaPayCard_API::request($refundData, "$apiHost/v1/payments/{$orkestaPaymentId}/refund", 'POST');

        $order->add_order_note('Refund was requested.');
    } catch (Exception $e) {
        OrkestaPayCard_Logger::error('#orkesta_woocommerce_order_refunded', ['error' => $e->getMessage()]);
        $order->add_order_note('There was an error creating the refund: ' . $e->getMessage());
    }

    return;
}
