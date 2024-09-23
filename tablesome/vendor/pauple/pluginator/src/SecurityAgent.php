<?php

namespace Pauple\Pluginator;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Pauple\Pluginator\SecurityAgent')) {
    class SecurityAgent 
    {
     
        public function __construct()
        {
        }

        // public function add_query_arg(array $args, $url = '')
        // {
        //     $url = add_query_arg($args, $url);
        //     $url = esc_url($url);
        //     return $url;
        // }

        public function add_query_arg(array $args, $url = '', $mode = 'raw')
        {
            $url = add_query_arg($args, $url);
            if ($mode == 'raw') {
                $url = esc_url_raw($url);
            } else {
                $url = esc_url($url);
            }
            return $url;
        }

        public function remove_query_arg($key)
        {
            $url = remove_query_arg($key);
            $url = esc_url($url);
            return $url;
        }

        public function wp_remote_get($url, $params)
        {
            $response = wp_remote_get(esc_url_raw($url), $params);
            return $response;
        }


    } // end class
}