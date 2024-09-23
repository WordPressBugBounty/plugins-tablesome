<?php

namespace Tablesome\Includes\Modules\Workflow;

use Tablesome\Workflow_Library\Actions\Files_Generate_CSV;
use Tablesome\Workflow_Library\Actions\GSheet_Add_Row;
use Tablesome\Workflow_Library\Actions\GSheet_Load_From;
use Tablesome\Workflow_Library\Actions\Hubspot_Add_Contact;
use Tablesome\Workflow_Library\Actions\Hubspot_Add_Contact_To_Static_List;
use Tablesome\Workflow_Library\Actions\Mailchimp_Add_Contact;
use Tablesome\Workflow_Library\Actions\Notion_Database;
use Tablesome\Workflow_Library\Actions\Open_Ai_Generate_Text;
use Tablesome\Workflow_Library\Actions\Slack_Send_Message_To_Channel;
use Tablesome\Workflow_Library\Actions\Slack_Send_Message_To_User;
use Tablesome\Workflow_Library\Actions\Tablesome_Add_Row;
use Tablesome\Workflow_Library\Actions\Tablesome_Filter_Table;
use Tablesome\Workflow_Library\Actions\Tablesome_Generate_Pdf;
use Tablesome\Workflow_Library\Actions\Tablesome_Load_WP_Query_Content;
use Tablesome\Workflow_Library\Actions\WP_Post_Creation;
use Tablesome\Workflow_Library\Actions\WP_Redirection;
use Tablesome\Workflow_Library\Actions\WP_Send_Mail;
use Tablesome\Workflow_Library\Actions\WP_User_Creation;
use Tablesome\Workflow_Library\Integrations\GSheet;
use Tablesome\Workflow_Library\Integrations\Mailchimp;
use Tablesome\Workflow_Library\Integrations\Notion;
use Tablesome\Workflow_Library\Integrations\OpenAi;
use Tablesome\Workflow_Library\Integrations\Slack;
use Tablesome\Workflow_Library\Integrations\Tablesome;
use Tablesome\Workflow_Library\Integrations\WP_Core;
use Tablesome\Workflow_Library\Triggers\Cf7;
use Tablesome\Workflow_Library\Triggers\Elementor;
use Tablesome\Workflow_Library\Triggers\Fluent;
use Tablesome\Workflow_Library\Triggers\Forminator;
use Tablesome\Workflow_Library\Triggers\Gravity;
use Tablesome\Workflow_Library\Triggers\On_Send_Email;
use Tablesome\Workflow_Library\Triggers\Tablesome as TablesomeTrigger;
use Tablesome\Workflow_Library\Triggers\WP_Forms;
use \Tablesome\Workflow_Library\Integrations\Files;
use \Tablesome\Workflow_Library\Integrations\Hubspot;
use \Tablesome\Workflow_Library\Integrations\Woocommerce;
use \Tablesome\Workflow_Library\Triggers\Woo_Thankyou as Woo_Thankyou;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\Workflow\Library')) {
    class Library
    {

        public $triggers = [];
        public $integrations = [];
        public $actions = [];
        public $pro_text = '- Pro';

        public function __construct()
        {

        }
        public function init()
        {
            $this->triggers = array(
                'tablesome' => new TablesomeTrigger(),
                'cf7' => new Cf7(),
                'wpforms' => new WP_Forms(),
                'elementor' => new Elementor(),
                'forminator' => new Forminator(),
                'gravity' => new Gravity(),
                'fluent' => new Fluent(),
                'email' => new On_Send_Email(),
                'woocommerce_thankyou' => new Woo_Thankyou(),
            );

            $this->integrations = array(
                'openai' => new OpenAi(),
                'tablesome' => new Tablesome(),
                'wordpress' => new WP_Core(),
                'mailchimp' => new Mailchimp(),
                'notion' => new Notion(),
                'slack' => new Slack(),
                'hubspot' => new Hubspot(),
                'files' => new Files(),
                'woocommerce' => new Woocommerce(),
            );

            $this->actions = array(
                'add_row' => new Tablesome_Add_Row(),
                'create_pdf' => new Tablesome_Generate_Pdf(),
                'add_contact' => new Mailchimp_Add_Contact(),
                'add_page' => new Notion_Database(),
                'redirection' => new WP_Redirection(),
                'add_new_wp_post' => new WP_Post_Creation(),
                'add_new_wp_user' => new WP_User_Creation(),
                'send_mail' => new WP_Send_Mail(),
                'filter_table' => new Tablesome_Filter_Table(),
                'load_wp_query_content' => new Tablesome_Load_WP_Query_Content(),
                'gsheet_add_row' => new GSheet_Add_Row(),
                'gsheet_load_from' => new GSheet_Load_From(),
                'slack_send_message_to_channel' => new Slack_Send_Message_To_Channel(),
                'slack_send_message_to_user' => new Slack_Send_Message_To_User(),
                'hubspot_add_contact' => new Hubspot_Add_Contact(),
                'hubspot_add_contact_to_static_list' => new Hubspot_Add_Contact_To_Static_List(),
                'files_generate_csv' => new Files_Generate_CSV(),
                'open_ai_generate_text' => new Open_Ai_Generate_Text(),
            );

            if (pauple_is_feature_active('gsheet_action')) {
                $this->actions['gsheet_add_row'] = new GSheet_Add_Row();
                $this->integrations['gsheet'] = new GSheet();
            }

        }

        public function get_triggers_config()
        {
            $configs = [];
            $is_premium = tablesome_fs()->can_use_premium_code__premium_only();
            // error_log('$this->triggers : ' . print_r($this->triggers, true));
            foreach ($this->triggers as $trigger) {
                // error_log('$trigger : ' . print_r($trigger, true));
                $config = $trigger->get_config();
                $config["trigger_label"] = $config["is_premium"] == "yes" && !$is_premium ? $config["trigger_label"] . $this->pro_text : $config["trigger_label"];

                $configs[] = $config;
            }
            return $configs;
        }

        public function get_trigger_name($trigger_id)
        {
            $trigger_configs = $this->get_triggers_config();
            foreach ($trigger_configs as $config) {
                // error_log('$config : ' . print_r($config, true));
                if ($config['trigger_id'] == $trigger_id) {
                    return $config['trigger'];
                }
            }
            return '';
        }

        public function get_action_name($action_id)
        {
            $action_configs = $this->get_all_actions_config();
            // error_log('get_action_name() $action_configs : ' . print_r($action_configs, true));
            foreach ($action_configs as $config) {
                // error_log('get_action_name() $config : ' . print_r($config, true));
                // error_log('get_action_name() $action[id] : ' . $config['id']);
                // error_log('get_action_name() $action_id : ' . print_r($action_id, true));
                if ($config['id'] == $action_id) {
                    return $config['integration'] . "-" . $config['name'];
                }
            }
            return '';
        }

        public function get_all_actions_config()
        {
            $configs = [];
            $is_premium = tablesome_fs()->can_use_premium_code__premium_only();

            foreach ($this->actions as $action_name => $action_instance) {
                $action_config = $action_instance->get_config();
                $action_config["label"] = $action_config["is_premium"] && !$is_premium ? $action_config["label"] . $this->pro_text : $action_config["label"];
                $configs[] = $action_config;

            }
            // $configs[] = $config;

            return $configs;
        }

        public function get_actions_config()
        {
            $configs = [];
            $is_premium = tablesome_fs()->can_use_premium_code__premium_only();
            foreach ($this->integrations as $name => $integration_instance) {
                $config = $integration_instance->get_config();

                foreach ($this->actions as $action_name => $action_instance) {
                    $action_config = $action_instance->get_config();
                    if ($config['integration'] == $action_config['integration']) {
                        $action_config["label"] = $action_config["is_premium"] && !$is_premium ? $action_config["label"] . $this->pro_text : $action_config["label"];

                        $config['actions'][] = $action_config;
                    }
                }
                $configs[] = $config;
            }
            return $configs;
        }

    } // END CLASS

}
