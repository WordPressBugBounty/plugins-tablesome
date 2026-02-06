<?php

namespace Tablesome\Workflow_Library\Triggers;

use Tablesome\Includes\Modules\Workflow\Abstract_Trigger;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Triggers\Elementor')) {
    class Elementor extends Abstract_Trigger
    {

        public $trigger_source_id = 0;
        public $trigger_source_data = array();

        public $unsupported_formats = array(
            'hidden',
            'password',
            'acceptance',
            'step',
            'recaptcha',
            'recaptcha_v3',
            'honeypot',

            // can be used in the future
            'time',
            'html',
        );

        /**
         * Signature field types from various Elementor signature plugins
         * - e-addons: 'signature'
         * - Piotnet (PAFE): 'signature', 'pafe_signature'
         * - Dynamic.ooo: 'signature'
         * - Cool FormKit: 'signature'
         * - GM Signature: 'signature'
         */
        public $signature_field_types = array(
            'signature',
            'e_signature',
            'esignature',
            'pafe_signature',
        );

        /**
         * Allowed file extensions for upload validation
         */
        protected $allowed_extensions = array(
            // Images
            'jpg', 'jpeg', 'jpe', 'gif', 'png', 'bmp', 'tiff', 'tif', 'ico', 'webp',
            // Documents
            'pdf', 'doc', 'docx', 'txt', 'csv', 'xls', 'xlsx', 'ppt', 'pptx',
            // Video
            'mp4', 'mov', 'wmv', 'avi', 'mpg', 'mpeg', 'ogv', '3gp', '3g2',
            // Audio
            'mp3', 'ogg', 'wav', 'm4a',
            // Archives
            'zip', 'rar', '7z', 'tar', 'gz'
        );

        /**
         * Allowed MIME types for upload validation
         */
        protected $allowed_mime_types = array(
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

        public function get_config()
        {
            $is_active = defined('ELEMENTOR_PRO_PATH') ? true : false;

            return array(
                'integration' => 'elementor',
                'integration_label' => __('Elementor', 'tablesome'),
                'trigger' => 'tablesome_elementor_form_submit',
                'trigger_id' => 3,
                'trigger_label' => __('On Form Submit', 'tablesome'),
                'trigger_type' => 'forms',
                'is_active' => $is_active,
                'is_premium' => "no",
                'hooks' => array(
                    array(
                        'priority' => 10,
                        'accepted_args' => 4,
                        'name' => 'elementor_pro/forms/new_record',
                        'callback_name' => 'trigger_callback',
                    ),
                ),
                'supported_actions' => [],
                'unsupported_actions' => [8, 9]
            );
        }

        public function trigger_callback($record, $handler)
        {
            if (empty($record)) {
                return;
            }

            $form_id = $record->get_form_settings('id');
            if (empty($form_id)) {
                return;
            }

            $submission_data = $this->get_formatted_posted_data($record->get('fields'));
            $submission_data = apply_filters("tablesome_form_submission_data", $submission_data, $form_id);

            $this->trigger_source_id = $form_id;
            $this->trigger_source_data = array(
                'integration' => $this->get_config()['integration'],
                'form_title' => $record->get('form_settings')['form_name'],
                'form_id' => $form_id,
                'data' => $submission_data,
            );

            $this->run_triggers($this, $this->trigger_source_data);
        }

        public function conditions($trigger_meta, $trigger_data)
        {
            $integration = isset($trigger_meta['integration']) ? $trigger_meta['integration'] : '';
            $trigger_id = isset($trigger_meta['trigger_id']) ? $trigger_meta['trigger_id'] : '';

            if ($integration != $this->get_config()['integration'] || $trigger_id != $this->get_config()['trigger_id']) {
                return false;
            }

            $trigger_source_id = isset($trigger_meta['form_id']) ? $trigger_meta['form_id'] : 0;
            if (isset($trigger_data['form_id']) && $trigger_data['form_id'] == $trigger_source_id) {
                return true;
            }
            return false;
        }

        public function get_collection()
        {
            $forms = $this->get_posts();
            if (empty($forms)) {
                return [];
            }

            foreach ($forms as $index => $form) {
                $forms[$index]['fields'] = $this->get_post_fields($form["id"]);
            }
            return $forms;
        }

        public function get_posts()
        {
            $posts = [];
            global $wpdb;
            $post_metas = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT pm.meta_value
                        FROM $wpdb->postmeta pm
                            LEFT JOIN $wpdb->posts p
                                ON p.ID = pm.post_id
                        WHERE p.post_type IS NOT NULL
                        AND p.post_status = %s
                        AND pm.meta_key = %s
                        AND pm.`meta_value` LIKE %s",
                    'publish',
                    '_elementor_data',
                    '%%form_fields%%'
                )
            );

            if (!empty($post_metas)) {
                foreach ($post_metas as $post_meta) {
                    $inner_forms = self::get_all_inner_forms(json_decode($post_meta->meta_value));
                    if (!empty($inner_forms)) {
                        foreach ($inner_forms as $form) {
                            $posts[] = array(
                                'id' => $form->id,
                                'label' => $form->settings->form_name . " (ID: " . $form->id . ")",
                                'integration_type' => 'elementor',
                            );
                        }
                    }
                }
            }

            return $posts;
        }

        /**
         * Return all the specific fields of a form ID
         */
        public function get_post_fields($form_id)
        {
            $fields = [];

            global $wpdb;
            $query = "SELECT ms.meta_value  FROM {$wpdb->postmeta} ms JOIN {$wpdb->posts} p on p.ID = ms.post_id WHERE ms.meta_key LIKE '_elementor_data' AND ms.meta_value LIKE '%form_fields%' AND p.post_status = 'publish' ";
            $post_metas = $wpdb->get_results($query);

            if (!empty($post_metas)) {
                foreach ($post_metas as $post_meta) {
                    $inner_forms = self::get_all_inner_forms(json_decode($post_meta->meta_value));
                    if (!empty($inner_forms)) {
                        foreach ($inner_forms as $form) {
                            if ($form->id == $form_id) {
                                if (!empty($form->settings->form_fields)) {
                                    foreach ($form->settings->form_fields as $field) {
                                        $type = isset($field->field_type) && !empty($field->field_type) ? $field->field_type : 'text';

                                        if (!in_array($type, $this->unsupported_formats)) {
                                            $options = self::get_options($field);
                                            $single_field = [
                                                'id' => $field->custom_id,
                                                'label' => !empty($field->field_label) ? $field->field_label : 'unknown',
                                                'field_type' => $type,
                                            ];
                                            if (!empty($options)) {
                                                $single_field['options'] = $options;
                                            }

                                            $fields[] = $single_field;
                                        }

                                    }
                                }
                            }
                        }
                    }
                }
            }

            return $fields;
        }

        public static function get_all_inner_forms($elements)
        {
            $block_is_on_page = array();
            if (!empty($elements)) {
                foreach ($elements as $element) {
                    if ('widget' === $element->elType && 'form' === $element->widgetType) {
                        $block_is_on_page[] = $element;
                    }
                    if (!empty($element->elements)) {
                        $inner_block_is_on_page = self::get_all_inner_forms($element->elements);
                        if (!empty($inner_block_is_on_page)) {
                            $block_is_on_page = array_merge($block_is_on_page, $inner_block_is_on_page);
                        }
                    }
                }
            }

            return $block_is_on_page;
        }

        public static function get_options($field)
        {
            $options = [];
            if (isset($field->field_options) && !empty($field->field_options)) {
                $options_list = preg_split('/\r\n|\r|\n/', $field->field_options);

                foreach ($options_list as $option) {
                    array_push($options, [
                        'label' => $option,
                        'id' => $option,
                    ]);
                }
            }

            return $options;
        }

        /**
         * Validate uploaded file URL
         *
         * @param string $file_url The file URL to validate
         * @return bool True if valid, false otherwise
         */
        protected function is_valid_upload_url($file_url)
        {
            if (empty($file_url)) {
                return false;
            }

            // Validate URL format
            if (!filter_var($file_url, FILTER_VALIDATE_URL)) {
                return false;
            }

            // Security: Ensure URL is from the same site (WordPress uploads)
            // Use parse_url to properly compare hostnames instead of string prefix matching
            // to prevent subdomain bypass attacks (e.g., example.com.evil.com)
            $upload_dir = wp_upload_dir();
            $site_url = site_url();

            // Parse URLs to extract hostnames
            $file_url_parts = parse_url($file_url);
            $upload_url_parts = parse_url($upload_dir['baseurl']);
            $site_url_parts = parse_url($site_url);

            // Validate that all URLs have required components
            if (empty($file_url_parts['host']) || empty($file_url_parts['scheme'])) {
                error_log('Tablesome Elementor: Invalid file URL structure: ' . $file_url);
                return false;
            }

            if (empty($upload_url_parts['host']) || empty($site_url_parts['host'])) {
                error_log('Tablesome Elementor: Invalid site/upload URL configuration');
                return false;
            }

            // Normalize hostnames to lowercase for comparison
            $file_host = strtolower($file_url_parts['host']);
            $upload_host = strtolower($upload_url_parts['host']);
            $site_host = strtolower($site_url_parts['host']);

            // Compare schemes (http/https) - must match
            $file_scheme = strtolower($file_url_parts['scheme']);
            $upload_scheme = isset($upload_url_parts['scheme']) ? strtolower($upload_url_parts['scheme']) : 'http';
            $site_scheme = isset($site_url_parts['scheme']) ? strtolower($site_url_parts['scheme']) : 'http';

            // Check if hostname matches exactly (prevents subdomain bypass)
            $host_matches = ($file_host === $upload_host || $file_host === $site_host);
            
            // Check if scheme matches
            $scheme_matches = ($file_scheme === $upload_scheme || $file_scheme === $site_scheme);

            // URL must match both hostname and scheme
            $is_local_url = $host_matches && $scheme_matches;

            if (!$is_local_url) {
                error_log('Tablesome Elementor: Rejected external file URL: ' . $file_url);
                return false;
            }

            // Validate file extension
            $file_type = wp_check_filetype(basename($file_url));
            if (empty($file_type['ext']) || !in_array(strtolower($file_type['ext']), $this->allowed_extensions, true)) {
                error_log('Tablesome Elementor: Invalid file extension: ' . ($file_type['ext'] ?: 'unknown'));
                return false;
            }

            // Validate MIME type if available
            if (!empty($file_type['type']) && !in_array($file_type['type'], $this->allowed_mime_types, true)) {
                error_log('Tablesome Elementor: Invalid MIME type: ' . $file_type['type']);
                return false;
            }

            return true;
        }

        public function get_formatted_posted_data($fields)
        {
            $data = array();
            if (empty($fields)) {
                return $data;
            }

            foreach ($fields as $id => $field) {
                $value = $field['value'];
                $unix_timestamp = '';
                $file_url = '';
                $file_type = array('type' => '', 'ext' => '');
                $is_signature = false;

                if ($field['type'] == 'date' && is_valid_tablesome_date($value, 'Y-m-d')) {
                    $unix_timestamp = (int) convert_tablesome_date_to_unix_timestamp($value, 'Y-m-d');
                    $unix_timestamp = $unix_timestamp * 1000; // convert to milliseconds
                } else if ($field['type'] == 'upload') {
                    $value = is_array($value) ? $value[0] : $value;

                    // Security: Validate the upload URL
                    if ($this->is_valid_upload_url($value)) {
                        $file_url = $value;
                        $file_type = wp_check_filetype(basename($value));
                    } else {
                        // Invalid upload - clear the value
                        $value = '';
                        $file_url = '';
                    }
                } else if ($this->is_signature_field($field['type'])) {
                    // Handle signature fields from third-party Elementor plugins
                    $signature_result = $this->process_signature_field($value);
                    $value = $signature_result['url'];
                    $file_url = $signature_result['url'];
                    $is_signature = true;

                    // Security: Validate the signature URL if it's not empty
                    if (!empty($file_url) && !$signature_result['is_base64']) {
                        // Only validate non-base64 URLs (base64 was just saved locally)
                        if (!$this->is_valid_upload_url($file_url)) {
                            $value = '';
                            $file_url = '';
                        }
                    }

                    if (!empty($file_url)) {
                        $file_type = wp_check_filetype(basename($file_url));
                    }
                }

                $data[$id] = array(
                    'label' => $field['title'],
                    'value' => $value,
                    'type' => $field['type'],
                    'unix_timestamp' => $unix_timestamp,
                );

                if ($field['type'] == 'upload') {
                    $data[$id]['file_type'] = $file_type['type'] ?: '';
                    $data[$id]['linkText'] = 'View File';
                    $data[$id]['file_url'] = $file_url;
                }

                // Add signature-specific metadata for proper display and PDF generation
                if ($is_signature && !empty($file_url)) {
                    $data[$id]['file_type'] = $file_type['type'] ?: 'image/png';
                    $data[$id]['linkText'] = 'View Signature';
                    $data[$id]['file_url'] = $file_url;
                    $data[$id]['is_signature'] = true;
                }
            }
            return $data;
        }

        /**
         * Check if field type is a signature field
         *
         * @param string $field_type The field type to check
         * @return bool True if it's a signature field
         */
        protected function is_signature_field($field_type)
        {
            return in_array(strtolower($field_type), $this->signature_field_types);
        }

        /**
         * Process signature field value and convert to file URL if needed
         *
         * Handles various formats from different signature plugins:
         * - Direct URL string
         * - Base64 data URL (data:image/png;base64,...)
         * - Array with nested structure (e.g., ['file']['file_url'])
         *
         * @param mixed $value The signature field value
         * @return array Contains 'url' and 'is_base64' keys
         */
        protected function process_signature_field($value)
        {
            $result = array(
                'url' => '',
                'is_base64' => false,
            );

            if (empty($value)) {
                return $result;
            }

            // Handle array structures from various plugins
            if (is_array($value)) {
                // Forminator-style: ['file']['file_url']
                if (isset($value['file']['file_url'])) {
                    $value = $value['file']['file_url'];
                }
                // Some plugins use: ['url']
                elseif (isset($value['url'])) {
                    $value = $value['url'];
                }
                // Some plugins use: ['file_url']
                elseif (isset($value['file_url'])) {
                    $value = $value['file_url'];
                }
                // Array of URLs, take first
                elseif (isset($value[0]) && is_string($value[0])) {
                    $value = $value[0];
                }
            }

            // Now $value should be a string
            if (!is_string($value)) {
                return $result;
            }

            // Check if it's a base64 data URL
            if (strpos($value, 'data:image') === 0) {
                $saved_url = $this->save_base64_signature_to_file($value);
                if ($saved_url) {
                    $result['url'] = $saved_url;
                    $result['is_base64'] = true;
                }
            } else {
                // It's already a URL
                $result['url'] = $value;
            }

            return $result;
        }

        /**
         * Convert base64 signature data to a saved file and return the URL
         *
         * @param string $base64_data The base64 data URL (e.g., data:image/png;base64,...)
         * @return string|false The URL of the saved file or false on failure
         */
        protected function save_base64_signature_to_file($base64_data)
        {
            // Parse the base64 data URL - only allow safe image formats
            if (!preg_match('/^data:image\/(png|jpeg|jpg|gif);base64,(.+)$/i', $base64_data, $matches)) {
                error_log('Tablesome: Invalid base64 signature format');
                return false;
            }

            $extension = strtolower($matches[1]);
            if ($extension === 'jpeg') {
                $extension = 'jpg';
            }

            // Security: Validate extension is in allowed list
            if (!in_array($extension, array('png', 'jpg', 'gif'), true)) {
                error_log('Tablesome: Signature extension not allowed: ' . $extension);
                return false;
            }

            $decoded_data = base64_decode($matches[2], true);
            if ($decoded_data === false) {
                error_log('Tablesome: Failed to decode base64 signature');
                return false;
            }

            // Security: Validate the decoded data is actually an image
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->buffer($decoded_data);
            $allowed_signature_mimes = array('image/png', 'image/jpeg', 'image/gif');
            if (!in_array($mime_type, $allowed_signature_mimes, true)) {
                error_log('Tablesome: Invalid signature MIME type: ' . $mime_type);
                return false;
            }

            // Generate unique filename
            $filename = 'signature_' . time() . '_' . wp_rand(1000, 9999) . '.' . $extension;
            $filename = sanitize_file_name($filename);

            // Get upload directory
            $upload_dir = wp_upload_dir();
            $signature_dir = $upload_dir['basedir'] . '/tablesome-signatures';

            // Create directory if it doesn't exist
            if (!file_exists($signature_dir)) {
                wp_mkdir_p($signature_dir);

                // Add index.php for security
                $index_file = $signature_dir . '/index.php';
                if (!file_exists($index_file)) {
                    file_put_contents($index_file, '<?php // Silence is golden');
                }
            }

            $file_path = $signature_dir . '/' . $filename;

            // Save the file
            $bytes_written = file_put_contents($file_path, $decoded_data);
            if ($bytes_written === false) {
                error_log('Tablesome: Failed to save signature file');
                return false;
            }

            // Return the URL
            $file_url = $upload_dir['baseurl'] . '/tablesome-signatures/' . $filename;

            return $file_url;
        }
    }

}
