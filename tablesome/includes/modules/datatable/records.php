<?php

namespace Tablesome\Includes\Modules\Datatable;

if (!class_exists('\Tablesome\Includes\Modules\Datatable\Records')) {
    class Records
    {
        public $myque;
        // public $tablesome_db;
        public $access_controller;
        public $table_crud_wp;
        public $record;
        // public $datatable;

        public function __construct()
        {
            // $this->datatable = $datatable;
            $this->myque = new \Tablesome\Includes\Modules\Myque\Myque();
            // $this->tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
            $this->access_controller = new \Tablesome\Components\TablesomeDB\Access_Controller();
            $this->table_crud_wp = new \Tablesome\Includes\Lib\Table_Crud_WP\Table_Crud_WP();
            $this->record = new \Tablesome\Includes\Modules\Datatable\Record();
        }

        public function get($args)
        {
            $default_args = array(
                'number' => 0,
                'orderby' => array('rank_order', 'id'),
                'order' => 'ASC',
                'limit' => TABLESOME_MAX_RECORDS_TO_READ,
            );

            $args = wp_parse_args($args, $default_args); // array or string args merge

            $records = $this->myque->get_rows($args);

            // $rows = $this->tablesome_db->get_formatted_rows($records, $args['table_meta'], $args['collection']);
            $rows = [];
            return $rows;
        }

        public function is_admin()
        {
            // Allow administrators and editors to bypass column restrictions
            $is_admin = current_user_can('manage_options'); // Administrators
            $is_editor = current_user_can('edit_posts'); // Editors and above
            return $is_admin || $is_editor;
        }

        public function update_records($args, $response_data = [])
        {
            $args['table_name'] = $this->table_crud_wp->get_table_name($args['table_id'], 1);

            $user_can_update = $this->access_controller->can_update_table($args);
            $response_data = [];
            $response_data['updated_records_count'] = 0;

            if ($user_can_update == false) {
                return $response_data;
            }

            // $records = [];
            // $modified_records = [];
            $is_admin = $this->is_admin();
            $modified_records = isset($args['records_updated']) ? $args['records_updated'] : array();

            // error_log('$modified_records : ' . print_r($modified_records, true));
            foreach ($modified_records as $record) {
                $record_id = isset($record['record_id']) ? $record['record_id'] : 0;
                $user_record = $this->table_crud_wp->helper->get_column_ided_record($args['table_id'], $args['meta_data'], $record);
                $user_record['post_id'] = $args['table_id'];
                $user_record['rank_order'] = isset($record['rank_order']) ? $record['rank_order'] : '';
                $update_record = false;

                if ($record_id == 0) {
                    $insert_record = $this->record->insert($args['query'], $user_record);
                }

                $db_record = $this->record->get_db_record($record_id, $args);

                $row_permissions = $this->get_row_action_permissions($db_record, $args['meta_data']);

                $active_editable_columns = $this->get_editable_columns($args['meta_data']);
                if (!$is_admin && isset($active_editable_columns) && !empty($active_editable_columns)) {
                    $user_record = $this->record->get_editable_cells($user_record, $active_editable_columns);
                }

                // error_log('$row_permissions : ' . print_r($row_permissions, true));
                // error_log('$user_record : ' . print_r($user_record, true));
                if ($row_permissions['is_editable']) {
                    $update_record = $this->record->update_single_record($args['query'], $record_id, $user_record, $db_record);
                }

                // error_log('$record_id : ' . $record_id);
                // error_log('$row_permissions : ' . print_r($row_permissions, true));
                // error_log('$user_record : ' . print_r($user_record, true));
                // error_log('$db_record : ' . print_r($db_record, true));
                // error_log('$update_record : ' . $update_record);

                if ($update_record) {
                    $response_data['updated_records_count'] = isset($response_data['updated_records_count']) ? ++$response_data['updated_records_count'] : 1;
                }

            } // END foreach

            return $response_data;

        }

        public function get_editable_columns($table_meta)
        {
            // return [];
            $permissions = $this->access_controller->get_permissions($table_meta);
            $editable_columns = isset($permissions['editable_columns']) ? $permissions['editable_columns'] : [];
            
            // SECURITY: Protect email column from being edited when email_column_match is enabled
            // Only restrict for non-admins; admins and editors can still edit the email column
            $access_control = isset($table_meta['options']['access_control']) ? $table_meta['options']['access_control'] : [];
            $record_edit_access = isset($access_control['record_edit_access']) ? $access_control['record_edit_access'] : '';
            $email_column_id = isset($access_control['email_column_for_row_access']) ? $access_control['email_column_for_row_access'] : null;
            
            // If email_column_match mode is enabled and email column is set, remove it from editable columns (non-admins only)
            if ($record_edit_access === 'email_column_match' && $email_column_id !== null && $email_column_id !== '' && !$this->is_admin()) {
                // Remove email column from editable columns array
                $editable_columns = array_diff($editable_columns, [$email_column_id]);
                
                // Log security event
                error_log('Tablesome Security: Email column ' . $email_column_id . ' automatically excluded from editable columns (email_column_match mode)');
            }
            
            return $editable_columns;
        }

        public function insert_many($table_id, $meta_data, $records)
        {
            $props = [
                'columns' => isset($meta_data['columns']) ? $meta_data['columns'] : [],
                'rows_count' => 0,
                'rows' => array(),
                'meta_data' => $meta_data,
                'records_inserted_count' => 0,
            ];
            $current_batch_no = 1;
            $record_counter = 0;
            foreach ($records as $index => $record) {

                $props["rows"][] = $record;

                $end_row_index = ($current_batch_no * TABLESOME_BATCH_SIZE) - 1;
                if ($index == $end_row_index) {
                    $current_batch_no++;

                    $params = $this->get_inserts_record_values($table_id, $props);
                    $result = $this->table_crud_wp->insert_many($table_id, $params);
                    if ($result) {
                        $records_inserted_count = intval($props['records_inserted_count']) + intval($result);
                        $props['records_inserted_count'] = $records_inserted_count;
                    }
                    unset($props['rows']);

                }

                $record_counter++;

                if ($record_counter == TABLESOME_MAX_RECORDS_TO_READ) {
                    break;
                }
            }

            if (isset($props["rows"]) && !empty($props["rows"]) && $record_counter <= $end_row_index) {
                $params = $this->get_inserts_record_values($table_id, $props);
                $result = $this->table_crud_wp->insert_many($table_id, $params);

                if ($result) {
                    $records_inserted_count = intval($props['records_inserted_count']) + intval($result);
                    $props['records_inserted_count'] = $records_inserted_count;
                }
                unset($props['rows']);
            }

            $props["rows_count"] = $record_counter;

            return $props;
        }

        public function delete_records($args, $record_ids)
        {
            $args['table_name'] = $this->table_crud_wp->get_table_name($args['table_id'], 1);
            $query = isset($args['query']) ? $args['query'] : null;
            $table_meta_data = isset($args['meta_data']) ? $args['meta_data'] : [];

            /** Returen if the record_ids array is empty */
            if (empty($record_ids)) {return;}

            foreach ($record_ids as $record_id) {
                $can_delete = $this->record->can_user_delete_record($record_id, $args, $table_meta_data);

                if ($can_delete) {
                    $query->delete_item($record_id);
                }
            }
            return true;
        }

        public function get_inserts_record_values($table_id, $props)
        {
            $timestamp = current_time('timestamp');
            $datetime = date('Y-m-d H:i:s', $timestamp);
            $author_id = get_current_user_id();

            $params = array();

            $defaults = array(
                'post_id' => $table_id,
                'author_id' => $author_id,
                'updated_by' => $author_id,
                'created_at' => $datetime,
                'updated_at' => $datetime,
            );

            foreach ($props['rows'] as $index => $row) {
                $defaults['rank_order'] = isset($row['rank_order']) ? $row['rank_order'] : '';
                $column_values_args = $this->table_crud_wp->helper->get_column_ided_record($table_id, $props['meta_data'], $row);
                $params[] = array_merge($defaults, $column_values_args);
            }

            return $params;
        }

        // public function save($params)
        // {
        //     //  $user_permissions = $this->tablesome_db->get_user_permissions();

        //     if (isset($params['records_deleted']) && is_array($params['records_deleted']) && !empty($params['records_deleted'])) {
        //         $this->delete_records($params, $params['records_deleted']);
        //     }

        //     if (isset($params['records_inserted']) && !empty($params['records_inserted']) && is_array($params['records_inserted'])) {
        //         $insert_info = $this->insert_many($params['table_id'], $params['meta_data'], $params['records_inserted']);
        //         $inserted_records_count = isset($insert_info) && $insert_info['records_inserted_count'] ? $insert_info['records_inserted_count'] : 0;
        //     }

        //     // $response_data = $datatable->records->update_records($params);
        //     $response_data['inserted_records_count'] = $inserted_records_count;

        //     return $response_data;
        // }

        // public function delete_records($args, $record_ids)
        // {
        //     $args['table_name'] = $this->table_crud_wp->get_table_name($args['table_id'], 1);
        //     $query = isset($args['query']) ? $args['query'] : null;
        //     $table_meta_data = isset($args['meta_data']) ? $args['meta_data'] : [];

        //     /** Returen if the record_ids array is empty */
        //     if (empty($record_ids)) {return;}

        //     foreach ($record_ids as $record_id) {
        //         $can_delete = $this->can_user_delete_record($record_id, $args, $table_meta_data);

        //         error_log('$can_delete : ' . $can_delete);
        //         if ($can_delete) {
        //             $query->delete_item($record_id);
        //         }
        //     }
        //     return true;
        // }

        public function get_row_action_permissions($record, $table_meta)
        {
            $permissions = $this->access_controller->get_permissions($table_meta);

            $is_administrator = $this->access_controller->is_site_admin();

            $is_rest_backend = (defined('REST_REQUEST') && REST_REQUEST);

            $is_admin = current_user_can('administrator');
            $can_edit = false;
            $record_edit_access = '';

            if (!$is_admin) {
                // Don't need to get permissions data if user accessing the table in admin area
                $permissions = $this->access_controller->get_permissions($table_meta);
                $can_edit = isset($permissions['can_edit']) ? $permissions['can_edit'] : false;
                $record_edit_access = isset($permissions['record_edit_access']) ? $permissions['record_edit_access'] : '';
            }

            $row_permissions = [
                'is_editable' => false,
                'is_deletable' => false,
            ];

            // error_log('is_admin: ' . $is_admin);
            // error_log('is_admin(): ' . is_admin());
            // error_log('is_rest_backend: ' . $is_rest_backend);
            // error_log('$can_edit: ' . $can_edit);
            // error_log('record_edit_access: ' . $record_edit_access);

            if ($is_admin || ($is_administrator && $can_edit)) {
                // error_log('get_row_action_permissions - admin? $row_permissions: ' . print_r($row_permissions, true));
                $row_permissions['is_editable'] = true;
                $row_permissions['is_deletable'] = true;
                return $row_permissions;
            }

            // NOT ADMIN area or ADMINISTRATOR user

            if (!$can_edit) {
                $row_permissions['is_editable'] = false;
                $row_permissions['is_deletable'] = false;
                return $row_permissions;
            }

            // Cache user data to avoid repeated wp_get_current_user() calls
            $current_user = wp_get_current_user();
            $current_user_id = $current_user ? $current_user->ID : 0;

            $row_permissions['is_deletable'] = $this->access_controller->can_delete_record($record, $table_meta, $permissions, $current_user, $current_user_id);

            if (!empty($record_edit_access)) {
                $row_permissions['is_editable'] = $this->access_controller->can_edit_record($record, $table_meta, $record_edit_access, $current_user, $current_user_id);
            }

            return $row_permissions;
        }

    } // END CLASS

}
