<?php

namespace Tablesome\Workflow_Library\Actions;

use Tablesome\Includes\Modules\Workflow\Action;
use Tablesome\Includes\Modules\Workflow\Traits\Cell_Format;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Actions\Files_Generate_CSV')) {
    class Files_Generate_CSV extends Action
    {
        use Cell_Format;
        public $gdrive;
        public $action_meta;
        public $trigger_class;
        public $trigger_instance;
        public $placeholders;
        public $wp_media_file_handler;
        public $tmp_direrctory_name = 'tablesome-tmp';

        public function __construct()
        {
            $this->gdrive = new \Tablesome\Workflow_Library\External_Apis\GDrive();
            $this->wp_media_file_handler = new \Tablesome\Includes\Modules\WP_Media_File_Handler();
        }

        public function get_config()
        {
            return array(
                'id' => 15,
                'name' => 'files_generate_csv',
                'label' => __('Generate CSV', 'tablesome'),
                'integration' => 'files',
                'is_premium' => true,
            );
        }

        public function do_action($trigger_class, $trigger_instance)
        {
            global $tablesome_workflow_data;
            error_log('*** Files Generate CSV File  ***');
            $this->bind_props($trigger_class, $trigger_instance);
            // By default, we will use comma as delimiter
            $delimiter = isset($this->action_meta['delimiter']) ? $this->action_meta['delimiter'] : ',';
            $storing_location_type = isset($this->action_meta['storing_in']["location_type"]) ? $this->action_meta['storing_in']["location_type"] : '';
            $storing_location = isset($this->action_meta['storing_in']["location"]) ? $this->action_meta['storing_in']["location"] : '';
            $trigger_source_data = isset($trigger_class->trigger_source_data['data']) ? $trigger_class->trigger_source_data['data'] : [];

            if (empty($storing_location_type)) {
                return;
            }

            $data = $this->get_data($trigger_source_data);
            $csv = $this->get_csv_content($data, $delimiter);
            $file_name = $this->get_file_name();
            $attachment_url = '';
            switch ($storing_location_type) {
                case 'gdrive':
                    if (empty($storing_location)) {
                        return;
                    }
                    $args = [
                        "file_content" => $csv,
                        "file_name" => $file_name,
                        "file_type" => 'text/csv',
                        "location" => $storing_location,
                    ];
                    $result = $this->gdrive->upload_file($args);
                    $attachment_url = isset($result['webViewLink']) ? $result['webViewLink'] : '';
                    break;
                case 'wp_media_folder':
                    // Include required files
                    $this->wp_media_file_handler->include_core_files();

                    $upload_dir = wp_upload_dir();

                    $base_path = $upload_dir['basedir'] . '/' . $this->tmp_direrctory_name . '/';
                    $file_path = $base_path . $file_name;

                    $this->wp_media_file_handler->maybe_create_dir($base_path);

                    // Create & Write CSV file
                    $file = fopen($file_path, 'w');
                    foreach ($data as $line) {
                        fputcsv($file, $line, $delimiter);
                    }
                    fclose($file);

                    $url = $upload_dir['baseurl'] . '/' . $this->tmp_direrctory_name . '/' . $file_name;

                    // Upload file to media library
                    $attachment_id = $this->wp_media_file_handler->upload_file_from_url($url, [
                        'can_delete_temp_file_after_download' => true,
                        'file_path' => $file_path,
                    ]);
                    $attachment_url = !empty($attachment_id) ? wp_get_attachment_url($attachment_id) : '';
                    break;
                case 'private_folder':
                    # code...
                    break;

                default:
                    # code...
                    break;
            }

            $data = array_merge($this->get_config(), [
                "attachment_url" => $attachment_url,
                'file_name' => $file_name,
            ]);

            array_push($tablesome_workflow_data, $data);
        }

        private function bind_props($trigger_class, $trigger_instance)
        {
            $this->trigger_class = $trigger_class;
            $this->trigger_instance = $trigger_instance;
            $this->action_meta = isset($this->trigger_instance['action_meta']) ? $this->trigger_instance['action_meta'] : [];
            $this->placeholders = $this->trigger_instance['_placeholders'];
        }

        private function get_data($trigger_source_data)
        {
            $header = [];
            $content = [];
            // error_log('$trigger_source_data : ' . print_r($trigger_source_data, true));
            foreach ($trigger_source_data as $field_id => $field_data) {
                $type = isset($field_data['type']) ? $field_data['type'] : '';
                $label = isset($field_data['label']) ? $field_data['label'] : '';
                $unix_timestamp = isset($field_data['unix_timestamp']) ? $field_data['unix_timestamp'] : '';
                $value = isset($field_data['value']) ? $field_data['value'] : '';
                $format_type = $this->get_cell_format_by_field_type($type);

                if ($format_type == 'date') {
                    $value = !empty($unix_timestamp) ? date('Y-m-d', ($unix_timestamp / 1000)) : '';
                } else if ($format_type == 'file') {
                    $value = !empty($value) && is_numeric($value) ? wp_get_attachment_url($value) : $value;
                }

                $header[] = $label;
                $content[] = $value;
            }
            $data = array_merge([$header], [$content]);
            return $data;
        }

        // public function work_with_dummy_data()
        // {

        //     $file_path = TABLESOME_PATH . "includes/data/action-configs/files-generate-csv.json";
        //     $dummydata = get_data_from_json_file('', $file_path);
        //     $form_data = $dummydata['test_data']['form_data'];
        //     $delimiter = $dummydata['test_data']['delimiter'];
        //     error_log('$form_data : ' . print_r($form_data, true));
        //     $csv = $this->get_csv_content($form_data, $delimiter);
        //     error_log('$csv : ' . print_r($csv, true));

        //     // $args = [
        //     //     "file_content" => $csv,
        //     //     "file_name" => 'test.csv',
        //     //     "file_type" => 'text/csv',
        //     //     "location" => $dummydata['test_data']['storing_in']['location'],
        //     // ];

        //     // $file_added = $this->gdrive->add_files_to_drive($args);
        //     // error_log('$file_added : ' . print_r($file_added, true));
        //     $file_name = $this->get_file_name();

        //     $this->wp_media_file_handler->include_core_files();

        //     $upload_dir = wp_upload_dir();

        //     $base_path = $upload_dir['basedir'] . '/' . $this->tmp_direrctory_name . '/';
        //     $file_path = $base_path . $file_name;

        //     $this->wp_media_file_handler->maybe_create_dir($base_path);

        //     // Create & Write CSV file
        //     $file = fopen($file_path, 'w');
        //     fwrite($file, $csv);
        //     fclose($file);

        //     $url = $upload_dir['baseurl'] . '/' . $this->tmp_direrctory_name . '/' . $file_name;

        //     // Upload file to media library
        //     $attachment_id = $this->wp_media_file_handler->upload_file_from_url($url, [
        //         'can_delete_temp_file_after_download' => true,
        //         'file_path' => $file_path,
        //     ]);
        //     error_log('[attachment_id] : ' . print_r($attachment_id, true));

        // }

        public function get_csv_content($data, $delimiter)
        {
            $csv = '';
            foreach ($data as $index => $value) {
                $csv .= implode($delimiter, $value) . "\n";
            }

            return $csv;
        }

        private function get_file_name()
        {
            $file_name = 'tablesome_csv_' . time() . '.csv';
            return $file_name;
        }
    }
}
