<?php

namespace Tablesome\Includes\Modules\Workflow\Traits;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\Workflow\Traits\Tablesome_Add_Row_Preprocessor')) {
    trait Tablesome_Add_Row_Preprocessor
    {

        public function preprocessing_event_params($event_params)
        {

            $event_params['fields_map'] = $this->match_columns_meta_compatiable_with_v0_8_5($event_params['fields_map']);

            // $this->log_event_params($event_params, 'before_wpforms_preprocessing');
            $event_params = $this->wpforms_preprocessing($event_params);
            // $this->log_event_params($event_params, 'after_wpforms_preprocessing');

            $event_params = $this->field_format_based_preprocessing($event_params);
            // $this->log_event_params($event_params, 'after_field_format_based_preprocessing');

            return $event_params;
        }

        private function log_event_params($event_params, $message = '')
        {

            error_log('--------' . $message . '---------------');
            error_log(' log_event_params event_params[fields_map]:' . print_r($event_params['fields_map'], true));
            error_log(' log_event_params event_params[source_data]:' . print_r($event_params['source_data'], true));
            error_log(' log_event_params event_params[table_meta][columns]:' . print_r($event_params['table_meta']['columns'], true));

        }

        /**
         * @version v0.8.6
         * Match Column Compatible v0.8.5
         * For adding the missing properties
         */
        private function match_columns_meta_compatiable_with_v0_8_5($fields_map)
        {
            foreach ($fields_map as $index => $fields_set) {

                $field_type_exists = isset($fields_set['field_type']) ? true : false;
                if (!$field_type_exists) {
                    $fields_map[$index]['field_type'] = 'trigger_source';
                }

                $column_id = isset($fields_set['column_id']) ? $fields_set['column_id'] : 0;
                $field_type = isset($fields_set['field_type']) ? $fields_set['field_type'] : '';
                $column_status_exists = isset($fields_set['column_status']) ? true : false;
                $detection_mode = isset($fields_set['detection_mode']) ? $fields_set['detection_mode'] : '';

                // Skip smart fields
                if ('tablesome_smart_fields' == $field_type) {
                    continue;
                }

                // change the detection mode to manual from auto
                if ("auto" == $detection_mode) {
                    $fields_map[$index]["detection_mode"] = "manual";
                }

                $column_status = 'pending';

                if ($column_id > 0) {
                    $column_status = 'published';
                }

                if ('trigger_source' == $fields_set['field_type'] && !$column_status_exists) {
                    $fields_map[$index]['column_status'] = $column_status;
                }
            }

            return $fields_map;
        }

        private function wpforms_preprocessing($event_params)
        {

            if ($event_params['integration'] != 'wpforms') {
                return $event_params;
            }

            // 1. Convert source data keys to int
            $event_params['source_data'] = $this->convert_array_keys_to_int($event_params['source_data']);

            // 2. Convert field_map's field_name to int
            foreach ($event_params['fields_map'] as $key => $field_set) {
                $field_type = isset($field_set['field_type']) ? $field_set['field_type'] : '';
                $field_set['field_name'] = isset($field_set['field_name']) ? (int) $field_set['field_name'] : '';
                $field_name = $field_set['field_name'];

                if ($field_type == 'tablesome_smart_fields' || !isset($field_set['field_name'])) {
                    continue;
                }

                // $field_set['field_name'] = (int) $field_set['field_name'];
                // $field_set['label'] = $event_params['source_data'][$field_name]['label'];

                $event_params['fields_map'][$key] = $field_set;
            }

            return $event_params;
        }

        public function convert_array_keys_to_int($input_array)
        {
            $keys = array_keys($input_array);
            $values = array_values($input_array);
            $int_keys = array_map('intval', $keys);
            $output_array = array_combine($int_keys, $values);

            return $output_array;
        }

        private function field_format_based_preprocessing($event_params)
        {
            $source_data = $event_params['source_data'];

            foreach ($source_data as $key => $field) {
                $field_type = isset($field['type']) ? $field['type'] : 'text';
                $column_type = $this->get_cell_format_by_field_type($field_type);
                $field['final_value'] = isset($field['value']) ? $field['value'] : '';

                if ($column_type == 'date') {
                    $field['final_value'] = isset($field['unix_timestamp']) ? $field['unix_timestamp'] : '';
                }

                $source_data[$key] = $field;
            }

            $event_params['source_data'] = $source_data;

            return $event_params;
        }

        private function field_format_based_preprocessing_old($event_params)
        {
            $source_data = $event_params['source_data'];
            foreach ($event_params['fields_map'] as $key => $field_set) {
                $column_id = isset($field_set['column_id']) ? intval($field_set['column_id']) : 0;
                $field_name = isset($field_set['field_name']) ? $field_set['field_name'] : '';

                $column_format = get_tablesome_cell_type($column_id, $event_params['table_columns']);

                $source_data[$field_name]['final_value'] = isset($source_data[$field_name]['value']) ? $source_data[$field_name]['value'] : '';
                if ($column_format == 'date') {
                    $source_data[$field_name]['final_value'] = isset($source_data[$field_name]['unix_timestamp']) ? $source_data[$field_name]['unix_timestamp'] : '';
                }

            } // end foreach

            $event_params['source_data'] = $source_data;

            return $event_params;
        }

    } // END CLASS
}
