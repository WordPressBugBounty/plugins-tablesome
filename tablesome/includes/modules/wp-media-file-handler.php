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

            $tmp = download_url($url);
            if (!is_wp_error($tmp)) {
                // delete temp file
                if ($can_delete_temp_file_after_download && $file_path) {
                    @unlink($file_path);
                }
            }

            $filename = pathinfo($url, PATHINFO_FILENAME);
            $extension = pathinfo($url, PATHINFO_EXTENSION);

            $args = array(
                'name' => "$filename.$extension",
                'tmp_name' => $tmp,
            );

            $attachment_id = media_handle_sideload($args, 0);
            return $attachment_id ? $attachment_id : 0;
        }
    }
}
