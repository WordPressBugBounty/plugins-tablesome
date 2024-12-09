<?php

if (!defined('ABSPATH')) {
    exit;
}

class Tablesome_Event_Log_Table_Row extends Tablesome\BerlinDB\Database\Row
{

    public function __construct($item)
    {
        parent::__construct($item);
    }

}