<?php

namespace Tablesome\Workflow_Library\External_Apis;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\External_Apis\Api_Connect')) {
    class Api_Connect
    {
        public $api_credentials_handler;
        public function __construct()
        {
            $this->api_credentials_handler = new \Tablesome\Includes\Modules\API_Credentials_Handler();
        }
        public function add_or_update_api_keys($request)
        {
            $params = $request->get_params();
            $api_key = isset($params['api_key']) ? $params['api_key'] : '';
            $type = isset($params['type']) ? $params['type'] : ''; // mailchimp, google
            $action = isset($params['action']) ? $params['action'] : '';

            if (!in_array($type, array('mailchimp', 'notion', 'openai'))) {
                $response_data = array(
                    'message' => 'Invalid integration type.',
                    'status' => false,
                );
                return rest_ensure_response($response_data);
            }

            $disconnect_success_message = "%s API key has been removed successfully.";

            if ($type == 'mailchimp') {

                $mailchimp = new \Tablesome\Workflow_Library\Integrations\Mailchimp();

                if ($action == 'disconnect') {

                    $mailchimp->remove_api_data();

                    return rest_ensure_response(array(
                        'action' => 'disconnect',
                        'status' => false,
                        'message' => sprintf($disconnect_success_message, 'Mailchimp'),
                    ));
                }

                $mailchimp->add_api($api_key);
                $response_data = $mailchimp->mailchimp_api->ping();
                error_log('$response_data : ' . print_r($response_data, true));
                return rest_ensure_response($response_data);

            } else if ($type == 'notion') {

                $notion = new \Tablesome\Workflow_Library\Integrations\Notion();

                if ($action == 'disconnect') {

                    $notion->remove_api_data();

                    return rest_ensure_response(array(
                        'action' => 'disconnect',
                        'status' => false,
                        'message' => sprintf($disconnect_success_message, 'Notion'),
                    ));
                }

                $notion->add_api($api_key);
                $response_data = $notion->notion_api->ping();

                return rest_ensure_response($response_data);

            } else if ($type == 'openai') {

                if ($action == 'disconnect') {

                    $this->api_credentials_handler->delete_api_credentials("openai");

                    return rest_ensure_response(array(
                        'action' => 'disconnect',
                        'status' => false,
                        'message' => sprintf($disconnect_success_message, 'OpenAI'),
                    ));
                }

                // store api key in db
                $data = [
                    'status' => false,
                    'message' => 'Invalid API key',
                    'api_key' => $api_key,
                ];
                $api_data = $this->api_credentials_handler->set_api_credentials("openai", $data);

                $api_handler = new \Tablesome\Workflow_Library\External_Apis\OpenAI();
                $response = $api_handler->ping();

                if ($response) {
                    $data['status'] = true;
                    $data['message'] = 'API key is valid';
                } else {
                    $error_message = isset($api_handler->error['message']) ? $api_handler->error['message'] : 'Invalid API key';
                    $data['status'] = false;
                    $data['message'] = $error_message;
                }

                // update data in db if token is valid
                $api_data = $this->api_credentials_handler->set_api_credentials("openai", $data);

                return rest_ensure_response($api_data);
            }

            return rest_ensure_response(array(
                'status' => false,
                'message' => 'We didn\'t yet implement your requested type of integration',
            ));
        }
    }
}
