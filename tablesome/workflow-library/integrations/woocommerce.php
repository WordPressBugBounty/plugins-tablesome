<?php

namespace Tablesome\Workflow_Library\Integrations;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Integrations\Woocommerce')) {
    class Woocommerce
    {

        public function get_config()
        {
            $is_active = class_exists('WooCommerce') ? true : false;

            return array(
                'integration' => 'woocommerce',
                'integration_label' => __('Woocommerce', 'tablesome'),
                'is_active' => $is_active,
                'is_premium' => false,
                'actions' => array(),
            );
        }

    }
}
