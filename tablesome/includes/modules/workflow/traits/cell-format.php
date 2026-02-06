<?php

namespace Tablesome\Includes\Modules\Workflow\Traits;

if (!trait_exists('\Tablesome\Includes\Modules\Workflow\Traits\Cell_Format')) {
    trait Cell_Format
    {
        public $supported_formats = array(
            'textarea' => array('textarea', 'address', 'post_excerpt', 'rich_text_input'),
            'date' => array('date', 'date-time', 'input_date'),
            'email' => array('email', 'input_email'),
            'file' => array("upload", "file-upload", "file", "fileupload", "post_image", "input_image", "input_file", "signature", "e_signature", "esignature", "pafe_signature"),
            'url' => array('url', 'input_url'),
            'button' => array('button'),
            'number' => array('number-slider', 'rating', 'number', 'postdata', 'currency', 'calculation', 'quantity', 'input_number'),
        );

        public function get_cell_format_by_field_type($field_type)
        {
            $format = 'text';
            foreach ($this->supported_formats as $cell_format => $field_types) {
                if (in_array($field_type, $field_types)) {
                    $format = $cell_format;
                    break;
                }
            }
            return $format;
        }
    }
}
