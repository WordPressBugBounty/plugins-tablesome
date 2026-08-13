<?php

namespace Tablesome\Workflow_Library\External_Apis;

use Tablesome\Includes\Modules\API_Credentials_Handler;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\External_Apis\Hubspot')) {
    class Hubspot
    {
        public $integration;
        public $api_credentials_handler;
        public function __construct()
        {
            $this->integration = "hubspot";
            $this->api_credentials_handler = new API_Credentials_Handler();
        }

        public function is_active()
        {
            $data = $this->api_credentials_handler->get_api_credentials("hubspot");
            return $data["status"] == "success";
        }

        public function get_static_lists()
        {
            $access_token = maybe_refresh_access_token_by_integration($this->integration);
            $url = " https://api.hubapi.com/contacts/v1/lists/static?count=100&offset=0";

            $response = wp_remote_post($url, array(
                'method' => 'GET',
                'headers' => [
                    "authorization" => "Bearer " . $access_token,
                ],
            ));

            if (is_wp_error($response)) {
                $data = [
                    'status' => "failed",
                    'message' => "Couldn't get the $this->integration static lists!",
                ];
            } else {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $status = isset($data['status']) ? $data['status'] : '';
                $category = isset($data['category']) ? $data['category'] : '';
                $token_expired = $status == "error" && $category == "EXPIRED_AUTHENTICATION";

                if ($token_expired) {
                    Retry::set_integration($this->integration);
                    return Retry::call($this, 'get_static_lists', []);
                }
                Retry::reset_count();
                $data = $this->get_formated_lists($data["lists"]);
            }

            return $data;
        }

        public function get_properties()
        {
            $access_token = maybe_refresh_access_token_by_integration($this->integration);
            $response = wp_remote_post("https://api.hubapi.com/crm/v3/properties/contacts?archived=false", array(
                'method' => 'GET',
                'headers' => [
                    "authorization" => "Bearer " . $access_token,
                ],
            ));

            if (is_wp_error($response)) {
                $data = [
                    'status' => "failed",
                    'message' => "Couldn't get $this->integration contacts properties!",
                ];
            } else {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $status = isset($data['status']) ? $data['status'] : '';
                $category = isset($data['category']) ? $data['category'] : '';
                $token_expired = $status == "error" && $category == "EXPIRED_AUTHENTICATION";

                if ($token_expired) {
                    Retry::set_integration($this->integration);
                    return Retry::call($this, 'get_properties', []);
                }
                Retry::reset_count();

                $data = $this->get_formated_properties($data["results"]);
            }

            // error_log(' get_properties data : ' . print_r($data, true));

            return $data;
        }

        public function add_contact($properties)
        {
            $access_token = maybe_refresh_access_token_by_integration($this->integration);
            $contact_properties = [
                "properties" => $properties,
            ];

            $url = "https://api.hubapi.com/crm/v3/objects/contacts";
            $args = array(
                'method' => 'POST',
                'headers' => [
                    "Authorization" => "Bearer " . $access_token,
                    "Content-Type" => "application/json",
                ],
                'body' => wp_json_encode($contact_properties),
            );

            $response = wp_remote_post($url, $args);

            error_log('add_contact $response : ' . print_r(json_decode(wp_remote_retrieve_body($response), true), true));

            if (is_wp_error($response)) {
                $data = [
                    'status' => "failed",
                    'message' => "Failed, contact not added!",
                ];
            } else {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $status = isset($data['status']) ? $data['status'] : '';
                $category = isset($data['category']) ? $data['category'] : '';
                $token_expired = $status == "error" && $category == "EXPIRED_AUTHENTICATION";

                if ($token_expired) {
                    Retry::set_integration($this->integration);
                    return Retry::call($this, 'add_contact', [$properties]);
                }
                Retry::reset_count();
                $data = array_merge([
                    'status' => "success",
                    'message' => "Successfully, added contact!",
                ], $data);
            }

            return $data;
        }

        /**
         * Fetch deal pipelines (and their stages) from HubSpot, formatted for
         * the workflow-builder Svelte UI dropdowns.
         *
         * Error states degrade to an empty list rather than leaking API
         * error bodies into the UI.
         *
         * @return array[] {
         *   @type string   $id     Pipeline id (e.g., 'default' or a numeric id).
         *   @type string   $label  Pipeline display label.
         *   @type array[]  $stages [[id, label], ...] ordered as HubSpot returns them.
         * }
         */
        /**
         * Fetch deal properties from HubSpot, formatted for the Create Deal
         * action's destination Select. Mirrors `get_properties()` (contacts)
         * but degrades to an empty list on any error rather than leaking
         * error bodies into the UI.
         *
         * @return array[] {
         *   @type string  $id      Property name (e.g., 'dealname').
         *   @type string  $label   Display label (e.g., 'Deal Name').
         *   @type string  $type    HubSpot fieldType.
         *   @type array[] $options Optional, for enumerated properties.
         * }
         */
        public function get_deal_properties()
        {
            $access_token = maybe_refresh_access_token_by_integration($this->integration);
            $url = "https://api.hubapi.com/crm/v3/properties/deals?archived=false";

            $response = wp_remote_request($url, [
                'method'  => 'GET',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
            ]);

            if (is_wp_error($response)) {
                error_log('Tablesome Hubspot get_deal_properties wp_error: ' . $response->get_error_message());
                return [];
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($code >= 400 || !is_array($body) || empty($body['results'])) {
                if ($code >= 400) {
                    error_log('Tablesome Hubspot get_deal_properties API error: ' . print_r($body, true));
                }
                return [];
            }

            return $this->get_formated_properties($body['results']);
        }

        public function get_pipelines()
        {
            $access_token = maybe_refresh_access_token_by_integration($this->integration);
            $url = "https://api.hubapi.com/crm/v3/pipelines/deals";

            $response = wp_remote_request($url, [
                'method'  => 'GET',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
            ]);

            if (is_wp_error($response)) {
                error_log('Tablesome Hubspot get_pipelines wp_error: ' . $response->get_error_message());
                return [];
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($code >= 400 || !is_array($body) || empty($body['results'])) {
                if ($code >= 400) {
                    error_log('Tablesome Hubspot get_pipelines API error: ' . print_r($body, true));
                }
                return [];
            }

            return $this->format_pipelines($body['results']);
        }

        private function format_pipelines($raw_pipelines)
        {
            $pipelines = [];
            foreach ($raw_pipelines as $pipeline) {
                if (empty($pipeline['id'])) {
                    continue;
                }
                $stages = [];
                if (!empty($pipeline['stages']) && is_array($pipeline['stages'])) {
                    foreach ($pipeline['stages'] as $stage) {
                        if (empty($stage['id'])) {
                            continue;
                        }
                        $stages[] = [
                            'id'    => (string) $stage['id'],
                            'label' => isset($stage['label']) ? (string) $stage['label'] : (string) $stage['id'],
                        ];
                    }
                }
                $pipelines[] = [
                    'id'     => (string) $pipeline['id'],
                    'label'  => isset($pipeline['label']) ? (string) $pipeline['label'] : (string) $pipeline['id'],
                    'stages' => $stages,
                ];
            }
            return $pipelines;
        }

        /**
         * Upsert a contact by email. Creates if the email doesn't exist in
         * HubSpot, updates the contact's properties if it does.
         *
         * Safeguards against Tablesome/frontend bugs that could corrupt the
         * customer's HubSpot data:
         *   - Requires a valid email (rejects field-swap bugs at the edge).
         *   - Strips empty / null / whitespace-only values so an unchecked
         *     form field never wipes an existing firstname/phone/company.
         *   - Short-circuits via the `tablesome/hubspot/skip_upsert` filter.
         *
         * @param array $properties HubSpot contact properties, keyed by
         *                          property name. Must contain 'email'.
         * @return array {
         *   @type string $status     'success' | 'failed' | 'skipped'
         *   @type string $message
         *   @type string $contact_id HubSpot vid, present on success
         *   @type bool   $created    true if a new contact was created
         * }
         */
        public function upsert_contact($properties)
        {
            $properties = $this->prepare_contact_properties($properties);

            if (!$this->is_valid_email_property($properties)) {
                error_log('Tablesome Hubspot upsert_contact rejected: invalid or missing email');
                return [
                    'status'  => 'failed',
                    'message' => 'Valid email is required for HubSpot upsert.',
                ];
            }

            /**
             * Allow callers to abort an upsert (e.g., staging environments).
             *
             * @param bool  $skip       Return true to abort.
             * @param array $properties Prepared properties that would be sent.
             */
            $skip = apply_filters('tablesome/hubspot/skip_upsert', false, $properties);
            if ($skip) {
                return [
                    'status'  => 'skipped',
                    'message' => 'Upsert skipped by tablesome/hubspot/skip_upsert filter.',
                ];
            }

            $access_token = maybe_refresh_access_token_by_integration($this->integration);
            $url = "https://api.hubapi.com/crm/v3/objects/contacts/batch/upsert";

            $payload = [
                'inputs' => [[
                    'idProperty' => 'email',
                    'id'         => $properties['email'],
                    'properties' => $properties,
                ]],
            ];

            $response = wp_remote_post($url, [
                'method'  => 'POST',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($payload),
            ]);

            if (is_wp_error($response)) {
                return [
                    'status'  => 'failed',
                    'message' => 'HubSpot upsert request failed: ' . $response->get_error_message(),
                ];
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($code >= 400 || !is_array($body)) {
                error_log('Tablesome Hubspot upsert_contact API error: ' . print_r($body, true));
                return [
                    'status'  => 'failed',
                    'message' => isset($body['message']) ? $body['message'] : 'HubSpot upsert failed.',
                ];
            }

            $results = isset($body['results']) && is_array($body['results']) ? $body['results'] : [];
            $first   = !empty($results) ? $results[0] : null;

            if (!$first || empty($first['id'])) {
                return [
                    'status'  => 'failed',
                    'message' => 'HubSpot upsert returned no contact id.',
                ];
            }

            return [
                'status'     => 'success',
                'message'    => 'Successfully upserted contact.',
                'contact_id' => (string) $first['id'],
                'created'    => isset($first['new']) ? (bool) $first['new'] : false,
            ];
        }

        /**
         * Create a new deal, optionally associated with a contact.
         *
         * Always creates — no upsert. Per customer requirement, a new deal is
         * created for every form submission.
         *
         * @param array $properties Deal properties (dealname, pipeline,
         *                          dealstage, amount, …). Empty values stripped.
         * @param array $options    {
         *   @type string $contact_id  HubSpot contact vid to associate.
         *                             Empty string / missing → no association.
         * }
         * @return array {
         *   @type string $status  'success' | 'failed'
         *   @type string $message
         *   @type string $deal_id HubSpot deal id, present on success.
         * }
         */
        public function create_deal($properties, $options = [])
        {
            $properties = $this->prepare_contact_properties($properties); // same stripper
            $contact_id = isset($options['contact_id']) ? (string) $options['contact_id'] : '';

            $access_token = maybe_refresh_access_token_by_integration($this->integration);
            $url = "https://api.hubapi.com/crm/v3/objects/deals";

            $payload = [
                'properties' => $properties,
            ];

            if ($contact_id !== '') {
                $payload['associations'] = [[
                    'to'    => ['id' => $contact_id],
                    'types' => [[
                        'associationCategory' => 'HUBSPOT_DEFINED',
                        // HubSpot-defined association type id for deal → contact
                        'associationTypeId'   => 3,
                    ]],
                ]];
            }

            $response = wp_remote_post($url, [
                'method'  => 'POST',
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode($payload),
            ]);

            if (is_wp_error($response)) {
                return [
                    'status'  => 'failed',
                    'message' => 'HubSpot deal request failed: ' . $response->get_error_message(),
                ];
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($code >= 400 || !is_array($body) || empty($body['id'])) {
                error_log('Tablesome Hubspot create_deal API error: ' . print_r($body, true));
                return [
                    'status'  => 'failed',
                    'message' => isset($body['message']) ? $body['message'] : 'HubSpot deal creation failed.',
                ];
            }

            return [
                'status'  => 'success',
                'message' => 'Successfully created deal.',
                'deal_id' => (string) $body['id'],
            ];
        }

        /**
         * Strip property values that would harmfully overwrite existing data
         * in HubSpot. Empty strings, nulls and whitespace-only strings are
         * removed. "0", false, integers and non-empty strings pass through.
         */
        public function prepare_contact_properties($properties)
        {
            if (!is_array($properties)) {
                return [];
            }
            $clean = [];
            foreach ($properties as $key => $value) {
                if ($value === null) {
                    continue;
                }
                if (is_string($value) && trim($value) === '') {
                    continue;
                }
                $clean[$key] = $value;
            }
            return $clean;
        }

        /**
         * Validate the mapped 'email' property. Catches frontend field-swap
         * bugs (e.g., a "name" field mistakenly landing in the email slot)
         * before they pollute HubSpot with junk contacts.
         */
        public function is_valid_email_property($properties)
        {
            if (empty($properties['email']) || !is_string($properties['email'])) {
                return false;
            }
            return (bool) is_email($properties['email']);
        }

        public function add_contact_to_static_list($args)
        {
            $data = $this->add_contact($args["properties"]);
            $list_id = $args["list_id"];
            $contact_id = isset($data['id']) ? $data['id'] : '';
            if (empty($list_id) || empty($contact_id)) {
                return false;
            }

            $subscriber_data = ["vids" => [$contact_id]];
            $data = $this->api_credentials_handler->get_api_credentials("hubspot");
            $url = "https://api.hubapi.com/contacts/v1/lists/$list_id/add";
            $args = array(
                'method' => 'POST',
                'headers' => [
                    "Authorization" => "Bearer " . $data["access_token"],
                    "Content-Type" => "application/json",
                ],
                'body' => wp_json_encode($subscriber_data),
            );

            $response = wp_remote_post($url, $args);
            error_log(' response : ' . print_r($response, true));

            if (is_wp_error($response)) {
                $data = [
                    'status' => "failed",
                    'message' => "Failed, contact not added to static list!",
                ];
            } else {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                $status = isset($data['status']) ? $data['status'] : '';
                $category = isset($data['category']) ? $data['category'] : '';
                $token_expired = $status == "error" && $category == "EXPIRED_AUTHENTICATION";

                if ($token_expired) {
                    Retry::set_integration($this->integration);
                    return Retry::call($this, 'add_contact_to_static_list', [$args]);
                }
                Retry::reset_count();
                $data = array_merge([
                    'status' => "success",
                    'message' => "Successfully, contact added to static list!",
                ], $data);
            }

            return rest_ensure_response(
                $data
            );
        }

        private function get_formated_lists($given_lists)
        {
            $lists = [];
            foreach ($given_lists as $list) {
                if ($list["dynamic"] == true) {
                    continue;
                }

                array_push($lists, [
                    "id" => $list["listId"],
                    "label" => ucfirst($list["name"]),
                    "integration_type" => $this->integration,
                ]);
            }

            if (empty($lists)) {
                $lists[] = [
                    "id" => "no_lists_found",
                    "label" => "No static list found! Please create a new static list.",
                    "integration_type" => $this->integration,
                    "disabled" => true,
                ];
            }

            return $lists;
        }

        private function get_formated_properties($properties)
        {
            $fields = [];
            foreach ($properties as $property) {

                if ($property["modificationMetadata"]["readOnlyValue"]) {
                    continue;
                }

                $single_option = [
                    "id" => $property["name"],
                    "label" => $property["label"],
                    "type" => $property["fieldType"],
                ];

                if (isset($property["options"]) && !empty($property["options"])) {
                    $single_options = [];
                    foreach ($property["options"] as $option) {
                        array_push($single_options, [
                            "id" => $option["value"],
                            "label" => $option["label"],
                        ]);
                    }
                    $single_option["options"] = $single_options;
                }
                $fields[$property["label"]] = $single_option;
            }
            ksort($fields);
            return array_values($fields);
        }
    }
}
