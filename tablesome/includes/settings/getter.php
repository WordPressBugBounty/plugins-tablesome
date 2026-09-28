<?php

namespace Tablesome\Includes\Settings;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Settings\Tablesome_Getter')) {

    class Tablesome_Getter
    {
        private static $options;
        private static $defaults;

        public static function get($option_name)
        {
            self::$defaults = self::default_settings();

            // Only set one time
            if (!isset(self::$options) || empty(self::$options)) {
                self::$options = get_option(TABLESOME_OPTIONS); // unique id of the framework
            }

            // error_log('self::$options : ' . print_r(self::$options, true));

            if (isset(self::$options[$option_name])) {
                if (isset(self::$defaults[$option_name])
                    && is_array(self::$defaults[$option_name])
                    && is_array(self::$options[$option_name])) {
                    return array_replace_recursive(
                        self::$defaults[$option_name],
                        self::$options[$option_name]
                    );
                }

                return self::$options[$option_name];
            }

            return isset(self::$defaults[$option_name]) ? self::$defaults[$option_name] : null;
        }

        /**
         * Keep the per-request cache in sync with WordPress option writes.
         *
         * The settings framework writes TABLESOME_OPTIONS directly. Without
         * invalidating this cache, later table renders in the same request can
         * continue using the previous global style values.
         */
        public static function option_added($option_name, $option_value)
        {
            if ($option_name === TABLESOME_OPTIONS) {
                self::$options = is_array($option_value) ? $option_value : [];
            }
        }

        public static function option_updated($option_name, $old_value, $option_value)
        {
            self::option_added($option_name, $option_value);
        }

        public static function set($option_name, $option_value)
        {
            // Only set one time
            if (!isset(self::$options) || empty(self::$options)) {
                self::$options = get_option(TABLESOME_OPTIONS); // unique id of the framework
            }

            if (isset($option_value)) {
                self::$options[$option_name] = $option_value;
                update_option(TABLESOME_OPTIONS, self::$options);
            }
        }

        public static function get_settings()
        {
            return self::$options;
        }

        public static function default_settings()
        {
            $defaults = array(
                // General Settings Start
                "num_of_records_per_page" => 10,
                "pagination_show_first_and_last_buttons" => true,
                "pagination_show_previous_and_next_buttons" => true,
                "show_serial_number_column" => false,
                "search" => true,
                "search_placeholder" => 'Type to Search ...',
                "search_error_message" => 'No matching records found',
                "hide_table_header" => false,
                "sorting" => true,
                "filters" => false,
                "date_timezone" => "site",
                "wp_site_search" => true,
                "export" => false,
                "enable_min_column_width" => true,
                "enable_max_column_width" => true,
                "min_column_width" => "",
                "max_column_width" => "",
                "table_display_mode" => "fit-to-container",
                "mobile_layout_mode" => "scroll-mode",

                // Style settings
                "style_disable" => false,
                "style_table_header_border_color" => array(
                    'all' => '1',
                    'style' => 'solid',
                    'color' => '#dddddd',
                ),
                "style_header_background" => '#1d1f20',
                "style_header_typography" => array(
                    'color' => '#ffffff',
                    'font-family' => 'Trebuchet MS',
                    'font-style' => 'normal',
                    'font-size' => '16',
                    'line-height' => '20',
                    'letter-spacing' => '0',
                    'text-align' => 'left',
                    'text-transform' => 'none',
                    'unit' => 'px',
                ),
                "style_row_background" => '#ffffff',
                "style_row_background_even" => '#f2f2f2',
                "style_cell_border" => array(
                    'all' => '1',
                    'style' => 'solid',
                    'color' => '#dddddd',
                ),
                "style_cell_typography" => array(
                    'color' => '#000000',
                    'font-family' => 'Trebuchet MS',
                    'font-style' => 'normal',
                    'font-size' => '16',
                    'line-height' => '20',
                    'letter-spacing' => '0',
                    'text-align' => 'left',
                    'text-transform' => 'none',
                    'unit' => 'px',
                ),
                "sticky_first_column" => false,

                // Form Settings
                'enabled_all_forms_entries' => true,
            );

            return $defaults;
        }
    } // END CLASS

    add_action('added_option', [Tablesome_Getter::class, 'option_added'], 10, 2);
    add_action('updated_option', [Tablesome_Getter::class, 'option_updated'], 10, 3);
}
