<?php

namespace Tablesome\Includes\Modules\TablesomeDB;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\TablesomeDB\BerlinDB_Db_Adapter')) {
    class BerlinDB_Db_Adapter
    {

        public function load()
        {
            /**
             * REQUIRE BERLINDB FILES.
             * For now, Tablesome\BerlinDB files are manually required.
             */
            require_once TABLESOME_PATH . 'includes/lib/berlin-db/core/base.php';
            require_once TABLESOME_PATH . 'includes/lib/berlin-db/core/column.php';
            require_once TABLESOME_PATH . 'includes/lib/berlin-db/core/meta.php';
            require_once TABLESOME_PATH . 'includes/lib/berlin-db/core/compare.php';
            require_once TABLESOME_PATH . 'includes/lib/berlin-db/core/date.php';
            require_once TABLESOME_PATH . 'includes/lib/berlin-db/core/query.php';
            require_once TABLESOME_PATH . 'includes/lib/berlin-db/core/row.php';
            require_once TABLESOME_PATH . 'includes/lib/berlin-db/core/schema.php';
            require_once TABLESOME_PATH . 'includes/lib/berlin-db/core/table.php';

        }

    }
}