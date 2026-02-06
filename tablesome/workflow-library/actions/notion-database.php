<?php

namespace Tablesome\Workflow_Library\Actions;

use Tablesome\Includes\Modules\Workflow\Action;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Actions\Notion_Database')) {
    class Notion_Database extends Action
    {

        public static $instance = null;
        public $notion_api = null;
        public $trigger_class;
        public $trigger_instance;

        public static function get_instance()
        {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function __construct()
        {
            $this->notion_api = new \Tablesome\Workflow_Library\External_Apis\Notion();
        }

        public function get_config()
        {
            return array(
                'id' => 3,
                'name' => 'add_page',
                'label' => __('Add Record to Notion DB', 'tablesome'),
                'integration' => 'notion',
                'is_premium' => false,
            );
        }

        public function do_action($trigger_class, $trigger_instance)
        {
            if (!$this->notion_api->api_status || empty($this->notion_api->api_key)) {
                return false;
            }
            $this->trigger_class = $trigger_class;
            $this->trigger_instance = $trigger_instance;

            $action_meta = $this->trigger_instance['action_meta'];
            $data = isset($this->trigger_class->trigger_source_data['data']) ? $this->trigger_class->trigger_source_data['data'] : [];

            $database_id = isset($action_meta['database_id']) ? $action_meta['database_id'] : '';
            $match_fields = isset($action_meta['match_fields']) ? $action_meta['match_fields'] : [];

            if (empty($database_id) || empty($match_fields)) {
                return;
            }

            $database = $this->notion_api->get_database_by_id($database_id);

            if (empty($database) || isset($database['status']) && 'failed' == $database['status']) {
                return;
            }

            $data = $this->get_matched_property_values($match_fields, $data, $database);

            if (empty($data)) {
                return;
            }

            $response_data = $this->notion_api->add_record_in_database($database_id, $data);

            return $response_data;
        }

        public function get_matched_property_values($match_fields, $form_data, $database)
        {
            $data = array();
            foreach ($match_fields as $match_field) {
                $property_id = isset($match_field['property_id']) ? $match_field['property_id'] : '';
                $field_name = isset($match_field['field_name']) ? $match_field['field_name'] : '';

                if (empty($property_id)) {
                    continue;
                }

                $property = $this->get_property($property_id, $database);

                if (empty($property)) {
                    continue;
                }

                $value = isset($form_data[$field_name]['value']) ? $form_data[$field_name]['value'] : '';
                
                $property_values = $this->get_property_values($property, $value);

                if (empty($property_values)) {
                    continue;
                }

                $data[$property_id] = $property_values;
            }
            
            return $data;
        }

        public function get_property($property_id, $database)
        {
            $data = [];
            $properties = isset($database['properties']) ? $database['properties'] : array();
            if (empty($properties)) {
                return $data;
            }
            foreach ($properties as $property) {
                if ($property['id'] == $property_id) {
                    $data = $property;
                    break;
                }
            }
            return $data;
        }

        public function get_property_values($property, $value)
        {
            $method_name = "get_{$property['type']}_values";
            if (method_exists($this, $method_name)) {
                return $this->$method_name($property, $value);
            } else {
                return [];
            }
        }

        public function get_title_values($property, $value)
        {
            $data = [];
            $data['title'] = array(
                array(
                    'type' => 'text',
                    'text' => array(
                        'content' => $value,
                    ),
                ),
            );
            return $data;
        }

        public function get_rich_text_values($property, $value)
        {
            $data = [];
            $data['rich_text'] = array(
                array(
                    'type' => "text",
                    "text" => array(
                        'content' => $value,
                    ),
                ),
            );
            return $data;
        }

        public function get_number_values($property, $value)
        {
            $data = null;
            $float_val = (float) $value;
            $int_val = (int) $value;
            $double_val = (double) $value;

            if (is_float($float_val)) {
                $data = floatval($float_val);
            } else if (is_int($int_val)) {
                $data = intval($int_val);
            } else if (is_double($double_val)) {
                $data = doubleval($double_val);
            }

            return array(
                'number' => $data,
            );
        }

        public function get_select_values($property, $value)
        {
            return array(
                'select' => array(
                    'name' => $value,
                ),
            );
        }

        public function get_multi_select_values($property, $value)
        {
            $values = isset($value) && !empty($value) ? explode(',', $value) : [];
            if (empty($values)) {
                return [];
            }

            $data = [];
            $data['multi_select'] = array();
            foreach ($values as $value) {
                $data['multi_select'][] = array(
                    'name' => $value,
                );
            }
            return $data;
        }

        public function get_checkbox_values($property, $value)
        {
            $checkbox_value = false;
            if (is_string($value)) {
                $checkbox_value = !empty($value) ? true : false;
            } else if (is_numeric($value)) {
                $checkbox_value = $value > 0 ? true : false;
            } else if (is_bool($value)) {
                $checkbox_value = $value ? true : false;
            }

            return array(
                'checkbox' => $checkbox_value,
            );
        }

        public function get_url_values($property, $value)
        {
            return array(
                'url' => $value,
            );
        }

        public function get_email_values($property, $value)
        {
            return array(
                'email' => $value,
            );
        }

        public function get_phone_number_values($property, $value)
        {
            return array(
                'phone_number' => $value,
            );
        }

        // public function get_people_values($property, $value)
        // {
        // }

        public function get_date_values($property, $value)
        {
			// Handle empty values
			if (empty($value)) {
				return [];
			}

			// If value is an array with day/month/year keys (e.g., Forminator style)
			if (is_array($value)) {
				$day = isset($value['day']) ? (int) $value['day'] : 0;
				$month = isset($value['month']) ? (int) $value['month'] : 0;
				$year = isset($value['year']) ? (int) $value['year'] : 0;

				if ($day && $month && $year && checkdate($month, $day, $year)) {
					$date_string = sprintf('%04d-%02d-%02d', $year, $month, $day);
					return array(
						'date' => array(
							'start' => $date_string,
						),
					);
				}
				return [];
			}

			// Try to reconstruct from trigger source data (for multi-part date fields)
			if (!is_valid_tablesome_date($value, 'Y-m-d')) {
				$form_data = isset($this->trigger_class->trigger_source_data['data']) ? $this->trigger_class->trigger_source_data['data'] : [];
				$field_base = isset($property['field_name']) ? $property['field_name'] : '';
				$day = isset($form_data["{$field_base}-day"]) ? (int) $form_data["{$field_base}-day"] : 0;
				$month = isset($form_data["{$field_base}-month"]) ? (int) $form_data["{$field_base}-month"] : 0;
				$year = isset($form_data["{$field_base}-year"]) ? (int) $form_data["{$field_base}-year"] : 0;

				if ($day && $month && $year && checkdate($month, $day, $year)) {
					$date_string = sprintf('%04d-%02d-%02d', $year, $month, $day);
					return array(
						'date' => array(
							'start' => $date_string,
						),
					);
				}
			}

			// If already in Y-m-d format, use it directly
			if (is_valid_tablesome_date($value, 'Y-m-d')) {
				return array(
					'date' => array(
						'start' => $value,
					),
				);
			}

			// Check if value is a time-only string (e.g., "12:12 am", "14:30", "3:45 PM")
			// Time patterns: HH:MM am/pm, HH:MM:SS, etc.
			$time_patterns = array(
				'/^(\d{1,2}):(\d{2})\s*(am|pm|AM|PM)$/i',  // 12:12 am, 3:45 PM
				'/^(\d{1,2}):(\d{2}):(\d{2})\s*(am|pm|AM|PM)?$/i',  // 12:12:30 am
				'/^(\d{1,2}):(\d{2})$/i',  // 14:30 (24-hour format)
				'/^(\d{1,2}):(\d{2}):(\d{2})$/i',  // 14:30:45 (24-hour format)
			);

			$is_time_only = false;
			foreach ($time_patterns as $pattern) {
				if (preg_match($pattern, trim($value), $matches)) {
					$is_time_only = true;
					break;
				}
			}

			// If it's a time-only value, combine with today's date for Notion datetime
			if ($is_time_only) {
				$parsed_time = strtotime($value);
				if ($parsed_time !== false) {
					// Use today's date combined with the parsed time
					$datetime = new \DateTime();
					$datetime->setTime(
						(int) date('H', $parsed_time),
						(int) date('i', $parsed_time),
						(int) date('s', $parsed_time)
					);
					// Format as ISO 8601 datetime for Notion: YYYY-MM-DDTHH:MM:SS
					$date_string = $datetime->format('Y-m-d\TH:i:s');
					return array(
						'date' => array(
							'start' => $date_string,
						),
					);
				}
			}

			// Try to parse various date formats using PHP's strtotime or DateTime
			// Common formats: m/d/Y, d/m/Y, Y-m-d, etc.
			$parsed_date = null;
			
			// Try strtotime first (handles many formats automatically)
			$timestamp = strtotime($value);
			if ($timestamp !== false) {
				$parsed_date = new \DateTime('@' . $timestamp);
			} else {
				// Try common date formats explicitly
				$common_formats = array('m/d/Y', 'd/m/Y', 'Y-m-d', 'm-d-Y', 'd-m-Y', 'Y/m/d');
				foreach ($common_formats as $format) {
					$datetime = \DateTime::createFromFormat($format, $value);
					if ($datetime !== false) {
						$parsed_date = $datetime;
						break;
					}
				}
			}

			// If we successfully parsed the date, format it appropriately for Notion
			if ($parsed_date instanceof \DateTime) {
				// Check if the parsed value includes time information
				$has_time = ($parsed_date->format('H:i:s') !== '00:00:00' || 
				            preg_match('/\d{1,2}:\d{2}/', $value));
				
				if ($has_time) {
					// Format as ISO 8601 datetime: YYYY-MM-DDTHH:MM:SS
					$date_string = $parsed_date->format('Y-m-d\TH:i:s');
				} else {
					// Format as date only: YYYY-MM-DD
					$date_string = $parsed_date->format('Y-m-d');
				}
				
				return array(
					'date' => array(
						'start' => $date_string,
					),
				);
			}

			// If all parsing attempts failed, return empty array
			return [];
        }

    }
}
