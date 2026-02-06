<?php

namespace Tablesome\Includes\Modules;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OAuth Health Monitor
 * 
 * Monitors OAuth token health for all integrations, provides admin notifications,
 * and ensures automation reliability through proactive token refresh.
 * 
 * @since 1.1.36
 */
if (!class_exists('\Tablesome\Includes\Modules\OAuth_Health_Monitor')) {
    class OAuth_Health_Monitor
    {
        const OPTION_NAME = 'tablesome_oauth_health_status';
        const CRON_HOOK = 'tablesome_oauth_health_check';
        const NOTICE_DISMISSED_OPTION = 'tablesome_oauth_notice_dismissed';
        
        /**
         * Supported integrations that use OAuth (includes legacy for internal checks)
         */
        private $oauth_integrations = ['google', 'google_safe', 'hubspot', 'slack'];
        
        /**
         * Integrations to display in admin UI (excludes legacy 'google')
         */
        private $display_integrations = ['google_safe', 'hubspot', 'slack'];
        
        /**
         * Display names for integrations
         */
        private $integration_display_names = [
            'google_safe' => 'Google',
            'hubspot' => 'HubSpot',
            'slack' => 'Slack',
        ];
        
        /**
         * Initialize the health monitor
         */
        public function __construct()
        {
            // Register cron hook
            add_action(self::CRON_HOOK, array($this, 'run_health_check_cron'));
            
            // Admin notices
            add_action('admin_notices', array($this, 'display_oauth_notices'));
            
            // Admin bar status indicator
            add_action('admin_bar_menu', array($this, 'add_admin_bar_status'), 100);
            add_action('admin_head', array($this, 'admin_bar_styles'));
            add_action('wp_head', array($this, 'admin_bar_styles'));
            
            // AJAX handler for dismissing notices
            add_action('wp_ajax_tablesome_dismiss_oauth_notice', array($this, 'ajax_dismiss_notice'));
            
            // Admin action for manual health check
            add_action('admin_action_tablesome_oauth_health_check', array($this, 'admin_action_health_check'));
            
            // Schedule cron if not scheduled
            if (!wp_next_scheduled(self::CRON_HOOK)) {
                wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
            }
        }
        
        /**
         * Handle admin action for manual health check
         */
        public function admin_action_health_check()
        {
            // Verify nonce
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'tablesome_oauth_health_check')) {
                wp_die(__('Security check failed.', 'tablesome'));
            }
            
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_die(__('You do not have permission to perform this action.', 'tablesome'));
            }
            
            // Run the health check
            $this->run_health_check();
            
            // Redirect back to the referring page
            $redirect_url = wp_get_referer();
            if (!$redirect_url) {
                $redirect_url = admin_url('edit.php?post_type=tablesome_cpt&page=tablesome-settings#tab=integrations/google');
            }
            
            // Add a success message
            add_settings_error('tablesome_oauth', 'health_check_complete', __('OAuth health check completed.', 'tablesome'), 'success');
            
            wp_safe_redirect($redirect_url);
            exit;
        }
        
        /**
         * Cron callback wrapper - runs health check without returning a value
         * WordPress action callbacks should not return anything
         */
        public function run_health_check_cron()
        {
            $this->run_health_check();
        }
        
        /**
         * Run health check for all OAuth integrations
         * 
         * @return array Health status for all integrations
         */
        public function run_health_check()
        {
            $api_credentials_handler = new API_Credentials_Handler();
            $health_status = array();
            
            foreach ($this->oauth_integrations as $integration) {
                $health_status[$integration] = $this->check_integration_health($integration, $api_credentials_handler);
            }
            
            // Store the health status
            $health_status['last_check'] = current_time('mysql', 1);
            $health_status['last_check_timestamp'] = time();
            update_option(self::OPTION_NAME, $health_status);
            
            // Log any issues
            $this->log_health_issues($health_status);
            
            return $health_status;
        }
        
        /**
         * Check health of a specific integration
         * 
         * @param string $integration Integration name
         * @param API_Credentials_Handler $handler Credentials handler
         * @return array Health status
         */
        private function check_integration_health($integration, $handler)
        {
            $credentials = $handler->get_api_credentials($integration);
            
            $status = array(
                'integration' => $integration,
                'is_configured' => false,
                'is_healthy' => false,
                'is_token_expired' => false,
                'has_refresh_token' => false,
                'can_refresh' => false,
                'last_refresh_attempt' => null,
                'last_refresh_success' => null,
                'last_error' => null,
                'error_count' => 0,
                'status_message' => '',
                'action_required' => false,
                'action_url' => $handler->get_redirect_url($integration),
            );
            
            // Check if configured
            if (empty($credentials['access_token']) && $credentials['status'] !== 'success') {
                $status['status_message'] = sprintf(__('%s is not configured.', 'tablesome'), ucfirst($integration));
                return $status;
            }
            
            $status['is_configured'] = true;
            $status['has_refresh_token'] = !empty($credentials['refresh_token']);
            $status['is_token_expired'] = isset($credentials['access_token_is_expired']) && $credentials['access_token_is_expired'];
            
            // If token is expired but we have refresh token, try to refresh
            if ($status['is_token_expired'] && $status['has_refresh_token']) {
                $status['can_refresh'] = true;
                $refresh_result = $this->attempt_token_refresh($integration);
                
                $status['last_refresh_attempt'] = current_time('mysql', 1);
                
                if ($refresh_result['success']) {
                    $status['is_healthy'] = true;
                    $status['is_token_expired'] = false;
                    $status['last_refresh_success'] = current_time('mysql', 1);
                    $status['status_message'] = sprintf(__('%s token refreshed successfully.', 'tablesome'), ucfirst($integration));
                } else {
                    $status['is_healthy'] = false;
                    $status['last_error'] = $refresh_result['error'];
                    $status['error_count'] = $this->increment_error_count($integration);
                    $status['action_required'] = true;
                    $status['status_message'] = sprintf(
                        __('%s authentication failed: %s. Please re-authenticate.', 'tablesome'),
                        ucfirst($integration),
                        $refresh_result['error']
                    );
                }
            } elseif (!$status['is_token_expired']) {
                $status['is_healthy'] = true;
                $status['status_message'] = sprintf(__('%s is working correctly.', 'tablesome'), ucfirst($integration));
            } else {
                // Token expired and no refresh token
                $status['is_healthy'] = false;
                $status['action_required'] = true;
                $status['status_message'] = sprintf(
                    __('%s token expired and cannot be refreshed. Please re-authenticate.', 'tablesome'),
                    ucfirst($integration)
                );
            }
            
            return $status;
        }
        
        /**
         * Attempt to refresh OAuth token
         * 
         * @param string $integration Integration name
         * @return array Result with 'success' and 'error' keys
         */
        private function attempt_token_refresh($integration)
        {
            $api_credentials_handler = new API_Credentials_Handler();
            $api_credentials = $api_credentials_handler->get_api_credentials($integration);
            
            $connector_domain = tablesome_get_connector_domain($integration);
            $endpoint = $connector_domain . "/wp-json/tablesome-connector/v1/oauth/exchange-token?integration=$integration";
            
            $response = wp_remote_post($endpoint, array(
                'method' => 'GET',
                'body' => $api_credentials,
                'timeout' => 30,
            ));
            
            // Check for WP errors
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'error' => $response->get_error_message(),
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $new_credentials = json_decode($body, true);
            
            // Check HTTP response code
            if ($response_code < 200 || $response_code >= 300) {
                return array(
                    'success' => false,
                    'error' => sprintf(__('HTTP error %d from connector service.', 'tablesome'), $response_code),
                );
            }
            
            // Check for empty response
            if (empty($new_credentials)) {
                return array(
                    'success' => false,
                    'error' => __('Empty response from connector service.', 'tablesome'),
                );
            }
            
            // Check for error in response
            if (isset($new_credentials['status']) && $new_credentials['status'] === 'failed') {
                $error_message = isset($new_credentials['message']) ? $new_credentials['message'] : __('Token refresh failed.', 'tablesome');
                return array(
                    'success' => false,
                    'error' => $error_message,
                );
            }
            
            // Check if we got a new access token
            if (empty($new_credentials['access_token'])) {
                return array(
                    'success' => false,
                    'error' => __('No access token in refresh response.', 'tablesome'),
                );
            }
            
            // Save the new credentials
            $api_credentials_handler->set_api_credentials($integration, $new_credentials);
            
            // Reset error count on success
            $this->reset_error_count($integration);
            
            return array(
                'success' => true,
                'error' => null,
            );
        }
        
        /**
         * Get the current health status (from cache or fresh)
         * 
         * @param bool $force_refresh Force a fresh health check
         * @return array Health status
         */
        public function get_health_status($force_refresh = false)
        {
            $cached_status = get_option(self::OPTION_NAME);
            
            // If no cached status or force refresh, run a new check
            if ($force_refresh || empty($cached_status) || !isset($cached_status['last_check_timestamp'])) {
                return $this->run_health_check();
            }
            
            // If cache is older than 1 hour, refresh
            $cache_age = time() - $cached_status['last_check_timestamp'];
            if ($cache_age > 3600) {
                return $this->run_health_check();
            }
            
            return $cached_status;
        }
        
        /**
         * Get health status for a specific integration
         * 
         * @param string $integration Integration name
         * @param bool $force_refresh Force a fresh check
         * @return array Integration health status
         */
        public function get_integration_health($integration, $force_refresh = false)
        {
            $health_status = $this->get_health_status($force_refresh);
            
            if (isset($health_status[$integration])) {
                return $health_status[$integration];
            }
            
            return array(
                'integration' => $integration,
                'is_configured' => false,
                'is_healthy' => false,
                'status_message' => sprintf(__('%s status unknown.', 'tablesome'), ucfirst($integration)),
            );
        }
        
        /**
         * Check if any integration requires action (only display integrations)
         * 
         * @return array List of integrations requiring action
         */
        public function get_integrations_requiring_action()
        {
            $health_status = $this->get_health_status();
            $requiring_action = array();
            
            // Only check display integrations (excludes legacy 'google')
            foreach ($this->display_integrations as $integration) {
                if (isset($health_status[$integration]) && 
                    isset($health_status[$integration]['action_required']) && 
                    $health_status[$integration]['action_required']) {
                    $requiring_action[] = $health_status[$integration];
                }
            }
            
            return $requiring_action;
        }
        
        /**
         * Display admin notices for OAuth issues
         */
        public function display_oauth_notices()
        {
            // Only show on Tablesome admin pages
            $screen = get_current_screen();
            if (!$screen || strpos($screen->id, 'tablesome') === false) {
                return;
            }
            
            // Check if notice was dismissed recently (within 24 hours)
            $dismissed = get_option(self::NOTICE_DISMISSED_OPTION, array());
            
            $requiring_action = $this->get_integrations_requiring_action();
            
            if (empty($requiring_action)) {
                return;
            }
            
            foreach ($requiring_action as $integration_status) {
                $integration = $integration_status['integration'];
                
                // Skip if dismissed recently
                if (isset($dismissed[$integration]) && (time() - $dismissed[$integration]) < 86400) {
                    continue;
                }
                
                $this->render_oauth_notice($integration_status);
            }
        }
        
        /**
         * Render a single OAuth notice
         * 
         * @param array $status Integration status
         */
        private function render_oauth_notice($status)
        {
            $integration = $status['integration'];
            // Use display name if available
            $integration_name = isset($this->integration_display_names[$integration]) 
                ? $this->integration_display_names[$integration] 
                : ucfirst(str_replace('_', ' ', $integration));
            $action_url = $status['action_url'];
            $error_message = isset($status['last_error']) ? $status['last_error'] : '';
            
            ?>
            <div class="notice notice-error tablesome-oauth-notice is-dismissible" 
                 data-integration="<?php echo esc_attr($status['integration']); ?>">
                <p>
                    <strong><?php _e('Tablesome - Integration Issue', 'tablesome'); ?></strong>
                </p>
                <p>
                    <?php 
                    printf(
                        __('%s authentication has failed. This may affect your automations and workflows.', 'tablesome'),
                        '<strong>' . esc_html($integration_name) . '</strong>'
                    );
                    ?>
                </p>
                <?php if (!empty($error_message)) : ?>
                <p>
                    <em><?php _e('Error:', 'tablesome'); ?> <?php echo esc_html($error_message); ?></em>
                </p>
                <?php endif; ?>
                <p>
                    <a href="<?php echo esc_url($action_url); ?>" class="button button-primary">
                        <?php _e('Re-authenticate Now', 'tablesome'); ?>
                    </a>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $('.tablesome-oauth-notice').on('click', '.notice-dismiss', function() {
                    var integration = $(this).closest('.tablesome-oauth-notice').data('integration');
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'tablesome_dismiss_oauth_notice',
                            integration: integration,
                            nonce: '<?php echo wp_create_nonce('tablesome_dismiss_oauth_notice'); ?>'
                        }
                    });
                });
            });
            </script>
            <?php
        }
        
        /**
         * AJAX handler for dismissing OAuth notices
         */
        public function ajax_dismiss_notice()
        {
            check_ajax_referer('tablesome_dismiss_oauth_notice', 'nonce');
            
            $integration = isset($_POST['integration']) ? sanitize_text_field($_POST['integration']) : '';
            
            if (empty($integration)) {
                wp_send_json_error('Invalid integration');
                return;
            }
            
            $dismissed = get_option(self::NOTICE_DISMISSED_OPTION, array());
            $dismissed[$integration] = time();
            update_option(self::NOTICE_DISMISSED_OPTION, $dismissed);
            
            wp_send_json_success();
        }
        
        /**
         * Log health issues for debugging
         * 
         * @param array $health_status Health status array
         */
        private function log_health_issues($health_status)
        {
            foreach ($this->oauth_integrations as $integration) {
                if (isset($health_status[$integration]) && 
                    isset($health_status[$integration]['action_required']) && 
                    $health_status[$integration]['action_required']) {
                    
                    $status = $health_status[$integration];
                    error_log(sprintf(
                        '[Tablesome OAuth Health] %s requires action: %s (Error count: %d)',
                        ucfirst($integration),
                        $status['status_message'],
                        isset($status['error_count']) ? $status['error_count'] : 0
                    ));
                }
            }
        }
        
        /**
         * Increment error count for an integration
         * 
         * @param string $integration Integration name
         * @return int New error count
         */
        private function increment_error_count($integration)
        {
            $error_counts = get_option('tablesome_oauth_error_counts', array());
            $count = isset($error_counts[$integration]) ? $error_counts[$integration] + 1 : 1;
            $error_counts[$integration] = $count;
            update_option('tablesome_oauth_error_counts', $error_counts);
            return $count;
        }
        
        /**
         * Reset error count for an integration
         * 
         * @param string $integration Integration name
         */
        private function reset_error_count($integration)
        {
            $error_counts = get_option('tablesome_oauth_error_counts', array());
            $error_counts[$integration] = 0;
            update_option('tablesome_oauth_error_counts', $error_counts);
        }
        
        /**
         * Add OAuth status indicator to admin bar
         * 
         * @param \WP_Admin_Bar $admin_bar The admin bar instance
         */
        public function add_admin_bar_status($admin_bar)
        {
            // Only show for users who can manage options
            if (!current_user_can('manage_options')) {
                return;
            }
            
            $health_status = $this->get_health_status();
            $requiring_action = $this->get_integrations_requiring_action();
            $issues_count = count($requiring_action);
            
            // Calculate last checked time
            $last_check_text = __('Never', 'tablesome');
            if (isset($health_status['last_check_timestamp'])) {
                $minutes_ago = round((time() - $health_status['last_check_timestamp']) / 60);
                if ($minutes_ago < 1) {
                    $last_check_text = __('Just now', 'tablesome');
                } elseif ($minutes_ago === 1) {
                    $last_check_text = __('1 min ago', 'tablesome');
                } elseif ($minutes_ago < 60) {
                    $last_check_text = sprintf(__('%d mins ago', 'tablesome'), $minutes_ago);
                } elseif ($minutes_ago < 120) {
                    $last_check_text = __('1 hour ago', 'tablesome');
                } else {
                    $hours_ago = round($minutes_ago / 60);
                    $last_check_text = sprintf(__('%d hours ago', 'tablesome'), $hours_ago);
                }
            }
            
            // Determine status icon and class
            if ($issues_count > 0) {
                $status_icon = '⚠️';
                $status_class = 'tablesome-oauth-warning';
                $status_text = sprintf(
                    _n('%d OAuth issue', '%d OAuth issues', $issues_count, 'tablesome'),
                    $issues_count
                );
            } else {
                // Check if any integrations are configured (only check display integrations)
                $configured_count = 0;
                foreach ($this->display_integrations as $integration) {
                    if (isset($health_status[$integration]) && 
                        isset($health_status[$integration]['is_configured']) && 
                        $health_status[$integration]['is_configured']) {
                        $configured_count++;
                    }
                }
                
                if ($configured_count > 0) {
                    $status_icon = '✓';
                    $status_class = 'tablesome-oauth-healthy';
                    $status_text = __('OAuth OK', 'tablesome');
                } else {
                    $status_icon = '○';
                    $status_class = 'tablesome-oauth-none';
                    $status_text = __('No OAuth', 'tablesome');
                }
            }
            
            // Add main menu item
            $admin_bar->add_node(array(
                'id' => 'tablesome-oauth-status',
                'title' => sprintf(
                    '<span class="ab-icon %s">%s</span><span class="ab-label">%s</span>',
                    esc_attr($status_class),
                    $status_icon,
                    esc_html($status_text)
                ),
                'href' => admin_url('edit.php?post_type=tablesome_cpt&page=tablesome-settings#tab=integrations/google'),
                'meta' => array(
                    'title' => sprintf(__('Tablesome OAuth Status - Last checked: %s', 'tablesome'), $last_check_text),
                    'class' => $status_class,
                ),
            ));
            
            // Add "Last checked" submenu item
            $admin_bar->add_node(array(
                'id' => 'tablesome-oauth-last-check',
                'parent' => 'tablesome-oauth-status',
                'title' => sprintf(
                    '<span class="tablesome-oauth-submenu-label">%s:</span> <span class="tablesome-oauth-submenu-value">%s</span>',
                    __('Last checked', 'tablesome'),
                    esc_html($last_check_text)
                ),
                'href' => false,
                'meta' => array(
                    'class' => 'tablesome-oauth-submenu-item',
                ),
            ));
            
            // Add individual integration statuses (only display integrations, not legacy 'google')
            foreach ($this->display_integrations as $integration) {
                if (!isset($health_status[$integration])) {
                    continue;
                }
                
                $int_status = $health_status[$integration];
                // Use display name if available, otherwise format the integration name
                $int_name = isset($this->integration_display_names[$integration]) 
                    ? $this->integration_display_names[$integration] 
                    : ucfirst(str_replace('_', ' ', $integration));
                
                if (!isset($int_status['is_configured']) || !$int_status['is_configured']) {
                    $int_icon = '○';
                    $int_label = __('Not configured', 'tablesome');
                } elseif (isset($int_status['is_healthy']) && $int_status['is_healthy']) {
                    $int_icon = '✓';
                    $int_label = __('Healthy', 'tablesome');
                } else {
                    $int_icon = '⚠️';
                    $int_label = __('Needs attention', 'tablesome');
                }
                
                $admin_bar->add_node(array(
                    'id' => 'tablesome-oauth-' . $integration,
                    'parent' => 'tablesome-oauth-status',
                    'title' => sprintf(
                        '<span class="tablesome-oauth-submenu-icon">%s</span> <span class="tablesome-oauth-submenu-label">%s:</span> <span class="tablesome-oauth-submenu-value">%s</span>',
                        $int_icon,
                        esc_html($int_name),
                        esc_html($int_label)
                    ),
                    'href' => isset($int_status['action_url']) ? $int_status['action_url'] : false,
                    'meta' => array(
                        'class' => 'tablesome-oauth-submenu-item',
                    ),
                ));
            }
            
            // Add "Check Now" action
            $admin_bar->add_node(array(
                'id' => 'tablesome-oauth-check-now',
                'parent' => 'tablesome-oauth-status',
                'title' => sprintf(
                    '<span class="tablesome-oauth-submenu-icon">🔄</span> <span class="tablesome-oauth-submenu-label">%s</span>',
                    __('Run Health Check', 'tablesome')
                ),
                'href' => admin_url('admin.php?action=tablesome_oauth_health_check&_wpnonce=' . wp_create_nonce('tablesome_oauth_health_check')),
                'meta' => array(
                    'class' => 'tablesome-oauth-submenu-item tablesome-oauth-action',
                ),
            ));
        }
        
        /**
         * Output admin bar styles
         */
        public function admin_bar_styles()
        {
            if (!is_admin_bar_showing() || !current_user_can('manage_options')) {
                return;
            }
            ?>
            <style>
                #wp-admin-bar-tablesome-oauth-status > .ab-item {
                    display: flex !important;
                    align-items: center !important;
                }
                #wp-admin-bar-tablesome-oauth-status .ab-icon {
                    font-size: 14px !important;
                    margin-right: 5px !important;
                    line-height: 1 !important;
                }
                #wp-admin-bar-tablesome-oauth-status.tablesome-oauth-healthy .ab-icon {
                    color: #46b450 !important;
                }
                #wp-admin-bar-tablesome-oauth-status.tablesome-oauth-warning .ab-icon {
                    color: #ffb900 !important;
                }
                #wp-admin-bar-tablesome-oauth-status.tablesome-oauth-none .ab-icon {
                    color: #72777c !important;
                }
                #wp-admin-bar-tablesome-oauth-status .ab-label {
                    font-size: 13px !important;
                }
                .tablesome-oauth-submenu-item .ab-item {
                    display: flex !important;
                    align-items: center !important;
                    gap: 5px !important;
                }
                .tablesome-oauth-submenu-icon {
                    width: 18px !important;
                    text-align: center !important;
                }
                .tablesome-oauth-submenu-label {
                    color: rgba(240, 246, 252, 0.7) !important;
                }
                .tablesome-oauth-submenu-value {
                    color: #fff !important;
                }
                .tablesome-oauth-action .ab-item {
                    background: rgba(0, 0, 0, 0.1) !important;
                }
                .tablesome-oauth-action .ab-item:hover {
                    background: rgba(0, 0, 0, 0.2) !important;
                }
            </style>
            <?php
        }
        
        /**
         * Clean up on plugin deactivation
         */
        public static function deactivate()
        {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }
}

