<?php

namespace Tablesome\Includes\Modules\Workflow;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
// Underscore (_example) property name consider as derived values.
if (!class_exists('\Tablesome\Includes\Modules\Workflow\Trigger')) {

    /**
     * Class Trigger
     * @package Tablesome\Includes\Modules\Workflow
     */

    class Trigger
    {

        public $id;
        public $name;
        public $is_premium = false;

        public function __construct($id, $name)
        {
            $this->id = $id;
            $this->name = $name;
        }

        // public function get_instances($trigger_class, $trigger_source_data)
        // {
        //     $trigger_instances = [];
        //     $tables = $this->get_tables();
        //     if (!isset($tables) || empty($tables)) {
        //         return $trigger_instances;
        //     }

        //     foreach ($tables as $table) {

        //         $triggers_meta = get_tablesome_table_triggers($table->ID);

        //         if (!isset($triggers_meta) || empty($triggers_meta)) {
        //             continue;
        //         }
        //         // Free trigger for free user (Can access 1 trigger per table)
        //         $free_trigger_applied = false;

        //         foreach ($triggers_meta as $trigger_position => $trigger_meta) {

        //             if (true == ($free_trigger_applied && !$this->is_premium)) {
        //                 continue;
        //             }

        //             /**
        //              * Free plan user can access the 1 trigger and 3 actions per table
        //              */
        //             if (!$free_trigger_applied && !$this->is_premium && "no" == $trigger_class->get_config()['is_premium']) {
        //                 $free_trigger_applied = true;
        //             }

        //             $trigger_id = isset($trigger_meta['trigger_id']) ? $trigger_meta['trigger_id'] : 0;

        //             $trigger_does_not_have_instance = !$trigger_class->conditions($trigger_meta, $trigger_source_data);
        //             $not_instance_of_current_trigger = $trigger_class->get_config()['trigger_id'] != $trigger_id;
        //             $trigger_does_not_have_actions = !isset($trigger_meta['actions']) || empty($trigger_meta['actions']);
        //             $trigger_is_not_active = !isset($trigger_meta['status']) || $trigger_meta['status'] != 1;
        //             $trigger_does_not_have_permission = !$this->can_access_trigger($trigger_class);

        //             $trigger_is_not_valid = $trigger_does_not_have_instance
        //                 || $not_instance_of_current_trigger
        //                 || $trigger_does_not_have_actions
        //                 || $trigger_is_not_active
        //                 || $trigger_does_not_have_permission;

        //             if ($trigger_is_not_valid) {
        //                 continue;
        //             }

        //             $trigger_instances[] = array(
        //                 'trigger_meta' => $trigger_meta,
        //                 'table_id' => $table->ID,
        //                 'trigger_position' => $trigger_position,
        //                 'trigger_data' => $trigger_source_data,
        //             );
        //         }
        //     }

        //     return $trigger_instances;
        // }
    } // End of class Trigger

}
