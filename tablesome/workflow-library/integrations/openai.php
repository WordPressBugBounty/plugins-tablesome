<?php

namespace Tablesome\Workflow_Library\Integrations;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Integrations\OpenAi')) {
    class OpenAi
    {

        public $gdrive = null;

        public function __construct()
        {

        }

        public function get_config()
        {
            return array(
                'integration' => 'openai',
                'integration_label' => __('OpenAI', 'tablesome'),
                'is_active' => true,
                'is_premium' => false,
                'actions' => array(),
            );
        }

        public function get_folders()
        {
            return $this->gdrive->get_folders();
        }

        public function get_sub_folders($parent_folder_id)
        {
            return $this->gdrive->get_sub_folders($parent_folder_id);
        }

    }
}
