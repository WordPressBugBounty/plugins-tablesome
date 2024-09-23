<?php

namespace Tablesome\Workflow_Library\Triggers;

use Tablesome\Includes\Modules\Workflow\Abstract_Trigger;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Triggers\Forminator')) {
    class Forminator extends Abstract_Trigger
    {

        public $trigger_source_id = 0;
        public $trigger_source_data = array();

        public $unsupported_formats = array(
            // 'stripe',
            // 'paypal',
            'captcha',
        );

        public static $instance = null;

        public static function get_instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function get_config()
        {
            $is_active = class_exists('Forminator') ? true : false;
            return array(
                'integration' => 'forminator',
                'integration_label' => __('Forminator', 'tablesome'),
                'trigger' => 'tablesome_forminator_form_submit',
                'trigger_id' => 4,
                'trigger_label' => __('On Form Submit', 'tablesome'),
                'trigger_type' => 'forms',
                'is_active' => $is_active,
                'is_premium' => "no",
                'hooks' => array(
                    array(
                        'priority' => 10,
                        'accepted_args' => 3,
                        'name' => 'forminator_custom_form_submit_before_set_fields',
                        'callback_name' => 'trigger_callback',
                    ),
                ),
                'supported_actions' => [],
                'unsupported_actions' => [8, 9]
            );
        }

        public function trigger_callback($entry, $form_id, $fields_data)
        {
            $entry_id = $entry->entry_id;

            $form = \Forminator_API::get_form($form_id);

            // error_log(' Forminator form : ' . print_r($form, true));
            // error_log(' Forminator fields_data : ' . print_r($fields_data, true));
            // error_log(' Forminator entry : ' . print_r($entry, true));
            $submission_data = $this->get_formatted_posted_data($fields_data, $form);
            $submission_data = apply_filters("tablesome_form_submission_data", $submission_data);

            // error_log(' Forminator submission_data : ' . print_r($submission_data, true));

            $this->trigger_source_id = $form_id;
            $this->trigger_source_data = array(
                'integration' => $this->get_config()['integration'],
                'form_title' => isset($form->settings['formName']) ? $form->settings['formName'] : 'Untitled Table - ' . $form_id,
                'form_id' => $form_id,
                'data' => $submission_data,
            );

            $this->run_triggers($this, $this->trigger_source_data);
        }

        public function conditions($trigger_meta, $trigger_data)
        {
            $integration = isset($trigger_meta['integration']) ? $trigger_meta['integration'] : '';
            $trigger_id = isset($trigger_meta['trigger_id']) ? $trigger_meta['trigger_id'] : '';

            if ($integration != $this->get_config()['integration'] || $trigger_id != $this->get_config()['trigger_id']) {
                return false;
            }

            $trigger_source_id = isset($trigger_meta['form_id']) ? $trigger_meta['form_id'] : 0;
            if (isset($trigger_data['form_id']) && $trigger_data['form_id'] == $trigger_source_id) {
                return true;
            }
            return false;
        }

        public function get_collection()
        {
            $posts = $this->get_posts();
            if (empty($posts)) {
                return [];
            }

            foreach ($posts as $index => $post) {
                $posts[$index]['fields'] = $this->get_post_fields($post['id']);
            }
            return $posts;
        }

        public function get_posts()
        {
            if (!class_exists('Forminator')) {
                return [];
            }

            $forms = \Forminator_API::get_forms(null, 1, -1, '');

            if (is_wp_error($forms) || empty($forms)) {
                return [];
            }
            $posts = [];
            foreach ($forms as $form) {
                $posts[] = array(
                    'id' => $form->id,
                    'label' => $form->settings['formName'] . " (ID: " . $form->id . ")",
                    'integration_type' => 'forminator',
                );
            }
            return $posts;

        }

        public function get_post_fields($id, $args = array())
        {
            if (!class_exists('Forminator')) {
                return [];
            }
            $fields = \Forminator_API::get_form_fields($id);
            if (is_wp_error($fields) || empty($fields)) {
                return [];
            }

            $form_fields = array();

            foreach ($fields as $field_obj) {

                $type = $field_obj->__get('type');
                $id = $field_obj->__get('element_id');
                $label = $field_obj->__get('field_label');
                if (in_array($type, $this->unsupported_formats)) {
                    continue;
                }

                $field = array(
                    'id' => $id,
                    'label' => !empty($label) ? $label : $id,
                    'field_type' => $type,
                );

                $options = $field_obj->__get('options');

                if (!empty($options)) {

                    $options = (array_map(function ($option) {
                        $return_data = $option;
                        $return_data['id'] = $return_data['value'];
                        return $return_data;
                    }, $options));

                    $field['options'] = $options;
                }
                $form_fields[] = $field;
            }

            $form_fields = $this->post_processing_fields($form_fields);
            return $form_fields;
        }

        public function get_formatted_posted_data($fields_data, $form_obj)
        {
            $data = array();

            // Gettings other paypal fields

            foreach ($fields_data as $field_data) {
                $name = $field_data['name'];
                $additional_data = [];

                if (in_array($name, ['_forminator_user_ip'])) {
                    continue;
                }

                $field = \Forminator_API::get_form_field($form_obj->id, $name, true);

                // error_log(' Forminator field : ' . print_r($field, true));
                // error_log(' Forminator field_data : ' . print_r($field_data, true));

                if (is_wp_error($field)) {
                    continue;
                }

                $type = $field['type'];

                if (in_array($type, $this->unsupported_formats)) {
                    continue;
                }

                if ($type == 'paypal') {
                    $value = isset($field_data['value']) && isset($field_data['value']['status']) ? $field_data['value']['status'] : '';
                    $additional_data = isset($field_data['value']) && isset($field_data['value']) ? $field_data['value'] : [];
                } else {
                    $value = isset($field_data['value']) ? $field_data['value'] : '';
                }

                if ($type == 'date') {
                    $incoming_date_format = $field_data['field_array']['date_format'];
                    // $incoming_date_format = 'm-d-Y';
                    // Note: Special Conversion for Forminator Date Field
                    $incoming_date_format = str_replace("dd", "d", $incoming_date_format);
                    $incoming_date_format = str_replace("mm", "m", $incoming_date_format);
                    $incoming_date_format = str_replace("yy", "Y", $incoming_date_format);

                    $unix_timestamp = (int) convert_tablesome_date_to_unix_timestamp($value, $incoming_date_format);
                    // $unix_timestamp = strtotime($value);
                    $unix_timestamp = $unix_timestamp * 1000; // convert to milliseconds

                    // error_log('$incoming_date_format : ' . $incoming_date_format);
                    // error_log('$unix_timestamp : ' . $unix_timestamp);

                } else if ($type == 'postdata') {
                    /** Get the Post ID */
                    $value = isset($value['postdata']) ? $value['postdata'] : 0;
                } else if ($type == 'file') {
                    $attachment_id = isset($value['file']) ? $value['file'] : '';
                    $value = $attachment_id;

                } else if ($type == 'upload') {
                    $file = isset($value['file']) ? $value['file'] : '';

                    // error_log(' Forminator file : ' . print_r($file, true));

                    $value = '';
                    if (isset($file['success']) && $file['success'] == 1 && isset($file['file_url'])) {
                        $file_url = is_array($file['file_url']) ? implode(',', $file['file_url']) : $file['file_url'];

                        $value = '';
                        // $value = attachment_url_to_postid($url);
                        $file_type = wp_check_filetype($file_url);

                        // error_log(' Forminator url : ' . $url);
                        // error_log(' Forminator value : ' . $value);
                    }
                } else {
                    if (is_array($value) && !empty($value)) {
                        if ($type == 'time') {
                            $value = sprintf('%02d', $value['hours']) . ':' . sprintf('%02d', $value['minutes']) . ' ' . $value['ampm'];
                        } else if ($type == 'calculation') {
                            $value = isset($value['result']) ? $value['result'] : '';
                        } else if ($type == 'name') {
                            $value = implode(' ', $value);
                        } else {
                            $value = implode(', ', $value);
                        }
                    }
                }

                $data[$name] = array(
                    'type' => $type,
                    'label' => isset($field['field_label']) && !empty($field['field_label']) ? $field['field_label'] : $name,
                    'value' => $value ?? '',
                    'unix_timestamp' => isset($unix_timestamp) ? $unix_timestamp : '', // use this prop when the column format type is date
                    'additional_data' => $additional_data,
                );

                if ($type == 'upload') {
                    // $data[$name]['type'] = 'file';
                    // error_log(' Forminator file_type : ' . print_r($file_type, true));
                    $data[$name]['file_type'] = isset($file_type) ? $file_type['type'] : '';
                    $data[$name]['linkText'] = 'View File';
                    $data[$name]['file_url'] = $file_url ?? '';
                    $data[$name]['type'] = 'file';
                }
            }

            // $old_url = 'http://tablesome-dev.local/wp-content/uploads/forminator/617_7a9de121849d5b46ac6356cb2bc211ef/uploads/uGUH9tJmPivK-Copy.of_.Member.Roster.Tab_.csv';
            // $old_attachment_id = attachment_url_to_postid($old_url);
            // error_log(' Forminator old_attachment_id : ' . $old_attachment_id);

            $data = $this->post_processing($data);
            // error_log(' Forminator formatted data : ' . print_r($data, true));
            return $data;
        }

        public function post_processing_fields($form_fields)
        {

            end($form_fields);
            $last_key = key($form_fields);
            reset($form_fields);
            // error_log(' Forminator form_fields : ' . print_r($form_fields, true));
            // Processing for paypal fields

            $last_key = $last_key + 1;
            foreach ($form_fields as $primary_key => $field) {

                if ($field['field_type'] == 'paypal') {
                    $form_fields[$last_key++] = [
                        'id' => $field['id'] . '_mode',
                        'label' => 'Mode',
                        'field_type' => 'paypal',
                    ];

                    $form_fields[$last_key++] = [
                        'id' => $field['id'] . '_transaction_id',
                        'label' => 'Transaction ID',
                        'field_type' => 'paypal',
                    ];

                    $form_fields[$last_key++] = [
                        'id' => $field['id'] . '_status',
                        'label' => 'Payment Status',
                        'field_type' => 'paypal',
                    ];

                    $form_fields[$last_key++] = [
                        'id' => $field['id'] . '_amount',
                        'label' => 'Payment Amount',
                        'field_type' => 'paypal',
                    ];

                    $form_fields[$last_key++] = [
                        'id' => $field['id'] . '_currency',
                        'label' => 'Payment Currency',
                        'field_type' => 'paypal',
                    ];

                    $form_fields[$last_key++] = [
                        'id' => $field['id'] . '_transaction_link',
                        'label' => 'Transaction Link',
                        'field_type' => 'paypal',
                    ];

                }
            }

            // error_log(' Forminator form_fields eenndd: ' . print_r($form_fields, true));
            return $form_fields;
        }
        public function post_processing($data)
        {

            // Processing for paypal fields
            foreach ($data as $key => $field) {
                if ($field['type'] == 'paypal') {
                    $data[$key]['value'] = $field['value'];

                    // Mode
                    if (isset($field['additional_data']['mode'])) {
                        $data[$key . '_mode'] = array(
                            'type' => 'paypal',
                            'label' => 'Mode',
                            'value' => isset($field['additional_data']['mode']) ? $field['additional_data']['mode'] : '',
                        );
                    }

                    // Transaction ID
                    if (isset($field['additional_data']['transaction_id'])) {
                        $data[$key . '_transaction_id'] = array(
                            'type' => 'paypal',
                            'label' => 'Transaction ID',
                            'value' => isset($field['additional_data']['transaction_id']) ? $field['additional_data']['transaction_id'] : '',
                        );
                    }

                    // Payment Status
                    if (isset($field['additional_data']['status'])) {
                        $data[$key . '_status'] = array(
                            'type' => 'paypal',
                            'label' => 'Payment Status',
                            'value' => isset($field['additional_data']['status']) ? $field['additional_data']['status'] : '',
                        );
                    }

                    // Payment Amount
                    if (isset($field['additional_data']['amount'])) {
                        $data[$key . '_amount'] = array(
                            'type' => 'paypal',
                            'label' => 'Payment Amount',
                            'value' => isset($field['additional_data']['amount']) ? $field['additional_data']['amount'] : '',
                        );
                    }

                    // Payment Currency
                    if (isset($field['additional_data']['currency'])) {
                        $data[$key . '_currency'] = array(
                            'type' => 'paypal',
                            'label' => 'Payment Currency',
                            'value' => isset($field['additional_data']['currency']) ? $field['additional_data']['currency'] : '',
                        );
                    }

                    // Transaction Link
                    if (isset($field['additional_data']['transaction_link'])) {
                        $data[$key . '_transaction_link'] = array(
                            'type' => 'paypal',
                            'label' => 'Transaction Link',
                            'value' => isset($field['additional_data']['transaction_link']) ? $field['additional_data']['transaction_link'] : '',
                        );
                    }

                }
            } // End of foreach

            return $data;
        }
    } // End of class
}
