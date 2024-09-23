<?php

namespace Tablesome\Includes;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Security_Agent')) {
    class Security_Agent
    {
        public function add_query_arg(array $args, $url = '')
        {
            $url = add_query_arg($args, $url);
            $url = esc_url($url);
            return $url;
        }

        public function remove_query_arg($key)
        {
            $url = remove_query_arg($key);
            $url = esc_url($url);
            return $url;
        }
    } // END class

}
