<?php

namespace Tablesome\Includes\Modules\Myque;

// Refactor this class

// Query Builder for MySQL
if (!class_exists('\Tablesome\Includes\Modules\Myque\Mysql')) {
    class Mysql
    {

        public $wpdb;
        public $schema;

        public function __construct()
        {
            global $wpdb;
            $this->wpdb = $wpdb;
            $this->schema = new \Tablesome\Includes\Lib\Table_Crud_WP\Schema();
        }

        public function create_table($table_name, $columns)
        {
            $column_names = array();

            // error_log('columns : ' . print_r($columns, true));

            foreach ($columns as $column) {
                $name = "column_" . $column['id'];
                $column_names[] = $name;

            }

            if (empty($table_name) || empty($columns)) {
                error_log('create_table: $table_name or $columns is empty');
                return false;
            }

            $query = "CREATE TABLE IF NOT EXISTS $table_name";
            $query .= " ( ";
            $query .= $this->schema->get_schema($column_names);
            $query .= " ); ";

            // error_log('$query : ' . $query);

            $result = $this->wpdb->query($query);

            return $result;
        }

        public function get_row_count($table_id)
        {
            if( !isset($table_id) || empty($table_id) || is_null($table_id) || $table_id == 0 ){
                return 0;
            }
            $table_name = TABLESOME_TABLE_NAME . '_' . $table_id;
            $table_name = $this->wpdb->prefix . $table_name;
            $query = "SELECT COUNT(*) FROM $table_name";
            return $this->wpdb->get_var($query);
        }

        public function check_and_add_missing_columns($record, $table_name)
        {
            // error_log('check_and_add_missing_columns $record : ' . print_r($record, true));
            // error_log('check_and_add_missing_columns $table_name : ' . print_r($table_name, true));
            $table_columns = $this->get_table_columns($table_name);
            $record_columns = array_keys($record);
            // $missing_columns = array_diff($record_columns, $table_columns);
            $missing_columns = [];

            foreach($record_columns as $record_column){
                $isColumnMissing = true;
                // error_log('check_and_add_missing_columns $record_column : ' . $record_column);
                foreach($table_columns as $table_column) {
                    // error_log('check_and_add_missing_columns $table_column : ' . $table_column['Field']);
                    if($table_column['Field'] == $record_column){
                        $isColumnMissing = false;
                        break;
                    }
                }
                if($isColumnMissing){
                    $missing_columns[] = ['name' => $record_column, 'format' => 'TEXT', 'table_name' => $table_name];
                }
            }


            // error_log('check_and_add_missing_columns $table_columns : ' . print_r($table_columns, true));
            // error_log('check_and_add_missing_columns $record_columns : ' . print_r($record_columns, true));
            // error_log('check_and_add_missing_columns $missing_columns : ' . print_r($missing_columns, true));

            if(empty($missing_columns)){
                return;
            }

            $response = [];

            foreach($missing_columns as $column){
                $this->insert_column($column, $response);
            }

            return;
        }

        public function insert_record($record, $table_name, $insert_args)
        {
            // error_log('insert_record $record : ' . print_r($record, true));
            // error_log('insert_record $table_name : ' . print_r($table_name, true));
            global $wpdb;

            // For debugging purposes only
            // $this->get_columns($table_name);

            $response = '';

            if (!isset($record) || is_null($record)) {
                return 0;
            }

            $query = "INSERT INTO $table_name (";

            $ii = 0;

            if (count($record) == 0) {
                return 0;
            }

            // Check if record has columns missing in table
            // $this->check_and_add_missing_columns($record, $table_name);


            foreach ($record as $key => $cell) {
                # code...
                // $column_name = $this->get_column_name($cell);
                $column_name = $key;
                $query .= " `$column_name`";

                // Add comma if not the last item
                if ($ii < count($record) - 1) {
                    $query .= ",";
                }
                $ii++;
            } // END of cell loop

            $query .= ") SELECT ";

            $ii = 0;
            foreach ($record as $key => $value) {
                # code...
                // $value = $cell['value'];
                $value = esc_sql($value);
                $query .= " '$value'";
                // Add comma if not the last item
                if ($ii < count($record) - 1) {
                    $query .= ",";
                }

                $ii++;
            } // END of cell loop

            $query .= " ";
            $enabled_prevent_duplication = isset($insert_args['enable_duplication_prevention']) && $insert_args['enable_duplication_prevention'] == 1 ? true : false;
            $enabled_limit_submission = isset($insert_args['enable_submission_limit']) && $insert_args['enable_submission_limit'] == 1 ? true : false;
            $submission_limit = isset($insert_args['max_allowed_submissions']) && !empty($insert_args['max_allowed_submissions']) ? intval($insert_args['max_allowed_submissions']) : 100;
            $prevent_field_column = isset($insert_args['prevent_field_column']) ? $insert_args['prevent_field_column'] : "";
            $can_add_prevent_query = ($enabled_prevent_duplication || $enabled_limit_submission);

            if ($can_add_prevent_query) {
                // WHERE clause
                $query .= "FROM (SELECT COUNT(*) cnt FROM " . $table_name . ") sub";
                $query .= " "; // space
                $query .= "WHERE ";

                if ($enabled_prevent_duplication && !empty($prevent_field_column) && isset($record[$prevent_field_column])) {
                    $query .= "NOT EXISTS (SELECT 1 FROM " . $table_name . " WHERE " . $prevent_field_column . " = '" . esc_sql($record[$prevent_field_column]) . "' ) ";
                }

                $query .= " "; // space

                if ($enabled_prevent_duplication && $enabled_limit_submission) {
                    $query .= "AND ";
                }

                if ($enabled_limit_submission) {
                    $query .= "cnt < " . $submission_limit . ";";
                }
            }

            // error_log('$query : ' . $query);

            // Todo: Add wpdb->prepare() to $query
            // Example: $wpdb->query( $wpdb->prepare($query) );

            $insert_success_bool = $wpdb->query($query);
            $inserted_record_id = $wpdb->insert_id;
            // $inserted_record_id = $wpdb->query("SELECT LAST_INSERT_ID();");
            // error_log('$inserted_record_id : ' . $inserted_record_id);
            $record['record_id'] = $inserted_record_id;

            // error_log('mysql->insert_record $record : ' . print_r($record, true));
            return $record;
        }

        public function update_record($record, $table_name, $insert_args)
        {
            // error_log('update_record $record : ' . print_r($record, true));

            $prevent_field_column = isset($insert_args['prevent_field_column']) ? $insert_args['prevent_field_column'] : "";
            $prevent_field_value = isset($record[$prevent_field_column]) ? $record[$prevent_field_column] : "";

            global $wpdb;
            $query = "UPDATE $table_name SET ";
            $ii = 0;
            foreach ($record as $key => $value) {
                $value = esc_sql($value);
                $query .= " `$key` = '$value'";
                // Add comma if not the last item
                if ($ii < count($record) - 1) {
                    $query .= ",";
                }
                $ii++;
            } // END of cell loop
            $query .= " WHERE `$prevent_field_column` = '$prevent_field_value' LIMIT 1;";
            $wpdb->query($query);
            return $record;
        }   

        public function insert_column($args , $response = array()){
            global $wpdb;
            $table_name = $args['table_name'];
            $args['table_name'] = $table_name;
            $column_name = $args['name'];
            $column_type = $args['format'];

            $query = "ALTER TABLE $table_name ADD $column_name $column_type DEFAULT ''";
            // error_log('insert_column $query : ' . $query);
            $response['new_column_created'] = $wpdb->query($query);
            return $response;
        }

        public function copy_column_content($args, $response = array())
        {
            global $wpdb;
            $table_name = $args['table_name'];
            $args['table_name'] = $table_name;
            $source_column = $args['source_column'];
            $target_column = $args['target_column'];

            if(empty($source_column) || empty($target_column)){
                error_log('copy_column_content: $source_column or $target_column is empty');
                return $response;
            }

            $query = "UPDATE $table_name SET $target_column = $source_column";
            $response['copied_column_records'] = $wpdb->query($query);
            return $response;
        }

        public function duplicate_column($args, $response = array())
        {

            // error_log('args: ' . print_r($args, true));
            global $wpdb;
            $table_name = $args['table_name'];
            $args['table_name'] = $table_name;
            $source_column = $args['source_column'];
            $target_column = $args['target_column'];

            if(empty($source_column) || empty($target_column)){
                error_log('duplicate_column: $source_column or $target_column is empty');
                return $response;
            }

            // If target column already exists, return
            $columns = $this->get_table_columns($table_name);
            if($this->does_column_exists($columns, $target_column)){
                $response = $this->copy_column_content($args, $response);
                return $response;
            }

            // Create New Column
            $query = "ALTER TABLE $table_name ADD $target_column TEXT NOT NULL";
            $response['new_column_created'] = $wpdb->query($query);

            // Copy Data from Source Column to Target Column
            $query = "UPDATE $table_name SET $target_column = $source_column";
            // error_log('$query : ' . $query);
            $response['copied_column_records'] = $wpdb->query($query);

            return $response;
        }

        public function get_row($record_id, $args)
        {
            if (empty($record_id)) {
                return null;
            }
            $table_name = $args['table_name'];
            $query = "select * from {$table_name} where id = {$record_id}";
            $db_record = $this->wpdb->get_row($query);
            if (is_wp_error($db_record)) {
                error_log("get_record error:" . $db_record->get_error_message());
                return null;
            }
            return $db_record;

        }
        public function get_rows($args)
        {
            // error_log(' Mysql $args : ' . print_r($args, true));
            global $wpdb;

            $table_name = $wpdb->prefix . $args['table_name'];
            $args['table_name'] = $table_name;

            $query = "SELECT * FROM $table_name";
            if (isset($args['where'])) {
                $query .= $this->convert_conditions_to_sql_string($args['where'], $table_name);
            }

            $query .= $this->orderby($args);
            $query .= " LIMIT " . $args['limit'];
            $result = $wpdb->get_results($query);

            // error_log('Mysql->get_rows $query: ' . $query);
            // error_log('Mysql->get_rows $result count: ' . count($result));
            // error_log('Mysql->get_rows $result : ' . print_r($result, true));

            return $result;
        }

        public function orderby($args)
        {
            $sql_string = " ORDER BY ";
            $orderByArgs = [];
            foreach ($args['orderby'] as $key => $value) {
                $orderByArgs[] = $args['table_name'] . "." . $value;
            }
            // Looks like wptablesome_table_287.column_2, wptablesome_table_287.column_3 ....
            $sql_string = $sql_string . implode(',', $orderByArgs);
            $sql_string .= " " . $args['order'];

            return $sql_string;
        }

        public function get_table_columns($table_name)
        {
            global $wpdb;

            $table_name_escaped = esc_sql($table_name);
            $sql = $this->wpdb->prepare("SHOW COLUMNS FROM %1s", $table_name_escaped);

            // $query = "SHOW COLUMNS FROM $table_name";
            $result = $wpdb->get_results($sql, 'ARRAY_A');

            // error_log(' result: ' . print_r($result, true));
            return $result;
        }

        public function does_column_exists($columns, $column_name)
        {
            foreach ($columns as $key => $column) {
                if ($column['Field'] == $column_name) {
                    return true;
                }
            }
            return false;
        }

        public function convert_conditions_to_sql_string($conditions, $table_name)
        {
            error_log('convert_conditions_to_sql_string: ');
            // error_log('$conditions : ' . print_r($conditions, true));

            $columns = $this->get_table_columns($table_name);
            foreach ($conditions as $key => $condition) {
                $column_name = $condition['operand_1'];

                // Remove columns which are not in Table
                if (!$this->does_column_exists($columns, $column_name)) {
                    unset($conditions[$key]);
                    continue;
                }

                if ($condition['operator'] == 'empty' || $condition['operator'] == 'is_empty') {
                    // Convert empty and not_empty to a condition_group
                    $conditions[$key] = $this->convert_condition_to_condition_group($condition, 'OR');
                    $new_condition = $condition;
                    $new_condition['operator'] = 'is_null';
                    array_push($conditions[$key]['conditions'], $new_condition);
                }

                if ($condition['operator'] == 'not_empty' || $condition['operator'] == 'is_not_empty') {
                    // Convert empty and not_empty to a condition_group
                    $conditions[$key] = $this->convert_condition_to_condition_group($condition, 'AND');
                    $new_condition = $condition;
                    $new_condition['operator'] = 'is_not_null';
                    array_push($conditions[$key]['conditions'], $new_condition);
                }
            }

            // error_log('$conditions : ' . print_r($conditions, true));

            $count = count($conditions);
            $ii = 0;

            // Return if filter conditions are empty
            if ($count <= 0) {
                return "";
            }

            $sql_string = " WHERE ";

            foreach ($conditions as $key => $condition) {

                if (isset($condition['conditions'])) {
                    $sql_string .= $this->get_condition_group_sql($condition, $table_name);
                } else {
                    $sql_string .= $this->get_single_condition_sql($condition, $table_name);
                }

                if ($ii < $count - 1) {
                    $sql_string .= " AND ";
                }
                $ii++;
            }

            $sql_string = rtrim($sql_string, ' AND ');

            error_log('$sql_string : ' . $sql_string);
            return $sql_string;
        }

        public function get_condition_group_sql($condition_group, $table_name)
        {
            error_log('$condition_group : ' . print_r($condition_group, true));

            $sql_string = '';
            $jj = 0;
            $count = count($condition_group['conditions']);

            // Return if filter conditions are empty
            if ($count <= 0) {
                return "";
            }

            $sql_string .= " ( ";

            foreach ($condition_group['conditions'] as $key1 => $condition) {
                $sql_string .= $this->get_single_condition_sql($condition, $table_name);
                if ($jj < $count - 1) {
                    $sql_string .= isset($condition_group['relation']) ? " " . $condition_group['relation'] . " " : " AND ";
                }

                $jj++;
            }

            $sql_string .= " ) ";

            return $sql_string;
        }

        public function convert_condition_to_condition_group($condition, $relation)
        {
            $condition_group = [
                'conditions' => [
                    $condition,
                ],
                'relation' => $relation,
            ];

            return $condition_group;
        }

        public function get_single_condition_sql($condition, $table_name)
        {
            error_log('$condition : ' . print_r($condition, true));
            $sql_string = '';
            $condition = $this->condition_modifier($condition);
            $operand1 = $condition['operand_1'];
            $mysql_operator = $condition['mysql_operator'];
            $operand2 = $condition['operand_2'];

            error_log('$condition after : ' . print_r($condition, true));

            if ($condition['data_type'] == 'datetime') {
                // Todo: Detect operand2 format and convert to unix timestamp
                $sql_string .= $this->date_statements($condition, $table_name);
                // $sql_string .= "FROM_UNIXTIME(CAST($table_name.$operand1 / 1000 as UNSIGNED)) $mysql_operator '$operand2'";
            } else if ($condition['data_type'] == 'number') {
                $sql_string .= "CAST($table_name.$operand1 as UNSIGNED) $mysql_operator $operand2";
            } else if ($condition['data_type'] == 'json') {
                $sql_string .= "JSON_EXTRACT($table_name.$operand1, '$.value') $mysql_operator $operand2";
            } else {
                $sql_string .= "TRIM(" . $table_name . "." . $operand1 . ") " . $mysql_operator . " " . $operand2;
            }

            return $sql_string;
        }

        public function date_statements($condition, $table_name)
        {
            $sql_string = '';
            $operand1 = $condition['operand_1'];
            $mysql_operator = $condition['mysql_operator'];
            $operand2 = $condition['operand_2'];
            $operand2_meta = isset($condition['operand_2_meta']) ? $condition['operand_2_meta'] : '';
            $operand1_date_format = isset($condition['operand_1_date_format']) ? $condition['operand_1_date_format'] : 'js_timestamp';
            $operator = isset($condition['operator']) ? $condition['operator'] : '';

            if ($condition['data_type'] != 'datetime') {
                return $sql_string;
            }

            if ($operand2 == 'last_seven_days' || $operand2 == 'last_thirty_days' || $operand2 == 'last_n_days' || $operand2 == 'next_n_days') {
                if ($mysql_operator == 'is' || $mysql_operator == '=') {
                    $mysql_operator = 'BETWEEN';
                } else if ($mysql_operator == 'is_not' || $mysql_operator == '!=') {
                    $mysql_operator = 'NOT BETWEEN';
                }
            }

            if ($operand1_date_format == "js_timestamp") {
                $operand1_query_string = "FROM_UNIXTIME(CAST($table_name.$operand1 / 1000 as UNSIGNED))";
            } else {
                $operand1_query_string = $table_name . "." . $operand1;
            }

            // error_log('$mysql_operator : ' . $mysql_operator);
            // error_log('date_statements $condition : ' . print_r($condition, true));

            /* Special case for null and not null */
            if ($operator == 'null' || $operator == 'not_null' || $operator == 'is_null' || $operator == 'is_not_null') {
                $sql_string .= " $operand1_query_string $mysql_operator ";
                return $sql_string;
            }

            if ($operand2 == 'today') {
                $sql_string .= "DATE($operand1_query_string) $mysql_operator CURDATE()";
            } else if ($operand2 == 'tomorrow') {
                $sql_string .= "DATEDIFF($operand1_query_string, CURDATE()) $mysql_operator 1";
            } else if ($operand2 == 'yesterday') {
                $sql_string .= "DATEDIFF($operand1_query_string, CURDATE()) $mysql_operator -1";
            } else if ($operand2 == 'last_seven_days') {
                $sql_string .= "$operand1_query_string $mysql_operator  CURDATE() - INTERVAL 7 DAY AND CURDATE()";
            } else if ($operand2 == 'last_thirty_days') {
                $sql_string .= "$operand1_query_string $mysql_operator  CURDATE() - INTERVAL 30 DAY AND CURDATE()";
            } else if ($operand2 == 'last_n_days') {
                $operand2_meta = isset($operand2_meta) ? (int) $operand2_meta : 0;
                $sql_string .= "$operand1_query_string $mysql_operator  CURDATE() - INTERVAL $operand2_meta DAY AND CURDATE()";
            } else if ($operand2 == 'next_n_days') {
                $operand2_meta = isset($operand2_meta) ? (int) $operand2_meta : 0;
                $sql_string .= "$operand1_query_string $mysql_operator  CURDATE() AND CURDATE() + INTERVAL $operand2_meta DAY";
            } else if ($operand2 == 'current_month') {
                $sql_string .= "MONTH($operand1_query_string) $mysql_operator MONTH(CURRENT_DATE())";
            } else if ($operand2 == 'current_year') {
                $sql_string .= "YEAR($operand1_query_string) $mysql_operator YEAR(CURRENT_DATE())";
            } else if ($operand2 == 'month') {
                $sql_string .= "MONTH($operand1_query_string) $mysql_operator CAST($operand2_meta as UNSIGNED)";
            } else if ($operand2 == 'year') {
                $sql_string .= "YEAR($operand1_query_string) $mysql_operator CAST($operand2_meta as UNSIGNED)";
            } else if ($operand2 == 'exact_date') {
                $sql_string .= "DATE($operand1_query_string) $mysql_operator DATE(FROM_UNIXTIME(CAST($operand2_meta / 1000 as UNSIGNED)))";
            } else {
                $sql_string .= "DATE($operand1_query_string) $mysql_operator DATE(FROM_UNIXTIME(CAST($operand2 / 1000 as UNSIGNED)))";
            }

            return $sql_string;

        }

        public function condition_modifier($condition)
        {
            if ($this->is_general_condition($condition)) {
                $condition = $this->general_condition_modifier($condition);
                return $condition;
            }

            // Number and Datetime
            $condition = $this->number_condition_modifier($condition);

            // Text, RichText
            $condition = $this->string_condition_modifier($condition);

            error_log('$condition condition_modifier : ' . print_r($condition, true));
            return $condition;
        }

        public function is_general_condition($condition)
        {
            $general_conditions = array('empty', 'is_empty', 'not_empty', 'is_not_empty', 'is_null', 'is_not_null');
            return in_array($condition['operator'], $general_conditions);
        }

        public function general_condition_modifier($condition)
        {
            if ($condition['operator'] == 'empty' || $condition['operator'] == 'is_empty') {
                $condition['operand_2'] = "''";
                $condition['mysql_operator'] = "=";
            } else if ($condition['operator'] == 'not_empty' || $condition['operator'] == 'is_not_empty') {
                $condition['operand_2'] = "''";
                $condition['mysql_operator'] = "<>";
            } else if ($condition['operator'] == 'is_null') {
                $condition['operand_2'] = "";
                $condition['mysql_operator'] = "IS NULL";
            } else if ($condition['operator'] == 'is_not_null') {
                $condition['operand_2'] = "";
                $condition['mysql_operator'] = "IS NOT NULL";
            }

            return $condition;
        }

        public function number_condition_modifier($condition)
        {
            // Allow only number and datetime
            if ($condition['data_type'] != 'number' && $condition['data_type'] != 'datetime') {
                return $condition;
            }

            $condition['mysql_operator'] = $condition['operator'];

            return $condition;

        }

        public function string_condition_modifier($condition)
        {

            // Allow only text and json
            if ($condition['data_type'] != 'text' && $condition['data_type'] != 'json') {
                // $condition['mysql_operator'] = $condition['operator'];
                return $condition;
            }

            error_log('$condition[operator] : ' . $condition['operator']);

            if ($condition['operator'] == 'contains') {
                $condition['operand_2'] = "%" . $condition['operand_2'] . "%";
                $condition['mysql_operator'] = "LIKE";
            } else if ($condition['operator'] == 'does_not_contain') {
                $condition['operand_2'] = "%" . $condition['operand_2'] . "%";
                $condition['mysql_operator'] = "NOT LIKE";
            } else if ($condition['operator'] == 'starts_with') {
                $condition['operand_2'] = $condition['operand_2'] . "%";
                $condition['mysql_operator'] = "LIKE";
            } else if ($condition['operator'] == 'ends_with') {
                $condition['operand_2'] = "%" . $condition['operand_2'];
                $condition['mysql_operator'] = "LIKE";
            } else if ($condition['operator'] == 'is') {
                $condition['operand_2'] = $condition['operand_2'];
                $condition['mysql_operator'] = "=";
            } else if ($condition['operator'] == 'is_not') {
                $condition['operand_2'] = $condition['operand_2'];
                $condition['mysql_operator'] = "<>";
            } else {
                $condition['mysql_operator'] = $condition['operator'];
            }

            //
            // SHOULD ADD ' '
            $condition['operand_2'] = "'" . $condition['operand_2'] . "'";

            return $condition;
        }

        public function delete_table($table_id)
        {
            global $wpdb;
            $table_name = TABLESOME_TABLE_NAME . '_' . $table_id;
            $table_name = $wpdb->prefix . $table_name;
            $query = "DROP TABLE IF EXISTS $table_name";
            $result = $wpdb->query($query);
            // error_log('delete_table $result : ' . $result);
            return $result;
        }

        public function empty_the_table($table_id)
        {
            global $wpdb;
            $table_name = TABLESOME_TABLE_NAME . '_' . $table_id;
            $table_name = $wpdb->prefix . $table_name;
            $query = "DELETE FROM $table_name";
            $result = $wpdb->query($query);
            return $result;
        }

    } // END CLASS
}

//
