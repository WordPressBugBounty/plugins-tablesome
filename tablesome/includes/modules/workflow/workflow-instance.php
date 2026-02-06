<?php

namespace Tablesome\Includes\Modules\Workflow;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\Workflow\Workflow_Instance')) {
    class Workflow_Instance
    {

        public $trigger_id = 0;
        public $integration = '';
        public $form_id = 0;
        public $actions = [];
        public $status = 'active';
        public $table_id = 0;
        public $trigger_position = 0;
        public $trigger_source_data = [];
        public $id = '';
        public $actions_helper;
        public $workflows;
        public $trigger;

        /**
         * @param array $props
         * @param array $props['trigger_id']
         *
         */

        public function __construct($props)
        {
            // error_log('$props : ' . print_r($props, true));
            $this->actions = $props['trigger_meta']['actions'];
            $this->status = $props['trigger_meta']['status'];
            $this->trigger_id = $props['trigger_meta']['trigger_id'];
            // $this->integration = $props['integration'];
            $this->form_id = isset($props['trigger_meta']['form_id']) ? $props['trigger_meta']['form_id'] : 0;
            $this->table_id = $props['table_id'];
            $this->trigger_position = $props['trigger_position'];
            $this->trigger_source_data = $props['trigger_source_data'];
            $this->id = $trigger_instance_id = md5(uniqid(rand(), true));
            $this->actions_helper = new \Tablesome\Includes\Modules\Workflow\Actions_Helper();
            $this->workflows = new \Tablesome\Includes\Modules\Workflow\Workflows();
            $this->trigger = $props['trigger_class'];
        }

        public function run()
        {
            global $workflow_redirection_data, $workflow_data;

            $workflow_data[$this->id] = $this->get_workflow_data($this->trigger_source_data);

            $trigger_instance['_trigger_instance_id'] = $this->id;
            $trigger_instance['table_id'] = $this->table_id;
            $trigger_instance['trigger_position'] = $this->trigger_position;

            $configured_free_action_positions = $this->get_configured_free_action_positions($this->actions);
            global $tablesome_workflow_data;
            $tablesome_workflow_data = [];

            $placeholders = $this->trigger->getPlaceholders($this->trigger_source_data);

            foreach ($this->actions as $action_position => $action) {
                /** Appends the action position and the action meta in $trigger_data array */
                $trigger_instance['action_position'] = $action_position;
                $trigger_instance['action_meta'] = $action;
                $trigger_instance['_placeholders'] = $placeholders;

                $action_id = isset($action['action_id']) ? intval($action['action_id']) : 0;

                // Skip disabled actions
                if (isset($action['status']) && !$action['status']) {
                    continue;
                }

                $action_class = $this->actions_helper->get_action_class_by_id($action_id, $this->actions);

                // Free plan users only have an access to access the free actions.
                $can_access_the_action = ($this->workflows->is_premium_user || !$this->workflows->is_premium_user && in_array($action_position, $configured_free_action_positions)) ? true : false;

                // error_log('$action_class : ' . $action_class);
                if (empty($action_class) || is_null($action_class) || !$can_access_the_action) {
                    continue;
                }

                if ($action_class->conditions($trigger_instance, $action)) {
                    $action_class->do_action($this->trigger, $trigger_instance);
                }
            }
        }

        public function get_workflow_data($trigger_source_data)
        {
            $integration_name = isset($trigger_source_data['integration']) ? $trigger_source_data['integration'] : '';
            $smart_field_values = get_tablesome_smart_field_values();
            $trigger_data = [];

            $data = isset($trigger_source_data['data']) ? $trigger_source_data['data'] : [];

            // error_log('get_workflow_data trigger_source_data:' . print_r($trigger_source_data, true));
            foreach ($data as $field_name => $field_data) {
                $trigger_data[$field_name] = isset($field_data['value']) ? $field_data['value'] : '';
                $is_file = isset($field_data['value']) && isset($field_data["type"]) && $field_data["type"] == "file";

                // error_log('get_workflow_data is_file:' . $is_file);

                if ($is_file) {
                    $trigger_data[$field_name] = isset($field_data['value']) && !empty($field_data['value']) ? wp_get_attachment_link($field_data["value"]) : "";

                    if ($integration_name == 'forminator') {
                        $trigger_data[$field_name] = !empty($field_data['file_url']) ? $field_data["file_url"] : "";
                    }
                    // Forminator - File condition
                }
            }

            // Do not use array_merge() because it will re-index the numerical keys.
            return $smart_field_values + $trigger_data;

            // return array_merge($smart_field_values, $trigger_data);
        }

        public function get_configured_free_action_positions($configured_actions)
        {
            $free_actions_ids = $this->actions_helper->get_free_action_ids($this->actions);
            $positions = [];
            foreach ($configured_actions as $position => $action_meta) {
                $action_id = isset($action_meta['action_id']) ? $action_meta['action_id'] : 0;
                if (in_array($action_id, $free_actions_ids) && count($positions) < 3) {
                    $positions[] = $position;
                }
            }
            return $positions;
        }

    } // END CLASS
}
