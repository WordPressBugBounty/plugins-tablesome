<?php

namespace Tablesome\Workflow_Library\External_Apis;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\External_Apis\OpenAI')) {
    class OpenAI
    {
        /**
         * Used until a model is saved in Settings → Integrations → OpenAI.
         * OpenAI's deprecations page names gpt-5.6-terra as the replacement
         * for gpt-3.5-turbo, which this action used until it was shut down
         * on 2026-10-23.
         */
        const DEFAULT_MODEL = 'gpt-5.6-terra';

        const MODEL_SETTING = 'openai_model';
        const MODELS_TRANSIENT = 'tablesome_openai_models';
        const PARAM_FALLBACKS_TRANSIENT = 'tablesome_openai_param_fallbacks_';
        const LAST_ERROR_OPTION = 'tablesome_openai_last_error';

        /**
         * Request parameters a model may refuse. When OpenAI rejects one of
         * them, the request is retried without it.
         */
        const OPTIONAL_PARAMS = array('reasoning_effort', 'temperature', 'top_p', 'frequency_penalty', 'presence_penalty');

        /** Model ids containing these are not text-generation models. */
        const NON_TEXT_MODEL_KEYWORDS = array('embedding', 'tts', 'whisper', 'transcribe', 'dall-e', 'image', 'audio', 'realtime', 'moderation', 'sora', 'davinci', 'babbage');

        public $base_url = 'https://api.openai.com/v1';

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

        public function get_model()
        {
            $model = self::sanitize_model(\Tablesome\Includes\Settings\Tablesome_Getter::get(self::MODEL_SETTING));
            if ('' === $model) {
                $model = self::DEFAULT_MODEL;
            }
            return apply_filters('tablesome_openai_model', $model);
        }

        public static function sanitize_model($model)
        {
            if (!is_string($model)) {
                return '';
            }
            // Model ids look like "gpt-5.6-terra" or "ft:gpt-4o-mini:org::id".
            return preg_replace('/[^A-Za-z0-9._:\-\/]/', '', sanitize_text_field($model));
        }

        /**
         * @param string $prompt
         * @param array  $payload max_tokens, temperature, top_p, frequency_penalty, presence_penalty
         * @return array|\WP_Error The decoded chat completion, or why there is no text.
         */
        public function completions_request($prompt, $payload = array())
        {
            $this->error = '';

            $info = $this->get_openai_auth_info();
            if (empty($info['api_key'])) {
                return $this->fail('openai_not_connected', __('OpenAI is not connected. Add your API key in Tablesome → Settings → Integrations → OpenAI.', 'tablesome'));
            }

            $body = $this->get_chat_body($prompt, $payload);
            $fallbacks_key = self::PARAM_FALLBACKS_TRANSIENT . md5($body['model']);
            $cached_fallbacks = get_transient($fallbacks_key);
            $cached_fallbacks = is_array($cached_fallbacks) ? $cached_fallbacks : array();
            $fallbacks = $cached_fallbacks;
            $body = $this->apply_param_fallbacks($body, $fallbacks);

            // Each retry drops one optional parameter (or lowers reasoning_effort once), so this always ends.
            for ($attempt = 0; $attempt < count(self::OPTIONAL_PARAMS) + 2; $attempt++) {
                $response = wp_remote_post($this->base_url . '/chat/completions', array(
                    'headers' => $this->get_api_headers($info),
                    'body' => wp_json_encode($body),
                    'timeout' => 60,
                ));

                if (is_wp_error($response)) {
                    /* translators: %s: error from the HTTP transport, e.g. a timeout. */
                    return $this->fail('openai_http_error', sprintf(__('Could not reach OpenAI: %s', 'tablesome'), $response->get_error_message()));
                }

                $response_code = (int) wp_remote_retrieve_response_code($response);
                $data = json_decode(wp_remote_retrieve_body($response), true);

                if (200 === $response_code) {
                    break;
                }

                $fallback = $this->get_param_fallback($response_code, $data, $body);
                if (empty($fallback)) {
                    return $this->fail('openai_api_error', $this->get_api_error_message($response_code, $data), array('status' => $response_code));
                }
                $fallbacks = array_merge($fallbacks, $fallback);
                $body = $this->apply_param_fallbacks($body, $fallback);
            }

            if (200 !== $response_code) {
                return $this->fail('openai_api_error', $this->get_api_error_message($response_code, $data), array('status' => $response_code));
            }

            // Remember what this model refused so later runs skip the failed attempts.
            if ($fallbacks !== $cached_fallbacks) {
                set_transient($fallbacks_key, $fallbacks, WEEK_IN_SECONDS);
            }

            if (!isset($data['choices'][0]['message']) || !is_array($data['choices'][0]['message'])) {
                return $this->fail('openai_invalid_response', __('OpenAI returned a response without any text.', 'tablesome'));
            }

            $message = $data['choices'][0]['message'];
            $content = isset($message['content']) && is_string($message['content']) ? $message['content'] : '';
            if ('' === trim($content)) {
                return $this->fail('openai_empty_output', $this->get_empty_output_message($data['choices'][0]));
            }

            return $data;
        }

        public function ping()
        {
            $models = $this->fetch_models();
            if (is_wp_error($models)) {
                return false;
            }
            return true;
        }

        /**
         * Text-generation model ids for the settings field, newest first.
         * Cached by ping() when the key is saved, and refreshed daily.
         */
        public function get_models()
        {
            $models = get_transient(self::MODELS_TRANSIENT);
            if (is_array($models)) {
                return $models;
            }

            $info = $this->get_openai_auth_info();
            if (empty($info['api_key'])) {
                return array();
            }

            $models = $this->fetch_models();
            if (is_wp_error($models)) {
                // Don't call OpenAI on every settings page load while the key is broken.
                set_transient(self::MODELS_TRANSIENT, array(), HOUR_IN_SECONDS);
                return array();
            }
            return $models;
        }

        /** Lists the models the key can use. Costs no tokens. */
        public function fetch_models()
        {
            $this->error = '';

            $info = $this->get_openai_auth_info();
            if (empty($info['api_key'])) {
                return $this->fail('openai_not_connected', __('Invalid API key', 'tablesome'));
            }

            $response = wp_remote_get($this->base_url . '/models', array(
                'headers' => $this->get_api_headers($info),
                'timeout' => 20,
            ));

            if (is_wp_error($response)) {
                /* translators: %s: error from the HTTP transport, e.g. a timeout. */
                return $this->fail('openai_http_error', sprintf(__('Could not reach OpenAI: %s', 'tablesome'), $response->get_error_message()));
            }

            $response_code = (int) wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);

            if (200 !== $response_code || !isset($data['data']) || !is_array($data['data'])) {
                return $this->fail('openai_api_error', $this->get_api_error_message($response_code, $data), array('status' => $response_code));
            }

            $models = array_filter($data['data'], function ($model) {
                if (empty($model['id']) || !is_string($model['id'])) {
                    return false;
                }
                foreach (self::NON_TEXT_MODEL_KEYWORDS as $keyword) {
                    if (false !== strpos($model['id'], $keyword)) {
                        return false;
                    }
                }
                return true;
            });

            usort($models, function ($a, $b) {
                $a_created = isset($a['created']) ? (int) $a['created'] : 0;
                $b_created = isset($b['created']) ? (int) $b['created'] : 0;
                return $b_created - $a_created;
            });

            $model_ids = array_values(array_map(function ($model) {
                return $model['id'];
            }, $models));

            set_transient(self::MODELS_TRANSIENT, $model_ids, DAY_IN_SECONDS);

            return $model_ids;
        }

        public static function record_error($message)
        {
            update_option(self::LAST_ERROR_OPTION, array(
                'message' => $message,
                'time' => time(),
            ), false);
        }

        public static function get_last_error()
        {
            $last_error = get_option(self::LAST_ERROR_OPTION);
            return is_array($last_error) && !empty($last_error['message']) ? $last_error : null;
        }

        public static function clear_last_error()
        {
            if (false !== get_option(self::LAST_ERROR_OPTION)) {
                delete_option(self::LAST_ERROR_OPTION);
            }
        }

        private function get_chat_body($prompt, $payload)
        {
            $body = array(
                'model' => $this->get_model(),
                'messages' => array(
                    array('role' => 'user', 'content' => (string) $prompt),
                ),
                // Reasoning tokens count against max_completion_tokens, so a
                // 100-token limit can be spent before any text is written.
                // "none" matches how gpt-3.5-turbo behaved.
                'reasoning_effort' => 'none',
            );

            // max_tokens is deprecated and refused by reasoning models.
            $max_tokens = isset($payload['max_tokens']) ? absint($payload['max_tokens']) : 0;
            if ($max_tokens > 0) {
                $body['max_completion_tokens'] = $max_tokens;
            }

            foreach (array('temperature', 'top_p', 'frequency_penalty', 'presence_penalty') as $param) {
                if (isset($payload[$param]) && is_numeric($payload[$param])) {
                    $body[$param] = (float) $payload[$param];
                }
            }

            return apply_filters('tablesome_openai_chat_completion_body', $body, $prompt, $payload);
        }

        /**
         * When a model refuses a parameter, OpenAI answers 400 and names it,
         * e.g. "Unsupported value: 'temperature' does not support 0.9 with this model".
         *
         * @return array [param => replacement value, or null to drop it], empty if the error is not about a parameter.
         */
        private function get_param_fallback($response_code, $data, $body)
        {
            if (400 !== $response_code || !isset($data['error']) || !is_array($data['error'])) {
                return array();
            }

            $param = isset($data['error']['param']) && is_string($data['error']['param']) ? $data['error']['param'] : '';
            $code = isset($data['error']['code']) && is_string($data['error']['code']) ? $data['error']['code'] : '';
            $message = isset($data['error']['message']) && is_string($data['error']['message']) ? $data['error']['message'] : '';

            $is_unsupported = in_array($code, array('unsupported_parameter', 'unsupported_value'), true)
            || preg_match('/Unsupported (?:parameter|value)|Unrecognized request argument/', $message);
            if (!$is_unsupported) {
                // Other errors (bad key, unknown model, quota) need the site owner, not a retry.
                return array();
            }

            if ('' === $param && preg_match("/(?:Unsupported (?:parameter|value): '|Unrecognized request argument supplied: )([a-z_]+)/", $message, $matches)) {
                $param = $matches[1];
            }

            if (!in_array($param, self::OPTIONAL_PARAMS, true) || !array_key_exists($param, $body)) {
                return array();
            }

            $is_unsupported_value = 'unsupported_value' === $code || false !== stripos($message, 'Unsupported value');
            if ('reasoning_effort' === $param && 'none' === $body['reasoning_effort'] && $is_unsupported_value) {
                // Some reasoning models have no "none" setting; "low" is the cheapest one they all accept.
                return array('reasoning_effort' => 'low');
            }

            return array($param => null);
        }

        private function apply_param_fallbacks($body, $fallbacks)
        {
            foreach ($fallbacks as $param => $value) {
                if (!array_key_exists($param, $body)) {
                    continue;
                }
                if (null === $value) {
                    unset($body[$param]);
                } else {
                    $body[$param] = $value;
                }
            }
            return $body;
        }

        private function get_api_error_message($response_code, $data)
        {
            if (isset($data['error']['message']) && is_string($data['error']['message']) && '' !== $data['error']['message']) {
                return $data['error']['message'];
            }
            /* translators: %d: HTTP status code. */
            return sprintf(__('OpenAI returned HTTP %d.', 'tablesome'), $response_code);
        }

        private function get_empty_output_message($choice)
        {
            if (!empty($choice['message']['refusal']) && is_string($choice['message']['refusal'])) {
                /* translators: %s: the model's refusal text. */
                return sprintf(__('OpenAI declined the prompt: %s', 'tablesome'), $choice['message']['refusal']);
            }

            $finish_reason = isset($choice['finish_reason']) ? (string) $choice['finish_reason'] : '';
            if ('length' === $finish_reason) {
                return __('OpenAI reached the Max Tokens limit before writing any text. Increase Max Tokens in the Generate Text action.', 'tablesome');
            }

            /* translators: %s: OpenAI finish_reason value. */
            return sprintf(__('OpenAI returned no text (finish reason: %s).', 'tablesome'), '' !== $finish_reason ? $finish_reason : 'unknown');
        }

        private function fail($code, $message, $data = array())
        {
            $this->error = array('message' => $message);
            error_log('Tablesome OpenAI error (' . $code . '): ' . $message);
            return new \WP_Error($code, $message, $data);
        }
    }
}
