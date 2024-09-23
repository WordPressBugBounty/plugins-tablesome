<?php

namespace Tablesome\Workflow_Library\External_Apis;

use Tablesome\Includes\Modules\API_Credentials_Handler;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\External_Apis\Slack')) {
    class Slack
    {
        public $integration;
        public $api_credentials_handler;
        public $base_url = 'https://slack.com/api/';
        public $auth_error_codes = ['invalid_auth', 'token_expired'];

        public function __construct()
        {
            $this->integration = "slack";
            $this->api_credentials_handler = new API_Credentials_Handler();
        }

        public function is_active()
        {
            $data = $this->api_credentials_handler->get_api_credentials($this->integration);
            return $data["status"] == "success";
        }
        public function get_channels()
        {
            $can_fetch = true;
            $cursor = "";
            $channels = [];
            while ($can_fetch) {
                $response = $this->make_request('GET', 'conversations.list', ['exclude_archived' => 1, "limit" => 1000, "types" => "public_channel,private_channel", "cursor" => $cursor]);
                // $is_auth_error = $this->is_auth_error($response);
                $is_ok = (isset($response['ok']) && $response['ok'] == true);
                // if ($is_auth_error) {
                //     Retry::set_integration($this->integration);
                //     Retry::call([$this, 'get_channels'], []);
                // }
                // Retry::reset_count();
                if (!$is_ok) {
                    return [];
                }
                $current_page_channels = isset($response['channels']) ? $response['channels'] : [];
                $channels = array_merge($channels, $current_page_channels);
                $cursor = isset($response['response_metadata']['next_cursor']) ? $response['response_metadata']['next_cursor'] : "";
                if (empty($cursor)) {
                    $can_fetch = false;
                }
            }

            $channels = array_filter($channels, function ($channel) {
                return $channel['is_channel'] == true;
            });

            if (!isset($channels) || empty($channels)) {
                return [];
            }

            return array_map(function ($channel) {
                return [
                    'id' => $channel['id'],
                    'label' => $channel['name'],
                    'topic' => $channel['topic']['value'],
                ];
            }, $channels);
        }

        public function get_users()
        {
            $response = $this->make_request('GET', 'users.list', []);
            // $is_auth_error = $this->is_auth_error($response);

            // if ($is_auth_error) {
            //     Retry::set_integration($this->integration);
            //     Retry::call([$this, 'get_users'], []);
            // }
            // Retry::reset_count();

            $is_ok = (isset($response['ok']) && $response['ok'] == true);
            if (!$is_ok) {
                return [];
            }

            $members = $response['members'];
            $users = array_filter($members, function ($member) {
                return $member['is_bot'] == false && $member['deleted'] == false && $member['id'] != "USLACKBOT";
            });

            if (empty($users)) {
                return [];
            }

            $users = array_map(function ($user) {
                return [
                    'id' => $user['id'],
                    'label' => $user['real_name'] . " (" . $user['name'] . ")",
                ];
            }, $users);
            return array_values($users);
        }

        public function send_message($channel_id, $message)
        {
            $params = [
                'text' => $message,
                'channel' => $channel_id, // TODO: Test channel. For sending message to a channel.
                // 'thread_ts' => '1631000000.000100' // TODO: Test thread_ts. For replying to a message.
            ];
            $response = $this->make_request('POST', 'chat.postMessage', $params);

            // if ($this->is_auth_error($response)) {
            //     Retry::set_integration($this->integration);
            //     Retry::call([$this, 'send_message'], [$channel_id, $message]);
            // }
            // Retry::reset_count();
            return $response;
        }

        private function get_header()
        {
            // TODO: Uncomment this line when the refresh token is implemented.
            // $access_token = maybe_refresh_access_token_by_integration($this->integration);

            $slack_api_credentials = $this->api_credentials_handler->get_api_credentials('slack');
            $access_token = isset($slack_api_credentials['access_token']) ? $slack_api_credentials['access_token'] : '';
            return array(
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json;charset=utf-8',
            );
        }

        private function make_request($request_method = 'GET', $method = '', $params = [])
        {
            $header = $this->get_header();
            $url = $this->base_url . $method;

            $payload = [
                'method' => $request_method,
                'headers' => $header,
            ];
            if ($request_method == 'GET') {
                $url .= '?' . http_build_query($params);
            } else if ($request_method == 'POST') {
                $payload['body'] = wp_json_encode($params);
            }

            $response = wp_remote_post($url, $payload);
            $response_code = wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);

            if (is_wp_error($response) || !is_tablesome_success_response($response_code)) {
                return $data;
            }

            return $data;
        }

        private function is_auth_error($response)
        {
            return (isset($response['error']) && in_array($response['error'], $this->auth_error_codes));
        }

    }
}
