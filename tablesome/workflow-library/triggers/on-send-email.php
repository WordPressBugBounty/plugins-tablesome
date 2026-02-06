<?php

namespace Tablesome\Workflow_Library\Triggers;

use Tablesome\Includes\Modules\Workflow\Abstract_Trigger;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Triggers\On_Send_Email')) {
    class On_Send_Email extends Abstract_Trigger
    {
        public $trigger_source_data;

        public function get_config()
        {
            // $is_active = class_exists('WPCF7') ? true : false;

            return array(
                'integration' => 'email',
                'integration_label' => __('Email', 'tablesome'),
                'trigger' => 'tablesome_on_send_email',
                'trigger_id' => 8,
                'trigger_label' => __('On Email Send', 'tablesome'),
                'trigger_type' => 'email',
                'is_active' => true,
                'is_premium' => "no",
                'hooks' => array(
                    array(
                        "hook_type" => "filter",
                        'priority' => 10,
                        'accepted_args' => 1,
                        'name' => 'wp_mail',
                        'callback_name' => 'trigger_callback',
                    ),
                ),
                'supported_actions' => [1],
                'unsupported_actions' => []
            );
        }

        public function trigger_callback($posted_data)
        {
            // error_log('send_mail callback $posted_data : ' . print_r($posted_data, true));

            $is_trigger_configured = $this->workflows->is_trigger_configured_somewhere($this);

            // error_log('send_mail callback $is_trigger_configured : ' . $is_trigger_configured);
            // error_log('send_mail callback $posted_data : ' . print_r($posted_data, true));

            if (false == $is_trigger_configured) {
                return $posted_data;
            }

            $data = array();
            foreach ($posted_data as $key => $value) {
                // $post_fields = $this->get_post_fields();

                if ($key != 'headers') {
                    $field = $this->get_field_by_id($key);
                    $field_type = isset($field['field_type']) ? $field['field_type'] : 'text';
                    $label = isset($field['label']) ? $field['label'] : $key;
                    if ("message" == $key) {

                        // remove script tags
                        $value = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $value);
                        // remove style tags
                        $value = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', "", $value);
                        // remove html comments
                        $value = preg_replace('/<!--(.|\s)*?-->/', '', $value);
                        // remove html tags
                        $value = strip_tags($value, '<p><a>');
                        // remove multiple spaces
                        $value = preg_replace('/\s+/', ' ', $value);

                        $value = trim($value);
                    }

                    if ("attachments" === $key) {
                        $value = $this->get_attachment_links($value);
                    }

                    if ("to" === $key && is_array($value)) {
                        $value = implode(", ", $value);
                    }

                    $data[$key] = array(
                        'label' => $label,
                        'value' => $value,
                        'type' => $field_type,
                    );
                }

            }

            $this->trigger_source_data = array(
                'integration' => $this->get_config()['integration'],
                'data' => $data,
            );

            $this->run_triggers($this, $this->trigger_source_data);

            return $posted_data;
        }

        private function get_attachment_links($attachments)
        {
            if (empty($attachments)) {
                return "";
            }

            $links = "";
            foreach ($attachments as $link) {
                $is_file_exist = file_exists($link);
                $filename = basename($link);
                $attachment_id = $this->upload_file_from_path($link);
                $attachment_link = wp_get_attachment_url($attachment_id);
                $links .= '<p><a href="' . $attachment_link . '">' . $filename . '<a></p>';
            }
            return $links;
        }
        public function get_field_by_id($id)
        {
            $fields = $this->get_post_fields();
            $field = [];
            foreach ($fields as $element) {
                if ($id == $element['id']) {
                    $field = $element;
                    break;
                }
            }

            return $field;
        }

        public function get_post_fields($document_id = 0, array $args = array())
        {

            $fields = [
                [
                    "id" => 'to',
                    "label" => 'To',
                    "field_type" => 'email',
                ],
                [
                    "id" => 'subject',
                    "label" => 'Subject',
                    "field_type" => 'text',
                ],
                [
                    "id" => 'message',
                    "label" => 'Message',
                    "field_type" => 'textarea',
                ],
                [
                    "id" => 'attachments',
                    "label" => 'Attachments',
                    "field_type" => 'textarea',
                ],
            ];

            return $fields;
        }

        public function conditions($trigger_meta, $trigger_data)
        {

            return true;
        }

        public function upload_file_from_path($file, $title = null)
        {
            // Security: Validate that source file exists and is readable
            if (!file_exists($file) || !is_readable($file)) {
                error_log('Tablesome Email: Source file not accessible: ' . $file);
                return 0;
            }
            
            $filename = basename($file);
            
            // Security: Define allowed file extensions and MIME types
            $allowed_extensions = array(
                // Images
                'jpg', 'jpeg', 'jpe', 'gif', 'png', 'bmp', 'tiff', 'tif', 'ico', 'webp',
                // Safe documents
                'pdf', 'doc', 'docx', 'txt', 'csv', 'xls', 'xlsx', 'ppt', 'pptx',
                // Video
                'mp4', 'mov', 'wmv', 'avi', 'mpg', 'mpeg', 'ogv', '3gp', '3g2',
                // Audio
                'mp3', 'ogg', 'wav', 'm4a',
                // Archives
                'zip', 'rar', '7z', 'tar', 'gz'
            );
            
            $allowed_mime_types = array(
                // Images
                'image/jpeg', 'image/gif', 'image/png', 'image/bmp', 'image/tiff', 'image/x-icon', 'image/webp',
                // Documents
                'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain', 'text/csv',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                // Video
                'video/mp4', 'video/quicktime', 'video/x-ms-wmv', 'video/avi', 'video/mpeg', 'video/ogg', 'video/3gpp', 'video/3gpp2',
                // Audio
                'audio/mpeg', 'audio/ogg', 'audio/wav', 'audio/mp4',
                // Archives
                'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar', 'application/gzip'
            );
            
            // Security: Validate file type from filename BEFORE copying
            $wp_filetype_pre = wp_check_filetype($filename, null);
            if ($wp_filetype_pre['type'] === false || empty($wp_filetype_pre['ext'])) {
                error_log('Tablesome Email: Invalid file type from filename: ' . $filename);
                return 0;
            }
            
            // Security: Check if extension is in allowed list
            if (!in_array(strtolower($wp_filetype_pre['ext']), $allowed_extensions, true)) {
                error_log('Tablesome Email: File extension not allowed: ' . $wp_filetype_pre['ext']);
                return 0;
            }
            
            $media_dir = wp_upload_dir();
            $time_now = time();
            $safe_filename = sanitize_file_name($filename);
            $dest_file = $media_dir['path'] . '/' . $time_now . '-' . $safe_filename;
            
            // Copy the file to destination
            if (!copy($file, $dest_file)) {
                error_log('Tablesome Email: Failed to copy file: ' . $file);
                return 0;
            }
            
            // Security: Validate actual file content after copying
            $wp_filetype = wp_check_filetype_and_ext($dest_file, $safe_filename, null);
            
            // If validation fails, delete the file immediately
            if ($wp_filetype['type'] === false || 
                empty($wp_filetype['ext']) || 
                !in_array(strtolower($wp_filetype['ext']), $allowed_extensions, true) ||
                !in_array($wp_filetype['type'], $allowed_mime_types, true)) {
                
                @unlink($dest_file);
                error_log('Tablesome Email: File content validation failed, file deleted: ' . $safe_filename);
                return 0;
            }
            
            // Create attachment with validated file type
            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => sanitize_file_name(pathinfo($safe_filename, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit',
            );
            
            $attachment_id = wp_insert_attachment($attachment, $dest_file);
            
            // If attachment creation fails, clean up the file
            // wp_insert_attachment can return int|WP_Error but PHPStan stubs only show int
            /** @phpstan-ignore-next-line */
            if (is_wp_error($attachment_id)) {
                @unlink($dest_file);
                error_log('Tablesome Email: Attachment creation error: ' . $attachment_id->get_error_message());
                return 0;
            }
            
            if (!$attachment_id) {
                @unlink($dest_file);
                error_log('Tablesome Email: Failed to create attachment');
                return 0;
            }
            
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $dest_file);
            wp_update_attachment_metadata($attachment_id, $attachment_metadata);
            
            return (int) $attachment_id;
        }

    } // END class
}
