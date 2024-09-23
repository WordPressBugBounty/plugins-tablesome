<?php

namespace Tablesome\Components\Import;

if (!class_exists('\Tablesome\Components\Import\Controller')) {
    class Controller
    {
        public $view;
        public $table;
        public $model;

        public function __construct()
        {
            $this->view = new \Tablesome\Components\Import\View();
            $this->table = new \Tablesome\Includes\Core\Table();

        }

        public function render()
        {
            $html = $this->view->get_import_page_content();

            // $rows = $this->table->get_rows(262);
            // $row = $this->table->get_row(262,267520);
            // error_log('[$row] : ' . print_r($row, true));
            // $content = ['OLd','old','old','old'];
            // $update = $this->table->update_row(262,267520,$content);
            // $delete = $this->table->delete_row(262,267520);
            $allowed_html = tablesome_allowed_html();
            $allowed_form_attrs = tablesome_form_attrs();
            $allowed_html['form'] = $allowed_form_attrs;
            echo wp_kses($html, $allowed_html);

            // echo $html;
        }

        // public function get_sanitized_props()
        // {
        //     $props = [
        //         'read_first_row_as_column' => false,
        //         'table_title' => 'Untitled Table',
        //     ];

        //     if (isset($_REQUEST['read_first_row_as_column']) && !empty($_REQUEST['read_first_row_as_column'])) {
        //         $props['read_first_row_as_column'] = sanitize_text_field(wp_unslash($_REQUEST['read_first_row_as_column']));
        //     }

        //     if (isset($_REQUEST['table_title']) && !empty($_REQUEST['table_title'])) {
        //         $props['table_title'] = sanitize_text_field(wp_unslash($_REQUEST['table_title']));
        //     }

        //     return $props;
        // }

    } // end of class
}
