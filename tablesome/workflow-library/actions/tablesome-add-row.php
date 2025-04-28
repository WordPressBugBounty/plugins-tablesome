<?php

namespace Tablesome\Workflow_Library\Actions;

use Tablesome\Includes\Modules\Workflow\Action;
use Tablesome\Includes\Modules\Workflow\Traits\Cell_Format;
use Tablesome\Includes\Modules\Workflow\Traits\Tablesome_Add_Row_Preprocessor;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Actions\Tablesome_Add_Row')) {
    class Tablesome_Add_Row extends Action
    {
        use Cell_Format;
        use Tablesome_Add_Row_Preprocessor;
        public $match_columns = [];
        public $new_last_column_id;
        public $table_meta_needs_update = false;
        public $last_column_id;
        public $exception_table_id = 254;
        public $datatable;
        public $myque;

        public function __construct()
        {
            $this->myque = new \Tablesome\Includes\Modules\Myque\Myque();
            $this->datatable = new \Tablesome\Includes\Modules\Datatable\Datatable();
        }
        public function get_config()
        {
            return array(
                'id' => 1,
                'name' => 'add_row',
                'label' => __('Add / Update Row', 'tablesome'),
                'integration' => 'tablesome',
                'is_premium' => false,
            );
        }

        public function do_action($trigger_event, $trigger_config)
        {
            $event_params = $this->get_params_for_action($trigger_event, $trigger_config);

            // error_log('tablesome-add-row->do_action() $event_params:' . print_r($event_params, true));

            // if ($event_params['integration'] != 'wpforms') {
            //     return $event_params;
            // }

            // error_log('after get_params_for_action event_params[fields_map]:' . print_r($event_params['fields_map'], true));
            // error_log('after get_params_for_action event_params[source_data]:' . print_r($event_params['source_data'], true));

            if ($this->is_valid_event($event_params) === false) {
                return;
            }

            $event_params = $this->update_source_data($event_params);
            // Should come after update_source_data()
            $event_params = $this->update_fields_map($event_params);

            // error_log('after update_source_data event_params[fields_map]:' . print_r($event_params['fields_map'], true));
            // error_log('after update_source_data event_params[source_data]:' . print_r($event_params['source_data'], true));

            // 1. Add Table Columns (if needed)
            $event_params = $this->update_table_columns($event_params);

            // 3. Update Table Meta
            $event_params = $this->update_postmeta($event_params);

            // error_log('after update_postmeta event_params[fields_map]:' . print_r($event_params['fields_map'], true));
            // error_log('after update_postmeta event_params[source_data]:' . print_r($event_params['source_data'], true));
            // error_log('after update_postmeta event_params[table_meta][columns]:' . print_r($event_params['table_meta']['columns'], true));

            // 2. Add Table Rows
            $row_values = $this->get_row_values($event_params);

            // error_log('row_values: ' . print_r($row_values, true));
            $result = $this->check_and_insert_table_row($row_values, $event_params);

            // error_log('result: ' . print_r($result, true));

            // 3. Update Table Meta
            // $this->update_table_meta();

            // 4. Update Triggers Meta
            $this->update_triggers_meta($event_params);

            // add_action('tablesome_after_action', array($this, 'after_add_row_example'), 10, 2);
            do_action('tablesome_after_action', $result, 'tablesome_add_row');

            return $result;
        }

        // public function after_add_row_example($result, $action_name)
        // {

        //     if ($action_name != 'tablesome_add_row') {
        //         return;
        //     }

        //     error_log('after_add_row');

        //     error_log('after_add_row $result: ' . print_r($result, true));
        // }

        private function get_row_values($event_params)
        {
            // error_log('get_row_values');

            $source_data = $event_params['source_data'];
            $fields_map = $event_params['fields_map'];

            // error_log('$source_data:' . print_r($source_data, true));
            // error_log('$fields_map:' . print_r($fields_map, true));

            $row_values = array();

            if (empty($fields_map)) {
                return $row_values;
            }

            // error_log('$fields_map:' . print_r($fields_map, true));

            foreach ($fields_map as $field_set) {

                $column_id = isset($field_set['column_id']) ? intval($field_set['column_id']) : 0;
                $detection_mode = isset($field_set['detection_mode']) ? $field_set['detection_mode'] : '';
                $field_name = isset($field_set['field_name']) ? strval($field_set['field_name']) : '';

                $invalid_field_name = empty($field_name) && $field_name !== '0';

                // error_log('get_row_values - $field_name:' . $field_name);
                // error_log('get_row_values - $invalid_field_name:' . $invalid_field_name);

                // Skip disabled fields
                if ('disabled' == $detection_mode || $invalid_field_name || empty($column_id)) {
                    continue;
                }

                $column_format = $this->get_column_type($field_name, $event_params);

                // error_log('$column_format:' . $column_format);
                // error_log('$field_set:' . print_r($field_set, true));
                // error_log('$source_data:' . print_r($source_data[$field_name], true));

                $db_column_name = "column_{$column_id}";
                $field_data = isset($source_data[$field_name]) ? $source_data[$field_name] : array();
                $field_value = isset($field_data['final_value']) ? $field_data['final_value'] : '';

                //  error_log('get_row_values() $field_name:' . $field_name);
                // error_log('get_row_values() $field_value:' . $field_value);
                if ($column_format == 'text') {
                    $field_value = (string) $field_value;
                }

                // Special case - wpforms date-time field with 'time' format (field option in wpforms)
                if ($column_format == 'text' && isset($field_data['type']) && $field_data['type'] == 'date-time') {
                    $field_value = (string) $field_data['value'];
                }

                $row_values[$db_column_name] = $field_value;
                // error_log('get_row_values() $column_format:' . $column_format);

                if ($column_format == 'file' || $column_format == 'url' || $column_format == 'button') {
                    $row_values = $this->store_meta_values($row_values, $field_data, $db_column_name);
                }

            }

            // error_log('$row_values: ' . print_r($row_values, true));
            return $row_values;
        }

        private function store_meta_values($row_values, $field_data, $db_column_name)
        {

            // error_log('store_meta_values field_data: ' . print_r($field_data, true));
            // if (empty($meta_values)) {
            //     return;
            // }
            $meta_values = [];

            foreach ($field_data as $key => $meta_value) {
                if ($key == 'final_value' || $key == 'value') {
                    continue;
                }
                $meta_values[$key] = $meta_value;
            }

            $row_values[$db_column_name . '_meta'] = wp_json_encode($meta_values);

            // error_log('store_meta_values row_values: ' . print_r($row_values, true));

            return $row_values;
        }

        private function check_and_insert_table_row($row_values, $event_params)
        {
            if (empty($row_values)) {
                return false;
            }

            // error_log('check_and_insert_table_row');
            $tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
            $db_table = $tablesome_db->create_table_instance($event_params['table_id'], $event_params['table_meta']);

            $query = $tablesome_db->query(array(
                'table_id' => $event_params['table_id'],
                'table_name' => $db_table->name,
            ));
            $default_record_values = $this->get_record_default($event_params['table_id']);
            $insert_record_data = array_merge(array(), $default_record_values, $row_values);

            $conditional_args = $this->get_conditional_args($event_params['action_meta']);

            // Check if we need to update on duplicate
            $on_duplicate = isset($event_params['action_meta']['on_duplicate']) ? $event_params['action_meta']['on_duplicate'] : 'skip';
            
            // error_log('event_params: ' . print_r($event_params, true));
            // error_log('enable_duplication_prevention: ' . $conditional_args['enable_duplication_prevention']);
            // error_log('on_duplicate: ' . $on_duplicate);
            // If duplication prevention is enabled and on_duplicate is set to "update"
            // if ($conditional_args['enable_duplication_prevention'] && $on_duplicate === 'update') {
            //     // Get the existing record ID based on the duplicate criteria field
            //     $record_id = $this->get_existing_record_id($event_params, $row_values);
                
            //     // error_log('get_existing_record_id() record_id: ' . $record_id);
            //     // If a duplicate record is found, update it instead of inserting
            //     if (!empty($record_id)) {
            //         return $this->update_existing_record($record_id, $row_values, $event_params, $db_table);
            //     }
            // }

            error_log('insert_record_data: ' . print_r($insert_record_data, true));

            $this->insert_missing_columns($insert_record_data, $db_table->name);
            // error_log('query: ' . print_r($insert_record_data, true));
            $result = $this->datatable->record->insert($query, $insert_record_data, $conditional_args);
            return $result;
        }

        private function get_existing_record_id($event_params, $row_values)
        {
            $action_meta = $event_params['action_meta'];
            $prevent_field_column = $this->get_prevent_field_column($action_meta);

            $row_values['author_id'] = get_current_user_id();
                
            // error_log('get_existing_record_id()prevent_field_column: ' . $prevent_field_column);
            // error_log('get_existing_record_id()row_values: ' . print_r($row_values, true));

            if (empty($prevent_field_column) || !isset($row_values[$prevent_field_column])) {
                return false;
            }
            
            global $wpdb;
            $table_id = $event_params['table_id'];
            $table_name = $wpdb->prefix . 'tablesome_table_' . $table_id;
            
            $field_value = esc_sql($row_values[$prevent_field_column]);

            // error_log('get_existing_record_id() field_value: ' . $field_value);
           
            // error_log('get_existing_record_id()table_name: ' . $table_name);
            $query = "SELECT id FROM $table_name WHERE $prevent_field_column = '$field_value' LIMIT 1";
            
            return $wpdb->get_var($query);
        }

        private function update_existing_record($record_id, $row_values, $event_params, $db_table)
        {
            if (empty($record_id)) {
                return false;
            }

            // Add default values for updated_at and updated_by
            $row_values['updated_at'] = current_time('mysql', true);
            $row_values['updated_by'] = get_current_user_id();

            // Update the record
            global $wpdb;
            $table_name = $wpdb->prefix . $db_table->name;
            
            // Prepare the SET part of the query
            $set_parts = [];
            foreach ($row_values as $column => $value) {
                $escaped_value = esc_sql($value);
                $set_parts[] = "`$column` = '$escaped_value'";
            }
            
            $set_clause = implode(', ', $set_parts);
            
            // Build and execute the UPDATE query
            $query = "UPDATE $table_name SET $set_clause WHERE id = $record_id";
            $result = $wpdb->query($query);
            
            return $result ? ['record_id' => $record_id, 'status' => 'updated'] : false;
        }

        private function insert_missing_columns($insert_record_data, $table_name)
        {
            global $wpdb;
            $table_name = $wpdb->prefix . $table_name;
            $this->myque->check_and_add_missing_columns($insert_record_data, $table_name);
        }

        public function get_record_default($table_id)
        {
            global $globalCurrentUserID;
            return array(
                'post_id' => $table_id,
                'author_id' => $globalCurrentUserID,
                'updated_by' => $globalCurrentUserID,
                'created_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
                'rank_order' => '',
            );
        }

        private function get_conditional_args($action_meta)
        {
            $prevent_field_column = $this->get_prevent_field_column($action_meta);
            $enable_submission_limit = isset($action_meta['enable_submission_limit']) ? $action_meta['enable_submission_limit'] : false;
            $enable_submission_limit = $enable_submission_limit && tablesome_fs()->can_use_premium_code__premium_only();
            $on_duplicate = isset($action_meta['on_duplicate']) ? $action_meta['on_duplicate'] : 'skip';
            $on_duplicate =  tablesome_fs()->can_use_premium_code__premium_only() ? $on_duplicate : 'skip';

            return array(
                'enable_duplication_prevention' => isset($action_meta['enable_duplication_prevention']) ? $action_meta['enable_duplication_prevention'] : false,
                'enable_submission_limit' => $enable_submission_limit,
                'max_allowed_submissions' => isset($action_meta['max_allowed_submissions']) ? $action_meta['max_allowed_submissions'] : false,
                'prevent_field_column' => $prevent_field_column,
                'on_duplicate' => isset($action_meta['on_duplicate']) ? $action_meta['on_duplicate'] : '',
            );
        }

        private function get_prevent_field_column($action_meta)
        {
            $duplicate_criteria_field = isset($action_meta['duplicate_criteria_field']) ? $action_meta['duplicate_criteria_field'] : "";
            $match_columns = isset($action_meta['match_columns']) ? $action_meta['match_columns'] : [];
            if (empty($duplicate_criteria_field)) {
                return '';
            }

            // If non table column fields
            if ($duplicate_criteria_field == 'created_at_datetime') {
                return 'created_at';
            } else if ($duplicate_criteria_field == 'created_by') {
                return 'author_id';
            }

            $column_name = '';
            foreach ($match_columns as $match_column) {
                $field_name = isset($match_column['field_name']) ? $match_column['field_name'] : '';
                $column_id = isset($match_column['column_id']) ? $match_column['column_id'] : '';
                if ($field_name == $duplicate_criteria_field && !empty($column_id) && is_numeric($column_id)) {
                    $column_name = "column_" . $column_id;
                    break;
                }
            }

            return $column_name;
        }

        private function get_params_for_action($trigger_event, $trigger_config)
        {
            $table_id = isset($trigger_config['table_id']) ? $trigger_config['table_id'] : 0;
            $table_meta = get_tablesome_data($table_id) ?? [];
            $table_columns = isset($table_meta['columns']) ? $table_meta['columns'] : [];

            $action_meta = isset($trigger_config['action_meta']) ? $trigger_config['action_meta'] : [];
            $fields_map = isset($action_meta['match_columns']) ? $action_meta['match_columns'] : [];

            $trigger_source_data = isset($trigger_event->trigger_source_data['data']) ? $trigger_event->trigger_source_data['data'] : [];
            $autodetect_enabled = isset($action_meta['autodetect_enabled']) ? $action_meta['autodetect_enabled'] : false;

            $integration = isset($trigger_event->trigger_source_data['integration']) ? $trigger_event->trigger_source_data['integration'] : '';

            $this->last_column_id = isset($table_meta['meta']['last_column_id']) ? $table_meta['meta']['last_column_id'] : 1;
            $this->new_last_column_id = $this->last_column_id;
            $event_params = [
                'table_id' => $table_id,
                'table_meta' => $table_meta,
                'action_meta' => $action_meta,
                'fields_map' => $fields_map,
                'source_data' => $trigger_source_data,
                'auto_detect' => $autodetect_enabled,
                'table_columns' => $table_columns,
                'integration' => $integration,
                'action_position' => $trigger_config['action_position'],
                'trigger_position' => $trigger_config['trigger_position'],
            ];

            // error_log('get_params_for_action() $trigger_source_data ' . print_r($trigger_source_data, true));

            // $event_params = $this->preprocessing_event_params($event_params);
            $event_params = $this->preprocessing_event_params($event_params); // Uses Trait preprocessing

            // error_log("event_params: " . print_r($event_params, true));

            // if ($table_id != $this->exception_table_id) {
            //     return;
            // }

            return $event_params;
        }

        private function update_fields_map($event_params)
        {
            // error_log('--update_fields_map()');
            $fields_map = $event_params['fields_map'];
            $source_data = $event_params['source_data'];

            // error_log('--before_update_fields_map $fields_map' . print_r($fields_map, true));
            // error_log('--before_update_fields_map $source_data' . print_r($source_data, true));

            if ($event_params['auto_detect'] == true) {
                $unmapped_fields = $this->get_unmapped_fields($fields_map, $source_data);
                // error_log('$source_data' . print_r($fields_map, true));
                // error_log('$fields_map' . print_r($fields_map, true));
                // error_log('$unmapped_fields' . print_r($unmapped_fields, true));
                // $fields_map = array_merge($fields_map, $unmapped_fields);
                $fields_map = $this->add_fields_before_smart_fields($fields_map, $unmapped_fields);
            }

            if (!empty($fields_map)) {
                $this->table_meta_needs_update = true;
            }

            foreach ($fields_map as $key => $field_set) {
                $field_name = isset($field_set['field_name']) ? $field_set['field_name'] : '';

                // error_log('$field_set' . print_r($field_set, true));
                // error_log('source_data $field_name: ' . print_r($source_data[$field_name], true));
                // error_log('field_name $isset: ' . isset($field_set['field_name']));
                // error_log('$field_name' . print_r($field_name, true));

                if (isset($source_data[$field_name])) {
                    $fields_map[$key]['label'] = $source_data[$field_name]['label'];
                }

                if (isset($field_set['column_id']) && $field_set['column_id'] != 0) {
                    continue;
                }

                if (isset($field_set['detection_mode']) && $field_set['detection_mode'] != 'disabled') {
                    // Set new column_id
                    $this->new_last_column_id = $this->new_last_column_id + 1;
                }

                $fields_map[$key]['column_id'] = $this->new_last_column_id;
                $fields_map[$key]['column_status'] = 'published';
            }

            // Final assignment
            $event_params['fields_map'] = $fields_map;

            return $event_params;
        }

        private function add_fields_before_smart_fields($fields_map, $unmapped_fields)
        {
            $index_of_first_smart_field = $this->get_index_of_first_smart_field($fields_map);
            $start_position = $index_of_first_smart_field;

            array_splice($fields_map, $start_position, 0, $unmapped_fields);

            // for ($ii = 0; $ii < count($unmapped_fields); $ii++) {
            //     $single_field = $unmapped_fields[$ii];
            //     );
            //     $start_position++;
            // }

            return $fields_map;

        }

        private function get_index_of_first_smart_field($fields_map)
        {
            $index_of_first_smart_field = 0;

            foreach ($fields_map as $key => $field_set) {
                if (isset($field_set['field_type']) && $field_set['field_type'] == 'tablesome_smart_fields') {
                    $index_of_first_smart_field = $key;
                    break;
                }
            }

            return $index_of_first_smart_field;
        }

        private function update_source_data($event_params)
        {
            // Get Trigger Source Data
            $trigger_data = $event_params['source_data'];
            $enabled_smart_fields = $this->get_enabled_smart_fields($event_params);

            // error_log('trigger_data: ' . print_r($trigger_data, true));
            // error_log('enabled_smart_fields: ' . print_r($enabled_smart_fields, true));

            // Merge Trigger Source Data with Smart Fields Data
            // $source_data = array_merge($trigger_data, $enabled_smart_fields);

            // Maintain the $keys even for numeric keys (important for wpforms)
            $source_data = $trigger_data + $enabled_smart_fields;

            // error_log('update_source_data() after source_data: ' . print_r($source_data, true));

            $event_params['source_data'] = $source_data;

            return $event_params;
        }

        private function get_enabled_smart_fields($event_params)
        {
            // error_log('get_enabled_smart_fields');
            $enabled_smart_fields = [];
            // Get Smart Fields Data
            $smart_fields_data = $this->get_extra_information();

            $fields_map = $event_params['fields_map'];

            foreach ($fields_map as $key => $field_set) {
                $field_name = isset($field_set['field_name']) ? $field_set['field_name'] : '';

                if (!isset($smart_fields_data[$field_name])) {
                    continue;
                }

                // error_log('field_set: ' . print_r($field_set, true));
                // error_log('smart_fields_data of $field_name: ' . print_r($smart_fields_data[$field_name], true));

                if (!isset($fields_map[$key]['detection_mode'])) {
                    continue;
                }

                if ('enabled' == $fields_map[$key]['detection_mode']) {
                    $enabled_smart_fields[$field_name] = $smart_fields_data[$field_name];
                }
            }

            return $enabled_smart_fields;
        }

        public function get_extra_information()
        {
            $current_datetime = date('Y-m-d H:i:s');
            $unix_timestamp = strtotime($current_datetime);

            $ip_address = [
                'label' => 'IP Address',
                'value' => get_tablesome_ip_address(),
                'type' => 'text',
                'final_value' => get_tablesome_ip_address(),
                'field_type' => 'tablesome_smart_fields',
            ];

            $page_source_url = [
                'label' => 'Page Source URL',
                'value' => get_tablesome_request_url(),
                'type' => 'url',
                'final_value' => get_tablesome_request_url(),
                'field_type' => 'tablesome_smart_fields',
            ];

            $created_at_datetime = [
                'label' => 'Submission Date',
                'value' => $current_datetime,
                'type' => 'date',
                'final_value' => $current_datetime,
                'field_type' => 'tablesome_smart_fields',
            ];

            $created_by = [
                'label' => 'Author ID',
                'value' => $this->get_current_user_id(),
                'type' => 'number',
                'final_value' => $this->get_current_user_id(),
                'field_type' => 'tablesome_smart_fields',
            ];

            $created_at = [
                'label' => 'Submission Date',
                'value' => $unix_timestamp * 1000,
                'type' => 'date',
                'final_value' => $unix_timestamp * 1000,
                'field_type' => 'tablesome_smart_fields',
            ];

            $values = array(
                'ip_address' => $ip_address,
                'page_source_url' => $page_source_url,
                'created_at_datetime' => $created_at_datetime,
                'created_at' => $created_at,
                'created_by' => $created_by,
            );

            // error_log('get_current_user_id: ' . $this->get_current_user_id(), );

            return $values;
        }

        private function get_current_user_id()
        {
            $user_id = apply_filters('determine_current_user', false);
            // wp_set_current_user($user_id);
            // $current_user = wp_get_current_user();
            return $user_id;
        }

        public function update_table_columns($event_params)
        {
            $fields_map = $event_params['fields_map'];

            // error_log('update_table_columns $fields_map: ' . print_r($fields_map, true));
            foreach ($fields_map as $key => $field_set) {

                if ($field_set['detection_mode'] == 'disabled') {
                    continue;
                }

                $label_exists = (isset($field_set['label']) && !empty($field_set['label']));

                // error_log('label_exists: ' . $label_exists);

                // $name = $field_set['field_name'];
                // $should_set_label = is_int($name) && $label_exists;
                // error_log('$field_set: ' . print_r($field_set, true));
                // error_log('is_int($name): ' . is_int($name));
                // error_log('name: ' . $name);
                // error_log('label: ' . $field_set['label']);
                // error_log('should_set_label: ' . $should_set_label);

                // if ($should_set_label) {
                //     $name = $field_set['label'];
                // }

                if ($label_exists) {
                    $name = $field_set['label'];
                } else {
                    $name = $field_set['field_name'];
                }

                $column = $this->get_column_from_table_columns($event_params['table_columns'], $field_set['column_id']);
                $column_format = $this->get_column_type($field_set['field_name'], $event_params);

                if ($column == false) {
                    // Is New Column
                    $event_params['table_columns'][] = array(
                        'id' => $field_set['column_id'],
                        'name' => $name,
                        'format' => $column_format,
                    );

                }

                // $event_params = $this->update_table_meta_column($column_format, $field_set['column_id'], $event_params);

            } // end foreach

            // $this->update_postmeta($event_params);

            // error_log('update_table_columns $event_params: ' . print_r($event_params, true));

            return $event_params;
        }

        private function update_table_meta_column($column_format, $column_id, $event_params)
        {
            error_log('update_table_meta_column $column_format: ' . $column_format);
            // return;
            $table_columns = $event_params['table_columns'];
            $column_needs_meta = false;
            $column_has_meta = false;

            if ($column_format == 'file' || $column_format == 'url' || $column_format == 'button') {
                $column_needs_meta = true;
            }

            $meta_column_id = $column_id . '_meta';
            $meta_column = $this->get_column_from_table_columns($table_columns, $meta_column_id);

            if ($meta_column) {
                $column_has_meta = true;
            }

            if ($column_needs_meta == true && $column_has_meta == false) {
                // Is New Column
                $event_params['table_columns'][] = array(
                    'id' => $meta_column_id,
                    'name' => $meta_column_id,
                    'format' => 'text',
                );

            }

            return $event_params;
        }

        private function get_column_from_table_columns($table_columns, $column_id)
        {
            foreach ($table_columns as $column) {
                if ($column['id'] == $column_id) {
                    return $column;
                }
            }

            return false;
        }

        public function get_unmapped_fields($fields_map, $source_data)
        {
            $new_fields = [];
            error_log('--get_unmapped_fields()');
            // error_log('$source_data' . print_r($source_data, true));
            // error_log('$fields_map' . print_r($fields_map, true));

            foreach ($source_data as $field_name => $field_set) {
                $single_map = [];
                $field_type = isset($field_set['field_type']) ? $field_set['field_type'] : 'trigger_source';
                $column_id = isset($fields_map['column_id']) ? $fields_map['column_id'] : 0;

                $field_found = $this->search_field_name($fields_map, $field_name);
                // error_log("get_unmapped_fields() " . $field_name . " field_found: " . $field_found);
                $is_new_field = !$field_found;

                if ($is_new_field == false) {
                    continue;
                }

                // error_log("get_unmapped_fields() field_set: " . print_r($field_set, true));

                // IMPORTANT
                $this->table_meta_needs_update = true;

                // // Set new column_id
                // $this->new_last_column_id = $this->new_last_column_id + 1;

                // $single_map['column_id'] = $this->new_last_column_id;
                // $single_map['column_status'] = 'published';
                $single_map['field_name'] = $field_name;
                $single_map['field_type'] = $field_type;
                if ($field_type == 'trigger_source') {
                    $single_map['detection_mode'] = 'automatic';
                } else {
                    $single_map['detection_mode'] = 'enabled';
                }

                $new_fields[] = $single_map;
            }

            error_log('$new_fields' . print_r($new_fields, true));

            // error_log("new_fields: " . print_r($new_fields, true));

            return $new_fields;
        }

        private function search_field_name($fields_map, $field_name)
        {
            // error_log("search_field_name() field_name: " . $field_name);
            $found = false;

            if (empty($fields_map)) {
                return $found;
            }

            foreach ($fields_map as $key => $field_set) {
                // error_log("search_field_name() field_set: " . print_r($field_set, true));
                // Use strval() to convert numbers to string.. ...Mainly for wpforms
                if (isset($field_set['field_name']) && strval($field_set['field_name']) == strval($field_name)) {
                    $found = true;
                    break;
                }
            }

            return $found;
        }

        private function get_column_type($field_name, $event_params)
        {
            // error_log("get_column_type() source_data: " . print_r($event_params['source_data'], true));
            // error_log("get_column_type() field_name: " . $field_name);

            $field_source = isset($event_params['source_data'][$field_name]) ? $event_params['source_data'][$field_name] : [];
            $field_type = isset($field_source['type']) ? $field_source['type'] : '';

            $column_type = $this->get_cell_format_by_field_type($field_type);

            // WPFORM SPECIAL CASES - 'time' format -> 'text' column type
            if (isset($field_source['ts_data_type']) && $field_source['ts_data_type'] != '') {
                $column_type = $field_source['ts_data_type'];
            }

            // error_log("get_column_type() field_source: " . print_r($field_source, true));
            // error_log("get_column_type() field_type: " . $field_type);
            // error_log("get_column_type() field_name: " . $field_name);
            // error_log("get_column_type() column_type: " . $column_type);
            return $column_type;
        }

        private function update_postmeta($event_params)
        {
            if ($this->table_meta_needs_update) {
                $event_params['table_meta']['columns'] = $event_params['table_columns'];
                $event_params['table_meta']['meta']['last_column_id'] = $this->new_last_column_id;
                $event_params['table_meta'] = set_tablesome_data($event_params['table_id'], $event_params['table_meta']);
            }

            // error_log('update_postmeta');
            // error_log('$this->table_meta_needs_update: ' . $this->table_meta_needs_update);
            // error_log('update_postmeta $event_params[table_meta][columns]: ' . print_r($event_params['table_meta']['columns'], true));

            return $event_params;
        }

        private function update_triggers_meta($event_params)
        {
            // error_log('update_triggers_meta');
            // error_log('update_triggers_meta event_params[action_position]: ' . $event_params['action_position']);
            // error_log('update_triggers_meta event_params[trigger_position]: ' . $event_params['trigger_position']);
            if ($this->table_meta_needs_update) {
                $match_columns = $event_params['fields_map'];
                // $this->actionmeta['match_columns'] = $this->match_columns;
                $triggersmeta = get_tablesome_table_triggers($event_params['table_id']);

                // error_log('triggersmeta: ' . print_r($triggersmeta, true));
                $triggersmeta[$event_params['trigger_position']]['actions'][$event_params['action_position']]['match_columns'] = $match_columns;
                $triggersmeta = set_tablesome_table_triggers($event_params['table_id'], $triggersmeta);
            }

            // error_log("update_triggers_meta triggersmeta: " . print_r($triggersmeta, true));

        }

        private function is_valid_event($event_params)
        {
            // Return if table id is empty or action meta is empty
            if (empty($event_params['table_id']) || is_null($event_params['table_id'])
                || empty($event_params['action_meta']) || empty($event_params['table_meta'])
                || empty($event_params['source_data'])) {
                return false;
            }

            return true;
        }

    } // END CLASS
}
