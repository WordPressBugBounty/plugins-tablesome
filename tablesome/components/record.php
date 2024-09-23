<?php

namespace Tablesome\Components;

if (!class_exists('\Tablesome\Components\Record')) {
    class Record
    {

        public function __construct()
        {
        }

        public function get_empty_record($columns)
        {
            $date = date('Y-m-d H:i:s');

            $empty_record = [
                "record_id" => 0,
                "content" => $this->get_empty_cells($columns),
                "rank_order" => "",
                "created_at" => $date,
                "updated_at" => $date,
                'is_editable' => false,
                'is_deletable' => false,
            ];

            return $empty_record;
        }

        public function get_empty_cells($columns = array())
        {
            if (empty($columns)) {
                return [];
            }
            $cells = [];
            foreach ($columns as $column_key => $column) {
                $cell = [
                    'type' => $column["format"],
                    'html' => "",
                    'value' => "",
                ];

                if ($cell["type"] == "url" || $cell["type"] == "button") {
                    $cell["link"] = "";
                    $cell["linkText"] = "";
                }
                $cells[$column_key] = $cell;
            }

            return $cells;

        }

    } // end class
}
