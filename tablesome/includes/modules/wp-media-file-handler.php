<?php

namespace Tablesome\Includes\Modules;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\WP_Media_File_Handler')) {
    class WP_Media_File_Handler
    {
        public function include_core_files()
        {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        public function maybe_create_dir($dir)
        {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }
        }

        public function upload_file_from_url($url, $args = [])
        {
            $can_delete_temp_file_after_download = isset($args['can_delete_temp_file_after_download']) ? $args['can_delete_temp_file_after_download'] : false;
            $file_path = isset($args['file_path']) ? $args['file_path'] : '';

            // Security: Validate URL format
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                error_log('Tablesome: Invalid URL format: ' . $url);
                return 0;
            }

            $filename = pathinfo($url, PATHINFO_FILENAME);
            $extension = pathinfo($url, PATHINFO_EXTENSION);

            // Security: Use WordPress allowed MIME types to match site/plugin policy
            $wp_allowed_mimes_map = \get_allowed_mime_types();
            $wp_allowed_mime_types = array_values($wp_allowed_mimes_map);

            // Require an extension; specific allowlist handled after content validation
            if (empty($extension)) {
                error_log('Tablesome: File extension missing in URL: ' . $url);
                return 0;
            }

            $tmp = download_url($url);
            if (is_wp_error($tmp)) {
                error_log('Tablesome: Failed to download file: ' . $tmp->get_error_message());
                return 0;
            }

            // delete temp file if requested
            if ($can_delete_temp_file_after_download && $file_path) {
                @unlink($file_path);
            }

            // Security: Validate the actual file type from content
            $wp_filetype = wp_check_filetype_and_ext($tmp, $filename . '.' . $extension, null);
            
            // Reject if file type validation fails
            if ($wp_filetype['type'] === false || empty($wp_filetype['ext'])) {
                @unlink($tmp);
                error_log('Tablesome: File type validation failed for: ' . $filename . '.' . $extension);
                return 0;
            }

            // Security: Ensure the MIME type is allowed by the WordPress/site policy
            if (!in_array($wp_filetype['type'], $wp_allowed_mime_types, true)) {
                @unlink($tmp);
                error_log('Tablesome: MIME type not allowed by site policy: ' . $wp_filetype['type']);
                return 0;
            }

            $args = array(
                'name' => "$filename." . $wp_filetype['ext'],
                'tmp_name' => $tmp,
            );

            $attachment_id = media_handle_sideload($args, 0);
            
            if (is_wp_error($attachment_id)) {
                error_log('Tablesome: media_handle_sideload error: ' . $attachment_id->get_error_message());
            }

            return $attachment_id ? $attachment_id : 0;
        }
    }
}
