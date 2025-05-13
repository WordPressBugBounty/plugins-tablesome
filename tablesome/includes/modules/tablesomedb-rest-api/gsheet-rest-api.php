<?php

namespace Tablesome\Includes\Modules\TablesomeDB_Rest_Api;

// Import WordPress global functions to use in this namespace
use function sanitize_text_field;
use function rest_ensure_response;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\TablesomeDB_Rest_Api\GSheet_Rest_Api')) {
    /**
     * GSheet_Rest_Api class for handling Google Sheets REST API requests
     */
    class GSheet_Rest_Api
    {
        public $gsheet_api;

        public function __construct()
        {
            $this->gsheet_api = new \Tablesome\Workflow_Library\External_Apis\GSheet();
        }

        /**
         * Count rows in a Google Sheet
         * 
         * @param \WP_REST_Request $request The request object
         * @return \WP_REST_Response The response object
         */
        public function get_row_count($request)
        {
            $spreadsheet_id = $request->get_param('spreadsheet_id') ? sanitize_text_field($request->get_param('spreadsheet_id')) : '';
            $sheet_name = $request->get_param('sheet_name') ? sanitize_text_field($request->get_param('sheet_name')) : 'Sheet1';

            $params = [
                'spreadsheet_id' => $spreadsheet_id,
                'sheet_name' => $sheet_name
            ];

            $result = $this->gsheet_api->get_row_count($params);
            
            return rest_ensure_response($result);
        }

        /**
         * Clear rows from a Google Sheet
         * 
         * @param \WP_REST_Request $request The request object
         * @return \WP_REST_Response The response object
         */
        public function delete_rows($request)
        {
            $params = $request->get_params();
            $params = [
                'spreadsheet_id' => isset($params['spreadsheet_id']) ? sanitize_text_field($params['spreadsheet_id']) : '',
                'sheet_name' => isset($params['sheet_name']) ? sanitize_text_field($params['sheet_name']) : 'Sheet1',
                'start_row' => isset($params['start_row']) ? intval($params['start_row']) : 6, // Default to row 6 (keeping top 5)
                'end_row' => isset($params['end_row']) ? intval($params['end_row']) : 1000, // Default to a large number
            ];

            $result = $this->gsheet_api->delete_rows($params);
            
            return rest_ensure_response($result);
        }
    }
}
