<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Global Classes

if (!class_exists('\Tablesome')) {
    class Tablesome
    {
        public function __construct()
        {
            $this->setup_autoload();

            // $this->test_myque();

            $this->load_library();
            $this->load_tablesome_functions();
            $this->register_cpt_and_taxonomy();
            $this->load_update_handler();
            $this->load_actions();
            $this->load_filters();
            $this->load_shortcodes();
            $this->load_blocks();

            // Test Prototypes
            // add_action('init', array($this, 'test_google_delete_rows'));
            // $this->test_prototypes();

            // global $myque_db_created;
            // $myque_db_created = false;
        }

        // public function test_google_delete_rows()
        // {
        //     $gsheet_api = new \Tablesome\Workflow_Library\External_Apis\GSheet();

        //     $params = [
        //         'spreadsheet_id' => '1SD0hILufpysPQ8HKeGhLA1OmQ8-m8F0Ybn0kNPA8y-c',
        //         'sheet_name' => 'Sheet1',
        //         'start_row' => 6,
        //         'end_row' => 1000,
        //     ];

        //     // $params2 = [
        //     //     'spreadsheet_id' => '1ba1PzANsNKwWf4Xl29ccYAkf6sg2ZSrysypdV3H3lWQ',
        //     //     'sheet_name' => 'tablepress',
        //     //     'coordinates' => 'A1:Z1000',
        //     //     'range' => 'tablepress',
        //     // ];

        //     $result = $gsheet_api->delete_rows($params);
        //     error_log("Google Sheet Delete Rows Result : " . print_r($result, true));
        // }

        public function test_prototypes()
        {

            $gsheet_api = new \Tablesome\Workflow_Library\External_Apis\GSheet();

            $params = [
                'spreadsheet_id' => '1SD0hILufpysPQ8HKeGhLA1OmQ8-m8F0Ybn0kNPA8y-c',
                'sheet_name' => 'Sheet1',
                'coordinates' => 'A1:Z1000',
                'range' => 'Sheet1',
            ];

            $params2 = [
                'spreadsheet_id' => '1ba1PzANsNKwWf4Xl29ccYAkf6sg2ZSrysypdV3H3lWQ',
                'sheet_name' => 'tablepress',
                'coordinates' => 'A1:Z1000',
                'range' => 'tablepress',
            ];

            // $rows = $gsheet_api->get_rows($params2);
            // error_log("Google Sheet Rows : " . print_r($rows, true));
            // $gsheet_api->get_rows();

            // $gsheet_load_from = new \Tablesome\Workflow_Library\Actions\GSheet_Load_From();
            // $gsheet_load_from->do_action();
            // $datatable = new \Tablesome\Includes\Modules\Datatable\Datatable();
            // $datatable->reset_entire_table_data();
        }

        protected function setup_autoload()
        {
            require_once TABLESOME_PATH . '/vendor/autoload.php';

            // Load Action Scheduler for async background processing (emails, etc.)
            // Action Scheduler has built-in version resolution - only highest version loads
            $action_scheduler_path = TABLESOME_PATH . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
            if (file_exists($action_scheduler_path)) {
                require_once $action_scheduler_path;
            }
        }

        protected function load_library()
        {
            if (!class_exists("\Pauple\Pluginator\Library")) {
                wp_die("\"freemius/wordpress-sdk\" and \"Codestar Framework\" library was not installed, \"Tablesome\" is depend on it. Do run \"composer update\".");
            }

            $library = new \Pauple\Pluginator\Library();
            $library::register_libraries(['codestar', 'freemius']);

            //
            global $pluginator_security_agent;

            if (!isset($pluginator_security_agent) || !($pluginator_security_agent instanceof \Pauple\Pluginator\SecurityAgent)) {
                $pluginator_security_agent = new \Pauple\Pluginator\SecurityAgent();
            }

        }

        public function load_tablesome_functions()
        {
            require_once TABLESOME_PATH . 'includes/functions.php';
            require_once TABLESOME_PATH . 'includes/workflow-functions.php';
            require_once TABLESOME_PATH . 'includes/settings/getter.php';

            // require_once TABLESOME_PATH . 'includes/security-agent.php';
            // global $pluginator_security_agent;
            // $pluginator_security_agent = new \Tablesome\Includes\Security_Agent();
        }

        /*  Register Tablesome Post types and its Taxonomies */
        public function register_cpt_and_taxonomy()
        {
            $cpt = new \Tablesome\Includes\Cpt();
            $cpt->register();
        }

        public function test_myque()
        {
            // $myque = new \Tablesome\Includes\Modules\Myque\Myque_Exp();

            // $myque->create_table();

            // $myque->add_new_column();
            // $myque->save_column_value();

            // $myque->get_rows();
            // $myque->load_test(10);
            // $myque->doctrine_wrapper();
        }
        public function load_actions()
        {
            new \Tablesome\Includes\Actions();
        }

        public function load_filters()
        {
            new \Tablesome\Includes\Filters();
        }

        public function load_update_handler()
        {
            $upgrade = new \Tablesome\Includes\Update\Upgrade();
            $upgrade::init();
        }

        /*  Tablesome Shortcode */
        public function load_shortcodes()
        {
            new \Tablesome\Includes\Shortcodes();

            /** Init shortcode builder  */
            $builder = new \Tablesome\Includes\Shortcode_Builder\Builder();
            $builder->init();
        }

        /* Gutenberg Blocks */
        public function load_blocks()
        {
            $block = new \Tablesome\Includes\Blocks\Tablesome_Shortcode_Block();
            $block->init();
        }
    }
    new Tablesome();
}
