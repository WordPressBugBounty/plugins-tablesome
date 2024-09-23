<?php

namespace Tablesome\Includes\Tracking;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists('\Tablesome\Includes\Tracking\Model')) {
    class Model
    {
        public function get_data()
        {
            $crud = new \Tablesome\Includes\Db\CRUD();
            $tables = $this->get_tables();
            $options_data = $this->get_options_data();
            $data = $this->get_required_fields_data_from_setting($options_data);
            $args = $this->get_exclude_tables_args();

            $table_collection_data = $crud->get_tables_collection($args);
            $table_features = $this->get_table_features($tables);
            $workflow_events = $this->get_workflow_events($tables);

            // $themes_and_plguins_data = $this->get_themes_and_plugins_data();

            $data = array_merge($data, $table_collection_data, $table_features, $workflow_events);

            /**
             * Differentiate the old and new data
             */
            $data = $this->get_differentiated_data($data);

            $data = $this->remove_not_needed_data($data);

            return $data;
        }

        public function remove_not_needed_data($data)
        {
            foreach (array('num_of_records_per_page', 'hide_table_header', 'sorting', 'min_column_width', 'plugins_info', 'themes_info') as $event_name) {
                if (isset($data[$event_name])) {
                    unset($data[$event_name]);
                }
            }
            return $data;
        }

        public function get_exclude_tables_args()
        {
            $option_name = TABLESOME_SAMPLE_TABLE_OPTION;
            $sample_table_id = \get_option($option_name);

            if (empty($sample_table_id)) {
                return [];
            }
            return array(
                'post__not_in' => array($sample_table_id),
            );
        }

        public function get_options_data()
        {
            $option_name = TABLESOME_OPTIONS;
            return \get_option($option_name);
        }

        public function get_required_fields()
        {
            return array(
                // "num_of_records_per_page",
                // "show_serial_number_column",
                "search",
                // "hide_table_header",
                // "sorting",
                "filters",
                "mobile_layout_mode",
                "style_disable",
                // "min_column_width",
            );
        }

        public function get_required_fields_data_from_setting($data)
        {
            $required_fields = $this->get_required_fields();

            foreach ($data as $key => $value) {
                if (!in_array($key, $required_fields)) {
                    unset($data[$key]);
                }
            }
            return $data;
        }

        public function get_user_props()
        {
            $fs_utils = new \Tablesome\Includes\Freemius_Utils();
            $fs_collection_props = $fs_utils->get_collection_props();
            $props = array();
            $props['plan'] = $fs_collection_props['plan'];
            $props['wp_version'] = $fs_collection_props['wp_version'];
            $props['php_version'] = $fs_collection_props['php_version'];
            $props['site_url'] = $fs_collection_props['site_url'];
            $props['email'] = $fs_collection_props['email'];
            $current_user = wp_get_current_user();
            if (isset($current_user) && !empty($current_user)) {
                $data = isset($current_user->data) ? $current_user->data : null;
                $props['login_name'] = isset($data->user_login) ? $data->user_login : '';
                $props['role'] = isset($current_user->roles[0]) ? $current_user->roles[0] : '';
            }
            return $props;
        }

        public function get_browser_name($user_agent)
        {
            if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) {
                return 'Opera';
            } elseif (strpos($user_agent, 'Edge')) {
                return 'Edge';
            } elseif (strpos($user_agent, 'Chrome')) {
                return 'Chrome';
            } elseif (strpos($user_agent, 'Safari')) {
                return 'Safari';
            } elseif (strpos($user_agent, 'Firefox')) {
                return 'Firefox';
            } elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) {
                return 'Internet Explorer';
            }

            return 'Other';
        }

        public function get_differentiated_data($current_events_data)
        {
            $last_time_stored_insights_data = get_tablesome_insights_data();

            $differentiated_data = tablesome_multi_array_diff_assoc($current_events_data, $last_time_stored_insights_data);

            $storing_data = array_merge($current_events_data, $differentiated_data);

            /**
             * Store current data
             */
            set_tablesome_insights_data($storing_data);

            return $differentiated_data;
        }

        public function get_themes_and_plugins_data()
        {
            return array(
                'plugins_info' => $this->get_plugins_info(),
                'themes_info' => $this->get_themes_info(),
            );
        }

        private function get_plugins_info()
        {
            $plugins = array();
            $helpers = new \Tablesome\Includes\Helpers();
            $all_plugins = $helpers->get_plugins_data();

            if (empty($all_plugins)) {
                return $plugins;
            }

            foreach ($all_plugins as $plugin) {
                $domain = $plugin['TextDomain'];

                $plugins[$domain] = array(
                    'name' => $plugin['Name'],
                    'version' => $plugin['Version'],
                    'is_active' => $plugin['is_active'],
                    'author' => $plugin['Author'],
                );
            }

            return $plugins;
        }

        private function get_themes_info()
        {
            $themes = array();
            $all_themes = wp_get_themes();

            if (empty($all_themes)) {
                return $themes;
            }

            $current_theme = wp_get_theme();
            $current_theme_domain = $current_theme->get('TextDomain');

            foreach ($all_themes as $theme) {
                $domain = $theme->get('TextDomain');
                $is_active = ($domain == $current_theme_domain);

                $themes[$domain] = array(
                    'name' => $theme->get('Name'),
                    'version' => $theme->get('Version'),
                    'is_active' => $is_active ? 1 : 0,
                );
            }
            return $themes;
        }

        public function get_workflow_events($tables)
        {
            $events = [];
            $status = $this->get_triggers_and_actions_status();
            $events['triggers_and_actions_used'] = $status;

            if (empty($status)) {
                return $events;
            }

            $integration_counts = $this->get_trigger_integration_counts($tables);

            $events['triggers_collection'] = $integration_counts['triggers_collection'];
            $events['actions_collection'] = $integration_counts['actions_collection'];

            // error_log('$integration_counts : ' . print_r($integration_counts, true));

            return $events;
        }

        /**
         * Use of the below method for checking users are using the triggers and actions feature or not.
         * If return true then the user is using the triggers and actions feature.
         * If return false then the user is not using the triggers and actions feature.
         */
        public function get_triggers_and_actions_status()
        {
            global $wpdb;

            $query = "SELECT count(pm.post_id) as records_count FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ";
            $query .= "ON p.ID = pm.post_id WHERE p.post_type = 'tablesome_cpt' AND p.post_status='publish' AND pm.meta_key = 'tablesome_table_triggers'";

            $result = $wpdb->get_var($query);

            return $result > 0 ? true : false;
        }

        public function get_trigger_integration_counts($tables)
        {
            if (!isset($tables) || empty($tables)) {
                return [];
            }

            $triggers_collection = array();
            $actions_collection = array();

            foreach ($tables as $table) {

                $triggers_meta = get_tablesome_table_triggers($table->ID);

                if (!isset($triggers_meta) || empty($triggers_meta)) {
                    continue;
                }

                foreach ($triggers_meta as $trigger_meta) {

                    $trigger_integration_name = isset($trigger_meta['integration']) ? $trigger_meta['integration'] : '';

                    $actions = isset($trigger_meta['actions']) ? $trigger_meta['actions'] : [];

                    foreach ($actions as $action_meta) {
                        $action_integration_name = isset($action_meta['integration']) ? $action_meta['integration'] : 'tablesome';
                        $action_id = isset($action_meta['action_id']) ? $action_meta['action_id'] : 0;
                        $action_name = $this->get_action_name_by_id($action_id);

                        // Collect what are the triggers they are using it and their count.
                        if (!isset($triggers_collection[$trigger_integration_name])) {
                            $triggers_collection[$trigger_integration_name]['count'] = 0;
                            $triggers_collection[$trigger_integration_name]['integrated'] = 1;
                        }

                        if (isset($triggers_collection[$trigger_integration_name])) {
                            $triggers_collection[$trigger_integration_name]['count']++;
                            $triggers_collection[$trigger_integration_name]['integrated'] = 1;
                        }

                        // Collect what are the integrations they are using and their count.
                        if (!isset($actions_collection[$action_integration_name])) {
                            $actions_collection[$action_integration_name]['count'] = 0;
                            $actions_collection[$action_integration_name]['active'] = 1;
                        }

                        if (isset($actions_collection[$action_integration_name])) {
                            $actions_collection[$action_integration_name]['count']++;
                            $actions_collection[$action_integration_name]['active'] = 1;
                        }

                        // Collect actions count. how many actions they are using in entire site.
                        if (!isset($actions_collection[$action_integration_name][$action_name])) {
                            $actions_collection[$action_integration_name][$action_name] = 0;
                        }

                        if (isset($actions_collection[$action_integration_name][$action_name])) {
                            $actions_collection[$action_integration_name][$action_name]++;
                        }

                    }
                }
            }

            return array(
                'triggers_collection' => $triggers_collection,
                'actions_collection' => $actions_collection,
            );
        }

        public function get_action_name_by_id($action_id)
        {
            $name = 'incorrect_action';
            if (empty($action_id)) {
                return $name;
            }
            $action_name = tablesome_workflow_manager()->get_action_prop_value_by_id($action_id, 'name');
            return !empty($action_name) ? $action_name : $name;
        }

        public function get_table_features($tables)
        {
            $features = [];
            if (!isset($tables) || empty($tables)) {
                return $features;
            }
            $add_row_action_auto_mapping_off_count = 0;
            $frontend_editing_tables_count = 0;
            foreach ($tables as $table) {
                $tablemeta = get_tablesome_data($table->ID);
                $table_triggersmeta = get_tablesome_table_triggers($table->ID);

                // Check if the table has frontend editing enabled.
                $access_control = isset($tablemeta['options']['access_control']) ? $tablemeta['options']['access_control'] : [];
                $enable_frontend_editing = isset($access_control['enable_frontend_editing']) ? $access_control['enable_frontend_editing'] : false;
                if ($enable_frontend_editing) {
                    $frontend_editing_tables_count++;
                }

                $add_row_action_auto_mapping_off_count = $this->get_add_row_action_auto_mapping_off_count($table_triggersmeta, $add_row_action_auto_mapping_off_count);
            }

            $features['frontend_editing_tables_count'] = $frontend_editing_tables_count;
            $features['add_row_action_auto_mapping_off_count'] = $add_row_action_auto_mapping_off_count;
            return $features;
        }

        private function get_tables()
        {
            $tables = get_posts(array('post_type' => TABLESOME_CPT, 'numberposts' => -1));
            return $tables;
        }
        private function get_add_row_action_auto_mapping_off_count($table_triggersmeta, $add_row_action_auto_mapping_off_count)
        {
            if (!isset($table_triggersmeta) || empty($table_triggersmeta)) {
                return $add_row_action_auto_mapping_off_count;
            }

            foreach ($table_triggersmeta as $trigger_meta) {
                $actions = isset($trigger_meta['actions']) ? $trigger_meta['actions'] : [];
                foreach ($actions as $action_meta) {
                    $action_id = isset($action_meta['action_id']) ? $action_meta['action_id'] : 0;
                    $action_name = $this->get_action_name_by_id($action_id);
                    $autodetect_enabled = isset($action_meta['autodetect_enabled']) ? $action_meta['autodetect_enabled'] : false;
                    if ($action_name == 'add_row' && !$autodetect_enabled) {
                        $add_row_action_auto_mapping_off_count++;
                    }
                }
            }
            return $add_row_action_auto_mapping_off_count;
        }
    }

}
