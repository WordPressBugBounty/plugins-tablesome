<?php

namespace Tablesome\Includes;

if (!class_exists('\Tablesome\Includes\Ajax_Handler')) {
    class Ajax_Handler
    {

        public $datatable;

        public function __construct()
        {
            $this->datatable = new \Tablesome\Includes\Modules\Datatable\Datatable();

            add_action('wp_ajax_store_tablesome_data', array($this, 'save_table'));

            // data import - ajax handler
            // add_action('wp_ajax_importing_data', array($this, 'import_table'));
            // add_action('wp_ajax_nopriv_importing_data', array($this, 'import_table'));

            /*** Get the table Props  */
            add_action('wp_ajax_get_tables_data', array($this, 'load_tables'));

            /*** Get the table Props  */
            add_action('wp_ajax_get_table_columns', array($this, 'get_table_columns_by_table_id'));

            add_action('wp_ajax_update_feature_notice_dismissal_data_via_ajax', array(new \Tablesome\Includes\Modules\Feature_Notice(), 'update_feature_notice_dismissal_data_via_ajax'));

            add_action("wp_ajax_get_redirection_data", array($this, 'get_redirection_data'));

        }

        public function save_table()
        {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => 'Unauthorized access. You do not have permission to perform this action.',
                    'code' => 'unauthorized'
                ), 403);
                wp_die();
            }

            /** Get table table-data from ajax request. And decode the table-data.*/
            $data = isset($_REQUEST['table_data']) ? json_decode(stripslashes($_REQUEST['table_data']), true) : [];

            $table = new \Tablesome\Includes\Core\Table();
            $getter = new \Tablesome\Includes\Ajax\Getter();
            $props = $getter->get_tablesome_storing_data_props_from_ajax($data);
            $post_title = isset($props['post_title']) && !empty($props['post_title']) ? $props['post_title'] : '';
            $post_data = array(
                'post_title' => $post_title,
                'post_type' => TABLESOME_CPT,
                'post_content' => '',
                'post_status' => 'publish',
            );

            $post_id = $this->datatable->post->save($props['post_id'], $post_data);

            $table->set_table_meta_data($post_id, $props);

            $edit_page_url = $table->get_edit_table_url($post_id);

            $response_message = isset($props["post_action"]) && !empty($props["post_action"]) && $props['post_action'] == 'add' ? 'Table Created' : 'Table Updated';

            $reponse = array(
                'message' => $response_message,
                'type' => 'UPDATED',
                'edit_page_url' => $edit_page_url,
            );

            wp_send_json($reponse);
            wp_die();
        }

        public function get_table_columns_by_table_id()
        {
            if (!current_user_can('edit_posts')) {
                wp_send_json_error(array(
                    'message' => 'Unauthorized access. You do not have permission to view table columns.',
                    'code' => 'unauthorized'
                ), 403);
                wp_die();
            }

            $table_id = isset($_REQUEST['table_id']) && !empty($_REQUEST['table_id']) && intval($_REQUEST['table_id']) ? $_REQUEST['table_id'] : 0;
            
            // Security: Check table ownership - Contributors can only view their own tables
            // Editors and Admins (with edit_others_posts) can view any table
            $post = get_post($table_id);
            if ($post) {
                $current_user_id = get_current_user_id();
                $can_edit_others_posts = current_user_can('edit_others_posts');
                // Security: Verify user is authenticated before checking ownership (prevent 0 == 0 bypass)
                $is_table_owner = ($current_user_id > 0 && $post->post_author == $current_user_id);
                
                if (!$can_edit_others_posts && !$is_table_owner) {
                    wp_send_json_error(array(
                        'message' => 'Unauthorized access. You do not have permission to view table columns.',
                        'code' => 'unauthorized'
                    ), 403);
                    wp_die();
                }
            }
            
            $shortcode_builder_handler = new \Tablesome\Includes\Shortcode_Builder\Handler();
            $validate_the_post_id = $shortcode_builder_handler->validate($table_id);
            $columns = [];

            $status = 'failed';
            $message = 'validation failed';
            if ($validate_the_post_id) {
                $status = 'success';
                $message = 'Successfully get the table columns';
                $columns = $shortcode_builder_handler->get_columns($table_id);
            }

            $response = array(
                'status' => $status,
                'message' => $message,
                'data' => $columns,
            );

            wp_send_json($response);
            wp_die();
        }

        public function get_redirection_data()
        {
            if (!is_user_logged_in()) {
                wp_send_json_error(array(
                    'message' => 'Unauthorized access. You must be logged in to access this resource.',
                    'code' => 'unauthorized'
                ), 403);
                wp_die();
            }

            $redirection_data = get_option('workflow_redirection_data');

            error_log('*** get_redirection_data ***');
            $redirection_data = isset($redirection_data) && !empty($redirection_data) ? $redirection_data : [];

            $response = array(
                'status' => 'success',
                'message' => 'Successfully get the redirection data',
                'data' => $redirection_data,
            );

            delete_option('workflow_redirection_data');
            wp_send_json($response);
            wp_die();
        }

    }
}
