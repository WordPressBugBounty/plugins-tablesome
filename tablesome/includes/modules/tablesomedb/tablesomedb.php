<?php

namespace Tablesome\Includes\Modules\TablesomeDB;

if (!class_exists('\Tablesome\Includes\Modules\TablesomeDB\TablesomeDB')) {
    class TablesomeDB
    {
        public $table_crud_wp;
        public $myque;
        public $access_controller;
        public $wpdb;
        public $record;
        public $datatable;
        public $permissions;

        public function __construct()
        {
            global $wpdb;
            $this->table_crud_wp = new \Tablesome\Includes\Lib\Table_Crud_WP\Table_Crud_WP();
            $this->myque = new \Tablesome\Includes\Modules\Myque\Myque();
            $this->access_controller = new \Tablesome\Components\TablesomeDB\Access_Controller();
            $this->wpdb = $wpdb;
            $this->record = new \Tablesome\Components\Record();
            $this->datatable = new \Tablesome\Includes\Modules\Datatable\Datatable();
        }

        public function get_rows($args)
        {
            $default_args = array(
                'number' => 0,
                'orderby' => array('rank_order', 'id'),
                'order' => 'ASC',
                'limit' => TABLESOME_MAX_RECORDS_TO_READ,
            );
            $args = wp_parse_args($args, $default_args); // array or string args merge

            // $myque = new \Tablesome\Includes\Modules\Myque\Myque();
            $records = $this->myque->get_rows($args);
            // $proxy = new \Tablesome\Includes\Modules\Proxy($this->myque);
            // $records = $proxy->get_rows($args);

            $rows = $this->get_formatted_rows($records, $args['table_meta'], $args['collection']);

            return $rows;
        }

        /**
         *  Now, this does not create a table in DB
         * Table is currrently create from includes/core/table.php
         * **/
        public function create_table_instance($table_id, array $table_meta = array(), array $requests = array())
        {
            $table_name = $this->table_crud_wp->get_table_name($table_id, 0);
            if (empty($table_meta)) {
                $table_meta = get_tablesome_data($table_id);
            }
            /** Get current table meta columns */
            $table_columns = $this->table_crud_wp->helper->get_table_columns($table_meta);

            /** Table schema */
            $table_schema = $this->table_crud_wp->schema->get_schema($table_columns);

            $table = new \Tablesome_Table(array(
                'table_name' => $table_name,
                'table_schema' => $table_schema,
            ));

            //TODO Fixes for test-env.
            if (!$table->exists()) {
                $table->install();
            }

            // Modify the table structure if we add/remove the columns
            $table->modify_the_table($table_meta, $table_columns, $requests);

            return $table;
        }

        public function table_exists($table_id)
        {
            $table_name = $this->table_crud_wp->get_table_name($table_id, 0);
            $table = new \Tablesome_Table(array(
                'table_name' => $table_name,
            ));
            return $table->exists() ? true : false;
        }

        public function get_table_schema_columns($table_id)
        {
            /** Get the current table meta columns by table-ID*/
            $table_columns = $this->table_crud_wp->get_table_columns_from_db($table_id);

            /**
             * Generate the table schema
             * Using that schema collection for querying the tablesome table records from DB by using the berlinDB
             */
            $table_schema_generator = new \Tablesome\Includes\Modules\TablesomeDB\Schema_Generator($table_columns);
            $columns = $table_schema_generator->get_columns();
            // $schema = new \Tablesome_Table_Schema($columns);
            return $columns;
        }

        public function query($args)
        {
            $table_id = isset($args['table_id']) ? $args['table_id'] : '';
            $table_name = isset($args['table_name']) ? $args['table_name'] : '';

            if (empty($table_id) || empty($table_name)) {return;}
            $schema_columns = $this->get_table_schema_columns($table_id);

            if (empty($schema_columns)) {return;}
            $args['schema_columns'] = $schema_columns;

            $query = new \Tablesome_Table_Query($args);
            return $query;
        }

        public function duplicate_columns($args = array(), $response_data = array())
        {
            $args1 = array(
                'table_id' => $args['table_id'],
                'table_name' => $args['table_name'],
                'duplicated_columns' => [
                    array(
                        'source_column' => 'column_12',
                        'target_column' => 'column_12_2',
                    ),
                ],
            );

            $proxy = new \Tablesome\Includes\Modules\Proxy($this->myque);
            $response_data = $proxy->duplicate_column($args1, $response_data);

            // $response_updated = $this->myque->duplicate_column($args1, $response_data);
            // error_log('$response_data : ' . print_r($response_data, true));

            return $response_data;
        }

        public function check_permission_for_record($permission, $record, $columns_meta)
        {
            $operand_1_source = $permission['operand_1'];
            $operator = $permission['operator'];
            $operand_2 = $permission['operand_2'];

            $column_number = str_replace("column_", "", $operand_1_source);
            $column_index = (int) array_search($column_number, array_column($columns_meta, 'id'));
            // error_log('$columns_meta : ' . print_r($columns_meta, true));
            // error_log('$column_index : ' . $column_index);
            // error_log('$operand_1_source : ' . $operand_1_source);
            // error_log('$record : ' . print_r($record, true));
            // Set operand_1 value from source
            $operand_1 = $record['content'][$column_index]['value'];

            $args = array(
                'operand_1' => $operand_1,
                'operand_2' => $operand_2,
                'operator' => $operator,
            );

            if ('datetime' == $permission['data_type'] || 'date' == $permission['data_type']) {
                // $args['operand_1'] = new \DateTime($operand_1);
                $args['operand_2'] = strtotime($operand_2);
                $args['operand_2'] = $args['operand_2'] * 1000; // js timestamp
            }

            $condition = false;
            $condition = $this->compare($args);

            return $condition;
        }

        public function compare($args)
        {
            $condition = false;
            if ($args['operator'] == '=') {
                $condition = $args['operand_1'] == $args['operand_2'];
            } else if ($args['operator'] == '<') {
                $condition = $args['operand_1'] < $args['operand_2'];
            } else if ($args['operator'] == '>') {
                $condition = $args['operand_1'] > $args['operand_2'];
            } else if ($args['operator'] == '<=') {
                $condition = $args['operand_1'] <= $args['operand_2'];
            } else if ($args['operator'] == '>=') {
                $condition = $args['operand_1'] >= $args['operand_2'];
            } else {
                $condition = $args['operand_1'] == $args['operand_2'];
            }

            return $condition;
        }

        // update

        // bulk-inserts

        // bulk-updates

        // delete table

        public function delete_table($table)
        {
            $result = $table->drop();
            return $result;
        }

        /**
         * Duplicate the table
         *
         * @param [array] $table -> Source table instance
         * @param [integer] $duplicate_table_id
         * @return void
         */
        public function duplicate_table($table, $duplicate_table_id)
        {
            if (empty($duplicate_table_id)) {return;}
            $duplicate_table_name = $this->table_crud_wp->get_table_name($duplicate_table_id);
            if (empty($duplicate_table_name)) {return;}
            $table_cloned = $table->_clone($duplicate_table_name);
            if (!$table_cloned) {return;}
            $table_records_copied = $table->copy($duplicate_table_name);
            return $table_records_copied;
        }

        public function get_formatted_rows($records, $table_meta, array $collection = array())
        {
            $processed_rows = array();

            if (empty($records)) {
                $columns = isset($table_meta['columns']) ? $table_meta['columns'] : [];
                $empty_record = $this->record->get_empty_record($columns);
                array_push($processed_rows, $empty_record);
                return $processed_rows;
            }

            $is_admin = is_admin();
            
            // Cache current user data once before the loop to avoid repeated expensive calls
            // Get user once and reuse for both is_site_admin() check and permission checks
            $current_user = wp_get_current_user();
            $current_user_id = $current_user ? $current_user->ID : 0;
            $is_administrator = $this->access_controller->is_site_admin_with_user($current_user);

            // Get permissions - pass cached user to avoid repeated wp_get_current_user() calls
            $permissions = $this->access_controller->get_permissions($table_meta, $current_user);
            $can_edit = isset($permissions['can_edit']) ? $permissions['can_edit'] : false;
            $record_edit_access = isset($permissions['record_edit_access']) ? $permissions['record_edit_access'] : '';
            
            // In admin area, skip per-record permission checks (all records are editable)
            if ($is_admin) {
                $can_edit = false; // Will be handled by is_admin check in loop
            }

            // Pre-process exclude_column_ids once to avoid repeated explode() calls
            $exclude_column_ids = isset($collection['exclude_column_ids']) && !empty($collection['exclude_column_ids']) 
                ? explode(",", $collection['exclude_column_ids']) 
                : [];

            foreach ($records as $record) {
                $formatted_content = $this->get_formatted_row($record, $table_meta, $collection, $exclude_column_ids);
                
                $process_row = array(
                    'record_id' => $record->id,
                    'rank_order' => $record->rank_order,
                    'content' => $formatted_content,
                    'created_at' => $record->created_at,
                    'updated_at' => $record->updated_at,
                    'is_editable' => false,
                    'is_deletable' => false,
                );

                if ($is_admin || ($is_administrator && $can_edit)) {
                    $process_row['is_editable'] = true;
                    $process_row['is_deletable'] = true;
                    $processed_rows[] = $process_row;
                    continue; // skip to next record
                }

                // NOT ADMIN area or ADMINISTRATOR user
                if (!$can_edit) {
                    $process_row['is_editable'] = false;
                    $process_row['is_deletable'] = false;
                    $processed_rows[] = $process_row;
                    continue; // skip to next record
                }

                // CAN EDIT - Pass cached user data to avoid repeated wp_get_current_user() calls
                $process_row['is_deletable'] = $this->access_controller->can_delete_record($record, $table_meta, $permissions, $current_user, $current_user_id);

                if (!empty($record_edit_access)) {
                    $process_row['is_editable'] = $this->access_controller->can_edit_record($record, $table_meta, $record_edit_access, $current_user, $current_user_id);
                }

                $processed_rows[] = $process_row;
            }

            return $processed_rows;
        }

        public function get_formatted_row($record, $table_meta, $collection, $exclude_column_ids = null)
        {
            $row_content = array();
            /** get exclude column ids - use pre-processed array if provided, otherwise process it */
            if ($exclude_column_ids === null) {
                $exclude_column_ids = isset($collection['exclude_column_ids']) && !empty($collection['exclude_column_ids']) 
                    ? explode(",", $collection['exclude_column_ids']) 
                    : [];
            }
            $columns = isset($table_meta['columns']) ? $table_meta['columns'] : [];
            // error_log('$columns : ' . print_r($columns, true));
            foreach ($columns as $column) {

                $column_id = isset($column['id']) ? $column['id'] : 0;
                $column_format = isset($column['format']) ? $column['format'] : 'text';

                if (in_array($column_id, $exclude_column_ids)) {
                    continue;
                }

                $db_column_name = 'column_' . $column_id;
                $db_meta_column_name = $db_column_name . '_meta';

                $cell_content = isset($record->$db_column_name) ? $record->$db_column_name : '';
                $cell_meta_content = isset($record->$db_meta_column_name) ? $record->$db_meta_column_name : '';

                // Sanitize cell content once and reuse for both html and value (50% reduction in tablesome_wp_kses calls)
                $sanitized_cell_content = tablesome_wp_kses($cell_content);

                $cell = [
                    'type' => esc_textarea($column_format),
                    'html' => $sanitized_cell_content,
                    'value' => $sanitized_cell_content,
                    'column_id' => intval($column_id),
                ];

                // error_log('$cell : ' . print_r($cell, true));

                $meta_columns = ($column_format == 'url' || $column_format == 'button' || $column_format == 'file');
                if ($meta_columns && !empty($cell_meta_content)) {
                    // Optimize: Decode JSON first, then sanitize only HTML fields if needed
                    // wp_kses_post() on JSON is expensive - decode first, sanitize selectively
                    // Try direct json_decode first (for properly stored data)
                    $meta_content = json_decode($cell_meta_content, true);
                    if ($meta_content === null && json_last_error() !== JSON_ERROR_NONE) {
                        // Fall back to stripslashes for legacy data that might have WordPress magic quotes
                        $meta_content = json_decode(stripslashes($cell_meta_content), true);
                    }

                    // Only sanitize HTML fields if they exist (link, linkText, html, value)
                    if (!empty($meta_content) && is_array($meta_content)) {
                        if (isset($meta_content['html'])) {
                            $meta_content['html'] = tablesome_wp_kses($meta_content['html']);
                        }
                        if (isset($meta_content['linkText'])) {
                            $meta_content['linkText'] = sanitize_text_field($meta_content['linkText']);
                        }
                        if (isset($meta_content['link'])) {
                            $meta_content['link'] = esc_url($meta_content['link']);
                        }
                        if (isset($meta_content['value'])) {
                            if ($column_format === 'url' || $column_format === 'button') {
                                $meta_content['value'] = esc_url($meta_content['value']);
                            } else {
                                $meta_content['value'] = sanitize_text_field($meta_content['value']);
                            }
                        }
                    }

                    $cell = !empty($meta_content) ? array_merge($cell, $meta_content) : $cell;
                }

                $cell['column_format'] = $column_format;

                $cell = apply_filters("tablesome_get_cell_data", $cell);

                $row_content[$column_id] = $cell;
            }
            return $row_content;
        }

        public function extract_link_content($column_format, $cell_content)
        {
            $data = array();
            $required_props = array('value', 'html', 'linkText');

            foreach ($required_props as $key) {

                $pattern = '/\[' . $key . '\]';
                $pattern .= '\(';
                $pattern .= '(.*?)';
                $pattern .= '\)/';

                preg_match($pattern, $cell_content, $results);
                $cell_value = isset($results[1]) ? $results[1] : '';

                if (!empty($cell_value)) {
                    $cell_value = str_replace('TS_{', '(', $cell_value);
                    $cell_value = str_replace('TS_}', ')', $cell_value);
                }

                $data[$key] = $cell_value;
            }

            // $cell_data = explode("||", $cell_content);
            // if ($column_format == 'button') {
            //     return array(
            //         'value' => isset($cell_data[0]) ? $cell_data[0] : '',
            //         'linkText' => isset($cell_data[1]) ? $cell_data[1] : '',
            //         'html' => isset($cell_data[2]) ? $cell_data[2] : '',
            //     );
            // }
            // return array(
            //     'value' => isset($cell_data[0]) ? $cell_data[0] : '',
            //     'html' => isset($cell_data[1]) ? $cell_data[1] : '',
            // );

            return $data;
        }

        public function get_tables_records_count($tables)
        {
            if (empty($tables)) {
                return 0;
            }
            $records_count = 0;
            foreach ($tables as $table) {
                $db_table = $this->create_table_instance($table->ID, []);
                $records_count = intval($records_count) + intval($db_table->count());
            }
            return $records_count;
        }

        public function get_max_rank_order_value($table_id)
        {
            $min_rank_order = '0|100000:';
            if (isset($table_id) && $table_id === 0) {
                return $min_rank_order;
            }

            global $wpdb;
            $table_name = $this->table_crud_wp->get_table_name($table_id, 1);
            $query = "select max(rank_order) as rank_order from {$table_name}";
            $rank_order = $wpdb->get_var($query);
            $rank_order = !empty($rank_order) ? $rank_order : $min_rank_order;
            return $rank_order;
        }

    }
}
