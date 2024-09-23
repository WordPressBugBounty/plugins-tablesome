<?php

namespace Tablesome\Workflow_Library\Actions;

use Tablesome\Includes\Modules\Workflow\Action;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

/* Maps Fields between 2 schemas of any entity type */
/* Examples:
1. Between a Table Fields and a Post Fields,
2. Table and Form Fields
3. CRM and Table
 */

if (!class_exists('\Tablesome\Workflow_Library\Actions\Fields_Map')) {
    class Fields_Map extends Action
    {

    } // end class
}
