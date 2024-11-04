<?php

namespace Tablesome\Includes\Modules\Datatable;

if (!class_exists('\Tablesome\Includes\Modules\Datatable\Datatable')) {
    class Datatable
    {
        public $wpdb;
        public $wp_prefix;
        public $table_name;
        public $records;
        public $columns;
        public $options;
        public $myque;
        public $tablesomedb_rest_api;
        public $tablesome_db;
        public $post;
        public $record;
        public $settings;
        public $access_controller;
        public $table_crud_wp;

        // public $source;

        public function __construct()
        {
            // $this->records = new \Tablesome\Includes\Modules\Datatable\Records();
            $this->myque = new \Tablesome\Includes\Modules\Myque\Myque();
            // $this->tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
            // $this->tablesomedb_rest_api = new \Tablesome\Includes\Modules\TablesomeDB_Rest_Api\TablesomeDB_Rest_Api();

            global $wpdb;
            $this->wpdb = $wpdb;
            $this->wp_prefix = $this->wpdb->prefix;

            $this->post = new \Tablesome\Includes\Modules\Datatable\Post();

            // Single Records
            $this->records = new \Tablesome\Includes\Modules\Datatable\Records();

            // Single Record
            $this->record = new \Tablesome\Includes\Modules\Datatable\Record($this);
            $this->settings = new \Tablesome\Includes\Modules\Datatable\Settings();

            $this->access_controller = new \Tablesome\Components\TablesomeDB\Access_Controller();

            $this->table_crud_wp = new \Tablesome\Includes\Lib\Table_Crud_WP\Table_Crud_WP();
        }

        public function reset_entire_table_data($params)
        {

            // error_log('reset_entire_table_data() :');
            // error_log('params:' . print_r($params, true));
            $columns = isset($params['columns']) ? $params['columns'] : [];
            $table_id = isset($params['table_id']) ? $params['table_id'] : 0;

            // error_log('reset_entire_table_data columns:' . print_r($columns, true));

            $table_name = $this->wp_prefix . 'tablesome_table_' . $table_id;
            // error_log('table_name:' . print_r($table_name, true));
            // 0. Delete the table in DB
            $this->myque->delete_table($table_id);

            // 1. Create table again in DB
            $create_table = $this->myque->create_table($table_name, $columns);

            if (!$create_table) {
                error_log('reset_entire_table_data() : create_table failed');
                error_log('last_error: ' . print_r($this->wpdb->last_error, true));
                // error_log('$create_table: ' . print_r($create_table, true));
            }

            // 2. Update post_meta

            // Reset 'last_column_id' to 0
            tablesome_update_last_column_id($table_id, -1);

            $meta_data = get_tablesome_data($table_id);
            $meta_data['columns'] = $columns;
            // $meta_data['last_column_id'] = 0;

            set_tablesome_data($table_id, $meta_data);

            // 3. Insert records
            $recordsData = $params['recordsData'];
            $recordsData['table_id'] = $table_id;
            $recordsData['meta_data'] = $meta_data;

            $this->run_crud($recordsData);
        }

        public function run_crud_with_monitoring($params)
        {

            error_log('run_crud_with_monitoring() : ');
            // Monitor max memory usage during the execution of the function
            $start_memory = memory_get_usage(true); 
            $start_time = microtime(true);

            // Monitor processor usage during the execution of the function
            $start_cpu = sys_getloadavg();

            $response_data = $this->run_crud($params);

            $end_cpu = sys_getloadavg();

            $cpu_usage = $end_cpu[0] - $start_cpu[0];

            error_log('CPU usage: ' . $cpu_usage);

            // $this->run_crud($params);

            $end_memory = memory_get_usage(true);
            $end_time = microtime(true);

            $memory_usage = ($end_memory - $start_memory) / (1024 * 1024); // Convert memory usage to MB

            $time_taken = $end_time - $start_time;

            error_log('Memory usage: (MB)' . $memory_usage);
            error_log('Time taken: ' . $time_taken);

            return $response_data;
        }

        public function update_columns_in_db($params)
        {
            $table_id = isset($params['table_id']) ? $params['table_id'] : 0;
            $table_name = $this->get_table_name($table_id);

            $columns_inserted = isset($params['columns_inserted']) ? $params['columns_inserted'] : [];
            $columns_updated = isset($params['columns_updated']) ? $params['columns_updated'] : [];
            $columns_deleted = isset($params['columns_deleted']) ? $params['columns_deleted'] : [];

            foreach ($columns_inserted as $column) {
                $column['name'] = 'column_' . $column['id'];
                $column['label'] = $column['name'];
                $column['table_name'] = $table_name;
                $this->myque->insert_column($column);
            }
            
            // $this->myque->update_column($columns_updated);
            // $this->myque->delete_column($columns_deleted);

        }

        public function duplicate_columns($params) {

            if(!isset($params['columns_duplicated']) || empty($params['columns_duplicated'])) {
                return;
            }

            // error_log('params[columns_duplicated]: ' . print_r($params['columns_duplicated'], true));
            $columns_duplicated = $params['columns_duplicated'];
            foreach ($columns_duplicated as $column) {
                $column['source_column'] = "column_" . $column['source_column_id'];
                $column['table_name'] = $this->get_table_name($params['table_id']);
                $column['target_column'] = "column_" . $column['id'];

                // error_log('column: ' . print_r($column, true));
                $this->myque->duplicate_column($column);
            }
        }
        public function run_crud($params)
        {
            
            $this->duplicate_columns($params);
            // return $this->run_crud_with_monitoring($params);

            # log number of inserted, updated and deleted records from $params
            $num_inserted = isset($params['records_inserted']) ? count($params['records_inserted']) : 0;
            $num_updated = isset($params['records_updated']) ? count($params['records_updated']) : 0;
            $num_deleted = isset($params['records_deleted']) ? count($params['records_deleted']) : 0;

            // Find added or deleted columns from $params
            // $columns_inserted = isset($params['columns_inserted']) ? $params['columns_inserted'] : [];

            $this->update_columns_in_db($params);

            // return $params;

            // error_log('params: ' . print_r($params, true));
            // error_log('num_inserted: ' . $num_inserted);
            // error_log('num_updated: ' . $num_updated);
            // error_log('num_deleted: ' . $num_deleted);


            if(isset($params['records_updated']) && !empty($params['records_updated'])) {
                $db_records = $this->get_db_records($params['table_id']);
                $changed_records = $this->get_changed_records($params['table_id'], $params['records_updated'], $db_records, $params['meta_data']);

                // error_log('changed_records: ' . print_r($changed_records, true));
                $params['records_updated'] = $changed_records;
                $changed_records_count = count($changed_records);
                // error_log('changed_records_count: ' . $changed_records_count);
                // error_log('changed_records: ' . print_r($changed_records, true));
            }
            

            // $tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();

            if (isset($params['records_deleted']) && is_array($params['records_deleted']) && !empty($params['records_deleted'])) {
                $this->records->delete_records($params, $params['records_deleted']);
            }

            /** Insert all records  */
            $inserted_records_count = 0;
            if (isset($params['records_inserted']) && !empty($params['records_inserted']) && is_array($params['records_inserted'])) {
                $insert_info = $this->records->insert_many($params['table_id'], $params['meta_data'], $params['records_inserted']);
                $inserted_records_count = isset($insert_info) && $insert_info['records_inserted_count'] ? $insert_info['records_inserted_count'] : 0;
            }

            // TODO: Need implement updating bulk record
            /**  */
            $response_data = $this->records->update_records($params);
            $response_data['inserted_records_count'] = $inserted_records_count;

            return $response_data;

        }

        public function get_db_records($table_id)
        {
            $table_name = $this->get_table_name($table_id);
            $query = "SELECT * FROM $table_name";
            return $this->wpdb->get_results($query, ARRAY_A);
        }

        public function has_record_changed($input_record, $db_record) {

            // if slash like {\"linkText\":\"\",\"value\":\"\"} remove it to like {"linkText":"","value":""}
            $input_record = wp_unslash($input_record);

            $array_diff = array_diff_assoc($input_record, $db_record);

            // Consider only column_ prefixed keys and rank_order
            foreach ($array_diff as $key => $value) {
                $is_empty_button = $value == '{"linkText":"","value":""}' && $db_record[$key] == '';
                $is_empty_media = $value == '{"file_url":"","type":"text","file_type":"","link":""}' && $db_record[$key] == '';
                $column_name_cond = !str_starts_with($key, 'column_') && $key !== 'rank_order';

                // error_log("is_empty_button: " . $is_empty_button);
                // error_log("value: " . $value);
                // error_log("db_record[key]: " . $db_record[$key]);
                if($column_name_cond || $is_empty_button || $is_empty_media) {
                    unset($array_diff[$key]);
                }
            }

            // error_log('$array_diff: ' . print_r($array_diff, true));
            if(!empty($array_diff)) {
                return true;
            }

            return false;
        }
        public function get_changed_records($table_id, $input_records, $db_records, $meta_data)
        {
            // ... existing code ...

            // Create a lookup array for db_records
            $db_records_lookup = [];
            foreach ($db_records as $db_record) {
                $db_records_lookup[$db_record['id']] = $db_record;
            }

            $changed_records = [];

            foreach ($input_records as $input_record) {
                $input_record_transformed = $this->table_crud_wp->helper->get_column_ided_record($table_id, $meta_data, $input_record);
                $input_record_transformed['record_id'] = $input_record['record_id'];
                $input_record_transformed['rank_order'] = $input_record['rank_order'];
                $record_id = $input_record['record_id'];
                // $input_record = $input_record_transformed;

                // Check if the record exists in the lookup array
                if (isset($db_records_lookup[$record_id])) {
                    $db_record = $db_records_lookup[$record_id];
                    $has_record_changed = $this->has_record_changed($input_record_transformed, $db_record);

                    if ($has_record_changed) {
                        $changed_records[] = $input_record;
                    }
                } else {
                    // If the record does not exist in db_records, consider it changed
                    $changed_records[] = $input_record;
                }
            }

            // error_log('changed_records: ' . print_r($changed_records, true));
            return $changed_records;
        }
       

        public function get_table_name($table_id, $prefix = 1)
        {
            if (!is_numeric($table_id)) {return $table_id;}

            $table_name = TABLESOME_TABLE_NAME . '_' . $table_id;
            if ($prefix == 0) {
                return $table_name;
            }
            return $this->wp_prefix . $table_name;
        }

        public function get_table()
        {

        }

        // create or update table
        // public function save_table($params)
        // {
        //     $can_save_table = $this->can_save_table($params);

        //     if (!$can_save_table) {
        //         return;
        //     }

        //     // Create a WordPress post of tablesome's post_type (if not update)

        //     if ($params['mode'] == 'create') {
        //         $params = $this->create_cpt_post($params);
        //         $params = $this->create_db_table($params);
        //     }

        //     $this->save_table_settings($params);

        //     $this->records->save($params);

        //     return $this->send_response($params);
        // }

        // public function can_save_table($params)
        // {
        //     $can_save_table = false;

        //     // Early Return
        //     if ($params['mode'] == 'read-only') {
        //         return $can_save_table;
        //     }

        //     // User Permissions

        //     return $can_save_table;
        // }

        // public function delete_table()
        // {

        // }

    } // END CLASS

}

