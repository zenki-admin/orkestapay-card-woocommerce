<?php

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die();
}

orkestapay_card_uninstall_plugin();

/**
 * Deletes plugin settings
 *
 * @return void
 */
function orkestapay_card_uninstall_plugin()
{
    if (function_exists('is_multisite') && is_multisite()) {
        if (false == is_super_admin()) {
            return;
        }

        delete_site_option('woocommerce_orkestapay_card_settings');
    } else {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        delete_option('woocommerce_orkestapay_card_settings');
    }
}
