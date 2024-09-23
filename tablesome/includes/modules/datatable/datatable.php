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

        public function run_crud($params)
        {

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
