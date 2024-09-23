<?php

namespace Tablesome\Workflow_Library\Integrations;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Integrations\Tablesome')) {
    class Tablesome
    {

        public function get_config()
        {
            return array(
                'integration' => 'tablesome',
                'integration_label' => __('Tablesome', 'tablesome'),
                'is_active' => true,
                'is_premium' => false,
                'actions' => array(),
            );
        }

    }
}
