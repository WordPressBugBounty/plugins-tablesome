<?php

namespace Tablesome\Components\CellTypes\File;

if (!class_exists('\Tablesome\Components\CellTypes\File\Controller')) {
    class Controller
    {
        public $model;
        public $view;

        public function __construct()
        {
            $this->model = new \Tablesome\Components\CellTypes\File\Model();
            $this->view = new \Tablesome\Components\CellTypes\File\View();

            add_filter("tablesome_get_cell_data", [$this, 'get_file_data']);
        }

        public function get_file_data($cell)
        {
            // error_log('get_file_data() cell : ' . print_r($cell, true));
            if (empty($cell['value']) || $cell['column_format'] != 'file') {
                return $cell;
            }

            $data = $this->model->get_media_data($cell);
            // error_log('get_file_data() cell : ' . print_r($cell, true));
            // error_log('get_file_data() data : ' . print_r($data, true));

            $cell['html'] = $this->view->get_media_view($data);
            if (isset($data['attachment']) && !empty($data['attachment'])) {
                $cell['attachment'] = $data['attachment'];
            }
            if (isset($data['link']) && !empty($data['link'])) {
                $cell['link'] = $data['link'];
            }
            if (isset($data['mime_type']) && !empty($data['mime_type'])) {
                $cell['file_type'] = $data['mime_type'];
            }

            return $cell;
        }

    } // END CLASS
}
