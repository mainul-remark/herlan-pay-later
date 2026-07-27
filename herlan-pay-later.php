<?php
/**
 * Plugin Name: Herlan Pay Later
 * Plugin URI: https://herlan.com
 * Description: Adds a "Pay Later" WooCommerce payment method that behaves like Cash on Delivery, but is only available at checkout to specific customer roles chosen from the admin settings.
 * Version: 1.0.0
 * Author: Herlan
 * Author URI: https://herlan.com/
 * Text Domain: herlan-pay-later
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) exit;

define('HPL_VERSION', '1.0.0');
define('HPL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HPL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HPL_GATEWAY_ID', 'herlan_pay_later');

/**
 * Declare High-Performance Order Storage (HPOS) compatibility.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * Bail out with an admin notice if WooCommerce isn't active.
 */
function hpl_woocommerce_missing_notice()
{
    echo '<div class="notice notice-error"><p>' .
        esc_html__('Herlan Pay Later requires WooCommerce to be installed and active.', 'herlan-pay-later') .
        '</p></div>';
}

/**
 * Load the gateway class and register it with WooCommerce.
 */
function hpl_init_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'hpl_woocommerce_missing_notice');
        return;
    }

    require_once HPL_PLUGIN_DIR . 'includes/class-wc-gateway-pay-later.php';
}
add_action('plugins_loaded', 'hpl_init_gateway', 11);

/**
 * Register the gateway with WooCommerce.
 *
 * @param array $gateways
 * @return array
 */
function hpl_add_gateway_class($gateways)
{
    if (class_exists('WC_Gateway_Pay_Later')) {
        $gateways[] = 'WC_Gateway_Pay_Later';
    }
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'hpl_add_gateway_class');
