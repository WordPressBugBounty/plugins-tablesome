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

        /**
         * Return the canonical, WordPress-prefixed form of a Tablesome table
         * identifier. Callers historically pass both generated forms, but SQL
         * must always target the table belonging to the current site prefix.
         *
         * @param mixed $table_name Tablesome table name.
         * @return string|false Canonical table name, or false when invalid.
         */
        private function normalize_tablesome_table_name($table_name)
        {
            if (!is_string($table_name)) {
                return false;
            }

            $table_prefix = $this->wpdb->prefix;

            // Check the complete prefixed form first. A custom prefix such as
            // "tablesome_" starts with the unprefixed table namespace, so a
            // prefix check based on strpos() would mistake the unprefixed name
            // for an already-prefixed identifier.
            $table_base_pattern = preg_quote(TABLESOME_TABLE_NAME, '/');
            $prefixed_pattern = '/^' . preg_quote($table_prefix, '/') . $table_base_pattern . '_[0-9]+$/D';
            if ($table_prefix !== '' && preg_match($prefixed_pattern, $table_name) === 1) {
                return $table_name;
            }

            $unprefixed_pattern = '/^' . $table_base_pattern . '_[0-9]+$/D';
            if (preg_match($unprefixed_pattern, $table_name) === 1) {
                return $table_prefix . $table_name;
            }

            return false;
        }

        public function create_table($table_name, $columns)
        {
            $column_names = array();

            // error_log('columns : ' . print_r($columns, true));

            foreach ($columns as $column) {
                $name = "column_" . $column['id'];
                $column_names[] = $name;

            }

            $table_name = $this->normalize_tablesome_table_name($table_name);
            if ($table_name === false || empty($columns)) {
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
            $table_id = intval($table_id);
            if( empty($table_id) || $table_id == 0 ){
                return 0;
            }
            $table_name = TABLESOME_TABLE_NAME . '_' . $table_id;
            $table_name = $this->wpdb->prefix . $table_name;
            $query = "SELECT COUNT(*) FROM `$table_name`";
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

            $table_name = $this->normalize_tablesome_table_name($table_name);
            if ($table_name === false) {
                error_log('insert_record: invalid table name');
                return 0;
            }

            if (!isset($record) || is_null($record)) {
                return 0;
            }

            if (count($record) == 0) {
                return 0;
            }

            // Filter to only valid column names, keeping columns and values in sync
            $columns = [];
            $values = [];
            foreach ($record as $key => $value) {
                $column_name = $this->sanitize_column_name($key);
                if (empty($column_name)) {
                    continue;
                }
                $columns[] = "`$column_name`";
                $values[] = "'" . esc_sql($value) . "'";
            }

            if (empty($columns)) {
                return 0;
            }

            $query = "INSERT INTO $table_name (" . implode(", ", $columns) . ") SELECT " . implode(", ", $values);

            $query .= " ";
            $enabled_prevent_duplication = isset($insert_args['enable_duplication_prevention']) && $insert_args['enable_duplication_prevention'] == 1 ? true : false;
            $enabled_limit_submission = isset($insert_args['enable_submission_limit']) && $insert_args['enable_submission_limit'] == 1 ? true : false;
            $submission_limit = isset($insert_args['max_allowed_submissions']) && !empty($insert_args['max_allowed_submissions']) ? intval($insert_args['max_allowed_submissions']) : 100;
            $prevent_field_column = isset($insert_args['prevent_field_column']) ? $this->sanitize_column_name($insert_args['prevent_field_column']) : "";
            $can_add_prevent_query = ($enabled_prevent_duplication || $enabled_limit_submission);

            if ($can_add_prevent_query) {
                // WHERE clause
                $query .= "FROM (SELECT COUNT(*) cnt FROM " . $table_name . ") sub";
                $query .= " "; // space
                $query .= "WHERE ";

                if ($enabled_prevent_duplication && !empty($prevent_field_column) && isset($record[$prevent_field_column])) {
                    $query .= $wpdb->prepare(
                        "NOT EXISTS (SELECT 1 FROM " . $table_name . " WHERE `" . $prevent_field_column . "` = %s ) ",
                        $record[$prevent_field_column]
                    );
                }

                $query .= " "; // space

                if ($enabled_prevent_duplication && $enabled_limit_submission) {
                    $query .= "AND ";
                }

                if ($enabled_limit_submission) {
                    $query .= "cnt < " . $submission_limit . ";";
                }
            }

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

            $table_name = $this->normalize_tablesome_table_name($table_name);
            if ($table_name === false) {
                error_log('update_record: invalid table name');
                return $record;
            }

            $prevent_field_column = isset($insert_args['prevent_field_column']) ? $this->sanitize_column_name($insert_args['prevent_field_column']) : "";
            $prevent_field_value = isset($record[$prevent_field_column]) ? $record[$prevent_field_column] : "";

            if (empty($prevent_field_column)) {
                error_log('update_record: invalid prevent_field_column');
                return $record;
            }

            global $wpdb;
            $assignments = [];
            foreach ($record as $key => $value) {
                $sanitized_key = $this->sanitize_column_name($key);
                if (empty($sanitized_key)) {
                    continue;
                }
                $value = esc_sql($value);
                $assignments[] = "`$sanitized_key` = '$value'";
            }

            if (empty($assignments)) {
                error_log('update_record: no valid columns to update');
                return $record;
            }

            $query = "UPDATE `$table_name` SET " . implode(", ", $assignments);
            $query = $wpdb->prepare(
                $query . " WHERE `$prevent_field_column` = %s LIMIT 1;",
                $prevent_field_value
            );
            $wpdb->query($query);
            return $record;
        }   

        public function insert_column($args , $response = array()){
            global $wpdb;
            $table_name = isset($args['table_name']) ? $args['table_name'] : '';
            $args['table_name'] = $table_name;
            $column_name = $this->sanitize_column_name($args['name']);
            $column_type = $this->sanitize_column_type($args['format']);

            $table_name = $this->normalize_tablesome_table_name($table_name);
            if ($table_name === false || empty($column_name) || empty($column_type)) {
                error_log('insert_column: invalid column name or type');
                $response['new_column_created'] = false;
                return $response;
            }

            $query = "ALTER TABLE `$table_name` ADD `$column_name` $column_type DEFAULT ''";
            // error_log('insert_column $query : ' . $query);
            $response['new_column_created'] = $wpdb->query($query);
            return $response;
        }

        public function copy_column_content($args, $response = array())
        {
            global $wpdb;
            $table_name = isset($args['table_name']) ? $args['table_name'] : '';
            $args['table_name'] = $table_name;
            $source_column = isset($args['source_column']) ? $this->sanitize_column_name($args['source_column']) : '';
            $target_column = isset($args['target_column']) ? $this->sanitize_column_name($args['target_column']) : '';

            $table_name = $this->normalize_tablesome_table_name($table_name);
            if($table_name === false || empty($source_column) || empty($target_column)){
                error_log('copy_column_content: $source_column or $target_column is empty or invalid');
                return $response;
            }

            $query = "UPDATE `$table_name` SET `$target_column` = `$source_column`";
            $response['copied_column_records'] = $wpdb->query($query);
            return $response;
        }

        public function duplicate_column($args, $response = array())
        {

            // error_log('args: ' . print_r($args, true));
            global $wpdb;
            $table_name = isset($args['table_name']) ? $args['table_name'] : '';
            $args['table_name'] = $table_name;
            $source_column = isset($args['source_column']) ? $this->sanitize_column_name($args['source_column']) : '';
            $target_column = isset($args['target_column']) ? $this->sanitize_column_name($args['target_column']) : '';

            $table_name = $this->normalize_tablesome_table_name($table_name);
            if($table_name === false || empty($source_column) || empty($target_column)){
                error_log('duplicate_column: $source_column or $target_column is empty or invalid');
                return $response;
            }

            // If target column already exists, return
            $columns = $this->get_table_columns($table_name);
            if($this->does_column_exists($columns, $target_column)){
                $response = $this->copy_column_content($args, $response);
                return $response;
            }

            // Create New Column
            $query = "ALTER TABLE `$table_name` ADD `$target_column` TEXT NOT NULL";
            $response['new_column_created'] = $wpdb->query($query);

            // Copy Data from Source Column to Target Column
            $query = "UPDATE `$table_name` SET `$target_column` = `$source_column`";
            // error_log('$query : ' . $query);
            $response['copied_column_records'] = $wpdb->query($query);

            return $response;
        }

        public function get_row($record_id, $args)
        {
            if (empty($record_id)) {
                return null;
            }
            $record_id = intval($record_id);
            $table_name = isset($args['table_name']) ? $args['table_name'] : '';
            $table_name = $this->normalize_tablesome_table_name($table_name);
            if ($table_name === false) {
                return null;
            }
            $query = $this->wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $record_id);
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

            $table_name = isset($args['table_name'])
                ? $this->normalize_tablesome_table_name($args['table_name'])
                : false;
            if ($table_name === false) {
                return [];
            }

            $args['table_name'] = $table_name;

            $query = "SELECT * FROM $table_name";
            if (isset($args['where'])) {
                $query .= $this->convert_conditions_to_sql_string($args['where'], $table_name);
            }

            $query .= $this->orderby($args);
            $query .= " LIMIT " . max(1, intval($args['limit']));

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

            // Validate orderby column names against actual table columns
            $table_columns = $this->get_table_columns($args['table_name']);
            $allowed_columns = ['id', 'rank_order'];
            foreach ($table_columns as $col) {
                $allowed_columns[] = $col['Field'];
            }

            foreach ($args['orderby'] as $key => $value) {
                $value = sanitize_key($value);
                if (in_array($value, $allowed_columns, true)) {
                    $orderByArgs[] = "`" . $args['table_name'] . "`.`" . $value . "`";
                }
            }

            if (empty($orderByArgs)) {
                $orderByArgs[] = "`" . $args['table_name'] . "`.`id`";
            }

            $sql_string = $sql_string . implode(',', $orderByArgs);

            // Whitelist order direction
            $order = strtoupper(trim($args['order']));
            $sql_string .= in_array($order, ['ASC', 'DESC'], true) ? " " . $order : " ASC";

            return $sql_string;
        }

        public function get_table_columns($table_name)
        {
            global $wpdb;

            $table_name = $this->normalize_tablesome_table_name($table_name);
            if ($table_name === false) {
                return [];
            }

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

            $table_name = $this->normalize_tablesome_table_name($table_name);
            if (!is_array($conditions) || $table_name === false) {
                return "";
            }

            $columns = $this->get_table_columns($table_name);
            foreach ($conditions as $key => $condition) {
                if (!is_array($condition)) {
                    unset($conditions[$key]);
                    continue;
                }
                $column_name = isset($condition['operand_1']) ? $condition['operand_1'] : '';

                // Remove columns which are not in Table
                if (!$this->does_column_exists($columns, $column_name)) {
                    unset($conditions[$key]);
                    continue;
                }

                if (isset($condition['conditions'])) {
                    if (!is_array($condition['conditions'])) {
                        unset($conditions[$key]);
                        continue;
                    }

                    foreach ($condition['conditions'] as $nested_key => $nested_condition) {
                        $nested_column_name = isset($nested_condition['operand_1']) ? $nested_condition['operand_1'] : '';
                        if (!$this->does_column_exists($columns, $nested_column_name)) {
                            unset($condition['conditions'][$nested_key]);
                        }
                    }

                    if (empty($condition['conditions'])) {
                        unset($conditions[$key]);
                        continue;
                    }

                    $conditions[$key] = $condition;
                }

                $operator = isset($condition['operator']) ? $condition['operator'] : '';
                if ($operator == 'empty' || $operator == 'is_empty') {
                    // Convert empty and not_empty to a condition_group
                    $conditions[$key] = $this->convert_condition_to_condition_group($condition, 'OR');
                    $new_condition = $condition;
                    $new_condition['operator'] = 'is_null';
                    array_push($conditions[$key]['conditions'], $new_condition);
                }

                if ($operator == 'not_empty' || $operator == 'is_not_empty') {
                    // Convert empty and not_empty to a condition_group
                    $conditions[$key] = $this->convert_condition_to_condition_group($condition, 'AND');
                    $new_condition = $condition;
                    $new_condition['operator'] = 'is_not_null';
                    array_push($conditions[$key]['conditions'], $new_condition);
                }
            }

            // error_log('$conditions : ' . print_r($conditions, true));

            // Return if filter conditions are empty
            if (empty($conditions)) {
                return "";
            }

            $condition_sql = [];
            foreach ($conditions as $condition) {

                if (isset($condition['conditions'])) {
                    $sql = $this->get_condition_group_sql($condition, $table_name);
                } else {
                    $sql = $this->get_single_condition_sql($condition, $table_name);
                }

                if ($sql !== '') {
                    $condition_sql[] = $sql;
                }
            }

            return empty($condition_sql) ? "" : " WHERE " . implode(" AND ", $condition_sql);
        }

        public function get_condition_group_sql($condition_group, $table_name)
        {
            if (!isset($condition_group['conditions']) || !is_array($condition_group['conditions'])) {
                return "";
            }

            $relation = isset($condition_group['relation']) ? strtoupper($condition_group['relation']) : 'AND';
            if ($relation !== 'AND' && $relation !== 'OR') {
                $relation = 'AND';
            }

            $condition_sql = array();
            foreach ($condition_group['conditions'] as $condition) {
                $sql = $this->get_single_condition_sql($condition, $table_name);
                if ($sql !== '') {
                    $condition_sql[] = $sql;
                }
            }

            if (empty($condition_sql)) {
                return "";
            }

            return " ( " . implode(" " . $relation . " ", $condition_sql) . " ) ";
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
            $sql_string = '';
            $table_name = $this->normalize_tablesome_table_name($table_name);
            if (!is_array($condition) || $table_name === false) {
                return $sql_string;
            }

            $data_type = isset($condition['data_type']) && is_string($condition['data_type'])
                ? strtolower(trim($condition['data_type']))
                : '';
            $data_type_aliases = [
                'string' => 'text',
                'array' => 'json',
                'date' => 'datetime',
            ];
            if (isset($data_type_aliases[$data_type])) {
                $data_type = $data_type_aliases[$data_type];
            }
            if (!in_array($data_type, ['text', 'json', 'number', 'datetime'], true)) {
                return $sql_string;
            }
            $condition['data_type'] = $data_type;

            $operand1 = isset($condition['operand_1']) ? $this->sanitize_column_name($condition['operand_1']) : '';
            if (empty($operand1)) {
                return $sql_string;
            }

            $condition['operand_1'] = $operand1;
            $condition = $this->condition_modifier($condition);
            $operand1 = $condition['operand_1'];
            $mysql_operator = $condition['mysql_operator'];
            $operand2 = $condition['operand_2'];

            if ($condition['data_type'] == 'datetime') {
                // Todo: Detect operand2 format and convert to unix timestamp
                $sql_string .= $this->date_statements($condition, $table_name);
                // $sql_string .= "FROM_UNIXTIME(CAST($table_name.$operand1 / 1000 as UNSIGNED)) $mysql_operator '$operand2'";
            } else if ($condition['data_type'] == 'number') {
                $sql_string .= "CAST($table_name.$operand1 as UNSIGNED) $mysql_operator $operand2";
            } else if ($condition['data_type'] == 'json') {
                $json_expression = "JSON_EXTRACT($table_name.$operand1, '$.value')";
                if (!empty($condition['json_contains'])) {
                    $json_negation = !empty($condition['json_contains_not']) ? 'NOT ' : '';
                    $sql_string .= $json_negation . "JSON_CONTAINS($json_expression, $operand2)";
                } else if ($condition['operator'] == 'is' || $condition['operator'] == 'is_not') {
                    $json_equals = "JSON_CONTAINS($json_expression, $operand2) AND JSON_CONTAINS($operand2, $json_expression)";
                    $sql_string .= $condition['operator'] == 'is_not'
                        ? "NOT ($json_equals)"
                        : "($json_equals)";
                } else {
                    $sql_string .= "CAST($json_expression AS CHAR) $mysql_operator $operand2";
                }
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

            $date_keywords = array(
                'today_and_after',
                'today_and_before',
                'today',
                'tomorrow',
                'yesterday',
                'last_seven_days',
                'last_thirty_days',
                'last_n_days',
                'next_n_days',
                'current_month',
                'current_year',
                'month',
                'year',
                'exact_date',
            );

            if (!in_array($operand2, $date_keywords, true)) {
                $operand2 = (int) $operand2;
            }
            $operand2_meta = (int) $operand2_meta;

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

            if ($operand2 == 'today_and_after') {
                $negate = ($mysql_operator == '!=' || $mysql_operator == 'is_not');
                $sql_string .= "DATE($operand1_query_string) " . ($negate ? '<' : '>=') . " CURDATE()";
            } else if ($operand2 == 'today_and_before') {
                $negate = ($mysql_operator == '!=' || $mysql_operator == 'is_not');
                $sql_string .= "DATE($operand1_query_string) " . ($negate ? '>' : '<=') . " CURDATE()";
            } else if ($operand2 == 'today') {
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

            if ($condition['data_type'] == 'json') {
                return $this->json_condition_modifier($condition);
            }

            // Number and Datetime
            $condition = $this->number_condition_modifier($condition);

            // Text, RichText
            $condition = $this->string_condition_modifier($condition);

            return $condition;
        }

        /**
         * Prepare JSON-backed condition operands without coercing arrays to
         * the string "Array". JSON_CONTAINS handles both scalar and list
         * candidates for array-valued fields; equality compares the persisted
         * JSON value as a JSON document.
         *
         * @param array $condition Condition data.
         * @return array Prepared condition data.
         */
        public function json_condition_modifier($condition)
        {
            $operator = isset($condition['operator']) ? $condition['operator'] : '';
            $operand = isset($condition['operand_2']) ? $condition['operand_2'] : '';
            $json_operand = wp_json_encode(is_array($operand) ? array_values($operand) : $operand);

            if ($json_operand === false) {
                $json_operand = 'null';
            }

            $condition['operand_2'] = "'" . esc_sql($json_operand) . "'";

            if ($operator == 'contains' || $operator == 'does_not_contain') {
                $condition['mysql_operator'] = 'JSON_CONTAINS';
                $condition['json_contains'] = true;
                $condition['json_contains_not'] = ($operator == 'does_not_contain');
                return $condition;
            }

            if ($operator == 'is') {
                $condition['mysql_operator'] = '=';
                return $condition;
            }

            if ($operator == 'is_not') {
                $condition['mysql_operator'] = '<>';
                return $condition;
            }

            $validated_operator = $this->validate_operator(
                $operator,
                $this->get_allowed_string_operators()
            );
            $condition['mysql_operator'] = $validated_operator === false ? '=' : $validated_operator;

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

        /**
         * Get whitelist of allowed SQL operators for number/datetime data types
         * 
         * @return array Whitelist of safe SQL operators
         */
        private function get_allowed_number_operators()
        {
            return array(
                '=',
                '!=',
                '<>',
                '<',
                '<=',
                '>',
                '>=',
                'BETWEEN',
                'NOT BETWEEN',
                'IS',       // Used for date comparisons (e.g., date is today)
                'IS_NOT',   // Used for date exclusions (e.g., date is_not today)
            );
        }

        /**
         * Get whitelist of allowed SQL operators for text/json data types
         * 
         * @return array Whitelist of safe SQL operators
         */
        private function get_allowed_string_operators()
        {
            return array(
                '=',
                '!=',
                '<>',
                'LIKE',
                'NOT LIKE',
            );
        }

        /**
         * Validate and sanitize SQL operator
         * 
         * @param string $operator The operator to validate
         * @param array $allowed_operators Whitelist of allowed operators
         * @return string|false Validated operator or false if invalid
         */
        private function validate_operator($operator, $allowed_operators)
        {
            if (empty($operator) || !is_string($operator)) {
                return false;
            }

            // Remove any whitespace and convert to uppercase for comparison
            $operator_clean = strtoupper(trim($operator));

            // Check if operator is in whitelist
            if (in_array($operator_clean, array_map('strtoupper', $allowed_operators), true)) {
                return $operator_clean;
            }

            return false;
        }

        public function number_condition_modifier($condition)
        {
            // Allow only number and datetime
            if ($condition['data_type'] != 'number' && $condition['data_type'] != 'datetime') {
                return $condition;
            }

            // SECURITY FIX: Validate operator against whitelist
            $allowed_operators = $this->get_allowed_number_operators();
            $validated_operator = $this->validate_operator($condition['operator'], $allowed_operators);

            if ($validated_operator === false) {
                // Invalid operator - use safe default
                $condition['mysql_operator'] = '=';
            } else {
                $condition['mysql_operator'] = $validated_operator;
            }

            // The UI uses the semantic operators `is` and `is_not` for date
            // comparisons. MySQL's `IS` operator is intended for NULL tests,
            // so emit ordinary equality/inequality for date values.
            if ($condition['data_type'] == 'datetime' && is_string($condition['operator'])) {
                $operator = strtolower(trim($condition['operator']));
                if ($operator == 'is') {
                    $condition['mysql_operator'] = '=';
                } else if ($operator == 'is_not') {
                    $condition['mysql_operator'] = '!=';
                }
            }

            // SECURITY FIX: Cast operand_2 to numeric to prevent SQL injection
            // For number conditions, operand_2 is interpolated directly into SQL
            if ($condition['data_type'] == 'number') {
                $condition['operand_2'] = floatval($condition['operand_2']);
            }

            return $condition;

        }

        public function string_condition_modifier($condition)
        {

            // Allow only text and json
            if ($condition['data_type'] != 'text' && $condition['data_type'] != 'json') {
                // $condition['mysql_operator'] = $condition['operator'];
                return $condition;
            }

            // Map user-friendly operators to SQL operators
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
                // SECURITY FIX: Validate operator against whitelist instead of direct assignment
                $allowed_operators = $this->get_allowed_string_operators();
                $validated_operator = $this->validate_operator($condition['operator'], $allowed_operators);

                if ($validated_operator === false) {
                    // Invalid operator - use safe default
                    $condition['mysql_operator'] = '=';
                } else {
                    $condition['mysql_operator'] = $validated_operator;
                }
            }

            // Escape operand_2 value to prevent SQL injection
            $condition['operand_2'] = "'" . esc_sql($condition['operand_2']) . "'";

            return $condition;
        }

        /**
         * Sanitize a column name to prevent SQL injection.
         * Only allows alphanumeric characters and underscores (matching the column_N pattern).
         *
         * @param string $column_name The column name to sanitize
         * @return string Sanitized column name, or empty string if invalid
         */
        public function sanitize_column_name($column_name)
        {
            if (empty($column_name) || !is_string($column_name)) {
                return '';
            }

            // Column names must match pattern: word characters only (letters, digits, underscores)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column_name)) {
                error_log('sanitize_column_name: rejected invalid column name: ' . substr($column_name, 0, 100));
                return '';
            }

            return $column_name;
        }

        /**
         * Sanitize a column type for ALTER TABLE statements.
         * Only allows known MySQL data types.
         *
         * @param string $column_type The column type to sanitize
         * @return string Sanitized column type, or empty string if invalid
         */
        public function sanitize_column_type($column_type)
        {
            if (empty($column_type) || !is_string($column_type)) {
                return '';
            }

            $allowed_types = [
                'text', 'TEXT',
                'varchar', 'VARCHAR',
                'int', 'INT',
                'integer', 'INTEGER',
                'bigint', 'BIGINT',
                'float', 'FLOAT',
                'double', 'DOUBLE',
                'decimal', 'DECIMAL',
                'date', 'DATE',
                'datetime', 'DATETIME',
                'timestamp', 'TIMESTAMP',
                'boolean', 'BOOLEAN',
                'tinyint', 'TINYINT',
                'smallint', 'SMALLINT',
                'mediumint', 'MEDIUMINT',
                'mediumtext', 'MEDIUMTEXT',
                'longtext', 'LONGTEXT',
                'json', 'JSON',
            ];

            $type_upper = strtoupper(trim($column_type));

            // Handle types with length specifiers like VARCHAR(255)
            if (preg_match('/^([A-Z]+)(\(\d+\))?$/', $type_upper, $matches)) {
                if (in_array($matches[1], array_map('strtoupper', $allowed_types), true)) {
                    return $type_upper;
                }
            }

            // Check for exact match
            if (in_array($column_type, $allowed_types, true)) {
                return $column_type;
            }

            error_log('sanitize_column_type: rejected invalid column type: ' . substr($column_type, 0, 100));
            return '';
        }

        public function delete_table($table_id)
        {
            global $wpdb;
            $table_id = intval($table_id);
            $table_name = TABLESOME_TABLE_NAME . '_' . $table_id;
            $table_name = $wpdb->prefix . $table_name;
            $query = "DROP TABLE IF EXISTS `$table_name`";
            $result = $wpdb->query($query);
            // error_log('delete_table $result : ' . $result);
            return $result;
        }

        public function empty_the_table($table_id)
        {
            global $wpdb;
            $table_id = intval($table_id);
            $table_name = TABLESOME_TABLE_NAME . '_' . $table_id;
            $table_name = $wpdb->prefix . $table_name;
            $query = "DELETE FROM `$table_name`";
            $result = $wpdb->query($query);
            return $result;
        }

    } // END CLASS
}

//
