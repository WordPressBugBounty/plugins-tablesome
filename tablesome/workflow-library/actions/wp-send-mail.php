<?php

namespace Tablesome\Workflow_Library\Actions;

use Tablesome\Includes\Modules\Workflow\Action;
use Tablesome\Includes\Modules\Workflow\Traits\Placeholder;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Actions\WP_Send_Mail')) {
    class WP_Send_Mail extends Action
    {
        use Placeholder;

        public $email_fields = [];
        public $from_email_address = [];
        public $to_address_data = [];
        public $subject_content = '';
        public $body_content = '';
        public $trigger_source_data = [];
        public $placeholders;

        protected $headers = [
            "from" => '',
            'cc' => '',
            'bcc' => '',
            'content_type' => '',
        ];

        // public function __construct() {
        //     add_action('wp_mail_failed', [$this, 'onMailError'], 10, 1);
        // }
        public function get_config()
        {
            return array(
                'id' => 7,
                'name' => 'send_mail',
                'label' => __('Send Mail', 'tablesome'),
                'integration' => 'wordpress',
                'is_premium' => false,
            );
        }

        // public function onMailError($err) {
        //     error_log('$err : ' . print_r($err, true));
        // }

        public function do_action($trigger_class, $trigger_instance)
        {
            error_log('*** WordPress Sent Email Action Called  ***');
            $this->bind_props($trigger_class, $trigger_instance);

            if (!$this->validate()) {
                return false;
            }

            $this->set_mail_headers();
            foreach ($this->to_address_data as $data) {
                $to_address_emails = $this->get_emails_by_prop_name($data, 'emails');
                if (empty($to_address_emails)) {
                    continue;
                }

                $cc_emails = $this->get_emails_by_prop_name($data, 'cc');
                if (!empty($cc_emails)) {
                    $cc_emails_in_string = implode(",", $cc_emails);
                    $this->headers['cc'] = "Cc: {$cc_emails_in_string}";
                }

                $bcc_emails = $this->get_emails_by_prop_name($data, 'bcc');
                if (!empty($bcc_emails)) {
                    $bcc_emails_in_string = implode(",", $bcc_emails);
                    $this->headers['bcc'] = "Bcc: {$bcc_emails_in_string}";
                }

                $headers_content = implode("\r\n", $this->headers);
                // add form entry csv attachment as link to the body content
                $attachments = [];

                $sent = \wp_mail(
                    $to_address_emails,
                    $this->subject_content,
                    $this->body_content,
                    $headers_content,
                    $attachments
                );
                // $this->get_attachments()

                //TODO: Add mail failure log
                // if (!$sent) {
                // }
            }
        }

        private function bind_props($trigger_class, $trigger_instance)
        {
            global $workflow_data;
            $_trigger_instance_id = $trigger_instance['_trigger_instance_id'];
            // Get the current trigger data with previous action outputs
            $current_trigger_outputs = isset($workflow_data[$_trigger_instance_id]) ? $workflow_data[$_trigger_instance_id] : [];

            $this->trigger_source_data = $trigger_class->trigger_source_data['data'];
            $action_meta = isset($trigger_instance['action_meta']) ? $trigger_instance['action_meta'] : [];

            $this->email_fields = isset($action_meta['email_fields']) ? $action_meta['email_fields'] : [];

            // Create the placeholders from the current trigger outputs
            $this->placeholders = $this->getPlaceholdersFromKeyValues($current_trigger_outputs);
            $this->add_csv_action_entry_link_as_placeholder();
            $this->add_pdf_action_entry_link_as_placeholder();
            // error_log('$this->placeholders : ' . print_r($this->placeholders, true));
            $from_email_address = isset($this->email_fields['from_address']['email']) ? trim($this->email_fields['from_address']['email']) : '';

            $this->from_email_address = !empty($from_email_address) ? $this->applyPlaceholders($this->placeholders, $from_email_address) : '';

            $this->to_address_data = isset($this->email_fields['to_address']) ? $this->email_fields['to_address'] : [];

            $this->subject_content = $this->get_subject_content();

            $this->body_content = $this->get_mail_body_content();
        }

        private function validate()
        {
            if (empty($this->from_email_address) || !is_email($this->from_email_address)) {
                return;
            }

            if (empty($this->to_address_data)) {
                return;
            }

            return true;
        }

        private function set_mail_headers()
        {
            $this->headers['from'] = "From: <{$this->from_email_address}>";
            $this->headers['content_type'] = "Content-Type: {$this->get_content_type()}; charset=utf-8\r\n";
        }

        private function get_emails_by_prop_name($email_data, $prop_name)
        {
            if (!isset($email_data[$prop_name]) || empty($email_data[$prop_name])) {
                return [];
            }
            $emails = explode(",", $email_data[$prop_name]);
            if (!is_array($emails) || count($emails) == 0) {
                return [];
            }

            $valid_emails = [];

            foreach ($emails as $email) {
                $email = trim($email);
                $email = $this->applyPlaceholders($this->placeholders, $email);
                if (is_email($email)) {
                    $valid_emails[] = $email;
                }
            }
            return count($valid_emails) ? array_unique(array_values($valid_emails)) : [];
        }

        private function get_content_type()
        {
            return 'text/html';
        }

        private function get_subject_content()
        {
            $content = isset($this->email_fields['subject']['content']) ? $this->email_fields['subject']['content'] : '';
            $content = $this->applyPlaceholders($this->placeholders, $content);
            return $content;
        }

        private function get_mail_body_content()
        {
            $content = isset($this->email_fields['body']['content']) ? $this->email_fields['body']['content'] : '';
            $content = $this->applyPlaceholders($this->placeholders, $content);
            // error_log('get_mail_body_content() $content : ' . print_r($content, true));
            return $content;
        }

        // Function to get array with id = 15
        public function getArrayWithId($array, $id)
        {
            foreach ($array as $item) {
                if ($item['id'] == $id) {
                    return $item;
                }
            }
            return null; // Return null if id is not found
        }

        public function get_action_data($action_id)
        {
            global $tablesome_workflow_data;

            // error_log('get_action_data() $tablesome_workflow_data : ' . print_r($tablesome_workflow_data, true));
            if (!isset($tablesome_workflow_data) || empty($tablesome_workflow_data)) {
                return [];
            }

            // Find array with $action_id = id
            // $csv_action_data = array_filter($tablesome_workflow_data, function ($data) {
            //     return $data['id'] == $action_id;
            // });

            // $action_data = array_filter($tablesome_workflow_data, function ($data) use ($action_id) {
            //     return $data['id'] == $action_id;
            // });

            $action_data = $this->getArrayWithId($tablesome_workflow_data, $action_id);

            // error_log('get_action_data() $action_data : ' . print_r($action_data, true));

            return $action_data;
        }

        public function add_csv_action_entry_link_as_placeholder()
        {
            global $tablesome_workflow_data;
            $csv_action_data = $this->get_action_data(15); // action_id = 15
            // error_log('add_csv_action_entry_link_as_placeholder() $csv_action_data : ' . print_r($csv_action_data, true));
            // $csv_action_data = isset($tablesome_workflow_data) && !empty($tablesome_workflow_data) ? $tablesome_workflow_data[0] : [];
            $attachment_url = isset($csv_action_data["attachment_url"]) ? $csv_action_data["attachment_url"] : "";
            $file_name = isset($csv_action_data["file_name"]) ? $csv_action_data["file_name"] : "";
            if (empty($attachment_url)) {
                return;
            }
            $file_name = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name);
            $this->placeholders['{{generated_csv}}'] = '<a href="' . $attachment_url . '">' . $file_name . ' (Generated CSV)</a>';
        }

        public function add_pdf_action_entry_link_as_placeholder()
        {
            global $tablesome_workflow_data;
            $action_data = $this->get_action_data(18); // action_id = 18
            // error_log('add_pdf_action_entry_link_as_placeholder() $action_data : ' . print_r($action_data, true));
            $attachment_url = isset($action_data["attachment_url"]) ? $action_data["attachment_url"] : "";
            $file_name = isset($action_data["file_name"]) ? $action_data["file_name"] : "";
            if (empty($attachment_url)) {
                return;
            }
            $file_name = preg_replace('/\\.[^.\\s]{3,4}$/', '', $file_name);
            $this->placeholders['{{generated_pdf}}'] = '<a href="' . $attachment_url . '">' . $file_name . ' (Generated PDF)</a>';
        }
    }
}
