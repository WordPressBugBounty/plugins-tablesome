<?php

if (!defined('ABSPATH')) {
    exit;
}

class Tablesome_Table_Schema extends \Tablesome\BerlinDB\Database\Schema
{
    public $columns = array();

    public function __construct($columns = [])
    {
        if (!empty($columns)) {
            $this->columns = $columns;
        }
        parent::__construct();
    }
}