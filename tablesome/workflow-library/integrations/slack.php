<?php

namespace Tablesome\Workflow_Library\Integrations;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Integrations\Slack')) {
    class Slack
    {
        public $slack_api;
        public function __construct()
        {
            $this->slack_api = new \Tablesome\Workflow_Library\External_Apis\Slack();
        }

        public function get_config()
        {
            return array(
                'integration' => 'slack',
                'integration_label' => __('Slack', 'tablesome'),
                'is_active' => $this->slack_api->is_active(),
                'is_premium' => true,
                'actions' => array(),
            );
        }
    }
}
