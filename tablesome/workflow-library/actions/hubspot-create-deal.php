<?php

namespace Tablesome\Workflow_Library\Actions;

use Tablesome\Includes\Modules\Workflow\Action;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\Tablesome\Workflow_Library\Actions\Hubspot_Create_Deal')) {
    class Hubspot_Create_Deal extends Action
    {
        public $hubspot_api;
        public $action_meta;
        public $trigger_class;
        public $trigger_instance;
        public $smart_field_values;
        public $trigger_source_data;

        public function __construct()
        {
            $this->hubspot_api = new \Tablesome\Workflow_Library\External_Apis\Hubspot();
        }

        public function get_config()
        {
            return [
                'id'          => 19,
                'name'        => 'hubspot_create_deal',
                'label'       => __('Create Deal', 'tablesome'),
                'integration' => 'hubspot',
                'is_premium'  => true,
            ];
        }

        public function do_action($trigger_class, $trigger_instance)
        {
            error_log('*** Hubspot Create Deal Action Called ***');
            $this->bind_props($trigger_class, $trigger_instance);

            $deal_properties = $this->get_deal_properties();

            if (empty($deal_properties)) {
                error_log('Hubspot Create Deal: no deal properties configured, skipping.');
                return;
            }

            $contact_id = $this->get_contact_id_from_previous_action();
            $require_contact = !empty($this->action_meta['require_contact_association']);

            if ($require_contact && empty($contact_id)) {
                error_log('Hubspot Create Deal: required contact association missing, skipping deal creation.');
                return;
            }

            $result = $this->hubspot_api->create_deal(
                $deal_properties,
                ['contact_id' => $contact_id]
            );

            // Emit deal_id into workflow_data for any downstream action
            if (isset($result['status']) && $result['status'] === 'success' && !empty($result['deal_id'])) {
                $trigger_instance_id = isset($this->trigger_instance['_trigger_instance_id'])
                    ? $this->trigger_instance['_trigger_instance_id']
                    : null;
                if ($trigger_instance_id) {
                    global $workflow_data;
                    if (!isset($workflow_data[$trigger_instance_id]) || !is_array($workflow_data[$trigger_instance_id])) {
                        $workflow_data[$trigger_instance_id] = [];
                    }
                    $workflow_data[$trigger_instance_id]['hubspot_deal_id'] = (string) $result['deal_id'];
                }
            }
        }

        /**
         * Merge static deal_properties from the action config with any
         * map_fields that target deal properties via form submission data.
         */
        public function get_deal_properties()
        {
            $properties = [];

            if (!empty($this->action_meta['deal_properties']) && is_array($this->action_meta['deal_properties'])) {
                $properties = $this->action_meta['deal_properties'];
            }

            if (!empty($this->action_meta['map_fields']) && is_array($this->action_meta['map_fields'])) {
                foreach ($this->action_meta['map_fields'] as $field) {
                    if (empty($field['destination_field']['id']) || empty($field['source_field']['id'])) {
                        continue;
                    }
                    $source = [
                        'id'          => $field['source_field']['id'],
                        'object_type' => isset($field['source_field']['object_type']) ? $field['source_field']['object_type'] : '',
                        'value'       => isset($field['destination_field']['value']) ? $field['destination_field']['value'] : '',
                    ];
                    $value = $this->get_value($source);
                    if ($value === '' || $value === null) {
                        continue;
                    }
                    $properties[$field['destination_field']['id']] = $value;
                }
            }

            return $properties;
        }

        /**
         * Read the contact id written by a preceding hubspot_add_contact
         * action into the workflow_data global.
         */
        public function get_contact_id_from_previous_action()
        {
            $associate = isset($this->action_meta['associate_with_contact_from_previous_action'])
                ? (bool) $this->action_meta['associate_with_contact_from_previous_action']
                : true;

            if (!$associate) {
                return '';
            }

            $trigger_instance_id = isset($this->trigger_instance['_trigger_instance_id'])
                ? $this->trigger_instance['_trigger_instance_id']
                : null;

            if (!$trigger_instance_id) {
                return '';
            }

            global $workflow_data;
            if (!empty($workflow_data[$trigger_instance_id]['hubspot_contact_id'])) {
                return (string) $workflow_data[$trigger_instance_id]['hubspot_contact_id'];
            }
            return '';
        }

        private function get_value($source)
        {
            $value = '';
            if ($source['object_type'] === 'trigger_source') {
                $value = isset($this->trigger_source_data[$source['id']]['value'])
                    ? $this->trigger_source_data[$source['id']]['value']
                    : $value;
            } elseif ($source['object_type'] === 'trigger_smart_fields') {
                $value = isset($this->smart_field_values[$source['id']]) ? $this->smart_field_values[$source['id']] : '';
            } else {
                $value = $source['value'];
            }
            return $value;
        }

        private function bind_props($trigger_class, $trigger_instance)
        {
            $this->trigger_class       = $trigger_class;
            $this->trigger_instance    = $trigger_instance;
            $this->trigger_source_data = isset($trigger_class->trigger_source_data['data'])
                ? $trigger_class->trigger_source_data['data']
                : [];
            $this->action_meta         = isset($trigger_instance['action_meta']) ? $trigger_instance['action_meta'] : [];
            $this->smart_field_values  = function_exists('get_tablesome_smart_field_values')
                ? get_tablesome_smart_field_values()
                : [];
        }
    }
}
