<?php

namespace Tablesome\Includes\Modules\TablesomeDB;

if (!class_exists('\Tablesome\Includes\Modules\TablesomeDB\TablesomeDB_New')) {
    class TablesomeDB_New
    {
        public $table_crud_wp;
        public $myque;
        public $access_controller;
        public $wpdb;
        public $record;

        public function __construct()
        {
            global $wpdb;
            $this->table_crud_wp = new \Tablesome\Includes\Lib\Table_Crud_WP\Table_Crud_WP();
            $this->myque = new \Tablesome\Includes\Modules\Myque\Myque();
            $this->access_controller = new \Tablesome\Components\TablesomeDB\Access_Controller();
            $this->wpdb = $wpdb;
            $this->record = new \Tablesome\Components\Record();
        }

        public function save_table_rest($params)
        {

        }

        public function get_table_data($params)
        {

            $table_data = [
                'settings' => [],
                'columns' => [],
                'records' => [],
            ];

            return $table_data;
        }

    } // end class
}
