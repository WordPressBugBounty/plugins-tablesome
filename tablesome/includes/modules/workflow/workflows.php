<?php

namespace Tablesome\Includes\Modules\Workflow;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\Workflow\Workflows')) {
    class Workflows
    {
        public $tables;
        public $store_all_entries;
        public $is_premium_user = false;

        public function __construct()
        {
            $this->is_premium_user = can_use_tablesome_premium();
            $this->tables = new \Tablesome\Includes\Modules\Tables\Tables();
            $this->store_all_entries = new \Tablesome\Workflow_Library\Actions\Store_All_Forms_Entries();
        }

        public function is_trigger_configured_somewhere($trigger_class)
        {
            // error_log('is_trigger_configured_somewhere');
            $is_trigger_configured = false;
            $workflow_instances = [];

            $tables = $this->tables->get_tables();
            if (!isset($tables) || empty($tables)) {
                return $workflow_instances;
            }

            foreach ($tables as $table) {

                $triggers_meta = get_tablesome_table_triggers($table->ID);

                if (!isset($triggers_meta) || empty($triggers_meta)) {
                    continue;
                }

                // $free_trigger_applied = false;

                foreach ($triggers_meta as $trigger_position => $trigger_meta) {
                    $trigger_props = [
                        'trigger_meta' => $trigger_meta,
                        'table_id' => $table->ID,
                        'trigger_position' => $trigger_position,
                        'trigger_class' => $trigger_class,
                        'trigger_source_data' => [],
                    ];

                    // $trigger_id = isset($trigger_meta['trigger_id']) ? $trigger_meta['trigger_id'] : 0;

                    $is_trigger_valid = $this->is_trigger_valid($trigger_props);

                    if (!$is_trigger_valid) {
                        continue;
                    }

                    $is_trigger_configured = true;

                }

            }

            return $is_trigger_configured;

        }

        public function get_workflows_with_trigger($trigger_class, $trigger_source_data)
        {
            $this->store_all_entries->init($trigger_class, $trigger_source_data);

            $workflow_instances = [];
            $tables = $this->tables->get_tables();
            if (!isset($tables) || empty($tables)) {
                return $workflow_instances;
            }

            foreach ($tables as $table) {

                $triggers_meta = get_tablesome_table_triggers($table->ID);

                if (!isset($triggers_meta) || empty($triggers_meta)) {
                    continue;
                }

                // Free trigger for free user (Can access 1 trigger per table)
                $free_trigger_applied = false;

                foreach ($triggers_meta as $trigger_position => $trigger_meta) {
                    $trigger_props = [
                        'trigger_meta' => $trigger_meta,
                        'table_id' => $table->ID,
                        'trigger_position' => $trigger_position,
                        'trigger_source_data' => $trigger_source_data,
                        'trigger_class' => $trigger_class,
                    ];

                    if (true == ($free_trigger_applied && !$this->is_premium_user)) {
                        continue;
                    }

                    /**
                     * Free plan user can access the 1 trigger and 3 actions per table
                     */
                    if (!$free_trigger_applied && !$this->is_premium_user && "no" == $trigger_class->get_config()['is_premium']) {
                        $free_trigger_applied = true;
                    }

                    $trigger_id = isset($trigger_meta['trigger_id']) ? $trigger_meta['trigger_id'] : 0;

                    $is_trigger_valid = $this->is_trigger_valid($trigger_props);

                    if (!$is_trigger_valid) {
                        continue;
                    }

                    $workflow_instance = new \Tablesome\Includes\Modules\Workflow\Workflow_Instance($trigger_props);
                    $workflow_instances[] = $workflow_instance;

                }
            }

            return $workflow_instances;
        }

        public function is_trigger_valid($trigger_props)
        {
            $trigger_class = $trigger_props['trigger_class'];
            $trigger_source_data = $trigger_props['trigger_source_data'];
            $trigger_meta = $trigger_props['trigger_meta'];
            $trigger_id = isset($trigger_meta['trigger_id']) ? $trigger_meta['trigger_id'] : 0;

            $trigger_does_not_have_instance = !$trigger_class->conditions($trigger_meta, $trigger_source_data);
            $not_instance_of_current_trigger = $trigger_class->get_config()['trigger_id'] != $trigger_id;
            $trigger_does_not_have_actions = !isset($trigger_meta['actions']) || empty($trigger_meta['actions']);
            $trigger_is_not_active = !isset($trigger_meta['status']) || $trigger_meta['status'] != 1;
            $trigger_does_not_have_permission = !$this->can_access_trigger($trigger_class);

            $trigger_is_not_valid = $trigger_does_not_have_instance
                || $not_instance_of_current_trigger
                || $trigger_does_not_have_actions
                || $trigger_is_not_active
                || $trigger_does_not_have_permission;

            return !$trigger_is_not_valid;
        }

        public function can_access_trigger($trigger_class)
        {
            if ($this->is_premium_user) {
                return true;
            }

            $is_premium_trigger = isset($trigger_class->get_config()['is_premium']) ? $trigger_class->get_config()['is_premium'] : "no";

            if ("no" == $is_premium_trigger) {
                return true;
            }

            return false;
        }

    } // END CLASS
}
