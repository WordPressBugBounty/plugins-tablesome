<?php

namespace Tablesome\Workflow_Library\Integrations;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Workflow_Library\Integrations\OpenAi')) {
    class OpenAi
    {

        public $gdrive = null;

        public function __construct()
        {

        }

        public function get_config()
        {
            return array(
                'integration' => 'openai',
                'integration_label' => __('OpenAI', 'tablesome'),
                'is_active' => true,
                'is_premium' => false,
                'actions' => array(),
            );
        }

        /** Tells admins that Generate Text failed, e.g. because the chosen model was retired. */
        public function print_error_notice()
        {
            if (!current_user_can('manage_options')) {
                return;
            }

            $last_error = \Tablesome\Workflow_Library\External_Apis\OpenAI::get_last_error();
            if (empty($last_error)) {
                return;
            }

            $settings_url = admin_url('edit.php?post_type=' . TABLESOME_CPT . '&page=tablesome-settings#tab=integrations/openai');
            $dismiss_url = wp_nonce_url(admin_url('admin-post.php?action=tablesome_dismiss_openai_error'), 'tablesome_dismiss_openai_error');
            $time = !empty($last_error['time']) ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $last_error['time']) : '';

            echo '<div class="notice notice-error">';
            echo '<p><strong>' . esc_html__('Tablesome: the "Generate Text (OpenAI)" workflow action failed.', 'tablesome') . '</strong> ';
            echo esc_html__('Actions that use its output were skipped.', 'tablesome') . '</p>';
            /* translators: 1: date and time of the failure, 2: error message from OpenAI. */
            echo '<p>' . esc_html(sprintf(__('Last error (%1$s): %2$s', 'tablesome'), $time, $last_error['message'])) . '</p>';
            echo '<p><a href="' . esc_url($settings_url) . '">' . esc_html__('Check the OpenAI key and model', 'tablesome') . '</a> | ';
            echo '<a href="' . esc_url($dismiss_url) . '">' . esc_html__('Dismiss', 'tablesome') . '</a></p>';
            echo '</div>';
        }

        public function dismiss_error_notice()
        {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('You do not have permission to perform this action.', 'tablesome'));
            }
            check_admin_referer('tablesome_dismiss_openai_error');

            \Tablesome\Workflow_Library\External_Apis\OpenAI::clear_last_error();

            wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
            exit;
        }

        /** Model suggestions for the Model field, from the key's GET /v1/models list. */
        public function print_models_datalist()
        {
            $models = (new \Tablesome\Workflow_Library\External_Apis\OpenAI())->get_models();

            echo '<datalist id="tablesome-openai-models">';
            foreach ($models as $model) {
                echo '<option value="' . esc_attr($model) . '"></option>';
            }
            echo '</datalist>';
        }

        public function get_folders()
        {
            return $this->gdrive->get_folders();
        }

        public function get_sub_folders($parent_folder_id)
        {
            return $this->gdrive->get_sub_folders($parent_folder_id);
        }

    }
}
