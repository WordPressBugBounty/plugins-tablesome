<?php

namespace Tablesome\Includes\Tracking;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Tracking\Dispatcher_Mixpanel')) {
    class Dispatcher_Mixpanel
    {

        public $project_token = '4d09d3450b29727118f552a3e715d2e6';
        public $event_properties_handler;
        public $tracker;

        public function __construct()
        {
            $this->event_properties_handler = new \Tablesome\Includes\Tracking\Event();
            $this->tracker = new \Tablesome\Includes\Tracking\Controller();
        }
        public function send_single_event($event_name = '', $value = null)
        {
            if (!$this->tracker->can_track()) {
                return;
            }
            // $post_fix = '_test';
            $post_fix = '';
            $event_name = $event_name . $post_fix;

            $properties = $this->get_properties($value);

            $event = array(
                'event' => $event_name,
                'properties' => $properties,
            );

            $url = "https://api.mixpanel.com/track";

            $params = array(
                'ip' => 1,
                'verbose' => 1,
                'test' => 0,
                'track_id' => uniqid(),
                'data' => base64_encode(wp_json_encode($event)),
            );

            $url .= '?' . http_build_query($params);

            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
            ));
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            // error_log('Mixpanel API Response: ' . $response_body);
            if ($response_code != 200) {
                error_log('Mixpanel API Error');
                return false;
            }
            return true;
        }

        private function get_properties($value)
        {
            $additional_properties = $this->event_properties_handler->get_general_properties();

            $event_properties = array(
                'token' => $this->project_token,
                'distinct_id' => isset($additional_properties['site_id']) ? $additional_properties['site_id'] : '',
                'time' => time(),
                'value' => $value,
            );

            return array_merge($event_properties, $additional_properties);
        }
    }
}
