<?php

namespace Tablesome\Workflow_Library\Triggers;

use Tablesome\Includes\Modules\Workflow\Abstract_Trigger;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Triggers\Woo_Thankyou')) {
    class Woo_Thankyou extends Abstract_Trigger
    {

        public $trigger_source_id = 0;
        public $trigger_source_data = array();

        public function get_config()
        {
            $is_active = class_exists('WooCommerce') ? true : false;

            return array(
                'integration' => 'woocommerce',
                'integration_label' => __('Woocommerce', 'tablesome'),
                'trigger' => 'woocommerce_thankyou',
                'trigger_id' => 9,
                'trigger_label' => __('Woocommerce - Order Complete (thank you page)', 'tablesome'),
                'trigger_type' => 'woo_payment_complete',
                'is_active' => $is_active,
                'is_premium' => "no",
                'supported_actions' => [],
                'unsupported_actions' => [8, 9],
                'hooks' => array(
                    array(
                        'priority' => 10,
                        'accepted_args' => 1,
                        'name' => 'woocommerce_thankyou',
                        'callback_name' => 'trigger_callback',
                    ),
                ),
            );
        }

        public function trigger_callback($order_id)
        {
            $is_active = class_exists('WooCommerce') ? true : false;

            if (!$is_active) {
                error_log('WooCommerce is not active');
                return;
            }
            error_log('woocommerce_thankyou');

            /** @phpstan-ignore-next-line */
            $order = wc_get_order($order_id);
            // error_log("Order ID: " . $order_id);
            // error_log("Order:" . print_r($order, true));

            $submission_data = $this->get_formatted_posted_data($order, $order_id);

            error_log("Submission Data: " . print_r($submission_data, true));
            $this->trigger_source_id = $order_id;
            $this->trigger_source_data = array(
                'integration' => $this->get_config()['integration'],
                'data' => $submission_data,
            );

            $this->run_triggers($this, $this->trigger_source_data);

        }

        public function get_formatted_posted_data($order, $order_id)
        {
            $submission_data = array(
                'order_id' => [

                    'label' => 'Order ID',
                    'value' => $order_id,
                    'type' => 'number',
                ],
                'customer_id' => [

                    'label' => 'Customer ID',
                    'value' => $order->get_customer_id(),
                    'type' => 'number',
                ],
                'total' => [

                    'label' => 'Total',
                    'value' => $order->get_total(),
                    'type' => 'number',
                ],
                'status' => [

                    'label' => 'Status',
                    'value' => $order->get_status(),
                    'type' => 'text',
                ],
                'order_key' => [

                    'label' => 'Order Key',
                    'value' => $order->get_order_key(),
                    'type' => 'text',
                ],
                'payment_method' => [

                    'label' => 'Payment Method',
                    'value' => $order->get_payment_method(),
                    'type' => 'text',
                ],
                'billing_first_name' => [

                    'label' => 'Billing First Name',
                    'value' => $order->get_billing_first_name(),
                    'type' => 'text',
                ],
                'billing_last_name' => [

                    'label' => 'Billing Last Name',
                    'value' => $order->get_billing_last_name(),
                    'type' => 'text',
                ],
                'billing_company' => [

                    'label' => 'Billing Company',
                    'value' => $order->get_billing_company(),
                    'type' => 'text',
                ],
                'billing_address_1' => [

                    'label' => 'Billing Address 1',
                    'value' => $order->get_billing_address_1(),
                    'type' => 'text',
                ],
                'billing_address_2' => [

                    'label' => 'Billing Address 2',
                    'value' => $order->get_billing_address_2(),
                    'type' => 'text',
                ],
                'billing_city' => [

                    'label' => 'Billing City',
                    'value' => $order->get_billing_city(),
                    'type' => 'text',
                ],
                'billing_state' => [

                    'label' => 'Billing State',
                    'value' => $order->get_billing_state(),
                    'type' => 'text',
                ],
                'billing_postcode' => [

                    'label' => 'Billing Postcode',
                    'value' => $order->get_billing_postcode(),
                    'type' => 'text',
                ],
                'billing_country' => [

                    'label' => 'Billing Country',
                    'value' => $order->get_billing_country(),
                    'type' => 'text',
                ],
                'billing_email' => [

                    'label' => 'Billing Email',
                    'value' => $order->get_billing_email(),
                    'type' => 'text',
                ],
                'billing_phone' => [

                    'label' => 'Billing Phone',
                    'value' => $order->get_billing_phone(),
                    'type' => 'text',
                ],
                'shipping_first_name' => [

                    'label' => 'Shipping First Name',
                    'value' => $order->get_shipping_first_name(),
                    'type' => 'text',
                ],
                'shipping_last_name' => [

                    'label' => 'Shipping Last Name',
                    'value' => $order->get_shipping_last_name(),
                    'type' => 'text',
                ],
                'shipping_company' => [

                    'label' => 'Shipping Company',
                    'value' => $order->get_shipping_company(),
                    'type' => 'text',
                ],
                'shipping_address_1' => [

                    'label' => 'Shipping Address 1',
                    'value' => $order->get_shipping_address_1(),
                    'type' => 'text',
                ],
                'shipping_address_2' => [

                    'label' => 'Shipping Address 2',
                    'value' => $order->get_shipping_address_2(),
                    'type' => 'text',
                ],
                'shipping_city' => [

                    'label' => 'Shipping City',
                    'value' => $order->get_shipping_city(),
                    'type' => 'text',
                ],
                'shipping_state' => [

                    'label' => 'Shipping State',
                    'value' => $order->get_shipping_state(),
                    'type' => 'text',
                ],
                'shipping_postcode' => [

                    'label' => 'Shipping Postcode',
                    'value' => $order->get_shipping_postcode(),
                    'type' => 'text',
                ],
                'shipping_country' => [

                    'label' => 'Shipping Country',
                    'value' => $order->get_shipping_country(),
                    'type' => 'text',
                ],
                'shipping_method' => [

                    'label' => 'Shipping Method',
                    'value' => $order->get_shipping_method(),
                    'type' => 'text',
                ],
                'shipping_total' => [

                    'label' => 'Shipping Total',
                    'value' => $order->get_shipping_total(),
                    'type' => 'number',
                ],
                'cart_tax' => [

                    'label' => 'Cart Tax',
                    'value' => $order->get_cart_tax(),
                    'type' => 'number',
                ],
                'shipping_tax' => [

                    'label' => 'Shipping Tax',
                    'value' => $order->get_shipping_tax(),
                    'type' => 'number',
                ],
                'total_tax' => [

                    'label' => 'Total Tax',
                    'value' => $order->get_total_tax(),
                    'type' => 'number',
                ],
                'payment_method_title' => [

                    'label' => 'Payment Method Title',
                    'value' => $order->get_payment_method_title(),
                    'type' => 'text',
                ],
            );

            return $submission_data;

            // $submission_data = array(
            //     'order_id' => $order_id,
            //     'customer_id' => $order->get_customer_id(),
            //     'total' => $order->get_total(),
            //     'status' => $order->get_status(),
            //     'order_key' => $order->get_order_key(),
            //     'payment_method' => $order->get_payment_method(),
            //     'billing_first_name' => $order->get_billing_first_name(),
            //     'billing_last_name' => $order->get_billing_last_name(),
            //     'billing_company' => $order->get_billing_company(),
            //     'billing_address_1' => $order->get_billing_address_1(),
            //     'billing_address_2' => $order->get_billing_address_2(),
            //     'billing_city' => $order->get_billing_city(),
            //     'billing_state' => $order->get_billing_state(),
            //     'billing_postcode' => $order->get_billing_postcode(),
            //     'billing_country' => $order->get_billing_country(),
            //     'billing_email' => $order->get_billing_email(),
            //     'billing_phone' => $order->get_billing_phone(),
            //     'shipping_first_name' => $order->get_shipping_first_name(),
            //     'shipping_last_name' => $order->get_shipping_last_name(),
            //     'shipping_company' => $order->get_shipping_company(),
            //     'shipping_address_1' => $order->get_shipping_address_1(),
            //     'shipping_address_2' => $order->get_shipping_address_2(),
            //     'shipping_city' => $order->get_shipping_city(),
            //     'shipping_state' => $order->get_shipping_state(),
            //     'shipping_postcode' => $order->get_shipping_postcode(),
            //     'shipping_country' => $order->get_shipping_country(),
            //     'shipping_method' => $order->get_shipping_method(),
            //     'shipping_total' => $order->get_shipping_total(),
            //     'cart_tax' => $order->get_cart_tax(),
            //     'shipping_tax' => $order->get_shipping_tax(),
            //     'total_tax' => $order->get_total_tax(),
            //     'payment_method_title' => $order->get_payment_method_title(),
            // );
        }

        public function conditions($trigger_meta, $trigger_data)
        {
            return true;
        }

    } // End of class
}
