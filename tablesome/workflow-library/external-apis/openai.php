<?php

namespace Tablesome\Workflow_Library\External_Apis;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\External_Apis\OpenAI')) {
    class OpenAI
    {
        public $model = 'gpt-3.5-turbo';
        public $base_url = 'https://api.openai.com/v1/chat';

        public $error = '';

        public function get_openai_auth_info()
        {
            $api_credentials_handler = new \Tablesome\Includes\Modules\API_Credentials_Handler();
            $openai_api_credentials = $api_credentials_handler->get_api_credentials('openai');
            return $openai_api_credentials;
        }

        public function get_api_headers($info)
        {
            $token = isset($info['api_key']) ? $info['api_key'] : '';

            $default_header = [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ];
            return $default_header;
        }

        public function completions_request($prompt, $payload = array())
        {

            $info = $this->get_openai_auth_info();
            $status = isset($info['status']) ? $info['status'] : false;
            $api_key = isset($info['api_key']) ? $info['api_key'] : '';

            if (!$status || empty($api_key)) {
                return false;
            }

            $url = "{$this->base_url}/completions";

            // $payload = [
            //     'messages' => [
            //         [
            //             "role" => "user",
            //             "content" => $prompt,
            //         ],
            //     ],
            //     'model' => $this->model,
            // ];

            // error_log('OpenAI prompt: ' . $prompt);
            $payload = [
                "model" => "gpt-3.5-turbo",
                "messages" => [
                    ["role" => "user", "content" => $prompt],
                ],
                "temperature" => 0.7,
            ];

            // error_log("OpenAI payload: " . print_r($payload, true));
            $body = wp_json_encode($payload);

            // error_log("OpenAI body: " . $body);
            $response = wp_remote_post($url, array(
                'headers' => $this->get_api_headers($info),
                'body' => $body,
                'timeout' => 60,
            ));

            $data = json_decode(wp_remote_retrieve_body($response), true);
            $response_code = wp_remote_retrieve_response_code($response);
            // error_log('OpenAI Completion Request Response : ' . print_r($data, true));

            $output = $data['choices'][0]['message']['content'];

            // error_log('OpenAI Completion Request output : ' . $output);
            if ($response_code != 200) {
                $this->error = isset($data['error']) ? $data['error'] : 'Something went wrong!';
                error_log('OpenAI Completion Request Error : ' . print_r($this->error, true));
                error_log('$response : ' . print_r($response, true));
                return false;
            }
            return $data;
        }

        public function ping()
        {
            // make a request to check if the token is valid or not
            $response = $this->completions_request('Check that token is valid Or Not!', array(
                'max_tokens' => 3,
                'temperature' => 0.5,
                'top_p' => 1,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ));
            if (!$response) {
                return false;
            }
            return true;
        }
    }
}
