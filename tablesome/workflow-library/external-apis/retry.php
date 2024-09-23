<?php

namespace Tablesome\Workflow_Library\External_Apis;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\External_Apis\Retry')) {
    class Retry
    {
        public static $retry_count = 0;
        public static $max_retry_count = 3;
        public static $integration = '';

        public static function set_integration($integration)
        {
            self::$integration = $integration;
        }

        public static function call($callback, $args)
        {
            if (self::$retry_count < self::$max_retry_count) {
                maybe_refresh_access_token_by_integration(self::$integration, true);
                self::$retry_count++;
                return call_user_func_array($callback, $args);
            }
        }

        public static function reset_count()
        {
            self::$retry_count = 0;
        }
    }
}
