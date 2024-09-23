<?php

namespace Tablesome\Components\System_Info;

if (!class_exists('\Tablesome\Components\System_Info\Controller')) {
    class Controller
    {
        public $model;
        public $view;

        public function __construct()
        {
            $this->model = new \Tablesome\Components\System_Info\Model();
            $this->view = new \Tablesome\Components\System_Info\View();
        }

        public function add_menu()
        {
            $label = __("System Info", "tablesome");

            add_submenu_page(
                'edit.php?post_type=' . TABLESOME_CPT,
                $label,
                $label,
                'manage_options',
                'tablesome-system-info-page',
                array($this, 'render')
            );
        }

        public function render()
        {

            // $page = get_query_var('page');

            // error_log('$page : ' . $page);
            $page = isset($_GET['page']) ? $_GET['page'] : '';
            $page = tablesome_wp_kses($page);
            if ($page != 'tablesome-system-info-page') {
                return;
            }
            $view_props = $this->model->get_viewProps();
            $html = $this->view->get_view($view_props['items']);
            echo wp_kses_post($html);
        }
    }
}
