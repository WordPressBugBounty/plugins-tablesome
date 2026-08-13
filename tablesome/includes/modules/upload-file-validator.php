<?php

namespace Tablesome\Includes\Modules;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\Tablesome\Includes\Modules\Upload_File_Validator')) {
    class Upload_File_Validator
    {
        protected $dangerous_extensions = array(
            'php',
            'php3',
            'php4',
            'php5',
            'php7',
            'phtml',
            'phar',
            'cgi',
            'pl',
            'py',
            'rb',
            'asp',
            'aspx',
            'jsp',
            'sh',
            'bash',
            'zsh',
            'ksh',
            'csh',
            'ps1',
            'bat',
            'cmd',
            'com',
            'exe',
            'dll',
            'so',
            'dylib',
            'scr',
            'msi',
            'jar',
            'class',
            'swf',
            'htm',
            'html',
            'js',
        );

        public function validate($file, $filename, $args = array())
        {
            if (!file_exists($file) || !is_readable($file)) {
                return new \WP_Error('tablesome_upload_file_not_accessible', __('Source file is not accessible.', 'tablesome'));
            }

            $safe_filename = sanitize_file_name($filename);
            if (empty($safe_filename)) {
                return new \WP_Error('tablesome_upload_empty_filename', __('Filename is empty after sanitization.', 'tablesome'));
            }

            $extension = strtolower(pathinfo($safe_filename, PATHINFO_EXTENSION));
            if (empty($extension)) {
                return new \WP_Error('tablesome_upload_missing_extension', __('File extension is missing.', 'tablesome'));
            }

            if ($this->is_dangerous_extension($extension)) {
                return new \WP_Error('tablesome_upload_dangerous_extension', __('This file extension is not allowed.', 'tablesome'));
            }

            $context = isset($args['context']) ? $args['context'] : 'local_upload';
            $integration = isset($args['integration']) ? $args['integration'] : '';
            $allowed_mimes = $this->get_allowed_mimes($context, $integration, $file, $safe_filename);

            $wp_filetype = $this->check_filetype_and_ext($file, $safe_filename, $allowed_mimes);
            if ($wp_filetype['type'] === false || empty($wp_filetype['ext'])) {
                return new \WP_Error('tablesome_upload_invalid_filetype', __('File type validation failed.', 'tablesome'));
            }

            $validated_extension = strtolower($wp_filetype['ext']);
            if ($this->is_dangerous_extension($validated_extension)) {
                return new \WP_Error('tablesome_upload_dangerous_validated_extension', __('This validated file extension is not allowed.', 'tablesome'));
            }

            if (!$this->is_mime_type_allowed($wp_filetype['type'], $allowed_mimes)) {
                return new \WP_Error('tablesome_upload_mime_not_allowed', __('MIME type is not allowed by site policy.', 'tablesome'));
            }

            return array(
                'ext' => $validated_extension,
                'type' => $wp_filetype['type'],
                'proper_filename' => isset($wp_filetype['proper_filename']) ? $wp_filetype['proper_filename'] : false,
                'filename' => $safe_filename,
            );
        }

        public function get_allowed_mimes($context = 'local_upload', $integration = '', $file = '', $filename = '')
        {
            $allowed_mimes = get_allowed_mime_types();
            $allowed_mimes = apply_filters('tablesome/upload_allowed_mimes', $allowed_mimes, $context, $integration, $file, $filename);

            if (!is_array($allowed_mimes)) {
                $allowed_mimes = array();
            }

            return $this->remove_dangerous_mimes($allowed_mimes);
        }

        protected function remove_dangerous_mimes($mimes)
        {
            foreach ($mimes as $extension_pattern => $mime_type) {
                if ($this->pattern_matches_dangerous_extension($extension_pattern)) {
                    unset($mimes[$extension_pattern]);
                }
            }

            return $mimes;
        }

        protected function check_filetype_and_ext($file, $filename, $allowed_mimes)
        {
            $mime_filter = function ($mimes = array(), $user = null) use ($allowed_mimes) {
                return $allowed_mimes;
            };

            add_filter('upload_mimes', $mime_filter, PHP_INT_MAX, 2);

            try {
                return wp_check_filetype_and_ext($file, $filename, $allowed_mimes);
            } finally {
                remove_filter('upload_mimes', $mime_filter, PHP_INT_MAX);
            }
        }

        protected function pattern_matches_dangerous_extension($extension_pattern)
        {
            foreach ($this->dangerous_extensions as $extension) {
                if (preg_match('!\.(' . $extension_pattern . ')$!i', 'file.' . $extension)) {
                    return true;
                }
            }

            return false;
        }

        protected function is_mime_type_allowed($mime_type, $allowed_mimes)
        {
            return in_array($mime_type, array_values($allowed_mimes), true);
        }

        protected function is_dangerous_extension($extension)
        {
            return in_array(strtolower($extension), $this->dangerous_extensions, true);
        }
    }
}
