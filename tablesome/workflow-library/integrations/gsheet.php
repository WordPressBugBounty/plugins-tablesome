<?php

namespace Tablesome\Workflow_Library\Integrations;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Integrations\GSheet')) {
    class GSheet
    {
        public $gsheet_api;
        public $gdrive_api;
        public function __construct()
        {
            $this->gsheet_api = new \Tablesome\Workflow_Library\External_Apis\GSheet();
            $this->gdrive_api = new \Tablesome\Workflow_Library\External_Apis\GDrive();
        }

        public function get_config()
        {
            return array(
                'integration' => 'gsheet',
                'integration_label' => __('GSheet', 'tablesome'),
                'is_active' => $this->gsheet_api->is_active(),
                'is_premium' => true,
                'actions' => array(),
            );
        }

        public function get_spreadsheets()
        {
            // error_log('get_spreadsheets');
            $files = $this->gdrive_api->get_spreadsheets();
            // error_log("get_spreadsheets: " . wp_json_encode($files));
            if (empty($files)) {
                return [];
            }
            $spreadsheets = array_map(function ($file) {
                return [
                    'id' => $file['id'],
                    'label' => $file['name'],
                    'integration_type' => "gsheet",
                ];
            }, $files);

            return $spreadsheets;
        }

        public function get_sheets_by_spreadsheet_id($spreadsheet_id)
        {
            // error_log('integrations -> get_sheets_by_spreadsheet_id');
            $data = $this->gsheet_api->get_sheets_by_spreadsheet_id($spreadsheet_id, true);
            $sheets = isset($data['sheets']) ? $data['sheets'] : [];

            // error_log('sheets: ' . print_r($sheets, true));
            if (empty($sheets)) {
                return [];
            }

            $sheets = array_map(function ($sheet) use ($spreadsheet_id) {
                $first_row_data = $this->gsheet_api->get_header_from_sheetId($spreadsheet_id, $sheet['properties']['sheetId'], $sheet['properties']['title']);
                $header = $this->convert_to_id_label($first_row_data);
                // $header_old = $this->get_first_row_data_from_sheet_grid_data_old($sheet);

                // error_log('sheetId: ' . $sheet['properties']['sheetId']);
                // error_log('label: ' . $sheet['properties']['title']);
                // error_log('header: ' . print_r($header, true));
                // error_log('header2: ' . print_r($header2, true));
                return [
                    'id' => "" . $sheet['properties']['sheetId'] . "",
                    'label' => $sheet['properties']['title'],
                    'options' => $header,
                ];
            }, $sheets);

            return $sheets;

            // return [
            //     'sheets' => $sheets,
            //     'spreadsheet_id' => $spreadsheet_id,
            //     'spreadsheet_name' => $data['properties']['title'],
            //     'spreadsheet_url' => $data['spreadsheetUrl'],
            // ];
        }

        public function get_spreadsheet_records($spreadsheet_id, $params)
        {
            $data = $this->gsheet_api->get_spreadsheet_records($spreadsheet_id, $params);
            $read_first_row_as_header = isset($params['read_first_row_as_header']) ? $params['read_first_row_as_header'] : false;
            $values = isset($data['values']) ? $data['values'] : [];
            if (!$read_first_row_as_header) {
                return $values;
            }

            $first_row = isset($values[0]) ? $values[0] : [];
            $header = [];
            $keys = array_flip($first_row);

            foreach ($keys as $keyIndex => $value) {
                $header[] = array(
                    'id' => $value,
                    'label' => $first_row[$value],
                );
            }
            return $header;
        }

        public function add_records_to_sheet($data)
        {
            if (!isset($data['spreadsheet_id']) || !isset($data['sheet_name']) || !isset($data['values'])) {
                return;
            }
            $result = $this->gsheet_api->add_records($data);
            return $result;
        }

        private function convert_to_id_label($row_values)
        {

            $cells_data = [];

            if (!empty($row_values)) {
                foreach ($row_values as $cell_index => $cell) {
                    $temp_column_name = 'Column: ' . tablesome_num2alpha($cell_index);
                    $cells_data[] = [
                        'id' => "" . $cell_index . "",
                        'label' => isset($cell) && !empty($cell) ? $cell : $temp_column_name,
                    ];
                }
            }

            return $cells_data;
        }

        private function get_first_row_data_from_sheet_grid_data_old($sheet)
        {
            $data = isset($sheet['data']) ? $sheet['data'] : [];
            // error_log("data: " . wp_json_encode($data));
            $row_data = isset($data[0]['rowData']) ? $data[0]['rowData'] : [];
            $first_row_values = isset($row_data[0]['values']) ? $row_data[0]['values'] : [];
            $cells_data = [];

            if (!empty($first_row_values)) {
                foreach ($first_row_values as $cell_index => $cell) {
                    $temp_column_name = 'Column: ' . tablesome_num2alpha($cell_index);
                    $cells_data[] = [
                        'id' => "" . $cell_index . "",
                        'label' => isset($cell['formattedValue']) && !empty($cell['formattedValue']) ? $cell['formattedValue'] : $temp_column_name,
                    ];
                }
            }

            return $cells_data;
        }
    } // end of class
}
