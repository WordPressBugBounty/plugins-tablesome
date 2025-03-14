<?php

namespace Tablesome\Includes\Modules\Myque;

// Query Builder for MySQL
if (!class_exists('\Tablesome\Includes\Modules\Myque\Myque')) {
    class Myque
    {

        public $mysql;

        public function __construct()
        {
            // $this->doctrine = new \Tablesome\Includes\Modules\Myque\Doctrine();
            $this->mysql = new \Tablesome\Includes\Modules\Myque\Mysql();
        }

        public function create_table($table_name, $columns)
        {
            return $this->mysql->create_table($table_name, $columns);
        }

        public function set_schema()
        {
            $this->mysql->set_schema();
        }

        public function get_row_count($table_id)
        {
            return $this->mysql->get_row_count($table_id);
        }

        public function check_and_add_missing_columns($record, $table_name)
        {
            return $this->mysql->check_and_add_missing_columns($record, $table_name);
        }

        public function insert_record($record, $table_name, $insert_args)
        {
            $response = $this->mysql->insert_record($record, $table_name, $insert_args);
            return $response;
        }

        public function insert_column($args, $response = array())
        {
            $response = $this->mysql->insert_column($args, $response);
            return $response;
        }

        public function duplicate_column($args, $response = array())
        {
            $response = $this->mysql->duplicate_column($args, $response);
            return $response;
        }

        public function get_rows($args)
        {
            $results = $this->mysql->get_rows($args);
            return $results;
        }

        public function get_row($record_id, $args)
        {
            return $this->mysql->get_row($record_id, $args);
        }

        // public function doctrine_get_rows($args) {
        //     $results = $this->doctrine->get_rows($args);
        //     return $results;
        // }

        public function delete_table($table_id)
        {
            $result = $this->mysql->delete_table($table_id);
            return $result;
        }

        public function empty_the_table($table_id)
        {
            $result = $this->mysql->empty_the_table($table_id);
            return $result;
        }

    }
}
