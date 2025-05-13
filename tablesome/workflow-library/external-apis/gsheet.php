<?php

namespace Tablesome\Workflow_Library\External_Apis;

use Error;
use Tablesome\Includes\Modules\API_Credentials_Handler;

// Import WordPress global functions to use in this namespace
// Note: As per user request, we're ignoring the lint errors for these imports
use function wp_remote_post;
use function wp_json_encode;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_body;
use function is_wp_error;
use function sprintf;
use function json_decode;
use function maybe_refresh_access_token_by_integration;
use function is_tablesome_success_response;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\External_Apis\GSheet')) {
    class GSheet
    {
        public $endpoint = 'https://sheets.googleapis.com';
        public $api_version = 'v4';
        public $integration = 'google_safe';
        public $api_credentials_handler;
        public function __construct()
        {
            $this->api_credentials_handler = new API_Credentials_Handler();
        }

        public function is_active()
        {
            $data = $this->api_credentials_handler->get_api_credentials($this->integration);
            return $data["status"] == "success";
        }

        public function get_sheets_by_spreadsheet_id($spreadsheet_id, $include_grid_data = false)
        {
            $access_token = maybe_refresh_access_token_by_integration($this->integration);

            $url = "https://sheets.googleapis.com/{$this->api_version}/spreadsheets/{$spreadsheet_id}";
            $parameters = [
                // 'includeGridData' => $include_grid_data,
                'alt' => 'json',
                // 'range' => '1:1'
            ];
            global $pluginator_security_agent;
            $url = $pluginator_security_agent->add_query_arg($parameters, $url, 'raw');
            $response = wp_remote_post($url, array(
                'method' => 'GET',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                ),
            ));

            // error_log('url: ' . $url);
            $response_code = wp_remote_retrieve_response_code($response);
            // error_log('response: ' . print_r($response, true));
            $data = json_decode(wp_remote_retrieve_body($response), true);

            // error_log('data: ' . print_r($data, true));
            $response_failed = (is_wp_error($response) || !is_tablesome_success_response($response_code));
            // error_log('response_failed: ' . print_r($response_failed, true));
            $is_authorization_error = isset($data['error']['code']) && $data['error']['code'] == 401;
            $error_data = isset($data['error']) ? $data['error'] : [];

            // error_log('error_data: ' . print_r($error_data, true));
            if ($is_authorization_error) {
                Retry::set_integration($this->integration);
                return Retry::call([$this, 'get_sheets_by_spreadsheet_id'], [$spreadsheet_id, $include_grid_data]);
            }
            Retry::reset_count();
            if ($response_failed && !$is_authorization_error) {
                return [];
            }
            return $data;
        }

        public function get_header_from_sheetId($spreadsheet_id, $sheet_id, $sheet_name)
        {
            // error_log('get_header_from_sheetId: step 1');
            $access_token = maybe_refresh_access_token_by_integration($this->integration);
            $url = "https://sheets.googleapis.com/{$this->api_version}/spreadsheets/{$spreadsheet_id}/values/{$sheet_name}!1:1";
            $parameters = [
                'alt' => 'json',
            ];
            global $pluginator_security_agent;
            $url = $pluginator_security_agent->add_query_arg($parameters, $url, 'raw');
            $response = wp_remote_post($url, array(
                'method' => 'GET',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                ),
            ));
            $response_code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);

            // error_log('get_header_from_sheetId: step 2');
            // error_log('data: ' . print_r($data, true));
            $response_failed = (is_wp_error($response) || !is_tablesome_success_response($response_code));
            $is_authorization_error = isset($data['error']['code']) && $data['error']['code'] == 401;
            $error_data = isset($data['error']) ? $data['error'] : [];
            if ($is_authorization_error) {
                Retry::set_integration($this->integration);
                return Retry::call([$this, 'get_header_from_sheetId'], [$spreadsheet_id, $sheet_id]);
            }
            Retry::reset_count();
            if ($response_failed && !$is_authorization_error) {
                return [];
            }

            // error_log('get_header_from_sheetId data: ' . print_r($data, true));
            return isset($data['values'][0]) ? $data['values'][0] : [];
        }

        public function get_sheet_name($spreadsheet_id, $sheet_id)
        {
            $sheet_name = '';
            $sheets = $this->get_sheets_by_spreadsheet_id($spreadsheet_id);

            // error_log('sheets: ' . print_r($sheets, true));

            if (empty($sheets)) {
                return '';
            }
            foreach ($sheets as $sheet) {
                if ($sheet['id'] == $sheet_id) {
                    $sheet_name = $sheet['label'];
                    break;
                }
            }
            return $sheet_name;
        }

        public function get_rows($params)
        {

            // $params = [
            //     'spreadsheet_id' => '1SD0hILufpysPQ8HKeGhLA1OmQ8-m8F0Ybn0kNPA8y-c',
            //     'sheet_name' => 'Sheet1',
            //     'coordinates' => 'A1:Z1000',
            //     'range' => 'Sheet1',
            // ];

            $spreadsheet_id = isset($params['spreadsheet_id']) ? $params['spreadsheet_id'] : '';

            $data = $this->get_spreadsheet_records($spreadsheet_id, $params);

            // error_log('data: ' . print_r($data, true));

            return $data;
        }

        public function add_records($data = array())
        {

            $spreadsheet_id = isset($data['spreadsheet_id']) ? $data['spreadsheet_id'] : '';
            $sheet_name = isset($data['sheet_name']) ? $data['sheet_name'] : '';

            // error_log('data: ' . print_r($data, true));
            // error_log('spreadsheet_id: ' . $spreadsheet_id);
            // error_log('sheet_name: ' . $sheet_name);

            // should be an array of arrays
            $values = isset($data['values']) ? $data['values'] : [];
            $range = isset($data['range']) ? $data['range'] : '';
            if (empty($spreadsheet_id) || empty($sheet_name) || empty($values) || empty($range)) {
                return;
            }
            $access_token = maybe_refresh_access_token_by_integration($this->integration);

            // Range where to look for Table
            $range = "$sheet_name!$range";

            $parameters = [
                "insertDataOption" => "INSERT_ROWS",
                "valueInputOption" => "RAW",
                'includeValuesInResponse' => true,
                'alt' => 'json',
            ];

            $url = "https://sheets.googleapis.com/{$this->api_version}/spreadsheets/{$spreadsheet_id}/values/{$range}:append";
            global $pluginator_security_agent;
            $url = $pluginator_security_agent->add_query_arg($parameters, $url, 'raw');

            $payload = [
                "values" => $values, // array of arrays
            ];

            $response = wp_remote_post($url, array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                ),
                'body' => wp_json_encode($payload),
            ));
            $response_code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $response_failed = (is_wp_error($response) || !is_tablesome_success_response($response_code));
            $is_authorization_error = isset($data['error']['code']) && $data['error']['code'] == 401;
            $error_data = isset($data['error']) ? $data['error'] : [];

            if ($is_authorization_error) {
                Retry::set_integration($this->integration);
                return Retry::call([$this, 'add_records'], [$data]);
            }

            Retry::reset_count();
            if ($response_failed && !$is_authorization_error) {
                return [];
            }
            return $data;
        }

        public function get_spreadsheet_records($spreadsheet_id, $params)
        {
            $sheet_name = isset($params['sheet_name']) ? $params['sheet_name'] : '';
            $range = isset($params['range']) ? $params['range'] : '';
            // error_log('spreadsheet_id: ' . $spreadsheet_id);
            // error_log('sheet_name: ' . $sheet_name);
            if (empty($spreadsheet_id) || empty($sheet_name)) {
                return;
            }
            // error_log('get_spreadsheet_records: step 2 ');
            $coordinates = isset($params['coordinates']) ? $params['coordinates'] : '';
            if (empty($range)) {
                $coordinates = "1:2"; // read first two rows by default
            }

            $range = "$sheet_name!$coordinates";
            $access_token = maybe_refresh_access_token_by_integration($this->integration);
            $url = "https://sheets.googleapis.com/{$this->api_version}/spreadsheets/{$spreadsheet_id}/values/{$range}";
            $parameters = [
                'alt' => 'json',
            ];
            global $pluginator_security_agent;
            $url = $pluginator_security_agent->add_query_arg($parameters, $url, 'raw');

            $response = wp_remote_post($url, array(
                'method' => 'GET',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                ),
            ));

            $response_code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $response_failed = (is_wp_error($response) || !is_tablesome_success_response($response_code));
            $is_authorization_error = isset($data['error']['code']) && $data['error']['code'] == 401;
            $error_data = isset($data['error']) ? $data['error'] : [];
            
            // error_log('$response: ' . print_r($response, true));
            // error_log('$response_code: ' . $response_code);
            if ($is_authorization_error) {
                Retry::set_integration($this->integration);
                return Retry::call([$this, 'get_spreadsheet_records'], [$spreadsheet_id, $params]);
            }

            Retry::reset_count();
            if ($response_failed && !$is_authorization_error) {
                return [];
            }

            return $data;
        }

        /**
         * Count rows in a Google Sheet
         * 
         * @param array $params Parameters for the request
         * @return array Response with row count information
         */
        public function get_row_count($params)
        {
            $spreadsheet_id = isset($params['spreadsheet_id']) ? $params['spreadsheet_id'] : '';
            $sheet_name = isset($params['sheet_name']) ? $params['sheet_name'] : 'Sheet1';

            if (empty($spreadsheet_id)) {
                return [
                    'status' => 'error',
                    'message' => 'Spreadsheet ID is required',
                ];
            }

            // Get all rows from the sheet to count them
            $data = $this->get_spreadsheet_records($spreadsheet_id, [
                'sheet_name' => $sheet_name,
                'coordinates' => 'A1:Z1000', // Adjust range as needed
            ]);

            $total_rows = isset($data['values']) ? count($data['values']) : 0;

            return [
                'status' => 'success',
                'total_rows' => $total_rows,
                'sheet_name' => $sheet_name,
            ];
        }

        /**
         * Clear rows from a Google Sheet
         * 
         * @param array $params Parameters for the request
         * @return array Response with deletion status
         */
        public function delete_rows($params)
        {

            // error_log("Google Sheet Delete Rows : " . print_r($params, true));
            $spreadsheet_id = isset($params['spreadsheet_id']) ? $params['spreadsheet_id'] : '';
            $sheet_name = isset($params['sheet_name']) ? $params['sheet_name'] : 'Sheet1';
            $start_row = isset($params['start_row']) ? intval($params['start_row']) : 6; // Default to row 6 (keeping top 5)
            $end_row = isset($params['end_row']) ? intval($params['end_row']) : 1000; // Default to a large number

            if (empty($spreadsheet_id)) {
                
                return [
                    'status' => 'error',
                    'message' => 'Spreadsheet ID is required',
                ];
            }

            // Get access token
            $access_token = maybe_refresh_access_token_by_integration($this->integration);
            
            if (empty($access_token)) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to get access token',
                ];
            }

            // Get sheet ID
            $sheets_data = $this->get_sheets_by_spreadsheet_id($spreadsheet_id);
            // error_log("Google Sheet Delete Rows  sheets_data: " . print_r($sheets_data, true));
            $sheet_id = null;
            
            if (isset($sheets_data['sheets'])) {
                foreach ($sheets_data['sheets'] as $sheet) {
                    // error_log("Checking sheet: " . print_r($sheet['properties']['title'], true) . " against " . $sheet_name);
                    if ($sheet['properties']['title'] == $sheet_name) {
                        $sheet_id = $sheet['properties']['sheetId'];
                        // error_log("Found matching sheet with ID: " . $sheet_id);
                        break;
                    }
                }
            }

            if ($sheet_id === null) {
                return [
                    'status' => 'error',
                    'message' => 'Sheet not found',
                ];
            }

            // Create a batch update request to clear the rows
            $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheet_id}:batchUpdate";
            
            // Prepare the request to clear content
            $payload = [
                'requests' => [
                    [
                        'updateCells' => [
                            'range' => [
                                'sheetId' => $sheet_id,
                                'startRowIndex' => $start_row - 1, // 0-indexed
                                'endRowIndex' => $end_row, // 0-indexed, exclusive
                            ],
                            'fields' => 'userEnteredValue'
                        ]
                    ]
                ]
            ];

            $response = wp_remote_post($url, [
                'method' => 'POST',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $access_token,
                ],
                'body' => wp_json_encode($payload),
            ]);

            $response_code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            // error_log("Google Sheet Delete Rows : " . print_r($data, true));
            if (is_wp_error($response) || !is_tablesome_success_response($response_code)) {
                return [
                    'status' => 'error',
                    'message' => 'Failed to clear rows',
                    'response' => $data,
                ];
            }

            return [
                'status' => 'success',
                'message' => sprintf('Cleared rows %d to %d', $start_row, $end_row),
                'sheet_id' => $sheet_id,
                'response' => $data,
            ];
        }

    }
}
