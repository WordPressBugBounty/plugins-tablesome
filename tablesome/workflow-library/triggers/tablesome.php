<?php

namespace Tablesome\Workflow_Library\Triggers;

use Tablesome\Includes\Modules\Workflow\Abstract_Trigger;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Triggers\Tablesome')) {
    class Tablesome extends Abstract_Trigger
    {

        public function get_config()
        {

            return array(
                'integration' => 'tablesome',
                'integration_label' => __('Tablesome', 'tablesome'),
                'trigger' => 'tablesome_on_table_load',
                'trigger_id' => 5,
                'trigger_label' => __('On Table Load', 'tablesome'),
                'trigger_type' => 'on_table_load',
                'is_active' => true,
                'is_premium' => "yes",
                'supported_actions' => [8, 9, 17],
                'unsupported_actions' => []
            );
        }

        public function get_post_fields()
        {
            return array();
        }

    }
}
