<?php

namespace Tablesome\Workflow_Library\Actions;

use Tablesome\Includes\Modules\Workflow\Action;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Actions\WP_Post_Creation')) {
    class WP_Post_Creation extends Action
    {

        public $trigger_class;
        public $trigger_instance;

        public $fields;
        public $action_meta;

        public $trigger_source_data;

        public $post_id;

        public $smart_field_values;

        public $available_status = array(
            'publish' => 'Publish',
            'future' => 'Future',
            'draft' => 'Draft',
            'pending' => 'Pending',
            'private' => 'Private',
            'trash' => 'Trash',
        );

        private static $DEFAULT_POST_STATUS = 'draft';

        private static $DEFAULT_POST_TITLE = 'Untitled Post';

        public function get_config()
        {
            return array(
                'id' => 4,
                'name' => 'add_new_wp_post',
                'label' => __('Add New WP Post', 'tablesome'),
                'integration' => 'wordpress',
                'is_premium' => false,
            );
        }

        public function do_action($trigger_class, $trigger_instance)
        {
            $this->bind_props($trigger_class, $trigger_instance);

            if (empty($this->fields)) {
                return;
            }

            $post_data = $this->get_post_data_from_trigger();
            // error_log("post_data: " . print_r($post_data, true));
            $result = wp_insert_post($post_data);

            if (0 == $result) {
                // $message = $result->get_error_message();
                return false;
            }

            $this->post_id = $result;

            $this->add_postmeta();

            $this->set_post_terms();

            $this->set_featured_image($post_data);
        }

        private function bind_props($trigger_class, $trigger_instance)
        {

            // error_log('trigger_instance: ' . print_r($trigger_instance, true));
            global $workflow_data;
            $_trigger_instance_id = $trigger_instance['_trigger_instance_id'];

            $this->trigger_class = $trigger_class;
            $this->trigger_instance = $trigger_instance;

            $this->trigger_source_data = $this->trigger_class->trigger_source_data['data'];
            $this->action_meta = isset($this->trigger_instance['action_meta']) ? $this->trigger_instance['action_meta'] : [];

            $this->fields = isset($this->action_meta['fields']) ? $this->action_meta['fields'] : [];

            // set post_id is 0
            $this->post_id = 0;
            // TODO: Rename the variable. Cause, It has smart field values, trigger source data as key-value pairs, and action responses as well.
            $this->smart_field_values = isset($workflow_data[$_trigger_instance_id]) ? $workflow_data[$_trigger_instance_id] : [];
        }

        private function get_post_data_from_trigger()
        {
            $post_data = array();

            $post_type_index = array_search('post_type', array_column($this->fields, 'name'));
            $post_type = isset($this->fields[$post_type_index]['value']) ? $this->fields[$post_type_index]['value'] : '';
            if (empty($post_type)) {
                return;
            }
            $post_type_exists = post_type_exists($post_type);

            if (!$post_type_exists) {
                return;
            }

            $post_data['post_title'] = $this->get_post_prop_value_by_name('post_title');
            $post_data['post_content'] = $this->get_post_prop_value_by_name('post_content');
            $post_data['post_type'] = $post_type;
            $post_data['post_status'] = $this->get_post_prop_value_by_name('post_status');
            $post_data['post_excerpt'] = $this->get_post_prop_value_by_name('post_excerpt');
            $post_data['post_featured_image'] = $this->get_post_prop_value_by_name('post_featured_image');
            // $post_data['post_author'] = $this->get_post_prop_value_by_name('post_author');

            return $post_data;
        }

        private function get_post_prop_value_by_name($name)
        {
            // error_log("this->fields: " . print_r($this->fields, true));
            // error_log('trigger_source_data: ' . print_r($this->trigger_source_data, true));
            $index = array_search($name, array_column($this->fields, 'name'));
            if (!is_numeric($index)) {
                return '';
            }

            $source_type = isset($this->fields[$index]['source_type']) ? $this->fields[$index]['source_type'] : 'custom';
            $value = isset($this->fields[$index]['value']) ? $this->fields[$index]['value'] : '';

            $target_field = $value;

            if ('trigger_source' === $source_type) {
                $value = isset($this->trigger_source_data[$target_field]['value']) ? $this->trigger_source_data[$target_field]['value'] : '';

                // Special condition for Forminator trigger - because forminator does not give attachment id
                if ('' == $value && isset($this->trigger_source_data[$target_field]['file_url']) && $this->trigger_source_data[$target_field]['type'] == 'file') {

                    $value = $this->trigger_source_data[$target_field]['file_url'];
                    // $value = attachment_url_to_postid($file_url);

                    // error_log('Forminator file_url: ' . print_r($file_url, true));
                    // error_log(' Forminator value: ' . print_r($value, true));
                }
            } else if ('trigger_smart_fields' === $source_type) {
                $value = isset($this->smart_field_values[$target_field]) ? $this->smart_field_values[$target_field] : '';
            }

            if ('post_status' === $name) {
                return !empty($value) && in_array($value, array_keys($this->available_status)) ? $value : self::$DEFAULT_POST_STATUS;
            } else if ('post_title' === $name) {
                return !empty($value) ? wp_strip_all_tags($value) : self::$DEFAULT_POST_TITLE;
            } else if ('post_author' === $name) {

                if (is_numeric($value)) {
                    return get_user_by('id', $value) ? intval($value) : 0;
                } else if (is_string($value)) {
                    /**
                     * If username exists its return the user-id else its return false.
                     * Ref: https://developer.wordpress.org/reference/functions/username_exists/
                     */
                    $user_id = username_exists($value);
                    return isset($user_id) ? $user_id : 0;
                }
            }

            return $value;
        }

        private function add_postmeta()
        {

            $postmeta_fields = array_filter($this->fields, function ($field) {
                return $field['field_type'] == 'postmeta';
            });

            if (empty($postmeta_fields)) {
                return;
            }

            foreach ($postmeta_fields as $field) {
                $meta_key = isset($field['key']) ? $field['key'] : '';
                $target_field = isset($field['value']) ? $field['value'] : '';
                $source_type = isset($field['source_type']) ? $field['source_type'] : 'custom';

                if (empty($meta_key)) {
                    continue;
                }
                $meta_value = $target_field;
                if ('trigger_source' === $source_type) {
                    $meta_value = isset($this->trigger_source_data[$target_field]['value']) ? $this->trigger_source_data[$target_field]['value'] : '';
                } else if ('trigger_smart_fields' === $source_type) {
                    $meta_value = isset($this->smart_field_values[$target_field]) ? $this->smart_field_values[$target_field] : '';
                }
                update_post_meta($this->post_id, $meta_key, $meta_value);
            }
        }

        private function set_post_terms()
        {
            /** @noinspection PhpUndefinedConstantInspection */
            $ABSPATH = defined('ABSPATH') ? ABSPATH : '';

            $taxonomy_fields = array_filter($this->fields, function ($field) {
                return $field['field_type'] == 'taxonomies';
            });

            if (empty($taxonomy_fields)) {
                return;
            }

            foreach ($taxonomy_fields as $taxonomy_field) {

                $taxonomy = isset($taxonomy_field['taxonomy']) ? $taxonomy_field['taxonomy'] : '';

                if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
                    continue;
                }

                $terms = isset($taxonomy_field['terms']) ? $taxonomy_field['terms'] : [];
                $source_type = isset($taxonomy_field['source_type']) ? $taxonomy_field['source_type'] : 'custom';

                if ('custom' === $source_type) {
                    foreach ($terms as $term_value) {
                        $term = get_term_by('id', (int) $term_value, $taxonomy);

                        if (!$term) {
                            continue;
                        }

                        if (!function_exists('wp_set_object_terms')) {
                            require_once $ABSPATH . 'wp-includes/taxonomy.php';
                        }
                        wp_set_object_terms($this->post_id, intval($term->term_id), $taxonomy, true);
                    }
                } else if ('trigger_source' === $source_type || 'trigger_smart_fields' === $source_type) {
                    // TODO How users includes the terms in form?

                }
            }

        }

        private function set_featured_image($post_data)
        {
            /** @noinspection PhpUndefinedConstantInspection */
            $ABSPATH = defined('ABSPATH') ? ABSPATH : '';

            if (!isset($post_data["post_featured_image"])) {
                return false;
            }

            // If the featured image is external url - Forminator case
            if (!is_numeric($post_data["post_featured_image"])) {
                $this->set_featured_image_from_external_url($post_data["post_featured_image"], $this->post_id);
                return;
            }

            $attachment_id = $post_data["post_featured_image"];
            $does_attachment_id_exist = isset($attachment_id) && !empty($attachment_id);
            $does_this_attachment_post_type = 'attachment' === get_post_type($attachment_id);

            if (!$does_attachment_id_exist || !$does_this_attachment_post_type) {
                return false;
            }

            wp_update_post(array(
                'ID' => $attachment_id,
                'post_parent' => $this->post_id,
            ), true);

            require_once $ABSPATH . "/wp-admin/includes/image.php";
            $attachment_data = wp_generate_attachment_metadata($attachment_id, get_attached_file($attachment_id));
            wp_update_attachment_metadata($attachment_id, $attachment_data);
            set_post_thumbnail($this->post_id, $attachment_id);
        }

        private function set_featured_image_from_external_url($url, $post_id)
        {
            /** @noinspection PhpUndefinedConstantInspection */
            $ABSPATH = defined('ABSPATH') ? ABSPATH : '';

            if (!filter_var($url, FILTER_VALIDATE_URL) || empty($post_id)) {
                return;
            }

            // Add Featured Image to Post
            $image_url = preg_replace('/\?.*/', '', $url); // removing query string from url & Define the image URL here
            $image_name = basename($image_url);
            
            // Security: Pre-validate that the name suggests an image; confirm again after save
            $wp_filetype = wp_check_filetype($image_name, null);
            if ($wp_filetype['type'] === false || empty($wp_filetype['ext']) || strpos($wp_filetype['type'], 'image/') !== 0) {
                error_log('Tablesome: Non-image or unknown file type rejected for featured image: ' . $image_name);
                return;
            }
            
            $upload_dir = wp_upload_dir(); // Set upload folder
            
            // Get image data from remote URL
            $response = wp_remote_get($url, array(
                'timeout' => 15,
                'sslverify' => true,
            ));
            
            // Check if the request was successful
            if (is_wp_error($response)) {
                error_log('Tablesome: Failed to retrieve remote file: ' . $response->get_error_message());
                return;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                error_log('Tablesome: Remote file returned non-200 status code: ' . $response_code);
                return;
            }
            
            $image_data = wp_remote_retrieve_body($response);
            
            // Verify we actually got data
            if (empty($image_data)) {
                error_log('Tablesome: Remote file has no content');
                return;
            }
            
            $unique_file_name = wp_unique_filename($upload_dir['path'], $image_name); // Generate unique name
            $filename = basename($unique_file_name); // Create image file name

            // Check folder permission and define file location
            if (wp_mkdir_p($upload_dir['path'])) {
                $file = $upload_dir['path'] . '/' . $filename;
            } else {
                $file = $upload_dir['basedir'] . '/' . $filename;
            }

            // Create the image file on the server
            $saved = file_put_contents($file, $image_data);
            
            if ($saved === false) {
                error_log('Tablesome: Failed to save file to disk: ' . $file);
                return;
            }
            
            // Security: Verify the actual file type is an image
            $wp_filetype_actual = wp_check_filetype_and_ext($file, $filename, null);
            if ($wp_filetype_actual['type'] === false || empty($wp_filetype_actual['ext']) || stripos($wp_filetype_actual['type'], 'image/') !== 0) {
                @unlink($file);
                error_log('Tablesome: File content validation failed (not an image), file deleted: ' . $filename);
                return;
            }

            // Set attachment data using the validated file type
            $attachment = array(
                'post_mime_type' => $wp_filetype_actual['type'],
                'post_title' => sanitize_file_name($filename),
                'post_content' => '',
                'post_status' => 'inherit',
            );

            // Create the attachment
            $attach_id = wp_insert_attachment($attachment, $file, $post_id);
            
            // @phpstan-ignore-next-line - wp_insert_attachment can return WP_Error
            if (is_wp_error($attach_id) || !$attach_id) {
                @unlink($file); // Delete the file if attachment creation failed
                error_log('Tablesome: Failed to create attachment');
                return;
            }

            // Include image.php
            require_once $ABSPATH . 'wp-admin/includes/image.php';

            // Define attachment metadata
            $attach_data = wp_generate_attachment_metadata($attach_id, $file);

            // Assign metadata to attachment
            wp_update_attachment_metadata($attach_id, $attach_data);

            // And finally assign featured image to post
            set_post_thumbnail($post_id, $attach_id);
        }
    }
}
