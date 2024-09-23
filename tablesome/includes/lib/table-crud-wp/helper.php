<?php

namespace Tablesome\Includes\Lib\Table_Crud_WP;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Lib\Table_Crud_WP\Helper')) {
    class Helper
    {

        public $utils;

        public function __construct()
        {
            $this->utils = new \Tablesome\Includes\Utils();
        }

        /**
         * Use of this method, Getting the table columns from the table meta-data
         *
         * @param [array] $meta_data
         * @return array
         */
        public function get_table_columns($meta_data)
        {
            $columns = array();
            if (!isset($meta_data['columns'])) {
                return $columns;
            }

            foreach ($meta_data['columns'] as $column) {
                $id = $column['id'];
                $columns[$id] = 'column_' . $id;
            }
            return $columns;
        }

        public function get_column_ided_record($table_id, $meta_data, $record)
        {
            // Additional information in $record

            // $record['post_id'] = $table_id;
            // $record['record_id'] = isset($record['record_id']) ? $record['record_id'] : 0;
            // $user_record['rank_order'] = isset($record['rank_order']) ? $record['rank_order'] : '';

            $columns = isset($meta_data['columns']) ? $meta_data['columns'] : [];
            $content = isset($record['content']) && !empty($record['content']) ? $record['content'] : [];

            // error_log('$columns: ' . print_r($columns, true)):

            /** First: Set the empty values to the db cells by columns */
            $cell_values = $this->get_column_ided_empty_record($columns, $content);

            // error_log('$columns' . print_r($columns, true));
            // error_log('$content' . print_r($content, true));
            // error_log('get_column_ided_empty_record $cell_values: ' . print_r($cell_values, true));

            if (empty($content)) {return $cell_values;}

            $cell_index = 0;
            foreach ($record['content'] as $cell_data) {

                /** Get columnId from table meta-column by using the cell index*/
                // default column id
                $column_id = isset($columns[$cell_index]['id']) ? $columns[$cell_index]['id'] : $cell_index;
                if (isset($cell_data['column_id'])) {
                    $column_id = $cell_data['column_id'];
                }

                $column = $this->get_column_by_id($columns, $column_id);

                // Column Format
                $column_format = isset($column['format']) ? $column['format'] : 'text';

                $cell_value = "";
                if (!is_array($cell_data)) {
                    $cell_value = $cell_data;
                }

                // error_log('$cell_data: ' . print_r($cell_data, true));

                $cell_value = isset($cell_data['value']) ? $cell_data['value'] : $cell_value;
                $cell_html = isset($cell_data['html']) ? $cell_data['html'] : $cell_value;

                if ($column_format == 'text' || $column_format == 'textarea') {
                    $cell_value = (string) $cell_value;
                }

                // error_log('2 $cell_value: ' . $cell_value);

                $cell_value = $this->utils->isNotEmptyExceptZero($cell_value) ? addslashes($cell_value) : '';
                $cell_html = $this->utils->isNotEmptyExceptZero($cell_html) ? addslashes($cell_html) : '';

                // error_log('3 $cell_value: ' . $cell_value);

                // $column_format = isset($columns[$cell_index]['format']) ? $columns[$cell_index]['format'] : 'text';

                // if (isset($cell_data['type'])) {
                //     $column_format = $cell_data['type'];
                // }

                // error_log('$cell_data' . print_r($cell_data, true));

                // DB Column Name
                $db_column_name = 'column_' . $column_id;
                $db_meta_column_name = $db_column_name . '_meta';

                $meta_columns = ($column_format == 'url' || $column_format == 'button' || $column_format == 'file');

                if ($meta_columns) {
                    // $cell_value = $this->get_converted_link_content($cell_data);

                    $cell_meta_args = array();

                    if ($column_format == 'file') {
                        $cell_meta_args['file_url'] = isset($cell_data['file_url']) ? $cell_data['file_url'] : '';
                        $cell_meta_args['type'] = isset($cell_data['type']) ? $cell_data['type'] : '';
                        $cell_meta_args['file_type'] = isset($cell_data['file_type']) ? $cell_data['file_type'] : '';
                        $cell_meta_args['link'] = isset($cell_data['link']) ? $cell_data['link'] : '';
                    } else {
                        $cell_meta_args = array(
                            'linkText' => isset($cell_data['linkText']) ? $cell_data['linkText'] : '',
                            'value' => isset($cell_data['value']) ? $cell_data['value'] : '',
                        );

                    }
                    $cell_values[$db_meta_column_name] = esc_sql(wp_json_encode($cell_meta_args, JSON_UNESCAPED_UNICODE));
                }

                // Should store the cell prop html value instead of value prop if the cell-format is textarea
                if ($column_format == 'textarea') {
                    $cell_value = $cell_html;
                }

                $cell_values[$db_column_name] = $cell_value;

                $cell_index++;
            }

            return $cell_values;
        }

        private function get_column_by_id($columns, $column_id)
        {
            $selected_column = false;

            for ($ii = 0; $ii < count($columns); $ii++) {
                $column = $columns[$ii];
                if ($column['id'] == $column_id) {
                    $selected_column = $column;
                    break;
                }
            }

            return $selected_column;
        }

        public function get_converted_link_content($cell_data)
        {
            $content = '';

            foreach ($cell_data as $cell_key => $cell_value) {
                $content .= '[' . $cell_key . ']';

                $cell_value = str_replace('(', 'TS_{', $cell_value);
                $cell_value = str_replace(')', 'TS_}', $cell_value);

                $content .= '(' . $cell_value . ')';
            }
            // $content = implode("||", $cell_data);
            return $content;
        }

        public function check_column_id_in_user_record($user_record, $column_id)
        {
            $does_column_id_exist_in_submission = false;
            foreach ($user_record as $cell_data) {
                if (isset($cell_data['column_id']) && $cell_data['column_id'] == $column_id) {
                    $does_column_id_exist_in_submission = true;
                    break;
                }
            }
            return $does_column_id_exist_in_submission;

        }
        public function get_column_ided_empty_record($columns, $user_record)
        {
            $record = [];
            foreach ($columns as $column) {
                $column_id = $column['id'];

                $does_column_id_exist_in_submission = $this->check_column_id_in_user_record($user_record, $column_id);
                if ($does_column_id_exist_in_submission == false) {
                    continue;
                }

                $column_format = isset($column['format']) ? $column['format'] : 'text';

                $db_column_name = 'column_' . $column_id;
                $db_meta_column_name = $db_column_name . '_meta';

                $meta_columns = ($column_format == 'url' || $column_format == 'button');

                if ($meta_columns) {
                    $record[$db_meta_column_name] = '';
                }

                $record[$db_column_name] = '';
            }
            return $record;
        }
    }
}
