<?php

namespace Tablesome\Includes\Modules\Workflow;

use Tablesome\Includes\Modules\Workflow\Event_Log\Event_Log;
use \Tablesome\Includes\Settings\Tablesome_Getter;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\Workflow\Workflow_Manager')) {
    class Workflow_Manager
    {

        public static $instance = null;

        public $library;

        public $workflows;

        public static function get_instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
                self::$instance->init();
            }
            return self::$instance;
        }

        public function init()
        {

            // add_filter('http_request_timeout', function ($timeout) {return 60;});

            $this->library = get_tablesome_workflow_library();
            $this->workflows = new \Tablesome\Includes\Modules\Workflow\Workflows();

            // if (pauple_is_feature_active('gsheet_action')) {
            //     $this->library->actions['gsheet_add_row'] = new GSheet_Add_Row();
            //     $this->library->integrations['gsheet'] = new GSheet();
            // }

            $this->register_trigger_hooks();
            // add_action("load_editor");

            add_filter("tablesome_form_submission_data", [self::$instance, "add_attachment_to_submission_data"], 10, 2);

            Event_Log::get_instance();
        }

        public function register_trigger_hooks()
        {

            foreach ($this->library->triggers as $key => $trigger) {
                $trigger->init($this->library->actions);
                $config = $trigger->get_config();

                if (!isset($config['hooks'])) {
                    continue;
                }

                foreach ($config['hooks'] as $hook) {

                    if (isset($hook['hook_type']) && $hook['hook_type'] == "filter") {
                        add_filter($hook['name'], array($trigger, $hook['callback_name']), $hook['priority'], $hook['accepted_args']);
                    } else {
                        add_action($hook['name'], array($trigger, $hook['callback_name']), $hook['priority'], $hook['accepted_args']);
                    }

                }

            }
        }

        public function get_trigger_prop_value_by_id($trigger_id, $prop_name)
        {
            $value = '';
            foreach ($this->library->triggers as $trigger) {
                $config = $trigger->get_config();
                if (isset($config['trigger_id']) && $config['trigger_id'] == $trigger_id) {
                    $value = isset($config[$prop_name]) ? $config[$prop_name] : '';
                    break;
                }
            }
            return $value;
        }

        public function get_action_prop_value_by_id($action_id, $prop_name)
        {
            $value = '';
            foreach ($this->library->actions as $action) {
                $config = $action->get_config();
                if (isset($config['id']) && $config['id'] == $action_id) {
                    $value = isset($config[$prop_name]) ? $config[$prop_name] : '';
                    break;
                }
            }
            return $value;
        }

        public function get_action_integration_label_by_id($action_id)
        {
            $label = '';
            foreach ($this->library->actions as $action) {
                $config = $action->get_config();
                if (isset($config['id']) && $config['id'] == $action_id) {
                    $integration = $config['integration'];
                    $label = $this->library->integrations[$integration]->get_config()['integration_label'];
                    break;
                }
            }
            return $label;
        }

        public function get_external_data_by_integration($integration)
        {
            if (!isset($this->library->integrations[$integration])) {
                return [];
            }

            $class = $this->library->integrations[$integration];

            if ($integration == 'notion') {
                return $class->notion_api->get_all_databases(array('excluded_props' => 'fields,archived'));
            } else if ($integration == 'mailchimp') {
                return $class->get_all_audiences(array('can_add_fields' => false, 'can_add_tags' => false));
            } else if ($integration == 'hubspot') {
                return $class->get_static_lists();
            } else if ($integration == 'gsheet') {
                // return $class->get_spreadsheets();
            }

        }

        public function get_external_data_fields_by_id($integration, $document_id)
        {
            if (!isset($this->library->integrations[$integration]) || empty($document_id)) {
                return [];
            }

            $class = $this->library->integrations[$integration];

            if ('notion' == $integration) {
                $database = $class->notion_api->get_database_by_id($document_id);
                return $class->notion_api->get_formatted_fieds($database);
            } else if ($integration == 'mailchimp') {
                return $class->get_all_fields_from_audience($document_id);
            } else if ($integration == 'hubspot') {
                return $class->get_fields();
            } else if ($integration == 'gsheet') {
                return $class->get_sheets_by_spreadsheet_id($document_id);
            } else if ($integration == 'slack' && $document_id == "channels") {
                return $class->slack_api->get_channels();
            } else if ($integration == 'slack' && $document_id == "users") {
                return $class->slack_api->get_users();
            }
        }

        public function get_posts_by_integration($integration)
        {
            $trigger_classs = isset($this->library->triggers[$integration]) ? $this->library->triggers[$integration] : null;
            if (is_null($trigger_classs)) {
                return [];
            }
            $posts = $trigger_classs->get_posts();
            return $posts;
        }

        public function get_post_fields_by_id($integration, $document_id)
        {
            // error_log('$this->library->triggers : ' . print_r($this->library->triggers, true));
            // error_log('$integration : ' . $integration);
            $trigger_classs = isset($this->library->triggers[$integration]) ? $this->library->triggers[$integration] : null;
            if (is_null($trigger_classs)) {
                return [];
            }

            $fields = $trigger_classs->get_post_fields($document_id);
            // error_log('get_post_fields_by_id $fields : ' . print_r($fields, true));
            return $fields;
        }

        public function find_trigger_source_id($form_id)
        {
            global $wpdb;

            // Get all 'tablesome_table_triggers' post_meta of all posts
            $trigger_meta_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
                    'tablesome_table_triggers'
                )
            );

            if (empty($trigger_meta_rows)) {
                return false;
            }

            // Loop through each meta value
            foreach ($trigger_meta_rows as $row) {
                $triggers = \maybe_unserialize($row->meta_value);
                
                if (!is_array($triggers)) {
                    continue;
                }

                // Loop through triggers array
                foreach ($triggers as $trigger) {
                    // Check if this is a valid trigger array with required keys
                    if (!isset($trigger['form_id']) || !isset($trigger['status']) || !isset($trigger['trigger_id'])) {
                        continue;
                    }

                    // Check if trigger is active and matches form_id
                    if ($trigger['status'] === true && (int) $trigger['form_id'] === (int) $form_id) {
                        return [
                            'post_id' => $row->post_id,
                            'trigger_id' => $trigger['trigger_id']
                        ];
                    }
                }
            }

            return false;
        }
        public function add_attachment_to_submission_data($submission_data, $form_id)
        {
            // error_log('add_attachment_to_submission_data submission_data' . print_r($submission_data, true));
            // Check if store all form entries is enabled
            $enabled_all_forms_entries = Tablesome_Getter::get('enabled_all_forms_entries');

            // Check if the form is added in a Tablesome trigger
            $is_trigger_configured = $this->find_trigger_source_id($form_id);

            // error_log('enabled_all_forms_entries : ' . $enabled_all_forms_entries);
            // error_log('is_trigger_configured : ' . $is_trigger_configured);

            if (!$is_trigger_configured && !$enabled_all_forms_entries) {
                return $submission_data;
            }


            // STORE FILES IN MEDIA LIBRARY

            $file_types = ["upload", "file-upload", "fileupload", "post_image", 'input_image', 'input_file'];
            if (isset($submission_data) && !empty($submission_data)) {
                // error_log(' before submission_data : ' . print_r($submission_data, true));

                foreach ($submission_data as $field_key => $field) {
                    if (in_array($field["type"], $file_types) && !empty($field["value"])) {
                        $file_url = self::$instance->get_single_url_from_value($field["value"]);
                        // error_log(' file_url : ' . print_r($file_url, true));

                        $field["value"] = self::$instance->upload_file_from_url($file_url);
                        $field["type"] = "file";
                    }

                    $submission_data[$field_key] = $field;
                }

                // error_log(' after submission_data : ' . print_r($submission_data, true));
                return $submission_data;
            }

            return $submission_data;
        }

        public function get_single_url_from_value($value)
        {
            $url = "";
            $is_comma_separated = false;
            $is_linebreak_separated = false;

            if (!empty($value)) {
                $comma_separated_values = explode(",", $value);
                $linebreak_separated_values = explode("\n", $value);

                $is_comma_separated = is_array($comma_separated_values) && count($comma_separated_values) > 1;
                $is_linebreak_separated = is_array($linebreak_separated_values) && count($linebreak_separated_values) > 1;

                if ($is_comma_separated) {
                    $value = $comma_separated_values[0];
                } else if ($is_linebreak_separated) {
                    $value = $linebreak_separated_values[0];
                }

                $url = trim($value);
            }

            return $url;
        }

        public function upload_file_from_url($url, $title = null)
        {
            require_once ABSPATH . "/wp-load.php";
            require_once ABSPATH . "/wp-admin/includes/image.php";
            require_once ABSPATH . "/wp-admin/includes/file.php";
            require_once ABSPATH . "/wp-admin/includes/media.php";

            // Security: Validate URL format
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                error_log('Tablesome: Invalid URL format: ' . $url);
                return false;
            }

            // Get the filename and extension ("photo.png" => "photo", "png")
            $filename = pathinfo($url, PATHINFO_FILENAME);
            $extension = pathinfo($url, PATHINFO_EXTENSION);

            // Security: Use WordPress allowed MIME types to match site/plugin policy
            $wp_allowed_mimes_map = \get_allowed_mime_types();
            $wp_allowed_mime_types = array_values($wp_allowed_mimes_map);

            // An extension is required or else WordPress will reject the upload
            if (!$extension) {
                error_log('Tablesome: No file extension found in URL: ' . $url);
                return false;
            }

            // Extension presence is required; specific allowlist handled after content check

            // Download url to a temp file
            $tmp = download_url($url);
            if (is_wp_error($tmp)) {
                error_log('Tablesome: Failed to download file: ' . $tmp->get_error_message());
                return false;
            }

            // Security: Validate the actual file type from content
            $wp_filetype = wp_check_filetype_and_ext($tmp, $filename . '.' . $extension, null);
            
            // Reject if file type validation fails
            if ($wp_filetype['type'] === false || empty($wp_filetype['ext'])) {
                @unlink($tmp);
                error_log('Tablesome: File type validation failed for: ' . $filename . '.' . $extension);
                return false;
            }

            // Security: Ensure the MIME type is allowed by the WordPress/site policy
            if (!in_array($wp_filetype['type'], $wp_allowed_mime_types, true)) {
                @unlink($tmp);
                error_log('Tablesome: MIME type not allowed by site policy: ' . $wp_filetype['type']);
                return false;
            }

            // Upload by "sideloading": "the same way as an uploaded file is handled by media_handle_upload"
            $args = array(
                'name' => sanitize_file_name("$filename." . $wp_filetype['ext']),
                'tmp_name' => $tmp,
            );

            // Do the upload
            $attachment_id = media_handle_sideload($args, 0, $title);

            // Cleanup temp file
            @unlink($tmp);

            // Error uploading
            if (is_wp_error($attachment_id)) {
                error_log('Tablesome: media_handle_sideload error: ' . $attachment_id->get_error_message());
                return false;
            }

            // Success, return attachment ID (int)
            return (int) $attachment_id;
        }
    }

}
