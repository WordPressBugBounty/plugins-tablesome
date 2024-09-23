<?php

namespace Tablesome\Includes\Modules\TablesomeDB_Rest_Api;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('\Tablesome\Includes\Modules\TablesomeDB_Rest_Api\TablesomeDB_Rest_Api')) {
    class TablesomeDB_Rest_Api
    {
        public $tablesome_db;
        public $workflow_library;
        public $response;
        public $datatable;

        public function __construct()
        {
            $this->datatable = new \Tablesome\Includes\Modules\Datatable\Datatable();
            $this->tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
        }

        public function init()
        {
            $namespece = 'tablesome/v1';

            /** All REST-API Routes */
            $routes_controller = new \Tablesome\Includes\Modules\TablesomeDB_Rest_Api\Routes();
            $routes = $routes_controller->get_routes();

            foreach ($routes as $route) {
                /** Register the REST route */
                register_rest_route($namespece, $route['url'], $route['args']);
            }
        }

        public function api_access_permission()
        {
            if (get_current_user_id() >= 1) {
                return true;
            }
            $error_code = "UNAUTHORIZED";
            return new \WP_Error($error_code, $this->get_error_message($error_code));
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

        public function is_admin_user()
        {
            if (current_user_can('manage_options')) {
                return true;
            }
            return false;
        }

        public function get_params($params)
        {
            // $params = $request->get_params();
            $params['table_id'] = isset($params['table_id']) ? intval($params['table_id']) : 0;
            $params['columns'] = isset($params['columns']) ? $params['columns'] : [];
            $params['last_column_id'] = isset($params['last_column_id']) ? intval($params['last_column_id']) : 0;
            $params['triggers'] = isset($params['triggers']) ? $params['triggers'] : [];
            $params['editorState'] = isset($params['editorState']) ? $params['editorState'] : [];
            $params['display'] = isset($params['display']) ? $params['display'] : [];
            $params['style'] = isset($params['style']) ? $params['style'] : [];
            $params['access_control'] = isset($params['access_control']) ? $params['access_control'] : [];
            $params['mode'] = isset($params['mode']) ? $params['mode'] : '';
            $params['records_updated'] = isset($params['records_updated']) ? $params['records_updated'] : [];
            $params['records_deleted'] = isset($params['records_deleted']) ? $params['records_deleted'] : [];
            $params['records_inserted'] = isset($params['records_inserted']) ? $params['records_inserted'] : [];
            $params['origin_location'] = isset($params['origin_location']) ? $params['origin_location'] : 'backend';

            // error_log('params : ' . print_r($params, true));

            // $filters = new \Tablesome\Includes\Filters();
            // $params = $filters->sanitizing_the_array_values($params);

            $params = $this->get_sanitized_params($params);

            return $params;
        }

        public function get_sanitized_params($params)
        {
            $params['records_updated'] = $this->get_sanitized_records($params['records_updated']);
            $params['records_deleted'] = $this->get_sanitized_records($params['records_deleted']);
            $params['records_inserted'] = $this->get_sanitized_records($params['records_inserted']);

            return $params;
        }

        public function get_sanitized_records($records_updated = [])
        {
            if (empty($records_updated)) {
                return $records_updated;
            }

            foreach ($records_updated as $key => $value) {

                $content = isset($value['content']) ? $value['content'] : [];
                foreach ($content as $key2 => $cell) {
                    $type = isset($cell['type']) ? $cell['type'] : 'text';

                    if (isset($records_updated[$key]['content'][$key2]['value'])) {
                        $records_updated[$key]['content'][$key2]['value'] = $this->sanitize_by_type($type, $value);
                    }
                    if (isset($records_updated[$key]['content'][$key2]['html'])) {
                        $records_updated[$key]['content'][$key2]['html'] = $this->sanitize_by_type('html', $value);
                    }
                }
            }

            return $records_updated;

        }

        public function sanitize_by_type($type, $content)
        {
            if ($type == 'text') {
                return sanitize_text_field($content);
            } else if ($type == 'html') {
                return tablesome_wp_kses($content);
            } else if ($type == 'number') {
                return intval($content);
            } else {
                return tablesome_wp_kses($content);
            }

        }

        public function get_param_rules()
        {

            $rules = [
                'column' => [
                    'id' => 'number',
                    'name' => 'string',
                    'type' => 'string',
                    'show_time' => 'number',
                    'index' => 'number',
                ],
                'record' => [
                    'record_id' => 'number',
                    'rank_order' => 'string',
                    'content' => 'cell',
                    'cell' => [
                        'type' => 'string',
                        'html' => 'html',
                        'value' => '',
                        'column_id' => 'number',
                    ],

                ],

            ];
            return $rules;

        }

        public function dispatch_mixpanel_event($params)
        {

            $event_params = [];
            // error_log('dispatch_mixpanel_event() params[triggers] : ' . print_r($params['triggers'], true));

            if (!empty($params['triggers'])) {
                $event_params = $this->get_triggers_and_actions($params['triggers'], $event_params);
                // error_log('dispatch_mixpanel_event() event_params : ' . print_r($event_params, true));
            }

            // error_log('dispatch_mixpanel_event() params : ' . print_r($params, true));
            $event_params = $this->update_records_count($params, $event_params);
            $event_params = $this->update_columns($params, $event_params);
            $event_params = $this->update_editor_settings($params, $event_params);
            $event_params = $this->update_display_settings($params, $event_params);
            $event_params = $this->update_style_settings($params, $event_params);

            $event_params['table_id'] = $params['table_id'];
            $event_params['mode'] = $params['mode'];
            $event_params['triggers_count'] = $this->count_items($params, 'triggers');
            $event_params['columns_count'] = $this->count_items($params, 'columns');

            $event_params['update_type'] = isset($params['update_type']) ? $params['update_type'] : 'edit';
            $event_params['origin_location'] = isset($params['origin_location']) ? $params['origin_location'] : 'backend';
            // Send Response
            $event_params['response_status'] = $this->response['status'];
            $dispatcher = new \Tablesome\Includes\Tracking\Dispatcher_Mixpanel();

            $dispatcher->send_single_event('tablesome_table_save', $event_params);

            // error_log('dispatch_mixpanel_event() event_params : ' . print_r($event_params, true));
        }

        public function count_items($params, $key)
        {
            $count = 0;
            if (isset($params[$key]) && !empty($params[$key])) {
                $count = count($params[$key]);
            }
            return $count;
        }

        public function update_style_settings($params, $event_params)
        {
            $event_params['style'] = isset($params['style']) ? $params['style'] : [];
            return $event_params;
        }

        public function update_display_settings($params, $event_params)
        {
            $event_params['display'] = isset($params['display']) ? $params['display'] : [];
            return $event_params;
        }

        public function update_editor_settings($params, $event_params)
        {
            $event_params['access_control'] = isset($params['access_control']) ? $params['access_control'] : [];
            return $event_params;
        }

        public function update_columns($params, $event_params)
        {
            // $event_params['columns_count'] = $this->count_items($params, 'columns');
            // $event_params['columns'] = $params['columns'];

            if (!isset($event_params['columns']) || empty($event_params['columns'])) {
                return $event_params;
            }

            foreach ($params['columns'] as $key => $column) {
                $format = isset($column['format']) ? $column['format'] : 'text';
                if (!isset($event_params['columns'][$format])) {
                    $event_params['columns'][$format] = 1;
                } else {
                    $event_params['columns'][$format] += 1;
                }

            }

            return $event_params;
        }

        public function update_records_count($params, $event_params)
        {
            $event_params['records_updated_count'] = isset($params['recordsData']) && isset($params['recordsData']['records_updated']) ? count($params['recordsData']['records_updated']) : 0;
            $event_params['records_deleted_count'] = isset($params['recordsData']) && isset($params['recordsData']['records_deleted']) ? count($params['recordsData']['records_deleted']) : 0;
            $event_params['records_inserted_count'] = isset($params['recordsData']) && isset($params['recordsData']['records_deleted']) ? count($params['recordsData']['records_inserted']) : 0;

            $event_params['records_count'] = $event_params['records_updated_count'] + $event_params['records_deleted_count'] + $event_params['records_inserted_count'];

            return $event_params;
        }

        public function get_triggers_and_actions($triggers, $event_params)
        {
            $event_params['triggers'] = [];
            $event_params['actions'] = [];
            // $workflow_library = new \Tablesome\Includes\Workflow\Library();

            if (!isset($triggers) || empty($triggers)) {
                return $event_params;
            }

            $this->workflow_library = get_tablesome_workflow_library();

            // error_log('get_triggers_and_actions() $triggers : ' . print_r($triggers, true));

            if (isset($triggers) && !empty($triggers) && is_array($triggers)) {

                foreach ($triggers as $trigger) {

                    if (empty($trigger) || !is_array($trigger)) {
                        continue;
                    }
                    $trigger_id = $trigger['trigger_id'];
                    $trigger_name = $this->workflow_library->get_trigger_name($trigger_id);

                    if (!isset($event_params['triggers'][$trigger_name])) {
                        $event_params['triggers'][$trigger_name] = 1;
                    } else {
                        $event_params['triggers'][$trigger_name]++;
                    }

                    if (!isset($trigger['actions']) || empty($trigger['actions'])) {
                        continue;
                    }

                    foreach ($trigger['actions'] as $action) {
                        $action_id = $action['action_id'];
                        $action_name = $this->workflow_library->get_action_name($action_id);
                        // $event_params['action_names'][] = $action_name;
                        if (!isset($event_params['actions'][$action_name])) {
                            $event_params['actions'][$action_name] = [];
                        }
                        if (!isset($event_params['actions'][$action_name]['count'])) {
                            $event_params['actions'][$action_name]['count'] = 1;
                        } else {
                            $event_params['actions'][$action_name]['count']++;
                        }

                        if ($action['action_id'] == 1) {
                            $event_params['actions'][$action_name]['autodetect_enabled'] = isset($action['autodetect_enabled']) ? $action['autodetect_enabled'] : false;
                            $event_params['actions'][$action_name]['enable_duplication_prevention'] = isset($action['enable_duplication_prevention']) ? $action['enable_duplication_prevention'] : false;
                            $event_params['actions'][$action_name]['enable_submission_limit'] = isset($action['enable_submission_limit']) ? $action['enable_submission_limit'] : false;
                        }
                    }
                }
            }

            return $event_params;
        }

        /* Replacement for create_or_update_table() */
        public function save_table_rest($request)
        {
            $params = $request->get_params();
            $params = $this->get_params($params);
            return $this->save_table($params);
        }

        public function save_table($params)
        {

            // error_log('save_table() $params : ' . print_r($params, true));

            $is_rest_backend = (defined('REST_REQUEST') && REST_REQUEST);
            $should_create_table = ($params['mode'] == 'editor' || is_admin()) && ($params['origin_location'] == 'backend');

            if ($params['origin_location'] == 'import') {
                $should_create_table = true;
            }

            // Can user create a table
            // $access_info = $this->check_table_access($post);

            // if (!$this->is_admin_user()) {
            //     // $error_code = "UNAUTHORIZED";
            //     // return new \WP_Error($error_code, $this->get_error_message($error_code));

            //     $this->response = array(
            //         'status' => 'failed',
            //         'message' => $this->get_error_message('UNAUTHORIZED'),
            //     );
            //     return $this->send_response($params);

            // }

            // error_log('save_table() $should_create_table : ' . $should_create_table);
            // error_log('save_table() $is_admin : ' . is_admin());
            // error_log('save_table() $mode : ' . $params['mode']);
            // error_log('save_table() $is_rest_backend : ' . $is_rest_backend);

            // Backend / Admin Area only
            if ($should_create_table) {
                // Create a WordPress post of tablesome's post_type (if not update)
                $params = $this->create_cpt_post($params);
                $params['update_type'] = 'create';

                if ($params['table_id'] == 0 || empty($params['table_id'])) {
                    return $this->send_response($params);
                }

                // Set table settings (as post_meta)
                $this->datatable->settings->save($params);
            } else {
                $params['update_type'] = 'edit';
            }

            // CRUD Records (update table records)
            $params['recordsData']['table_id'] = $params['table_id'];
            $this->response = $this->update_table_records($params['recordsData']);

            // error_log('save_table() $params[recordsData] : ' . print_r($params['recordsData'], true));
            // error_log('save_table() $this->response : ' . print_r($this->response, true));

            return $this->send_response($params);
        }

        public function send_response($params)
        {
            if ($this->response['status'] == 'success') {
                $this->response['message'] = 'Table saved successfully';
            }

            // error_log('response: ' . print_r($this->response, true));

            // Dispatch to Mixpanel
            $this->dispatch_mixpanel_event($params);
            return rest_ensure_response($this->response);
        }

        public function create_cpt_post($params)
        {
            // error_log('create_cpt_post() $params : ' . print_r($params, true));

            $table_title = 'Untitled Table';
            if (isset($params['table_title']) && !empty($params['table_title'])) {
                $table_title = isset($params['table_title']) ? $params['table_title'] : get_the_title($params['table_id']);
            }

            $post_data = array(
                'post_title' => $table_title,
                'post_type' => TABLESOME_CPT,
                'post_content' => isset($params['content']) ? $params['content'] : '',
                'post_status' => isset($params['table_status']) ? $params['table_status'] : 'publish',
            );

            // Retain 'private' status if the table is already private

            // error_log('create_cpt_post table_id: ' . $params['table_id']);
            // if (isset($params['table_id']) && $params['table_id'] > 0) {
            //     // $post_data['post_status'] = 'private';
            //     $post = get_post($params['table_id']);
            //     // error_log('create_cpt_post post: ' . print_r($post, true));
            //     if ($post->post_status == 'private') {
            //         $post_data['post_status'] = 'private';
            //     }
            // }

            $table = new \Tablesome\Includes\Core\Table();

            $params['table_id'] = $this->datatable->post->save($params['table_id'], $post_data);

            // error_log('create_cpt_post table_id: ' . $params['table_id']);

            if (empty($params['table_id'])) {
                $this->response = array(
                    'status' => 'failed',
                    'message' => $this->get_error_message('UNABLE_TO_CREATE_POST'),
                );
                // return rest_ensure_response($response);
            } else {
                $this->response = array(
                    'status' => 'success',
                    'message' => 'Table created successfully',
                    'table_id' => $params['table_id'],
                );
            }

            return $params;
        }

        public function get_tables($request)
        {
            $data = array();
            /** Get all tablesome posts */
            $posts = get_posts(
                array(
                    'post_type' => TABLESOME_CPT,
                    'numberposts' => -1,
                )
            );
            $response_data = array(
                'data' => $data,
                'message' => 'Get all tablesome tables data',
            );

            if (empty($posts)) {
                return rest_ensure_response($response_data);
            }
            $tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();

            foreach ($posts as $post) {
                $meta_data = get_tablesome_data($post->ID);

                error_log('$meta_data : ' . print_r($meta_data, true));

                $table = $tablesome_db->create_table_instance($post->ID);
                /** Get records count */
                $records_count = $table->count();

                $data[] = array(
                    'ID' => $post->ID,
                    'post_title' => $post->post_title,
                    'post_content' => $post->post_title,
                    'post_status' => $post->post_status,
                    'meta_data' => $meta_data,
                    'records_count' => $records_count,
                );
            }

            $response_data['data'] = $data;
            return rest_ensure_response($data);
        }

        public function check_table_access($post)
        {
            $result = [
                'has_access' => true,
                'message' => 'You have access to this table',
                'error_code' => null,
            ];

            if (empty($post) || $post->post_type != TABLESOME_CPT) {
                $result['error_code'] = "INVALID_POST";
                $result['has_access'] = false;
                $result['message'] = $this->get_error_message($result['error_code']);
                // return new \WP_Error($error_code, $this->get_error_message($error_code));
            }

            if ($post->post_status == 'private' && !current_user_can('read_private_posts')) {
                $result['error_code'] = "UNAUTHORIZED";
                $result['has_access'] = false;
                $result['message'] = $this->get_error_message($result['error_code']);
                // return new \WP_Error($error_code, $this->get_error_message($error_code));
            }

            if ($post->post_status != 'publish') {
                $result['error_code'] = "UNAUTHORIZED";
                $result['has_access'] = false;
                $result['message'] = $this->get_error_message($result['error_code']);
                // return new \WP_Error($error_code, $this->get_error_message($error_code));
            }

            return $result;

        }
        public function get_table_data($request)
        {
            $data = array();
            $table_id = $request->get_param('table_id');
            $post = get_post($table_id);

            $access_info = $this->check_table_access($post);

            if (!$access_info['has_access']) {
                return new \WP_Error($access_info['error_code'], $access_info['message']);
            }

            $tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
            $table_meta = get_tablesome_data($post->ID);

            $table = $tablesome_db->create_table_instance($post->ID);
            $records_count = $table->count();

            // $query = $tablesome_db->query(array(
            //     'table_id' => $post->ID,
            //     'table_name' => $table->name,
            //     'orderby' => array('rank_order', 'id'),
            //     'order' => 'asc',
            // ));

            // $records = isset($query->items) ? $query->items : [];
            // $records = $tablesome_db->get_formatted_rows($records, $table_meta, []);

            $args = array(
                'table_id' => $post->ID,
                'table_name' => $table->name,
            );

            $args['table_meta'] = $table_meta;
            $args['collection'] = [];

            $records = $tablesome_db->get_rows($args);

            $data = array(
                'ID' => $post->ID,
                'post_title' => $post->post_title,
                'post_content' => $post->post_content,
                'post_status' => $post->post_status,
                'meta_data' => $table_meta,
                'records' => $records,
                'records_count' => $records_count,
                'status' => 'success',
                'message' => 'Successfully get table with records',
            );

            return rest_ensure_response($data);
        }

        public function delete($request)
        {
            $table_id = $request->get_param('table_id');

            if (empty($table_id)) {
                $error_code = "REQUIRED_POST_ID";
                return new \WP_Error($error_code, $this->get_error_message($error_code));
            }

            $post = get_post($table_id);

            $access_info = $this->check_table_access($post);

            if (!$access_info['has_access']) {
                return new \WP_Error($access_info['error_code'], $access_info['message']);
            }

            // Check if user has permission to delete the table
            if (!current_user_can('delete_post', $table_id)) {
                $error_code = "UNAUTHORIZED";
                return new \WP_Error($error_code, $this->get_error_message($error_code));
            }

            // if (empty($post) || $post->post_type != TABLESOME_CPT) {
            //     $error_code = "INVALID_POST";
            //     return new \WP_Error($error_code, $this->get_error_message($error_code));
            // }
            $table = $this->tablesome_db->create_table_instance($post->ID);
            $table_drop = $table->drop();

            $message = 'Table Deleted';
            if (!$table_drop) {
                $message = 'Can\'t delete the table';
            }

            $response_data = array(
                'message' => $message,
            );
            return rest_ensure_response($response_data);
        }

        public function get_table_records($request)
        {
            $params = $request->get_params();

            $table_id = isset($params['table_id']) ? $params['table_id'] : 0;

            if (empty($table_id)) {
                $error_code = "REQUIRED_POST_ID";
                return new \WP_Error($error_code, $this->get_error_message($error_code));
            }

            $query_args = isset($params['query_args']) && is_array($params['query_args']) ? $params['query_args'] : [];

            $post = get_post($table_id);

            $access_info = $this->check_table_access($post);

            if (!$access_info['has_access']) {
                return new \WP_Error($access_info['error_code'], $access_info['message']);
            }

            // if (empty($post) || $post->post_type != TABLESOME_CPT) {
            //     $error_code = "INVALID_POST";
            //     return new \WP_Error($error_code, $this->get_error_message($error_code));
            // }
            $table_meta = get_tablesome_data($post->ID);
            $tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
            $table = $tablesome_db->create_table_instance($post->ID);

            $args = array_merge(
                array(
                    'table_id' => $post->ID,
                    'table_name' => $table->name,
                ), $query_args
            );

            $records = $tablesome_db->get_rows($args);

            // $query = $tablesome_db->query($query_args);

            // // TODO: Return the formatted data if need. don't send the actual db data
            // $records = isset($query->items) ? $query->items : [];

            $response_data = array(
                'records' => $tablesome_db->get_formatted_rows($records, $table_meta, []),
                'message' => 'Get records successfully',
                'status' => 'success',
            );

            return rest_ensure_response($response_data);
        }

        public function update_table_records_rest($request)
        {
            $params = $request->get_params();
            $this->response = $this->update_table_records($params);
            return $this->send_response($params);
        }

        public function update_table_records($params)
        {
            // error_log('update_table_records : ' . print_r($params, true));
            /* Input Validation */
            $params['mode'] = isset($params['mode']) ? $params['mode'] : '';
            $params['table_id'] = isset($params['table_id']) ? $params['table_id'] : 0;
            $params['meta_data'] = get_tablesome_data($params['table_id']);

            /* Early Return */
            if (empty($params['table_id'])) {
                $error_code = "REQUIRED_POST_ID";
                $this->response = array(
                    'status' => 'failed',
                    'message' => $this->get_error_message($error_code),
                );
                return new \WP_Error($error_code, $this->get_error_message($error_code));
            }

            $post = get_post($params['table_id']);

            $access_info = $this->check_table_access($post);

            if (!$access_info['has_access']) {
                return new \WP_Error($access_info['error_code'], $access_info['message']);
            }

            // if (empty($post) || $post->post_type != TABLESOME_CPT) {
            //     $error_code = "INVALID_POST";
            //     return new \WP_Error($error_code, $this->get_error_message($error_code));
            // }

            $table = $this->init_table($params);
            $params['table_name'] = $table->name;
            $tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
            $params['query'] = $tablesome_db->query(array(
                'table_id' => $params['table_id'],
                'table_name' => $params['table_name'],
            ));

            $response_data = $this->datatable->run_crud($params);

            $response_data = array_merge($response_data, array(
                'message' => 'Records modified successfully',
                'status' => 'success',
                'table_id' => $params['table_id'],
            ));

            // error_log("update_table_records() final response_data : " . print_r($response_data, true));

            return $response_data;
        }

        public function init_table($params)
        {
            $requests = array(
                'columns_deleted' => isset($params['columns_deleted']) ? $params['columns_deleted'] : [],
            );

            $tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
            $table = $tablesome_db->create_table_instance($params['table_id'], [], $requests);
            return $table;
        }

        public function delete_records($request)
        {
            $params = $request->get_params();
            $table_id = $request->get_param('table_id');
            $mode = isset($params['mode']) ? $params['mode'] : '';
            if (empty($table_id)) {
                $error_code = "REQUIRED_POST_ID";
                return new \WP_Error($error_code, $this->get_error_message($error_code));
            }

            $record_ids = $request->get_param("record_ids");

            $post = get_post($table_id);

            $access_info = $this->check_table_access($post);

            if (!$access_info['has_access']) {
                return new \WP_Error($access_info['error_code'], $access_info['message']);
            }

            // Check if user has permission to delete the table records
            // if (!current_user_can('delete_post', $table_id)) {
            //     $error_code = "UNAUTHORIZED";
            //     return new \WP_Error($error_code, $this->get_error_message($error_code));
            // }

            // if (empty($post) || $post->post_type != TABLESOME_CPT) {
            //     $error_code = "INVALID_POST";
            //     return new \WP_Error($error_code, $this->get_error_message($error_code));
            // }

            if (empty($record_ids)) {
                $error_code = "REQUIRED_RECORD_IDS";
                return new \WP_Error($error_code, $this->get_error_message($error_code));
            }

            $message = 'Records removed successfully';

            $tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
            $table = $tablesome_db->create_table_instance($post->ID);

            $query = $tablesome_db->query(array(
                'table_id' => $post->ID,
                'table_name' => $table->name,
            ));
            $args['table_id'] = $post->ID;
            $args['query'] = $query;
            $args['mode'] = $mode;
            $delete_records = $this->datatable->records->delete_records($args, $record_ids);

            $response_data = array(
                'message' => $message,
                'status' => ($delete_records) ? 'success' : 'failed',
            );
            return rest_ensure_response($response_data);
        }

    }
}
