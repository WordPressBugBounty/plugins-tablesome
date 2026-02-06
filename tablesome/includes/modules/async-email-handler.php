<?php

namespace Tablesome\Includes\Modules;

if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly

if (!class_exists('\Tablesome\Includes\Modules\Async_Email_Handler')) {
    /**
     * Async Email Handler
     *
     * Handles asynchronous email sending with multiple fallback layers:
     * 1. Action Scheduler (preferred - if available)
     * 2. WP Cron (wp_schedule_single_event - built-in fallback)
     * 3. Synchronous wp_mail (ultimate fallback)
     *
     * Performance impact: Reduces form submission time by 200ms-30s per email
     * by offloading email sending to background processing.
     */
    class Async_Email_Handler
    {
        /**
         * Action hook name for async email processing (Action Scheduler)
         */
        const ASYNC_EMAIL_HOOK = 'tablesome_process_async_email';

        /**
         * Action hook name for WP Cron fallback
         */
        const CRON_EMAIL_HOOK = 'tablesome_cron_send_email';

        /**
         * Action Scheduler group name
         */
        const AS_GROUP = 'tablesome-emails';

        /**
         * Whether async sending is enabled
         * @var bool
         */
        private $async_enabled = true;

        /**
         * Track if we've detected Action Scheduler issues
         * @var bool
         */
        private static $action_scheduler_healthy = true;

        /**
         * Option name for tracking failed async attempts
         * @var string
         */
        const FAILURE_COUNT_OPTION = 'tablesome_async_email_failures';

        /**
         * Max failures before temporarily disabling async
         * @var int
         */
        const MAX_FAILURES_BEFORE_DISABLE = 5;

        /**
         * Initialize the async email handler
         */
        public function __construct()
        {
            // Register the async email processing hooks
            add_action(self::ASYNC_EMAIL_HOOK, [$this, 'process_queued_email'], 10, 1);
            add_action(self::CRON_EMAIL_HOOK, [$this, 'process_cron_email'], 10, 1);

            // Check health status on init
            $this->check_health_status();
        }

        /**
         * Check if Action Scheduler has been having issues
         */
        private function check_health_status()
        {
            $failure_count = (int) get_option(self::FAILURE_COUNT_OPTION, 0);

            if ($failure_count >= self::MAX_FAILURES_BEFORE_DISABLE) {
                self::$action_scheduler_healthy = false;

                // Reset after 1 hour to try again
                $last_reset = get_option(self::FAILURE_COUNT_OPTION . '_reset', 0);
                if (time() - $last_reset > HOUR_IN_SECONDS) {
                    $this->reset_failure_count();
                    self::$action_scheduler_healthy = true;
                }
            }
        }

        /**
         * Record a failure
         */
        private function record_failure()
        {
            $count = (int) get_option(self::FAILURE_COUNT_OPTION, 0);
            update_option(self::FAILURE_COUNT_OPTION, $count + 1, false);
        }

        /**
         * Record a success (reset failure count)
         */
        private function record_success()
        {
            $count = (int) get_option(self::FAILURE_COUNT_OPTION, 0);
            if ($count > 0) {
                update_option(self::FAILURE_COUNT_OPTION, max(0, $count - 1), false);
            }
        }

        /**
         * Reset failure count
         */
        private function reset_failure_count()
        {
            update_option(self::FAILURE_COUNT_OPTION, 0, false);
            update_option(self::FAILURE_COUNT_OPTION . '_reset', time(), false);
        }

        /**
         * Check if Action Scheduler is available and functional
         *
         * @return bool True if Action Scheduler is available and healthy
         */
        public function is_action_scheduler_available()
        {
            // Check if function exists
            if (!function_exists('as_enqueue_async_action')) {
                return false;
            }

            // Check if we've been having issues
            if (!self::$action_scheduler_healthy) {
                return false;
            }

            return true;
        }

        /**
         * Check if WP Cron is available and functional
         *
         * @return bool True if WP Cron is available
         */
        public function is_wp_cron_available()
        {
            // Check if WP Cron is disabled
            if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
                return false;
            }

            return true;
        }

        /**
         * Check if async email sending should be used
         *
         * @return bool True if async sending should be used
         */
        public function should_use_async()
        {
            if (!$this->async_enabled) {
                return false;
            }

            // Allow filtering for specific use cases
            return apply_filters('tablesome_use_async_email', true);
        }

        /**
         * Queue an email for async sending with multiple fallback layers
         *
         * @param array $email_args Email arguments
         *   - to: array|string Recipients
         *   - subject: string Email subject
         *   - body: string Email body (HTML)
         *   - headers: array|string Email headers
         *   - attachments: array Optional attachments
         *
         * @return bool|int Action ID if queued, true if sent sync, false on error
         */
        public function queue_email($email_args)
        {
            // Validate required fields
            if (empty($email_args['to']) || empty($email_args['subject'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tablesome Async Email: Missing required email fields (to or subject)');
                }
                return false;
            }

            // Ensure all expected keys exist
            $email_args = wp_parse_args($email_args, [
                'to' => [],
                'subject' => '',
                'body' => '',
                'headers' => [],
                'attachments' => [],
                'queued_at' => current_time('mysql'),
                'attempt' => 1,
            ]);

            // If async is disabled, send immediately
            if (!$this->should_use_async()) {
                return $this->send_email_now($email_args);
            }

            // Try Action Scheduler first (Layer 1)
            if ($this->is_action_scheduler_available()) {
                $result = $this->enqueue_via_action_scheduler($email_args);
                if ($result !== false) {
                    return $result;
                }
                // Action Scheduler failed, record and continue to fallback
                $this->record_failure();
            }

            // Try WP Cron fallback (Layer 2)
            if ($this->is_wp_cron_available()) {
                $result = $this->enqueue_via_wp_cron($email_args);
                if ($result !== false) {
                    return $result;
                }
            }

            // Ultimate fallback: Send synchronously (Layer 3)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Tablesome Async Email: All async methods failed, sending synchronously');
            }
            return $this->send_email_now($email_args);
        }

        /**
         * Enqueue email via Action Scheduler (Layer 1 - Preferred)
         *
         * @param array $email_args Email arguments
         * @return int|false Action ID on success, false on failure
         */
        private function enqueue_via_action_scheduler($email_args)
        {
            try {
                $action_id = as_enqueue_async_action(
                    self::ASYNC_EMAIL_HOOK,
                    ['email_args' => $email_args],
                    self::AS_GROUP
                );

                if ($action_id && is_numeric($action_id) && $action_id > 0) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        $to = is_array($email_args['to']) ? implode(', ', $email_args['to']) : $email_args['to'];
                        error_log(sprintf(
                            'Tablesome: Email queued via Action Scheduler (ID: %d, To: %s)',
                            $action_id,
                            $to
                        ));
                    }
                    return $action_id;
                }

                return false;

            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tablesome Async Email: Action Scheduler exception - ' . $e->getMessage());
                }
                return false;
            } catch (\Error $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tablesome Async Email: Action Scheduler error - ' . $e->getMessage());
                }
                return false;
            }
        }

        /**
         * Enqueue email via WP Cron (Layer 2 - Fallback)
         *
         * @param array $email_args Email arguments
         * @return bool True on success, false on failure
         */
        private function enqueue_via_wp_cron($email_args)
        {
            try {
                // Create a unique transient key for this email
                $email_key = 'tablesome_email_' . md5(serialize($email_args) . microtime());

                // Store email data in transient (expires in 1 hour)
                set_transient($email_key, $email_args, HOUR_IN_SECONDS);

                // Schedule the cron event
                $scheduled = wp_schedule_single_event(
                    time() + 1, // Run in 1 second
                    self::CRON_EMAIL_HOOK,
                    [$email_key]
                );

                if ($scheduled !== false) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        $to = is_array($email_args['to']) ? implode(', ', $email_args['to']) : $email_args['to'];
                        error_log(sprintf('Tablesome: Email queued via WP Cron (Key: %s, To: %s)', $email_key, $to));
                    }

                    // Spawn cron if needed
                    if (function_exists('spawn_cron')) {
                        spawn_cron();
                    }

                    return true;
                }

                // If scheduling fails, clean up transient
                delete_transient($email_key);
                return false;

            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tablesome Async Email: WP Cron exception - ' . $e->getMessage());
                }
                return false;
            }
        }

        /**
         * Process a queued email from Action Scheduler
         *
         * @param array $email_args Email arguments
         * @return void
         */
        public function process_queued_email($email_args)
        {
            if (empty($email_args) || !is_array($email_args)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tablesome Async Email: Invalid email args received from Action Scheduler');
                }
                return;
            }

            $result = $this->send_email_now($email_args);

            if ($result) {
                $this->record_success();
            }

            $this->log_email_result($email_args, $result, 'Action Scheduler');
        }

        /**
         * Process a queued email from WP Cron
         *
         * @param string $email_key Transient key containing email data
         * @return void
         */
        public function process_cron_email($email_key)
        {
            // Retrieve email args from transient
            $email_args = get_transient($email_key);

            if (empty($email_args) || !is_array($email_args)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tablesome Async Email: Email data not found in transient (key: ' . $email_key . ')');
                }
                return;
            }

            // Delete transient immediately to prevent duplicate sends
            delete_transient($email_key);

            $result = $this->send_email_now($email_args);

            $this->log_email_result($email_args, $result, 'WP Cron');
        }

        /**
         * Log email result
         *
         * @param array $email_args Email arguments
         * @param bool $result Send result
         * @param string $method Method used (Action Scheduler, WP Cron, Sync)
         */
        private function log_email_result($email_args, $result, $method)
        {
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                return;
            }

            $to = is_array($email_args['to']) ? implode(', ', $email_args['to']) : $email_args['to'];
            $status = $result ? 'SUCCESS' : 'FAILED';

            error_log(sprintf(
                'Tablesome Async Email [%s]: %s - To: %s, Subject: %s',
                $method,
                $status,
                $to,
                $email_args['subject']
            ));
        }

        /**
         * Send email immediately (synchronous)
         *
         * @param array $email_args Email arguments
         * @return bool True on success, false on failure
         */
        public function send_email_now($email_args)
        {
            $to = isset($email_args['to']) ? $email_args['to'] : '';
            $subject = isset($email_args['subject']) ? $email_args['subject'] : '';
            $body = isset($email_args['body']) ? $email_args['body'] : '';
            $headers = isset($email_args['headers']) ? $email_args['headers'] : '';
            $attachments = isset($email_args['attachments']) ? $email_args['attachments'] : [];

            if (empty($to)) {
                return false;
            }

            // Convert headers array to string if needed
            if (is_array($headers)) {
                $headers = implode("\r\n", array_filter($headers));
            }

            try {
                return \wp_mail($to, $subject, $body, $headers, $attachments);
            } catch (\Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Tablesome Async Email: wp_mail exception - ' . $e->getMessage());
                }
                return false;
            }
        }

        /**
         * Enable or disable async email sending
         *
         * @param bool $enabled True to enable, false to disable
         */
        public function set_async_enabled($enabled)
        {
            $this->async_enabled = (bool) $enabled;
        }

        /**
         * Check if there are pending emails in the Action Scheduler queue
         *
         * @return int Number of pending emails
         */
        public function get_pending_count()
        {
            if (!$this->is_action_scheduler_available()) {
                return 0;
            }

            if (!function_exists('as_get_scheduled_actions')) {
                return 0;
            }

            try {
                $actions = as_get_scheduled_actions([
                    'hook' => self::ASYNC_EMAIL_HOOK,
                    'status' => \ActionScheduler_Store::STATUS_PENDING,
                    'group' => self::AS_GROUP,
                    'per_page' => -1,
                ], 'ids');

                return is_array($actions) ? count($actions) : 0;
            } catch (\Exception $e) {
                return 0;
            }
        }

        /**
         * Get async email status info
         *
         * @return array Status information
         */
        public function get_status()
        {
            $failure_count = (int) get_option(self::FAILURE_COUNT_OPTION, 0);

            return [
                'async_enabled' => $this->async_enabled,
                'action_scheduler_available' => function_exists('as_enqueue_async_action'),
                'action_scheduler_healthy' => self::$action_scheduler_healthy,
                'wp_cron_available' => $this->is_wp_cron_available(),
                'will_use_async' => $this->should_use_async(),
                'pending_emails' => $this->get_pending_count(),
                'failure_count' => $failure_count,
                'method' => $this->get_current_method(),
            ];
        }

        /**
         * Get current email sending method
         *
         * @return string Current method being used
         */
        public function get_current_method()
        {
            if (!$this->should_use_async()) {
                return 'sync';
            }

            if ($this->is_action_scheduler_available()) {
                return 'action_scheduler';
            }

            if ($this->is_wp_cron_available()) {
                return 'wp_cron';
            }

            return 'sync';
        }

        /**
         * Force reset health status (for admin/debugging)
         */
        public function force_reset_health()
        {
            $this->reset_failure_count();
            self::$action_scheduler_healthy = true;
        }

        /**
         * Reset static health state (for testing purposes)
         * This allows tests to reset the static variable between test runs.
         */
        public static function reset_static_health()
        {
            self::$action_scheduler_healthy = true;
        }
    }
}
