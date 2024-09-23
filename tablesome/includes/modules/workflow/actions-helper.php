<?php

namespace Tablesome\Includes\Modules\Workflow;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\Workflow\Actions_Helper')) {
    class Actions_Helper
    {
        public $library;

        public function __construct()
        {
            // error_log('Actions_Helper->__construct');
            $this->library = get_tablesome_workflow_library();
        }

        public function get_free_action_ids()
        {
            //  error_log('$actions : ' . print_r($this->library, true));
            $ids = array();

            if (empty($this->library->actions)) {
                return $ids;
            }

            foreach ($this->library->actions as $action_instance) {
                $config = $action_instance->get_config();
                if (false == $config['is_premium']) {
                    $ids[] = $config['id'];
                }
            }

            return $ids;
        }

        public function get_action_class_by_id($action_id)
        {
            $class = null;
            if (empty($action_id) || empty($this->library->actions)) {
                return $class;
            }

            foreach ($this->library->actions as $action_class) {
                $config = $action_class->get_config();
                if (isset($config['id']) && $config['id'] == $action_id) {
                    $class = $action_class;
                    break;
                }
            }
            return $class;
        }

    } // END CLASS
}
