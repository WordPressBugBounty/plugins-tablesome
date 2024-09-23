<?php

namespace Tablesome\Includes;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Translations')) {
    class Translations
    {
        public function get_strings()
        {
            return array_merge($this->get_site_strings(), $this->get_dashboard_strings());
        }

        public function get_site_strings()
        {
            $strings = array(
                'first' => __("First", "tablesome"),
                'previous' => __("Prev", "tablesome"),
                'next' => __('Next', 'tablesome'),
                'last' => __('Last', 'tablesome'),
                'sort_ascending' => __('Sort Ascending', 'tablesome'),
                'sort_descending' => __('Sort Descending', 'tablesome'),
                'insert_left' => __('Insert left', 'tablesome'),
                'insert_right' => __('Insert Right', 'tablesome'),
                'move_left' => __('Move left', 'tablesome'),
                'move_right' => __('Move Right', 'tablesome'),
                'duplicate' => __('Duplicate', 'tablesome'),
                'delete' => __('Delete', 'tablesome'),
                'serial_number' => __('S.No', 'tablesome'),

                'search_placeholder' => __('Type to Search ...', 'tablesome'),
                'sort' => __('Sort', 'tablesome'),
                'filter' => __('Filter', 'tablesome'),
                'add_a_filter' => __('Add a Filter', 'tablesome'),
                'filter_placeholder' => __('Type to filter ...', 'tablesome'),
                'column_placeholder' => __('Column name...', 'tablesome'),
                'export' => __('Export', 'tablesome'),
                'export_table' => __('Export Table', 'tablesome'),
                'export_table_header' => __('Export Table as', 'tablesome'),
                'export_table_csv' => __('CSV (.csv)', 'tablesome'),
                'export_table_excel' => __('Excel (.xlsx)', 'tablesome'),
                'export_table_pdf' => __('PDF (.pdf)', 'tablesome'),

                'format_type' => __("Format Type", 'tablesome'),
                'basic' => __('Basic', 'tablesome'),
                'import_table' => __('Import Table', 'tablesome'),
                'add_new_table' => __('Add New Table', 'tablesome'),
                'enter_table_id_alert' => __('Please enter the tablesome table id', 'tablesome'),

                'loading' => __('Loading...', 'tablesome'),

                'add_row' => __('Add Row', 'tablesome'),
                'edit_row' => __('Edit Row', 'tablesome'),
                'delete_row' => __('Delete Row', 'tablesome'),
                'drag_row' => __('Drag Row', 'tablesome'),
                'update_table' => __('Update Table', 'tablesome'),
                'changes_need_to_be_saved' => __('Changes needs to be saved', 'tablesome'),
                'full_or_filtered_table' => __('Full or Filtered Table', 'tablesome'),
                'choose_a_format' => __('Choose a format', 'tablesome'),
                'save_as' => __('Save as', 'tablesome'),
                'choose_a_delimiter' => __('Choose a Delimiter', 'tablesome'),
                'types' => __('Types', 'tablesome'),
                'entire_table' => __('Entire Table', 'tablesome'),
                'filtered_table' => __('Filtered Table', 'tablesome'),
                'formats' => __('Formats', 'tablesome'),
                'comma_separated_values' => __('comma separated values', 'tablesome'),
                'delimiters' => __('Delimiters', 'tablesome'),

                'select_operator' => __('Select Operator', 'tablesome'),
                'contains' => __('Contains', 'tablesome'),
                'does_not_contain' => __('Does not contain', 'tablesome'),
                'is' => __('Is', 'tablesome'),
                'is_not' => __('Is not', 'tablesome'),
                'starts_with' => __('Starts with', 'tablesome'),
                'ends_with' => __('Ends with', 'tablesome'),
                'empty' => __('Is empty', 'tablesome'),
                'not_empty' => __('Is not empty', 'tablesome'),
                'ascending' => __('Ascending', 'tablesome'),
                'descending' => __('Descending', 'tablesome'),
                'remember_this_on_save' => __('Remember this on Save', 'tablesome'),
                'created_at' => __('Created At', 'tablesome'),

            );
            return $strings;
        }

        public function get_dashboard_strings()
        {
            return [];
        }
    }
}
