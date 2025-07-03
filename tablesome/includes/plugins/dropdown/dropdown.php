<?php
/**
 * Dropdown Plugin for Tablesome
 *
 * @package Tablesome
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tablesome_Dropdown_Plugin {
    public function __construct() {
        add_filter('tablesome_column_formats', array($this, 'add_dropdown_format'));
        add_filter('tablesome_column_options', array($this, 'add_dropdown_options'));
    }

    /**
     * Add dropdown format to column formats
     *
     * @param array $formats Array of column formats
     * @return array Modified array of column formats
     */
    public function add_dropdown_format($formats) {
        $formats['dropdown'] = array(
            'name' => __('Dropdown', 'tablesome'),
            'icon' => 'list',
            'class' => 'tablesome__option--basic tablesome__option--basic-dropdown',
            'options' => array(
                'options' => array(
                    'type' => 'repeater',
                    'label' => __('Options', 'tablesome'),
                    'fields' => array(
                        'label' => array(
                            'type' => 'text',
                            'label' => __('Label', 'tablesome'),
                        ),
                        'value' => array(
                            'type' => 'text',
                            'label' => __('Value', 'tablesome'),
                        ),
                    ),
                ),
            ),
        );

        return $formats;
    }

    /**
     * Add dropdown options to column options
     *
     * @param array $options Array of column options
     * @return array Modified array of column options
     */
    public function add_dropdown_options($options) {
        if (!isset($options['dropdown'])) {
            $options['dropdown'] = array();
        }

        $options['dropdown'] = array_merge($options['dropdown'], array(
            'options' => array(
                'type' => 'repeater',
                'label' => __('Options', 'tablesome'),
                'fields' => array(
                    'label' => array(
                        'type' => 'text',
                        'label' => __('Label', 'tablesome'),
                    ),
                    'value' => array(
                        'type' => 'text',
                        'label' => __('Value', 'tablesome'),
                    ),
                ),
            ),
        ));

        return $options;
    }
}

// Initialize the plugin
new Tablesome_Dropdown_Plugin(); 