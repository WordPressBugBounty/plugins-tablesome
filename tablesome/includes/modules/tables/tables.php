<?php

namespace Tablesome\Includes\Modules\Tables;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\Tables\Tables')) {
    class Tables
    {
        public function get_tables()
        {
            $tables = get_posts(
                array(
                    'post_type' => TABLESOME_CPT,
                    'numberposts' => -1,
                    'post_status' => array('publish', 'private'),
                )
            );

            return $tables;
        }
    } // END CLASS
}
