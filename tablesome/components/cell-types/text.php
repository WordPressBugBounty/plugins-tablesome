<?php

namespace Tablesome\Components\CellTypes;

if (!class_exists('\Tablesome\Components\CellTypes\Text')) {
    class Text
    {
        public $utils;

        public function __construct()
        {
            $this->utils = new \Tablesome\Includes\Utils();
            add_filter("tablesome_get_cell_data", [$this, 'get_text_data']);
        }

        public function get_text_data($cell)
        {
            if ($cell['type'] != 'text') {
                return $cell;
            }

            // error_log("isset(cell[value]) = " . isset($cell["value"]));
            // error_log("empty(cell[value]) = " . empty($cell["value"]));

            $isNotEmptyExceptZero = $this->utils->isNotEmptyExceptZero($cell["value"]);

            $escaped_value = ($isNotEmptyExceptZero && gettype($cell["value"]) == "string") ? html_entity_decode($cell["value"]) : "";
            $cell["value"] = $escaped_value;
            $cell["html"] = $escaped_value;

            return $cell;
        }

    } // END CLASS
}
