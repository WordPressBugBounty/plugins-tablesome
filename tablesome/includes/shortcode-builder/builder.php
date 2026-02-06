<?php

namespace Tablesome\Includes\Shortcode_Builder;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Shortcode_Builder\Builder')) {
    class Builder
    {

        public function init()
        {
            // Priority 9 ensures shortcode instances are registered before CSF::setup()
            // runs at priority 10 and checks for show_in_editor instances
            add_action('init', array($this, 'initialize_shortcode_builder'), 9);
        }

        public function initialize_shortcode_builder()
        {
            // if (!function_exists('\CSF') && !class_exists('\CSF')) {
            //     require_once TABLESOME_PATH . 'includes/lib/codestar-framework/codestar-framework.php';
            // }

            $fields = new \Tablesome\Includes\Shortcode_Builder\Fields();

            $prefix = 'tablesome-shortcode';

            // Init shortcode builder
            // Note: show_in_editor must be true for CSF to enqueue scripts and render the modal.
            // We'll unregister CSF's block later so only our native block (tablesome/shortcode) appears.
            \CSF::createShortcoder($prefix, array(
                'button_title' => __('Add Tablesome Shortcode', "tablesome"),
                'select_title' => __('Select a shortcode', "tablesome"),
                'insert_title' => __('Insert Shortcode', "tablesome"),
                'show_in_editor' => true,
            ));

            // create builder section
            \CSF::createSection($prefix, array(
                'title' => __('Tablesome Shortcode', 'tablesome'),
                'view' => 'normal',
                'shortcode' => 'tablesome',
                'class' => 'tablesome-csf__section',
                'fields' => [
                    $fields->get_table_id_field(),
                    $fields->get_show_serial_number_column_field(),
                    $fields->get_search_field(),
                    $fields->get_hide_table_header_field(),
                    $fields->get_sort_field(),
                    $fields->get_filter_field(),
                    $fields->get_page_limit_field(),
                    $fields->get_exclude_columns_field(),
                ],
            ));

        }
    }
}
