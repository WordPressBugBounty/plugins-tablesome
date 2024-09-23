<?php

namespace Tablesome\Includes\Tracking;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('\Tablesome\Includes\Tracking\Event')) {
    class Event
    {

        public function get_general_properties()
        {
            $defaults = [
                'site_url' => get_site_url(),
                'language' => get_locale(),
                'wp_version' => get_bloginfo('version'),
                'php_version' => phpversion(),
                'plugin_version' => TABLESOME_VERSION,
                'is_multisite' => is_multisite(),
            ];

            $fs_properties = $this->get_fs_properties();

            return array_merge($defaults, $fs_properties);
        }

        public function get_fs_properties()
        {
            global $tablesome_fs;
            $site_info = $tablesome_fs->get_site();
            $user_info = $tablesome_fs->get_user();

            return [
                'user_id' => isset($user_info->id) ? $user_info->id : 0,
                'site_id' => isset($site_info->id) ? $site_info->id : 0,
                'plan' => $tablesome_fs->get_plan_name(),
                'is_trial' => $tablesome_fs->is_trial(),
                'is_free_plan' => $tablesome_fs->is_free_plan(),
                // 'user_email' => isset($user_info->email) ? $user_info->email : '',
            ];
        }

        public function get_properties($event, $value)
        {
            return array(
                'data' => $value,
                'label' => $this->get_event_title($event),
            );
        }

        public function get_event_title($type)
        {
            $events_titles = $this->get_events_titles();
            return isset($events_titles[$type]) ? $events_titles[$type] : 'Undefined-Event';
        }

        public function get_events_titles()
        {
            return [

                /*** Options fields from Settings */
                'num_of_records_per_page' => 'Total no. of records per page',
                // 'show_serial_number_column' => 'Show Serial Number Column (S.No)',
                'search' => 'Enable/Disable the Search',
                'hide_table_header' => 'Hide Table Header',
                'sorting' => 'Enable/Disable the Tablesome Sorting',
                'filters' => 'Enable/Disable the Tablesome Filters',
                'mobile_layout_mode' => 'Mobile Layout Mode',
                'style_disable' => 'Enable/Disable the Style',
                'min_column_width' => 'Min column width',

                /** Extras */
                'deactivate' => 'Tablesome plugin deactivated',
                'tables_count' => 'Total No of tables Count',
                'tables_column_format_collection' => 'Tables Columns format collection',
                'tables_records_count' => 'Total tables records count',

                'triggers_and_actions_used' => 'Triggers and Actions used Or not',
                'triggers_collection' => 'Total triggers collection',
                'actions_collection' => 'Total actions collection',
                'plugins_info' => 'Plugins info',
                'themes_info' => 'Themes info',

                // features
                'frontend_editing_tables_count' => 'Frontend Editing Tables Count',
                'add_row_action_auto_mapping_off_count' => 'Add Row Action Auto Mapping Off Count',
                'view_email_logs' => 'View Email Logs',
                'enable_email_logs' => 'Enable Email Logs',
            ];
        }
    }
}
