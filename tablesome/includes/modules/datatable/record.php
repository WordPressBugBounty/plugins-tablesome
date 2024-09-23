<?php

namespace Tablesome\Includes\Modules\Datatable;

if (!class_exists('\Tablesome\Includes\Modules\Datatable\Record')) {
    class Record
    {
        public $id;
        public $cells;
        public $must_have_cells;
        public $access_controller;
        public $wpdb;
        public $myque;
        public $table_crud_wp;

        public function __construct()
        {
            global $wpdb;
            $this->access_controller = new \Tablesome\Components\TablesomeDB\Access_Controller();
            $this->wpdb = $wpdb;
            $this->myque = new \Tablesome\Includes\Modules\Myque\Myque();
            $this->table_crud_wp = new \Tablesome\Includes\Lib\Table_Crud_WP\Table_Crud_WP();

        }

        public function insert($query, $data, $insert_args = [])
        {
            $post_id = isset($data['post_id']) ? $data['post_id'] : 0;
            /** Return, if post-id doesn't exists or that value is 0 */
            if (empty($post_id)) {return false;}

            /***
             * Add the default values  (like author_id, created_at, updated_at) to $data array if that array doesn't have.
             */
            $data = $this->get_additional_data($data);

            /** Insert the record using berlinDB */
            // $record_id = $query->add_item($data);

            /** Insert the record using MyQue */

            $table_name = $this->table_crud_wp->get_table_name($post_id, 1);

            // Move all properties in $data starting with "column_" to $data['content']
            // foreach ($data as $key => $value) {
            //     if (strpos($key, 'column_') === 0) {
            //         $data['content'][$key] = $value;
            //         unset($data[$key]);
            //     }
            // }

            $record_id = $this->myque->insert_record($data, $table_name, $insert_args);
            return !empty($record_id) ? $record_id : false;
        }

        public function get_editable_cells($data, $active_editable_columns)
        {

            $allowed_cells = [];
            foreach ($data as $column_name => $value) {
                $column_id = str_replace('column_', '', $column_name);
                if (is_numeric($column_id)) {
                    if (in_array($column_id, $active_editable_columns)) {
                        $allowed_cells[$column_name] = $value;
                    }
                } else {
                    // add non numeric columns
                    $allowed_cells[$column_name] = $value;
                }
            }
            return $allowed_cells;
        }

        public function update_single_record($query, $record_id, $user_record, $db_record_obj)
        {
            $post_id = isset($user_record['post_id']) ? $user_record['post_id'] : 0;

            // error_log('update_single_record post_id: ' . $post_id);

            // Return, if post-id doesn't exists or the value as 0
            if (empty($record_id) || empty($post_id)) {
                return false;
            }

            $user_record = $this->get_additional_data($user_record);

            // Don't update the created_at, author_id columns when updating the record

            foreach (['created_at', 'author_id'] as $excluded_column) {
                if (isset($user_record[$excluded_column])) {
                    unset($user_record[$excluded_column]);
                }
            }

            // Update the record using berlinDB
            $result = $query->update_item($record_id, $user_record);

            return !empty($result) ? $result : false;
        }

        public function get_additional_data($data)
        {
            $timestamp = current_time('timestamp');
            $datetime = date('Y-m-d H:i:s', $timestamp);

            $data['author_id'] = isset($data['author_id']) && !empty($data['author_id']) ? $data['author_id'] : get_current_user_id();
            $data['updated_by'] = isset($data['updated_by']) && !empty($data['updated_by']) ? $data['updated_by'] : get_current_user_id();
            $data['created_at'] = isset($data['created_at']) && !empty($data['created_at']) ? $data['created_at'] : $datetime;
            $data['updated_at'] = isset($data['updated_at']) && !empty($data['updated_at']) ? $data['updated_at'] : $datetime;
            $data['rank_order'] = isset($data['rank_order']) && !empty($data['rank_order']) ? $data['rank_order'] : '';

            return $data;
        }

        public function can_user_delete_record($record_id, $args, $table_meta_data)
        {
            $permissions = $this->access_controller->get_permissions($table_meta_data);
            $can_edit = isset($permissions['can_edit']) ? $permissions['can_edit'] : false;
            $can_delete_own_records = isset($permissions['can_delete_own_records']) ? $permissions['can_delete_own_records'] : false;

            $mode = isset($args['mode']) ? $args['mode'] : '';
            $is_admin = ($mode == 'editor');

            $can_delete = false;

            $current_user = get_tablesome_user_details();
            $is_administrator = isset($current_user['is_administrator']) ? $current_user['is_administrator'] : false;

            if ($is_admin || ($is_administrator && $can_edit)) {
                $can_delete = true;
                return $can_delete;
            }

            if ($can_edit && $can_delete_own_records) {
                $db_record = $this->get_db_record($record_id, $args);
                $record_created_by_current_user = isset($db_record->author_id) && $db_record->author_id == $current_user['user_id'];
                if ($record_created_by_current_user) {
                    $can_delete = true;
                }
            }

            return $can_delete;
        }

        public function get_db_record($record_id, $args)
        {
            $db_record = $this->myque->get_row($record_id, $args);
            return $db_record;
        }

    } // end class
}
