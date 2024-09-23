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
            $media_dir = wp_upload_dir();
            $time_now = time();
            $upload_data = array();

            copy($file, $media_dir['path'] . '/' . $time_now . '-' . basename($file));
            $upload_data['name'] = basename($file);
            $filename = $upload_data['name'];
            $file = $media_dir['path'] . '/' . $time_now . '-' . basename($file);

            $wp_filetype = wp_check_filetype($filename, null);

            $attachment = array(
                'post_mime_type' => $wp_filetype['type'],
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit',
            );

            $attachment_id = wp_insert_attachment($attachment, $file);
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $file);
            wp_update_attachment_metadata($attachment_id, $attachment_metadata);

            return (int) $attachment_id;
        }

    } // END class
}
