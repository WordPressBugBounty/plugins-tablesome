<?php

namespace Tablesome\Includes\Modules\Workflow;

use Tablesome\Includes\Modules\Workflow\Traits\Placeholder;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
// Underscore (_example) property name consider as derived values.
if (!class_exists('\Tablesome\Includes\Modules\Workflow\Abstract_Trigger')) {

    /* Abstract class for all triggers */
    abstract class Abstract_Trigger
    {
        use Placeholder;

        public $is_premium = false;
        public $actions = [];
        public $store_all_entries;
        public $workflows;
        public $actions_helper;

        public function __construct()
        {
            $this->is_premium = can_use_tablesome_premium();
            $this->store_all_entries = new \Tablesome\Workflow_Library\Actions\Store_All_Forms_Entries();
            $this->workflows = new \Tablesome\Includes\Modules\Workflow\Workflows();
            $this->actions_helper = new \Tablesome\Includes\Modules\Workflow\Actions_Helper();
        }

        public function init($actions)
        {
            $this->actions = $actions;
        }

        public function run_triggers($trigger_class, $trigger_source_data)
        {
            global $workflow_redirection_data, $workflow_data;
            $workflows = $this->workflows->get_workflows_with_trigger($trigger_class, $trigger_source_data);
            // $trigger_instances = $this->get_trigger_instances($trigger_class, $trigger_source_data);

            if (empty($workflows)) {
                return;
            }

            // delete redirection data in DB before loop the trigger instances
            delete_option('workflow_redirection_data');

            // TODO: Will be removed in the future. It Will be replaced with the $workflow_data variable.
            $placeholders = $this->getPlaceholders($trigger_source_data);

            // error_log('run_triggers() $placeholders : ' . print_r($placeholders, true));

            foreach ($workflows as $workflow) {
                $workflow->run();

            }

            // store the redirection data in DB if any redirection action has configured
            if (isset($workflow_redirection_data) && count($workflow_redirection_data) > 0) {
                update_option('workflow_redirection_data', $workflow_redirection_data);
            }

        }

        public function can_access_trigger($trigger_class)
        {
            if ($this->is_premium) {
                return true;
            }
            return ("no" == $trigger_class->get_config()['is_premium']);
        }

        public function getPlaceholders($triggerSourceData)
        {
            $smartFieldValues = get_tablesome_smart_field_values();
            $smartFieldsPlaceholders = $this->getPlaceholdersFromKeyValues($smartFieldValues);
            $triggerDataPlaceholders = $this->getPlaceholdersFromTriggerSourceData($triggerSourceData);
            return array_merge($smartFieldsPlaceholders, $triggerDataPlaceholders);
        }

    } // END class
}
