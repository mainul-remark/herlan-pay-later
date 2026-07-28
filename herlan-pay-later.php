<?php
/**
 * Plugin Name: Herlan Pay Later
 * Plugin URI: https://herlan.com
 * Description: Adds a "Pay Later" WooCommerce payment method that behaves like Cash on Delivery, but is only available at checkout to specific customer roles chosen from the admin settings.
 * Version: 1.1.1
 * Author: Muhammad Ali
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
 * The "Feature Policy" field renders wp_editor() (TinyMCE); make sure its
 * scripts/styles are enqueued on the WooCommerce settings screen.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    if ('woocommerce_page_wc-settings' !== $hook) {
        return;
    }

    wp_enqueue_editor();
    add_action('admin_footer', 'hpl_feature_policy_editor_change_script');
});

/**
 * TinyMCE only syncs its content back into the underlying <textarea> on
 * form submit, so editing the "Feature Policy" editor never fires a native
 * change/input event on that textarea. WooCommerce's settings page only
 * enables its "Save changes" button in response to such an event, so
 * without this it stays disabled after editing the policy text. This binds
 * TinyMCE's own change events to sync the textarea and dispatch a native
 * change event WooCommerce's script can see.
 */
function hpl_feature_policy_editor_change_script()
{
    $field_id = 'woocommerce_' . HPL_GATEWAY_ID . '_feature_policy';
    ?>
    <script>
    (function () {
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof tinymce === 'undefined') {
                return;
            }
            tinymce.on('AddEditor', function (e) {
                if (e.editor.id !== '<?php echo esc_js($field_id); ?>') {
                    return;
                }
                e.editor.on('change keyup input', function () {
                    e.editor.save();
                    var textarea = document.getElementById(e.editor.id);
                    if (textarea) {
                        textarea.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            });
        });
    })();
    </script>
    <?php
}

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
