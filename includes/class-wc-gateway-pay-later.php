<?php
/**
 * Class WC_Gateway_Pay_Later file.
 *
 * @package Herlan_Pay_Later
 */

if (!defined('ABSPATH')) exit;

/**
 * Pay Later Gateway.
 *
 * Behaves like WooCommerce's built-in Cash on Delivery gateway, except it is
 * only offered at checkout to customers who belong to one of the roles
 * selected in the gateway settings.
 *
 * @class WC_Gateway_Pay_Later
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_Pay_Later extends WC_Payment_Gateway
{
    /**
     * Instructions shown on the thank-you page and in order emails.
     *
     * @var string
     */
    public $instructions;

    /**
     * User roles allowed to see/use this payment method at checkout.
     *
     * @var array
     */
    public $allowed_roles;

    /**
     * Rich-text "feature policy" content shown to customers in a popup via
     * the "?" icon on the checkout email-verification bar (auth-popup plugin).
     *
     * @var string
     */
    public $feature_policy;

    public function __construct()
    {
        $this->id                 = HPL_GATEWAY_ID;
        $this->icon               = apply_filters('hpl_pay_later_icon', '');
        $this->has_fields         = false;
        $this->method_title       = __('Pay Later', 'herlan-pay-later');
        $this->method_description = __('Let selected customer roles place an order and pay later, the same way Cash on Delivery works.', 'herlan-pay-later');

        $this->init_form_fields();
        $this->init_settings();

        $this->title         = $this->get_option('title');
        $this->description   = $this->get_option('description');
        $this->instructions  = $this->get_option('instructions');
        $this->allowed_roles = $this->get_option('allowed_roles', array());
        $this->feature_policy = $this->get_option('feature_policy', '');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        add_filter('woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3);
    }

    /**
     * Settings screen fields (WooCommerce > Settings > Payments > Pay Later).
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled'       => array(
                'title'   => __('Enable/Disable', 'herlan-pay-later'),
                'label'   => __('Enable Pay Later', 'herlan-pay-later'),
                'type'    => 'checkbox',
                'default' => 'no',
            ),
            'title'         => array(
                'title'       => __('Title', 'herlan-pay-later'),
                'type'        => 'safe_text',
                'description' => __('Payment method title the customer sees at checkout.', 'herlan-pay-later'),
                'default'     => __('Pay Later', 'herlan-pay-later'),
                'desc_tip'    => true,
            ),
            'description'   => array(
                'title'       => __('Description', 'herlan-pay-later'),
                'type'        => 'textarea',
                'description' => __('Payment method description the customer sees at checkout.', 'herlan-pay-later'),
                'default'     => __('Place your order now and pay later.', 'herlan-pay-later'),
                'desc_tip'    => true,
            ),
            'instructions'  => array(
                'title'       => __('Instructions', 'herlan-pay-later'),
                'type'        => 'textarea',
                'description' => __('Shown on the order received page and in the customer order email.', 'herlan-pay-later'),
                'default'     => __('Your order will be paid for later, as arranged.', 'herlan-pay-later'),
                'desc_tip'    => true,
            ),
            'allowed_roles' => array(
                'title'             => __('Allowed user roles', 'herlan-pay-later'),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 400px;',
                'default'           => array(),
                'options'           => $this->get_role_options(),
                'description'       => __('Only logged-in customers with one of the selected roles will see this payment method at checkout. Leave empty to disable it for everyone.', 'herlan-pay-later'),
                'desc_tip'          => true,
                'custom_attributes' => array(
                    'data-placeholder' => __('Select roles', 'herlan-pay-later'),
                ),
            ),
            'feature_policy' => array(
                'title'       => __('Feature Policy', 'herlan-pay-later'),
                'type'        => 'wysiwyg',
                'description' => __('Shown to customers in a popup when they click the "?" icon on the email-verification bar at checkout. Leave blank to hide the icon.', 'herlan-pay-later'),
                'default'     => '',
            ),
        );
    }

    /**
     * Build role_key => Role Name options for the settings multiselect.
     *
     * @return array
     */
    private function get_role_options()
    {
        $options = array();
        foreach (wp_roles()->get_names() as $role_key => $role_name) {
            $options[$role_key] = translate_user_role($role_name);
        }
        return $options;
    }

    /**
     * Render the "Feature Policy" field as a rich-text editor instead of the
     * WooCommerce settings API's default plain-text input.
     *
     * @param string $key
     * @param array  $data
     * @return string
     */
    public function generate_wysiwyg_html($key, $data)
    {
        $field_key = $this->get_field_key($key);
        $data      = wp_parse_args($data, array(
            'title'       => '',
            'desc_tip'    => false,
            'description' => '',
        ));

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <?php
                    wp_editor(
                        $this->get_option($key),
                        $field_key,
                        array(
                            'textarea_name' => $field_key,
                            'textarea_rows' => 10,
                            'media_buttons' => false,
                            'teeny'         => true,
                        )
                    );
                    ?>
                    <?php echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Sanitize the "Feature Policy" rich-text field before saving, keeping
     * safe HTML (the same rules WooCommerce applies to its own textarea type).
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    public function validate_wysiwyg_field($key, $value)
    {
        $value = is_null($value) ? '' : $value;
        return wp_kses_post(trim(wp_unslash($value)));
    }

    /**
     * Only available when enabled and the current user holds an allowed role.
     *
     * @return bool
     */
    public function is_available()
    {
        if ('yes' !== $this->enabled) {
            return false;
        }

        if (empty($this->allowed_roles)) {
            return false;
        }

        if (!is_user_logged_in()) {
            return false;
        }

        $user = wp_get_current_user();

        $user_roles = (array) $user->roles;

        if (!array_intersect($user_roles, $this->allowed_roles)) {
            return false;
        }

        // Hide Pay Later from customers whose account email still needs
        // verification (auth-popup plugin's "Email Verification" feature).
        if (class_exists('Auth_Popup_Email_Verification') && !Auth_Popup_Email_Verification::is_verified($user->ID, $user->user_email)) {
            return false;
        }

        return parent::is_available();
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ($order->get_total() > 0) {
            $status = $order->has_downloadable_item() ? 'on-hold' : 'processing';
            /**
             * Filter the order status used for Pay Later orders.
             *
             * @param string   $status Default status.
             * @param WC_Order $order  Order object.
             */
            $status = apply_filters('hpl_process_payment_order_status', $status, $order);
            $order->update_status($status, __('Pay Later order placed — payment to be collected later.', 'herlan-pay-later'));
        } else {
            $order->payment_complete();
        }

        if (WC()->cart) {
            WC()->cart->empty_cart();
        }

        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Output instructions on the order received page.
     */
    public function thankyou_page()
    {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }
    }

    /**
     * Add instructions to the customer order email.
     *
     * @param WC_Order $order
     * @param bool     $sent_to_admin
     * @param bool     $plain_text
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
        }
    }

    /**
     * Mark Pay Later orders completed once payment_complete() runs on them.
     *
     * @param string        $status
     * @param int           $order_id
     * @param WC_Order|bool $order
     * @return string
     */
    public function change_payment_complete_order_status($status, $order_id = 0, $order = false)
    {
        if ($order && $this->id === $order->get_payment_method()) {
            $status = 'completed';
        }
        return $status;
    }
}
