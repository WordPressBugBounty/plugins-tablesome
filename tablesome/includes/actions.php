<?php

namespace Tablesome\Includes;

use Tablesome\Includes\Modules\API_Credentials_Handler;
use Tablesome\Components\Table\Settings\Settings as TableLevelSettings;
use Tablesome\Includes\Settings\Tablesome_Getter;
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
// Exit if accessed directly
if ( !class_exists( '\\Tablesome\\Includes\\Actions' ) ) {
    class Actions {
        public $utils;

        public $workflow_library;

        public $workflow_manager_instance;

        public $cron;

        public function __construct() {
            $this->utils = new \Tablesome\Includes\Utils();
            /** plugin activation Hook */
            register_activation_hook( TABLESOME__FILE__, array($this, 'activation_hook_callback') );
            /** plugin deactivation Hook */
            register_deactivation_hook( TABLESOME__FILE__, array(new \Tablesome\Includes\Core\Deactivation(), 'init') );
            /*  Tablesome Init Hook */
            add_action( 'init', array($this, 'init_hook') );
            /**  Rest Endpoints */
            add_action( 'rest_api_init', array(new \Tablesome\Includes\Modules\TablesomeDB_Rest_Api\TablesomeDB_Rest_Api(), 'init') );
            /* Admin Enqueing Script Action hook */
            add_action( 'admin_enqueue_scripts', array($this, 'handle_admin_assets') );
            /*  Enqueing Script Action hook */
            add_action( 'wp_enqueue_scripts', array($this, 'handle_frontend_assets') );
            // Admin Dashboard Area
            /*  Tablesome Admin Section Initialization Hook */
            add_action( 'admin_menu', [$this, 'add_submenu'] );
            add_action( 'admin_menu', [new \Tablesome\Components\System_Info\Controller(), 'add_menu'], 11 );
            add_action( 'admin_menu', [$this, 'add_external_links_as_a_submenus'], 11 );
            add_action( 'admin_menu', [new \Tablesome\Includes\Modules\Workflow\Event_Log\Event_Log_List_Page(), 'add_menu'] );
            // TODO: Remove the get_tables_count_collection method when tablesomeDB release after
            // add_action('admin_init', [$this, 'get_tables_count_collection']);
            add_action( 'admin_init', array($this, 'admin_init_hook') );
            add_action( 'admin_init', [new \Tablesome\Includes\Modules\Review_Notification(), 'init'] );
            add_action( 'admin_init', [new \Tablesome\Includes\Modules\Feature_Notice(), 'init'] );
            add_action( 'init', array($this, 'init_automation') );
            add_action( "load-post-new.php", [$this, "redirect_to_add_new_table_custom_page"] );
            add_action( 'admin_enqueue_scripts', 'wp_enqueue_media' );
            add_action( 'wp_enqueue_scripts', 'wp_enqueue_media' );
            add_action( 'admin_bar_menu', [$this, 'modify_admin_bar'], 99 );
            //#1150: Exclude columns not working in elementor table shortcode builder
            add_action( "elementor/editor/before_enqueue_scripts", [$this, "enqueue_shortcode_builder_script"] );
            add_action( 'before_delete_post', function ( $postId ) {
                global $post;
                if ( isset( $post ) && $post->post_type != TABLESOME_CPT ) {
                    return;
                }
                // $table = new \Tablesome\Includes\Core\Table();
                // $table->delete_records_by_table_id($postId);
                $tablesome_db = new \Tablesome\Includes\Modules\TablesomeDB\TablesomeDB();
                $table = $tablesome_db->create_table_instance( $postId );
                $table->drop( $table );
            } );
            new \Tablesome\Includes\Settings\Settings();
            $this->cron = new \Tablesome\Includes\Cron();
            add_action( 'tablesome/send_data_to_amplitude', [$this->cron, 'run'] );
            add_action(
                'admin_action_duplicate_the_tablesome_table',
                [$this, 'duplicate_table'],
                10,
                1
            );
            add_action(
                'admin_action_empty_the_tablesome_table',
                [$this, 'empty_table'],
                10,
                1
            );
            add_action(
                'admin_action_create_new_email_logs_trigger_table',
                [$this, 'create_new_email_logs_trigger_table'],
                10,
                1
            );
            add_action(
                'admin_action_publish_table',
                [$this, 'publish_table'],
                10,
                1
            );
            add_action(
                'admin_action_redirect_to_table',
                [$this, 'redirect_to_table'],
                10,
                1
            );
            add_action( 'admin_footer', array($this, 'print_premium_modal_content') );
            add_action( 'admin_footer', array($this, 'print_js_content') );
            add_action( 'wp_footer', array($this, 'print_js_content') );
            add_action( 'wp_footer', array($this, 'append_table_css') );
            /* Main Settings Saved */
            $tablesome_csf_prefix = TABLESOME_OPTIONS;
            add_action(
                "csf_{$tablesome_csf_prefix}_saved",
                [$this, 'dispatch_save_settings_event'],
                10,
                1
            );
        }

        public function after_plan_change( $change, $current_plan ) {
            $event_params = array(
                'change'       => $change,
                'current_plan' => $current_plan,
            );
            $dispatcher = new \Tablesome\Includes\Tracking\Dispatcher_Mixpanel();
            $dispatcher->send_single_event( 'plan_change', $event_params );
        }

        public function dispatch_save_settings_event( $request ) {
            $event_params = $request;
            // error_log('dispatch_save_settings_event');
            // error_log(print_r($request, true));
            $dispatcher = new \Tablesome\Includes\Tracking\Dispatcher_Mixpanel();
            $dispatcher->send_single_event( 'save_settings', $event_params );
        }

        public function duplicate_table() {
            global $pluginator_security_agent;
            // Ref: https://rudrastyh.com/wordpress/duplicate-post.html
            $default_params = array(
                'post_type'   => TABLESOME_CPT,
                'link_action' => 'DUPLICATE',
            );
            if ( empty( $_GET['table_id'] ) ) {
                $default_params['status'] = 'MISSING_TABLE_ID';
                wp_safe_redirect( $pluginator_security_agent->add_query_arg( $default_params, admin_url( 'edit.php' ) ) );
                exit;
            }
            // Nonce verification
            if ( !isset( $_GET['tablesome_duplicate_nonce'] ) || !wp_verify_nonce( $_GET['tablesome_duplicate_nonce'], TABLESOME_PLUGIN_BASE ) ) {
                $default_params['status'] = 'SESSION_EXPIRED';
                wp_safe_redirect( $pluginator_security_agent->add_query_arg( $default_params, admin_url( 'edit.php' ) ) );
                exit;
            }
            // Get the table id from the URL
            $table_id = absint( $_GET['table_id'] );
            // Get the table data
            $post = get_post( $table_id );
            if ( !isset( $post ) || empty( $post ) ) {
                $default_params['status'] = 'INVALID_POST_ID';
                wp_safe_redirect( $pluginator_security_agent->add_query_arg( $default_params, admin_url( 'edit.php' ) ) );
                exit;
            }
            $duplicate_table_controller = new \Tablesome\Components\Table\Duplicate_Table();
            $new_table_id = $duplicate_table_controller->duplicate_table( $post );
            if ( empty( $new_table_id ) ) {
                $default_params['status'] = 'TABLE_NOT_DUPLICATE';
                wp_safe_redirect( $pluginator_security_agent->add_query_arg( $default_params, admin_url( 'edit.php' ) ) );
                exit;
            }
            $default_params['status'] = 'TABLE_DUPLICATED';
            wp_safe_redirect( $pluginator_security_agent->add_query_arg( $default_params, admin_url( 'edit.php' ) ) );
            exit;
        }

        public function empty_table() {
            global $pluginator_security_agent;
            $default_params = array(
                'post_type'   => TABLESOME_CPT,
                'link_action' => 'EMPTY_TABLE',
            );
            if ( empty( $_GET['table_id'] ) ) {
                $default_params['status'] = 'MISSING_TABLE_ID';
                wp_safe_redirect( $pluginator_security_agent->add_query_arg( $default_params, admin_url( 'edit.php' ) ) );
                exit;
            }
            // Nonce verification
            if ( !isset( $_GET['tablesome_empty_table_nonce'] ) || !wp_verify_nonce( $_GET['tablesome_empty_table_nonce'], TABLESOME_PLUGIN_BASE ) ) {
                $default_params['status'] = 'SESSION_EXPIRED';
                wp_safe_redirect( $pluginator_security_agent->add_query_arg( $default_params, admin_url( 'edit.php' ) ) );
                exit;
            }
            // Get the table id from the URL
            $table_id = absint( $_GET['table_id'] );
            // Get the table data
            $post = get_post( $table_id );
            if ( !isset( $post ) || empty( $post ) ) {
                $default_params['status'] = 'INVALID_POST_ID';
                wp_safe_redirect( $pluginator_security_agent->add_query_arg( $default_params, admin_url( 'edit.php' ) ) );
                exit;
            }
            $myque = new \Tablesome\Includes\Modules\Myque\Myque();
            $result = $myque->empty_the_table( $table_id );
            if ( is_wp_error( $result ) || !$result ) {
                $default_params['status'] = 'TABLE_NOT_EMPTIED';
                wp_safe_redirect( $pluginator_security_agent->add_query_arg( $default_params, admin_url( 'edit.php' ) ) );
                exit;
            }
            $default_params['status'] = 'TABLE_EMPTIED';
            wp_safe_redirect( $pluginator_security_agent->add_query_arg( $default_params, admin_url( 'edit.php' ) ) );
            exit;
        }

        public function modify_admin_bar( $wp_admin_bar ) {
            // Update Edit Table URL
            if ( get_post_type() == "tablesome_cpt" && $wp_admin_bar->get_node( 'edit' ) ) {
                $edit_node = $wp_admin_bar->get_node( 'edit' );
                $edit_node->href = admin_url() . 'edit.php?post_type=' . TABLESOME_CPT . '&action=edit&post=' . get_the_ID() . '&page=tablesome_admin_page';
                $wp_admin_bar->add_node( $edit_node );
            }
            // Remove Tablesome Settings from admin bar menu
            $wp_admin_bar->remove_node( 'tablesome-settings' );
        }

        public function activation_hook_callback() {
            $dispatcher = new \Tablesome\Includes\Tracking\Dispatcher_Mixpanel();
            $dispatcher->send_single_event( 'activate' );
            $table = new \Tablesome\Includes\Db\Tablesome_Table();
            $table->create();
            $onboarding = new \Tablesome\Includes\Modules\Onboarding();
            $onboarding->init();
            $tablesome_cpt = TABLESOME_CPT;
            $option_name = "{$tablesome_cpt}_registered_datetime";
            // Capture the datetime when plugin is activated first.
            $already_captured_plugin_registered_datetime = get_option( $option_name );
            if ( !$already_captured_plugin_registered_datetime ) {
                update_option( $option_name, date( 'Y-m-d H:i:s', time() ) );
            }
        }

        // Belows are callback functions of adding Actions order wise
        public function init_hook() {
            $this->setGlobalCurrentUserID();
            $this->cron->action( 'start' );
            /*  Tablesome Table-Actions Ajax Hooks */
            new \Tablesome\Includes\Ajax_Handler();
            if ( is_admin() ) {
                $tracking_notices = new \Tablesome\Includes\Tracking\Notices();
                $tracking_notices->can_show_notices();
            }
            tablesome_fs()->add_action(
                'after_license_change',
                [$this, 'after_plan_change'],
                10,
                2
            );
        }

        public function admin_init_hook() {
            // edit post url, to redirecting custom page
            add_filter(
                'get_edit_post_link',
                function ( $url, $post_id ) {
                    $current_screen = get_current_screen();
                    if ( isset( $current_screen ) && $current_screen->post_type == TABLESOME_CPT ) {
                        $url = admin_url( 'edit.php?post_type=' . TABLESOME_CPT . '&action=edit&post=' . $post_id . '&page=tablesome_admin_page' );
                    }
                    return $url;
                },
                10,
                2
            );
            /** Show the notices when perform the duplicate and empty the table */
            ( new \Tablesome\Components\Table\Quick_Actions() )->show_notices();
            $this->load_api_admin_notices_by_status( $this->get_api_data() );
        }

        public function handle_admin_assets() {
            // TODO: Bundle splitting needed for dashboard assets in order to below condition work
            // $current_screen = get_current_screen();
            // if (isset($current_screen) && $current_screen->post_type != TABLESOME_CPT) {
            //     return;
            // }
            $bundle_name = TABLESOME_DOMAIN . '-admin-bundle';
            // Enqueue admin scripts
            $this->register_admin_assets( $bundle_name );
            $this->enqueue_admin_assets( $bundle_name );
            $this->localize_admin_assets( $bundle_name );
            // Load Freemius Styles
            $should_load_freemius_styles = function_exists( 'fs_asset_url' ) && !wp_style_is( 'fs_common' );
            if ( $should_load_freemius_styles ) {
                wp_enqueue_style(
                    'fs_common',
                    fs_asset_url( WP_FS__DIR_CSS . '/' . trim( '/admin/common.css', '/' ) ),
                    [],
                    false,
                    'all'
                );
            }
            // Load Typography Assets
            $typography = new \Tablesome\Includes\Settings\Typography();
            $typography->enqueue( $bundle_name );
            $typography::add_typography( "Roboto" );
            // Load Common Assets
            $this->run_common_script( $bundle_name, 0, 'tablesome-edit-cpt' );
            $this->enqueue_shortcode_builder_script();
        }

        public function should_load_frontend_assets( $location ) {
            $handle = 'quilljs';
            $list = 'enqueued';
            $is_script_enqueued_already = wp_script_is( $handle, $list );
            //     error_log(' is_script_enqueued_already: ' . $is_script_enqueued_already);
            // if ($is_script_enqueued_already) {
            //     return false;
            // }
            if ( is_singular( array(TABLESOME_CPT) ) || $location == 'tablesome_shortcode' ) {
                return true;
            }
            return false;
        }

        public function handle_frontend_assets( $table_id = 0, $location = '' ) {
            $should_load_frontend_assets = $this->should_load_frontend_assets( $location );
            $bundle_name = TABLESOME_DOMAIN . '-bundle';
            $workflow_bundle_name = TABLESOME_DOMAIN . '-workflow-bundle';
            $this->register_and_enqueue_workflow_scripts();
            $this->tablesome_ajax_object_localize_script( $workflow_bundle_name );
            if ( !$should_load_frontend_assets ) {
                return false;
            }
            if ( $table_id == 0 ) {
                $table_id = get_the_ID();
            }
            $this->register_frontend_assets( $bundle_name );
            $this->enqueue_frontend_assets( $bundle_name );
            $this->run_common_script( $bundle_name, $table_id, 'frontend' );
        }

        // Depend on admin or frontend enqueue_scripts
        protected function run_common_script( $bundle_name, $table_id = 0, $location = '' ) {
            $this->localize_common_script( $bundle_name );
            $this->localize_tablesome_settings( $bundle_name, $table_id );
            $this->register_common_assets();
            $this->enqueue_common_assets( $table_id, $location );
            $this->enqueue_sheetjs( $table_id, $location );
        }

        public function register_frontend_assets( $bundle_name ) {
            wp_register_style(
                $bundle_name,
                TABLESOME_URL . 'assets/bundles/public.bundle.css',
                [],
                TABLESOME_VERSION,
                'all'
            );
            wp_register_script(
                $bundle_name,
                TABLESOME_URL . 'assets/bundles/public.bundle.js',
                ['jquery'],
                TABLESOME_VERSION,
                false
            );
        }

        private function register_and_enqueue_workflow_scripts() {
            wp_register_script(
                TABLESOME_DOMAIN . '-workflow-bundle',
                TABLESOME_URL . 'assets/bundles/workflow.bundle.js',
                ['jquery'],
                TABLESOME_VERSION,
                false
            );
            wp_enqueue_script( TABLESOME_DOMAIN . '-workflow-bundle' );
        }

        public function enqueue_frontend_assets( $bundle_name ) {
            wp_enqueue_style( $bundle_name );
            wp_enqueue_script( $bundle_name );
        }

        public function localize_admin_assets( $bundle_name ) {
            $tablesome_localize_data = [
                "config" => TableLevelSettings::get_config(),
            ];
            wp_localize_script( $bundle_name, 'tablesome_api_data', $this->get_api_data() );
            wp_localize_script( $bundle_name, 'tablesome', $tablesome_localize_data );
            wp_enqueue_script(
                'google-picker-api',
                'https://apis.google.com/js/api.js?onload=onApiLoad',
                array(),
                null,
                true
            );
            wp_enqueue_script(
                'google-picker-api',
                'https://accounts.google.com/gsi/client?onload=onApiLoad',
                array(),
                null,
                true
            );
        }

        public function register_admin_assets( $bundle_name ) {
            wp_register_style(
                $bundle_name,
                TABLESOME_URL . 'assets/bundles/admin.bundle.css',
                [],
                TABLESOME_VERSION,
                'all'
            );
            wp_register_script(
                $bundle_name,
                TABLESOME_URL . 'assets/bundles/admin.bundle.js',
                ['jquery'],
                TABLESOME_VERSION,
                false
            );
            wp_register_style( 'material-icons', 'https://fonts.googleapis.com/icon?family=Material+Icons' );
        }

        public function enqueue_admin_assets( $bundle_name ) {
            wp_enqueue_style( $bundle_name );
            wp_enqueue_script( $bundle_name );
            wp_enqueue_style( 'material-icons' );
        }

        public function register_common_assets() {
            wp_register_style( 'quill-css', TABLESOME_URL . 'includes/lib/js/quilljs/quill.snow.css' );
            // wp_enqueue_style('quill-css');
            // Quill JS
            wp_register_script(
                'quilljs',
                TABLESOME_URL . 'includes/lib/js/quilljs/quill.min.js',
                [],
                TABLESOME_VERSION,
                true
            );
            // wp_enqueue_script('quilljs');
            // Sheet.JS
            wp_register_script(
                'sheetjs',
                TABLESOME_URL . 'includes/lib/js/sheetjs/xlsx.full.min.js',
                [],
                TABLESOME_VERSION,
                true
            );
            // wp_enqueue_script('sheetjs');
            wp_register_script(
                'svelte-dnd-action',
                TABLESOME_URL . 'includes/lib/js/svelte-dnd-action/svelte-dnd-action.min.js',
                [],
                TABLESOME_VERSION,
                true
            );
            // wp_enqueue_script('svelte-dnd-action');
            // wp_register_script('quilljs', TABLESOME_URL . 'includes/lib/js/quilljs/quill.min.js', [], TABLESOME_VERSION, true);
            // wp_register_script('sheetjs', TABLESOME_URL . 'includes/lib/js/sheetjs/xlsx.full.min.js', [], TABLESOME_VERSION, true);
            // wp_register_script('svelte-dnd-action', TABLESOME_URL . 'includes/lib/js/svelte-dnd-action/svelte-dnd-action.min.js', [], TABLESOME_VERSION, true);
        }

        public function enqueue_common_assets( $table_id, $location ) {
            wp_enqueue_script( 'svelte-dnd-action' );
            wp_enqueue_style( 'quill-css' );
            wp_enqueue_style( 'dashicons' );
            wp_enqueue_script( 'quilljs' );
            // $should_load_sheetjs = $this->should_load_sheetjs($table_id, $location);
            // wp_enqueue_script('sheetjs');
        }

        public function enqueue_sheetjs( $table_id, $location = '' ) {
            $tablesome_settings = $this->get_tablesome_settings_to_localize( $table_id );
            $desktop_export = $this->utils->get_bool( $tablesome_settings['display']['desktop-export'] );
            $mobile_export = $this->utils->get_bool( $tablesome_settings['display']['mobile-export'] );
            $is_export_enabled = $desktop_export || $mobile_export;
            $should_load_sheetjs = $location == 'tablesome-edit-cpt' || $is_export_enabled;
            // error_log('$table_id : ' . $table_id);
            // error_log('$should_load_sheetjs : ' . $should_load_sheetjs);
            if ( $should_load_sheetjs ) {
                // error_log('$tablesome_settings : ' . print_r($tablesome_settings, true));
                wp_enqueue_script( 'sheetjs' );
            }
        }

        public function get_api_data() {
            $api_credentials_handler = new API_Credentials_Handler();
            return array(
                'mailchimp_api_key'            => get_option( 'tablesome_mailchimp_api_key' ),
                'mailchimp_api_status'         => get_option( 'tablesome_mailchimp_api_status' ),
                'mailchimp_api_status_message' => get_option( 'tablesome_mailchimp_api_status_message' ),
                'notion_api_key'               => get_option( 'tablesome_notion_api_key' ),
                'notion_api_status'            => get_option( 'tablesome_notion_api_status' ),
                'notion_api_status_message'    => get_option( 'tablesome_notion_api_status_message' ),
                'api_credentials'              => $api_credentials_handler->get_all_api_credentials(),
            );
        }

        public function enqueue_shortcode_builder_script() {
            $bundle_name = TABLESOME_DOMAIN . '-shortcode-builder-bundle';
            wp_enqueue_script(
                $bundle_name,
                TABLESOME_URL . 'assets/bundles/shortcodebuilder.bundle.js',
                ['jquery'],
                TABLESOME_VERSION,
                false
            );
            $this->tablesome_ajax_object_localize_script( $bundle_name );
        }

        public function add_submenu() {
            $params = $this->get_params_from_url();
            $edit_table_title = __( "Edit Table", "tablesome" );
            $create_new_table_title = __( "Create New Table", "tablesome" );
            $page_title = ( $params['post_action'] == 'edit' ? $edit_table_title : $create_new_table_title );
            $submenu_pages = [
                [
                    'name'     => 'tablesome_admin_page',
                    'title'    => $page_title,
                    'menu'     => $create_new_table_title,
                    'callback' => [
                        'controller' => $this,
                        'method'     => 'get_add_template_view',
                    ],
                ],
                [
                    'name'     => 'tablesome-import',
                    'title'    => __( "Import a Table", "tablesome" ),
                    'menu'     => __( "Import a Table", "tablesome" ),
                    'callback' => [
                        'controller' => new \Tablesome\Components\Import\Controller(),
                        'method'     => 'render',
                    ],
                ],
                [
                    'name'     => 'tablesome-export',
                    'title'    => __( "Export a Table", "tablesome" ),
                    'menu'     => __( "Export a Table", "tablesome" ),
                    'callback' => [
                        'controller' => new \Tablesome\Components\Export(),
                        'method'     => 'render',
                    ],
                ],
                [
                    'name'     => 'tablesome-onboarding',
                    'title'    => __( "Getting Started", "tablesome" ),
                    'menu'     => __( "Getting Started", "tablesome" ),
                    'callback' => [
                        'controller' => new \Tablesome\Includes\Pages\Onboarding(),
                        'method'     => 'render',
                    ],
                ]
            ];
            foreach ( $submenu_pages as $submenu_page ) {
                $this->add_submenu_page( $submenu_page );
            }
        }

        public function add_submenu_page( $submenu_page ) {
            add_submenu_page(
                'edit.php?post_type=' . TABLESOME_CPT,
                /* main menu slug */
                $submenu_page["title"],
                /* page title */
                $submenu_page["menu"],
                /* page submenu title */
                'manage_categories',
                /* page roles and capability needed*/
                $submenu_page["name"],
                /* page name */
                array($submenu_page["callback"]["controller"], $submenu_page["callback"]["method"])
            );
        }

        public function add_external_links_as_a_submenus() {
            $docs = __( "Documentation", "tablesome" );
            $liked = __( "Liked Tablesome?", "tablesome" );
            $beta_link = __( "Try Latest (Beta)", "tablesome" );
            $menus = array(array(
                'page_title' => $beta_link,
                'menu_title' => $beta_link,
                'capability' => 'manage_options',
                'menu_slug'  => 'tablesome-test-beta-page',
                'callback'   => array($this, 'handle_external_links'),
            ), array(
                'page_title' => $docs,
                'menu_title' => $docs,
                'capability' => 'manage_categories',
                'menu_slug'  => 'tablesome-docs-page',
                'callback'   => array($this, 'handle_external_links'),
            ), array(
                'page_title' => $liked,
                'menu_title' => '<span class="dashicons dashicons-heart" style="color: #ff0077;"></span> ' . $liked,
                'capability' => 'manage_options',
                'menu_slug'  => 'tablesome-liked-page',
                'callback'   => array($this, 'handle_external_links'),
            ));
            $parent_slug = 'edit.php?post_type=' . TABLESOME_CPT;
            foreach ( $menus as $menu ) {
                add_submenu_page(
                    $parent_slug,
                    $menu['page_title'],
                    $menu['menu_title'],
                    $menu['capability'],
                    $menu['menu_slug'],
                    $menu['callback']
                );
            }
        }

        public function handle_external_links() {
            $page = ( isset( $_GET['page'] ) ? tablesome_wp_kses( $_GET['page'] ) : '' );
            if ( empty( $page ) ) {
                return;
            }
            return;
        }

        public function redirect_to_add_new_table_custom_page() {
            if ( isset( $_GET["post_type"] ) && $_GET["post_type"] == TABLESOME_CPT ) {
                wp_redirect( 'edit.php?post_type=' . TABLESOME_CPT . '&page=tablesome_admin_page' );
            }
        }

        public function get_add_template_view() {
            $defaults = array(
                'table_mode'     => 'editor',
                'pagination'     => true,
                'last_record_id' => 0,
            );
            $params = array_merge( $defaults, $this->get_params_from_url() );
            $dashboard_cpt_page = new \Tablesome\Includes\Dashboard\CPT_Page();
            $html = '<div class="tablesome-wrap wrap">';
            $html .= $dashboard_cpt_page->get_view( $params );
            $html .= '</div>';
            $allowed_html = tablesome_allowed_html();
            echo wp_kses( $html, $allowed_html );
        }

        public function get_params_from_url() {
            // error_log('get_params_from_url $_GET');
            // error_log(print_r($_GET, true));
            $post_id = 0;
            if ( isset( $_GET['post'] ) && is_array( $_GET['post'] ) ) {
                $post_id = [];
                foreach ( $_GET['post'] as $key => $value ) {
                    $post_id[$key] = ( tablesome_wp_kses( $value ) ? tablesome_wp_kses( $value ) : 0 );
                }
            } else {
                $post_id = ( isset( $_GET['post'] ) ? tablesome_wp_kses( $_GET['post'] ) : 0 );
            }
            // $post_id = isset($_GET['post']) ? tablesome_wp_kses($_GET['post']) : 0;
            $post_action = ( empty( $post_id ) ? 'add' : 'edit' );
            // error_log('get_params_from_url post_id: ' . $post_id);
            return [
                'post_id'     => $post_id,
                'post_action' => $post_action,
            ];
        }

        public function print_premium_modal_content( $args = array() ) {
            if ( isset( $_GET['post_type'] ) && $_GET['post_type'] == TABLESOME_CPT ) {
                $html = '<div id="tablesome__modal--premium-notice" class="tablesome__modal">';
                $html .= '<div class="tablesome__modal__content">';
                $html .= '<span class="tablesome__modal--close">&times;</span>';
                $html .= '<h1>Start Free Trial</h1>';
                $html .= '<p>Start free trial to access the premium features.</p>';
                $html .= '<a class="tablesome button-primary" href="' . tablesome_fs()->get_trial_url() . '">Start Free Trial</a>';
                $html .= '</div>';
                $html .= '</div>';
                echo wp_kses_post( $html );
            }
        }

        public function append_table_css() {
            global $tablesome_tables_collection;
            if ( empty( $tablesome_tables_collection ) ) {
                return;
            }
            $tables_css = "";
            foreach ( $tablesome_tables_collection as $table_props ) {
                $table_id = $table_props["collection"]["table_id"];
                $table_style_meta = $table_props["collection"]["style"];
                $tables_css .= " " . TableLevelSettings::get_table_css( $table_id, $table_style_meta );
            }
            // echo '<style><img title="</style><img src onerror=alert("Hello")>"></style>';
            if ( !empty( $tables_css ) ) {
                echo '<style type="text/css">' . wp_strip_all_tags( $tables_css ) . '</style>';
            }
        }

        public function print_js_content() {
            $this->print_tables_collection();
            $this->print_triggers_actions_collection();
            if ( is_admin() ) {
                $this->print_all_tables_collection();
            }
        }

        public function print_triggers_actions_collection() {
            $enqueue_data = $this->get_enqueue_data();
            // error_log('enqueue_data: ' . print_r($enqueue_data, true));
            // $script = "<script>";
            // $script .= "window.tablesomeTriggers = " . json_encode($enqueue_data) . ";";
            // $script .= "</script>";
            ?>
            <script>
                window.tablesomeTriggers = <?php 
            echo wp_json_encode( $enqueue_data );
            ?> ;
            </script>

            <?php 
            // echo $script;
        }

        public function get_enqueue_data() {
            $post_type = ( isset( $_GET['post_type'] ) ? tablesome_wp_kses( $_GET['post_type'] ) : '' );
            $page = ( isset( $_GET['page'] ) ? tablesome_wp_kses( $_GET['page'] ) : '' );
            $table_id = ( isset( $_GET['post'] ) ? tablesome_wp_kses( $_GET['post'] ) : 0 );
            if ( !is_admin() ) {
                $table_id = get_the_ID();
            }
            // error_log('table_id: ' . $table_id);
            $enqueue_data = array();
            $enqueue_data['triggers'] = get_tablesome_table_triggers( $table_id );
            if ( $post_type != TABLESOME_CPT || $page != 'tablesome_admin_page' ) {
                return $enqueue_data;
            }
            $api_data = $this->get_api_data();
            $enqueue_data['availableTriggers'] = $this->workflow_library->get_triggers_config();
            $enqueue_data['availableActions'] = $this->workflow_library->get_actions_config();
            // $enqueue_data['smartFields'] = get_default_tablesome_smart_fields();
            $enqueue_data['mailchimpCollection'] = $this->get_mailchimp_collection( $api_data );
            $enqueue_data['notionCollection'] = $this->get_notion_collection( $api_data );
            return $enqueue_data;
        }

        public function get_notion_collection( $api_data ) {
            $notion_message = $api_data['notion_api_status_message'];
            $notion_api_not_configured = empty( $api_data['notion_api_status'] ) && empty( $notion_message );
            if ( $notion_api_not_configured ) {
                $notion_message = 'Please configure Notion API in Tablesome for this action to work.';
            }
            return array(
                'status'       => $api_data['notion_api_status'],
                'message'      => $notion_message,
                'redirect_url' => admin_url( 'edit.php?post_type=' . TABLESOME_CPT . '&page=tablesome-settings#tab=integrations/notion' ),
            );
        }

        public function get_mailchimp_collection( $api_data ) {
            $mailchimp_message = $api_data['mailchimp_api_status_message'];
            $mailchimp_api_not_configured = empty( $api_data['mailchimp_api_status'] ) && empty( $mailchimp_message );
            if ( $mailchimp_api_not_configured ) {
                $mailchimp_message = 'Please configure Mailchimp API in Tablesome for this action to work';
            }
            return array(
                'status'       => $api_data['mailchimp_api_status'],
                'message'      => $mailchimp_message,
                'redirect_url' => admin_url( 'edit.php?post_type=' . TABLESOME_CPT . '&page=tablesome-settings#tab=integrations/mailchimp' ),
            );
        }

        public function print_tables_collection() {
            global $tablesome_tables_collection;
            if ( empty( $tablesome_tables_collection ) ) {
                return;
            }
            // $script = "<script type='text/javascript'>";
            // $script .= "window.tablesomeTables = " . tablesome_json_encode($tablesome_tables_collection) . ";";
            // $script .= "</script>";
            // echo $script;
            ?>
            <script type='text/javascript'>
                window.tablesomeTables = <?php 
            echo wp_json_encode( $tablesome_tables_collection );
            ?> ;
            </script>
    <?php 
        }

        public function print_all_tables_collection() {
            $post_type = tablesome_get_method( 'post_type' );
            $page = tablesome_get_method( 'page' );
            if ( $post_type != TABLESOME_CPT && $page != 'tablesome-export' ) {
                return;
            }
            $get_all_tables = get_posts( array(
                'post_type'      => TABLESOME_CPT,
                'orderby'        => 'ID',
                'post_status'    => 'publish',
                'order'          => 'DESC',
                'posts_per_page' => -1,
            ) );
            $tables = array();
            foreach ( $get_all_tables as $table ) {
                array_push( $tables, array(
                    'id'    => $table->ID,
                    'title' => esc_html( $table->post_title ),
                ) );
            }
            $script = "<script>";
            $script .= "window.tablesomeAllTables = " . wp_json_encode( $tables ) . ";";
            $script .= "</script>";
            echo $script;
        }

        public function localize_tablesome_settings( $bundle_name, $table_id = 0 ) {
            $tablesome_settings = $this->get_tablesome_settings_to_localize( $table_id );
            wp_localize_script( $bundle_name, 'tablesome_settings', $tablesome_settings );
        }

        public function localize_common_script( $bundle_name ) {
            $this->tablesome_ajax_object_localize_script( $bundle_name );
            // $tablesome_settings = $this->get_tablesome_settings_to_localize();
            $translations = new \Tablesome\Includes\Translations();
            $translation_strings = $translations->get_strings();
            $tablesome_fs = array(
                "plan"      => ( tablesome_fs()->can_use_premium_code__premium_only() ? 'premium' : 'free' ),
                "trial_url" => tablesome_fs()->get_trial_url(),
            );
            // wp_localize_script($bundle_name, 'tablesome_settings', $tablesome_settings);
            wp_localize_script( $bundle_name, 'translation_strings', $translation_strings );
            wp_localize_script( $bundle_name, 'tablesome_fs', $tablesome_fs );
        }

        public function get_tablesome_settings_to_localize( $table_id = 0 ) {
            $helpers = new \Tablesome\Includes\Helpers();
            $date_format = $helpers->get_date_fns_js_compatible_with_wp( get_option( "date_format" ) );
            $table = new \Tablesome\Components\Table\Controller();
            $table_level_settings = $table->get_table_level_settings( $table_id );
            $post_type = tablesome_get_method( 'post_type' );
            $current_page = tablesome_get_method( 'page' );
            $is_tablesome_onboarding_page = $post_type == 'tablesome_cpt' && $current_page == 'tablesome-onboarding';
            $tablesome_settings = [
                'rowLimit'                 => TABLESOME_MAX_RECORDS_TO_READ,
                'columnLimit'              => TABLESOME_MAX_COLUMNS_TO_READ,
                'customStyle'              => Tablesome_Getter::get( 'style_disable' ),
                'date_format'              => $date_format,
                'add_new_link'             => admin_url( 'edit.php?post_type=' . TABLESOME_CPT . '&page=tablesome_admin_page' ),
                'imageDirectory'           => TABLESOME_URL . 'assets/images/',
                'adminImageDirectory'      => TABLESOME_URL . 'assets/admin/images/',
                'storeAllForms'            => Tablesome_Getter::get( 'enabled_all_forms_entries' ),
                'storeAllFormsSettingsURL' => admin_url( 'edit.php?post_type=' . TABLESOME_CPT . '&page=tablesome-settings#tab=forms' ),
                'editorState'              => $table_level_settings["editorState"],
                'display'                  => $table_level_settings["display"],
                'style'                    => $table_level_settings["style"],
                'access_control'           => $table_level_settings["access_control"],
                'site_url'                 => site_url(),
                'date_timezone'            => Tablesome_Getter::get( 'date_timezone' ),
                'wp_site_search'           => Tablesome_Getter::get( 'wp_site_search' ),
            ];
            if ( $is_tablesome_onboarding_page ) {
                $tablesome_settings['email_logs_trigger_table_info'] = $this->get_email_logs_trigger_table_info();
            }
            return $tablesome_settings;
        }

        // public function register_common_assets()
        // {
        //     wp_register_style('quill-css', TABLESOME_URL . 'includes/lib/js/quilljs/quill.snow.css');
        //     wp_register_script('quilljs', TABLESOME_URL . 'includes/lib/js/quilljs/quill.min.js', [], TABLESOME_VERSION, true);
        //     wp_register_script('sheetjs', TABLESOME_URL . 'includes/lib/js/sheetjs/xlsx.full.min.js', [], TABLESOME_VERSION, true);
        //     wp_register_script('svelte-dnd-action', TABLESOME_URL . 'includes/lib/js/svelte-dnd-action/svelte-dnd-action.min.js', [], TABLESOME_VERSION, true);
        // }
        // public function enqueue_common_assets()
        // {
        //     wp_enqueue_style('quill-css');
        //     wp_enqueue_script('quilljs');
        //     wp_enqueue_script('sheetjs');
        //     wp_enqueue_script('svelte-dnd-action');
        //     wp_enqueue_style('dashicons');
        // }
        public function tablesome_ajax_object_localize_script( $bundle_name ) {
            $tablesome_ajax_object = $this->get_tablesome_ajax_object();
            wp_localize_script( $bundle_name, 'tablesome_ajax_object', $tablesome_ajax_object );
        }

        public function get_tablesome_ajax_object() {
            $url = get_rest_url( null, 'tablesome/v1/workflow/posts' );
            $tablesome_ajax_object = array(
                'nonce'          => wp_create_nonce( 'tablesome_nonce' ),
                'ajax_url'       => admin_url( 'admin-ajax.php' ),
                'rest_nonce'     => wp_create_nonce( 'wp_rest' ),
                'edit_table_url' => admin_url( 'edit.php?post_type=' . TABLESOME_CPT . '&action=edit&post=0&page=tablesome_admin_page' ),
                'api_endpoints'  => array(
                    'prefix'               => get_rest_url( null, 'tablesome/v1/tables/' ),
                    'save_table'           => get_rest_url( null, 'tablesome/v1/tables' ),
                    'import_records'       => get_rest_url( null, 'tablesome/v1/tables/import' ),
                    'store_api_key'        => get_rest_url( null, 'tablesome/v1/tablesome-api-keys/' ),
                    'workflow_posts_data'  => $url,
                    'workflow_posts'       => get_rest_url( null, 'tablesome/v1/workflow/posts?' ),
                    'workflow_fields'      => get_rest_url( null, 'tablesome/v1/workflow/fields?' ),
                    'workflow_terms'       => get_rest_url( null, 'tablesome/v1/workflow/terms?' ),
                    'workflow_taxonomies'  => get_rest_url( null, 'tablesome/v1/workflow/taxonomies?' ),
                    'workflow_user_roles'  => get_rest_url( null, 'tablesome/v1/workflow/get-user-roles?' ),
                    'workflow_post_types'  => get_rest_url( null, 'tablesome/v1/workflow/get-post-types?' ),
                    'workflow_users'       => get_rest_url( null, 'tablesome/v1/workflow/get-users?' ),
                    'get_oauth_data'       => get_rest_url( null, 'tablesome/v1/workflow/get-oauth-data?' ),
                    'delete_oauth_data'    => get_rest_url( null, 'tablesome/v1/workflow/delete-oauth-data?' ),
                    'get_access_token'     => get_rest_url( null, 'tablesome/v1/workflow/get-access-token' ),
                    'get-spreadsheet-data' => get_rest_url( null, 'tablesome/v1/workflow/get-spreadsheet-data' ),
                ),
                "site_domain"    => $_SERVER['SERVER_NAME'],
            );
            return $tablesome_ajax_object;
        }

        public function get_tables_count_collection() {
            global $pagenow;
            global $tablesome_tables_count_collection;
            $tablesome_tables_count_collection = array();
            return;
            $post_type = tablesome_get_method( 'post_type' );
            $page = tablesome_get_method( 'page' );
            /** The Current page is must be a tablesome tables summary page. otherwise, it's a return. */
            $is_tablesome_tables_list_page = isset( $pagenow ) && $pagenow == 'edit.php' && $post_type == 'tablesome_cpt' && empty( $page );
            if ( !$is_tablesome_tables_list_page ) {
                return;
            }
            $crud = new \Tablesome\Includes\Db\CRUD();
            /**
             * Tablesome tables count collection
             */
            $collections = $crud->get_tables_count_collection_by_query();
            $data = array();
            if ( empty( $collections ) ) {
                return;
            }
            foreach ( $collections as $collection ) {
                $table_id = $collection['post_id'];
                $data[$table_id] = $collection['records_count'];
            }
            $tablesome_tables_count_collection = $data;
        }

        public function init_automation() {
            // Initiate Workflow Manager
            $this->workflow_manager_instance = tablesome_workflow_manager();
            // Needs tablesome_fs() - Freemius to be loaded
            $this->workflow_library = get_tablesome_workflow_library();
        }

        public function load_api_admin_notices_by_status( $api_data ) {
            error_log( 'load_api_admin_notices_by_status api_data:' );
            error_log( print_r( $api_data, true ) );
            return;
            $status = ( $api_data['mailchimp_api_status'] == true ? true : false );
            $message = $api_data['mailchimp_api_status_message'];
            $api_not_configured = !$status && empty( $message );
            $page_from_url = tablesome_get_method( 'page' );
            $is_settings_page = $page_from_url == 'tablesome-settings';
            if ( $status == true || $api_not_configured || $is_settings_page ) {
                return;
            }
            $url = admin_url( 'edit.php?post_type=' . TABLESOME_CPT . '&page=tablesome-settings#tab=integrations/mailchimp' );
            $content = '';
            $content .= '<h3>' . __( 'Mailchimp API key validation has failed.', 'tablesome' ) . '</h3>';
            $content .= '<p>' . __( ' Response from Mailchimp', 'tablesome' ) . ' "' . $message . '"</p>';
            $content .= '<p><a href="' . $url . '">' . __( 'Click here', 'tablesome' ) . '</a> ' . __( 'to configure the Mailchimp API settings in Tablesome.', 'tablesome' ) . '</p>';
            $html = '<div class="helpie-notice notice notice-error is-dismissible">';
            $html .= $content;
            $html .= '</div>';
            add_action( 'admin_notices', function () use($html) {
                echo wp_kses_post( $html );
            } );
        }

        private function setGlobalCurrentUserID() {
            global $globalCurrentUserID;
            $currentUserID = get_current_user_id();
            if ( is_null( $globalCurrentUserID ) && !is_null( $currentUserID ) && !empty( $currentUserID ) ) {
                $globalCurrentUserID = $currentUserID;
            }
        }

        public function get_email_logs_trigger_table_info() {
            $tables = get_posts( array(
                'post_type'      => TABLESOME_CPT,
                'orderby'        => 'ID',
                'post_status'    => 'publish, draft',
                'order'          => 'DESC',
                'posts_per_page' => -1,
            ) );
            $email_logs_trigger_publish_table_id = 0;
            $email_logs_trigger_draft_table_id = 0;
            foreach ( $tables as $table ) {
                $status = $table->post_status;
                $triggersmeta = get_tablesome_table_triggers( $table->ID );
                if ( empty( $triggersmeta ) ) {
                    continue;
                }
                foreach ( $triggersmeta as $triggermeta ) {
                    $integration = ( isset( $triggermeta['integration'] ) ? $triggermeta['integration'] : '' );
                    $trigger_id = ( isset( $triggermeta['trigger_id'] ) ? $triggermeta['trigger_id'] : 0 );
                    $is_email_logs_trigger_action = $integration == 'email' && $trigger_id == 8;
                    // select first draft table ID
                    if ( $is_email_logs_trigger_action && $status == 'draft' && empty( $email_logs_trigger_draft_table_id ) ) {
                        $email_logs_trigger_draft_table_id = $table->ID;
                    }
                    // select first published table ID
                    if ( $is_email_logs_trigger_action && $status == 'publish' && empty( $email_logs_trigger_publish_table_id ) ) {
                        $email_logs_trigger_publish_table_id = $table->ID;
                    }
                }
            }
            if ( $email_logs_trigger_publish_table_id ) {
                // action URL for seeing email logs table
                $url = wp_nonce_url( add_query_arg( array(
                    'action'   => 'redirect_to_table',
                    'table_id' => $email_logs_trigger_publish_table_id,
                ), 'admin.php' ), TABLESOME_PLUGIN_BASE, 'tablesome_redirect_to_table_nonce' );
                $url = str_replace( '&amp;', '&', $url );
                return [
                    'url'    => $url,
                    'status' => 'table_exists',
                    'label'  => __( 'See Logs', 'tablesome' ),
                ];
            } else {
                if ( $email_logs_trigger_draft_table_id ) {
                    $url = wp_nonce_url( add_query_arg( array(
                        'action'         => 'publish_table',
                        'draft_table_id' => $email_logs_trigger_draft_table_id,
                    ), 'admin.php' ), TABLESOME_PLUGIN_BASE, 'tablesome_publish_table_nonce' );
                    $url = str_replace( '&amp;', '&', $url );
                    return [
                        'url'    => $url,
                        'status' => 'draft_table_exists',
                        'label'  => __( 'Publish Table', 'tablesome' ),
                    ];
                } else {
                    // action URL for create new email logs trigger table
                    $url = wp_nonce_url( add_query_arg( array(
                        'action' => 'create_new_email_logs_trigger_table',
                    ), 'admin.php' ), TABLESOME_PLUGIN_BASE, 'tablesome_email_logs_trigger_table_nonce' );
                    $url = str_replace( '&amp;', '&', $url );
                    return [
                        'url'    => $url,
                        'status' => 'create_new_table',
                        'label'  => __( 'Enable Email Logs', 'tablesome' ),
                    ];
                }
            }
        }

        public function create_new_email_logs_trigger_table() {
            global $pluginator_security_agent;
            if ( !current_user_can( 'manage_options' ) ) {
                return;
            }
            if ( !isset( $_GET['tablesome_email_logs_trigger_table_nonce'] ) || !wp_verify_nonce( $_GET['tablesome_email_logs_trigger_table_nonce'], TABLESOME_PLUGIN_BASE ) ) {
                return;
            }
            tablesome_track_event( 'enable_email_logs', 'onboarding_page' );
            $table_id = ( new \Tablesome\Includes\Modules\Onboarding() )->create_email_logs_table( 'publish' );
            if ( $table_id ) {
                $onboarding_page_url = $this->get_onboarding_url( 'CREATED_EMAIL_LOGS_TRIGGER_TABLE' );
            } else {
                // redirect to create new table page
                $onboarding_page_url = $this->get_onboarding_url( 'FAILED_TO_CREATE_EMAIL_LOGS_TRIGGER_TABLE' );
            }
            wp_redirect( $onboarding_page_url );
            exit;
        }

        public function get_onboarding_url( $status ) {
            global $pluginator_security_agent;
            $onboarding_page_url = admin_url( 'edit.php?post_type=' . TABLESOME_CPT . '&page=tablesome-onboarding' );
            $onboarding_page_url = $pluginator_security_agent->add_query_arg( array(
                'link_action' => 'EMAIL_LOGS',
            ), $onboarding_page_url );
            $onboarding_page_url = $pluginator_security_agent->add_query_arg( array(
                'status' => $status,
            ), $onboarding_page_url );
            return $onboarding_page_url;
        }

        public function publish_table() {
            global $pluginator_security_agent;
            // check_admin_referer('tablesome_publish_table');
            // Nonce verification
            if ( !isset( $_GET['tablesome_publish_table'] ) || !wp_verify_nonce( $_GET['tablesome_publish_table'], TABLESOME_PLUGIN_BASE ) ) {
                $default_params['status'] = 'SESSION_EXPIRED';
                wp_safe_redirect( $pluginator_security_agent->add_query_arg( $default_params, admin_url( 'edit.php' ) ) );
                exit;
            }
            // $draft_table_id = isset($_GET['draft_table_id']) ? $_GET['draft_table_id'] : 0;
            $draft_table_id = tablesome_get_method( 'draft_table_id', 0 );
            // error_log('$draft_table_id : ' . $draft_table_id);
            if ( !current_user_can( 'manage_options' ) || empty( $draft_table_id ) ) {
                return;
            }
            // publish table
            $table = get_post( $draft_table_id );
            if ( empty( $table ) ) {
                // add error message
                $onboarding_page_url = $this->get_onboarding_url( 'INVALID_POST_ID' );
                wp_redirect( $onboarding_page_url );
                return;
            }
            $table->post_status = 'publish';
            wp_update_post( $table );
            $onboarding_page_url = $this->get_onboarding_url( 'PUBLISHED_TABLE' );
            // reload page
            wp_safe_redirect( $onboarding_page_url );
            exit;
        }

        public function redirect_to_table() {
            // $table_id = isset($_GET['table_id']) ? $_GET['table_id'] : 0;
            $table_id = tablesome_get_method( 'table_id', 0 );
            $url = esc_url( admin_url( 'edit.php?post_type=' . TABLESOME_CPT . '&action=edit&post=' . $table_id . '&page=tablesome_admin_page' ) );
            tablesome_track_event( 'view_email_logs', 'onboarding_page' );
            wp_redirect( $url );
            exit;
        }

    }

    // end class
}