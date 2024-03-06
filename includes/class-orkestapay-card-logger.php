<?php
if (!defined('ABSPATH')) {
    exit(); // Exit if accessed directly
}

/**
 *  OrkestaPayCard_Logger class.
 *
 * Log all
 */
class OrkestaPayCard_Logger
{
    public static $logger;
    const WC_LOG_FILENAME = 'orkestapay-card';

    /**
     * Utilize WC logger class
     *
     */
    public static function log($message, $context = [])
    {
        if (!class_exists('WC_Logger')) {
            return;
        }

        if (empty(self::$logger)) {
            self::$logger = wc_get_logger();
        }

        $log_entry = "\n" . '====Start Log====' . "\n" . $message . ' -> ' . print_r($context, true) . "\n" . '====End Log====' . "\n\n";

        self::$logger->debug($log_entry, ['source' => self::WC_LOG_FILENAME]);
    }

    /**
     * Utilize WC logger class
     *
     */
    public static function error($message, $context = [])
    {
        if (!class_exists('WC_Logger')) {
            return;
        }

        if (empty(self::$logger)) {
            self::$logger = wc_get_logger();
        }

        $log_entry = "\n" . '====Start Error Log====' . "\n" . $message . ' -> ' . print_r($context, true) . "\n" . '====End Error Log====' . "\n\n";

        self::$logger->error($log_entry, ['source' => self::WC_LOG_FILENAME]);
    }
}
