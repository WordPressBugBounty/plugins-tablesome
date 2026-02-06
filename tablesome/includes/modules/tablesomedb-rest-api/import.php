<?php

namespace Tablesome\Includes\Modules\TablesomeDB_Rest_Api;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\TablesomeDB_Rest_Api\Import')) {
    class Import
    {

        public $datatable;

        public function __construct()
        {
            $this->datatable = new \Tablesome\Includes\Modules\Datatable\Datatable();
        }

        public function import_records($request)
        {
            // Importing records requires editor or admin permission
            if (!\current_user_can('edit_posts')) {
                $error_code = "UNAUTHORIZED";
                return new \WP_Error($error_code, $this->get_error_message($error_code));
            }
            
            $crud = new \Tablesome\Includes\Db\CRUD();
            error_log('[START] : ' . get_app_memory_usage());
            $params = $request->get_params();

            $table_id = isset($params['table_id']) ? $params['table_id'] : 0;

            if (empty($table_id)) {
                $error_code = "REQUIRED_POST_ID";
                return new \WP_Error($error_code, $this->get_error_message($error_code));
            }

            $post = get_post($table_id);

            if (empty($post) || $post->post_type != TABLESOME_CPT) {
                $error_code = "INVALID_POST";
                return new \WP_Error($error_code, $this->get_error_message($error_code));
            }

            // Security check: Verify user can edit this specific table
            // Users can import if they can edit others' posts (Editor/Admin) OR if they own the table
            $current_user_id = \get_current_user_id();
            $can_edit_others_posts = \current_user_can('edit_others_posts');
            // Security: Verify user is authenticated before checking ownership (prevent 0 == 0 bypass)
            $is_table_owner = ($current_user_id > 0 && $post->post_author == $current_user_id);

            if (!$can_edit_others_posts && !$is_table_owner) {
                $error_code = "UNAUTHORIZED";
                return new \WP_Error($error_code, $this->get_error_message($error_code));
            }
            $records = isset($params['records_inserted']) ? $params['records_inserted'] : [];
            $table_meta = get_tablesome_data($table_id);

            $tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
            $table = $tablesome_db->create_table_instance($table_id);
            $insert_info = $this->datatable->records->insert_many($table_id, $table_meta, $records);
            $records_inserted_count = isset($insert_info['records_inserted_count']) ? $insert_info['records_inserted_count'] : 0;

            $message = 'No records inserts';
            if ($records_inserted_count > 0) {
                $message = $records_inserted_count . ' records inserted successfully';
            }

            $response_data = array(
                'records_inserted_count' => $records_inserted_count,
                'message' => $message,
            );
            error_log('[END] : ' . get_app_memory_usage());
            return rest_ensure_response($response_data);
        }

        public function get_error_message($error_code)
        {
            $messages = array(
                'UNAUTHORIZED' => "You don't have an permission to access this resource",
                'REQUIRED_POST_ID' => "Required, Tablesome table ID ",
                'INVALID_POST' => "Invalid, Tablesome post",
                'REQUIRED_RECORD_IDS' => "Required, Tablesome table record IDs",
                'UNABLE_TO_CREATE' => "Unable to create a post.",
            );

            $message = isset($messages[$error_code]) ? $messages[$error_code] : 'Something Went Wrong, try later';
            return $message;
        }

        public function get_row_cells($record, $columns)
        {
            $cells = array();

            foreach ($record['content'] as $index => $cell_value) {
                $column_id = $columns[$index]['id'];
                $cells[$column_id] = $cell_value;
            }
            return $cells;
        }
    }
}
