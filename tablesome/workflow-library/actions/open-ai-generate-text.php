<?php

namespace Tablesome\Workflow_Library\Actions;

use Tablesome\Includes\Modules\Workflow\Action;
use Tablesome\Includes\Modules\Workflow\Traits\Placeholder;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Actions\Open_Ai_Generate_Text')) {
    class Open_Ai_Generate_Text extends Action
    {
        use Placeholder;
        public $action_meta;
        public $trigger_class;
        public $trigger_instance;
        public $placeholders;

        public $openai_api_handler;

        public function __construct()
        {
            $this->openai_api_handler = new \Tablesome\Workflow_Library\External_Apis\OpenAI();
        }

        public function get_config()
        {
            return array(
                'id' => 16,
                'name' => 'open_ai_generate_text',
                'label' => __('Generate Text (OpenAI)', 'tablesome'),
                'integration' => 'openai',
                'is_premium' => false,
            );
        }

        public function do_action($trigger_class, $trigger_instance)
        {
            global $workflow_data;
            $_trigger_instance_id = isset($trigger_instance['_trigger_instance_id']) ? $trigger_instance['_trigger_instance_id'] : '';

            error_log('*** OpenAI Action  ***');
            $this->bind_props($trigger_class, $trigger_instance);
            $prompt_content = isset($this->action_meta['action_fields']['body']['content']) ? $this->action_meta['action_fields']['body']['content'] : '';
            $prompt = $this->get_prompt_content($prompt_content);
            $payload = $this->get_payload($this->action_meta);
            $response = $this->openai_api_handler->completions_request($prompt, $payload);

            $action_output_id = isset($this->action_meta['outputs'][0]['id']) ? $this->action_meta['outputs'][0]['id'] : '';

            if (is_wp_error($response)) {
                \Tablesome\Workflow_Library\External_Apis\OpenAI::record_error($response->get_error_message());
                // Leave the output unset and tell the workflow which output is missing,
                // so actions that use it are skipped instead of getting empty text.
                return new \WP_Error($response->get_error_code(), $response->get_error_message(), array(
                    'failed_outputs' => '' !== $action_output_id ? array($action_output_id) : array(),
                ));
            }

            \Tablesome\Workflow_Library\External_Apis\OpenAI::clear_last_error();

            // Add current action responses to the global $workflow_data array by current trigger instance id
            $workflow_data[$_trigger_instance_id][$action_output_id] = $response['choices'][0]['message']['content'];
        }

        public function get_payload($actionmeta)
        {
            // The action's number fields save '' when cleared, so fall back to the defaults.
            return [
                'max_tokens' => $this->get_number($actionmeta, 'max_tokens', 100),
                'temperature' => $this->get_number($actionmeta, 'temperature', 0.9),
                'frequency_penalty' => $this->get_number($actionmeta, 'frequency_penalty', 0),
                'presence_penalty' => $this->get_number($actionmeta, 'presence_penalty', 0),
            ];
        }

        private function get_number($actionmeta, $key, $default)
        {
            return isset($actionmeta[$key]) && is_numeric($actionmeta[$key]) ? $actionmeta[$key] + 0 : $default;
        }

        private function bind_props($trigger_class, $trigger_instance)
        {
            $this->trigger_class = $trigger_class;
            $this->trigger_instance = $trigger_instance;
            $this->action_meta = isset($this->trigger_instance['action_meta']) ? $this->trigger_instance['action_meta'] : [];
            $this->placeholders = $this->trigger_instance['_placeholders'];
        }

        private function get_prompt_content($prompt_content)
        {
            $prompt_content = $this->applyPlaceholders($this->placeholders, $prompt_content);
            return $prompt_content;
        }

        // public function work_with_dummy_data()
        // {
        //     $file_path = TABLESOME_PATH . "includes/data/action-configs/openai-text-generate.json";
        //     $test_actionmeta = get_data_from_json_file('', $file_path);
        //     $test_trigger_data = $test_actionmeta['test_trigger_data'];
        //     // set placeholders
        //     $this->placeholders = $this->getPlaceholdersFromTriggerSourceData($test_trigger_data);

        //     $payload = $this->get_payload($test_actionmeta);
        //     $prompt_content = $this->get_prompt_content($test_actionmeta['prompt']);

        //     error_log('$test_actionmeta : ' . print_r($test_actionmeta, true));
        //     error_log('$test_trigger_data : ' . print_r($test_trigger_data, true));
        //     error_log('$payload : ' . print_r($payload, true));
        //     error_log('$this->placeholders : ' . print_r($this->placeholders, true));
        //     error_log('$prompt_content : ' . print_r($prompt_content, true));
        //     // $response = $this->openai_api_handler->completions_request($prompt_content, $payload);
        // }
    } // End of class
}
