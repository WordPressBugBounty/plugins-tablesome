<?php

namespace Tablesome\Includes\Modules\Datatable;

if (!class_exists('\Tablesome\Includes\Modules\Datatable\Settings')) {
    class Settings
    {

        // public $source;

        public function __construct()
        {
        }

        public function save($params)
        {

            // error_log('save_table_settings() $params : ' . print_r($params, true));
            // Previous saved value
            $previous_sort_settings = $this->get_previous_sort_settings($params['table_id']);

            // Only Admin User can change the sort settings
            if ($this->is_admin_user()) {
                $sort = isset($params['sort']) ? $params['sort'] : $previous_sort_settings;
            } else {
                $sort = $previous_sort_settings;
            }

            set_tablesome_table_triggers($params['table_id'], $params['triggers']);

            set_tablesome_data($params['table_id'],
                array(
                    'editorState' => $params['editorState'],
                    'options' => array(
                        'display' => $params['display'],
                        'style' => $params['style'],
                        'access_control' => $params['access_control'],
                        'sort' => $sort,
                    ),
                    'columns' => $params['columns'],
                    'meta' => array(
                        'last_column_id' => $params['last_column_id'],
                    ),
                )
            );

            $meta_data = get_tablesome_data($params['table_id']);

            $response = array(
                'table_id' => $params['table_id'],
                'table_meta' => $meta_data,
                'status' => 'success',
            );

            return $response;
        }

        public function is_admin_user()
        {
            if (current_user_can('manage_options')) {
                return true;
            }
            return false;
        }

        public function get_previous_sort_settings($table_id)
        {
            $previous_table_meta_data = get_tablesome_data($table_id);
            // error_log('$previous_table_meta_data : ' . print_r($previous_table_meta_data, true));
            $previous_sort_settings = array();
            if (isset($previous_table_meta_data['options']) && isset($previous_table_meta_data['options']['sort'])) {
                $previous_sort_settings = $previous_table_meta_data['options']['sort'];
            }

            return $previous_sort_settings;
        }
    } // END CLASS
}
