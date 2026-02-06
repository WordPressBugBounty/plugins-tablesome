<?php
/**
 * Tablesome Large Table Diagnostics
 *
 * This class helps diagnose issues with tables containing 1000+ records.
 * Enable by adding: define('TABLESOME_DEBUG_LARGE_TABLES', true); to wp-config.php
 *
 * @package Tablesome
 * @since 1.1.36
 */

namespace Tablesome\Includes\Debug;

if (!class_exists('\Tablesome\Includes\Debug\Large_Table_Diagnostics')) {
    class Large_Table_Diagnostics
    {
        private static $instance = null;
        private $timings = [];
        private $memory_usage = [];
        private $start_time;
        private $start_memory;

        public static function get_instance()
        {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct()
        {
            if (!$this->is_enabled()) {
                return;
            }

            $this->start_time = microtime(true);
            $this->start_memory = memory_get_usage(true);

            // Hook into key points in the table loading process
            add_action('tablesome_before_get_rows', [$this, 'before_get_rows'], 10, 1);
            add_action('tablesome_after_get_rows', [$this, 'after_get_rows'], 10, 2);
            add_action('shutdown', [$this, 'output_diagnostics']);
        }

        public function is_enabled()
        {
            return defined('TABLESOME_DEBUG_LARGE_TABLES') && TABLESOME_DEBUG_LARGE_TABLES === true;
        }

        public function log_timing($label, $start_time = null)
        {
            if (!$this->is_enabled()) {
                return;
            }

            $current_time = microtime(true);
            $duration = $start_time ? ($current_time - $start_time) * 1000 : 0;

            $this->timings[] = [
                'label' => $label,
                'time' => $current_time,
                'duration_ms' => round($duration, 2),
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ];
        }

        public function before_get_rows($args)
        {
            $table_id = isset($args['table_id']) ? $args['table_id'] : 0;
            $this->log_timing("START: get_rows for table #{$table_id}", $this->start_time);
        }

        public function after_get_rows($rows, $args)
        {
            $table_id = isset($args['table_id']) ? $args['table_id'] : 0;
            $row_count = is_array($rows) ? count($rows) : 0;
            $this->log_timing("END: get_rows - {$row_count} rows loaded for table #{$table_id}", $this->start_time);
        }

        public function output_diagnostics()
        {
            if (!$this->is_enabled() || empty($this->timings)) {
                return;
            }

            $total_duration = (microtime(true) - $this->start_time) * 1000;
            $memory_used = (memory_get_usage(true) - $this->start_memory) / 1024 / 1024;
            $peak_memory = memory_get_peak_usage(true) / 1024 / 1024;

            // Get PHP limits
            $memory_limit = ini_get('memory_limit');
            $max_execution_time = ini_get('max_execution_time');
            $max_input_vars = ini_get('max_input_vars');

            $diagnostics = "\n" . str_repeat('=', 60) . "\n";
            $diagnostics .= "TABLESOME LARGE TABLE DIAGNOSTICS\n";
            $diagnostics .= str_repeat('=', 60) . "\n\n";

            $diagnostics .= "PHP Environment:\n";
            $diagnostics .= "  - memory_limit: {$memory_limit}\n";
            $diagnostics .= "  - max_execution_time: {$max_execution_time}s\n";
            $diagnostics .= "  - max_input_vars: {$max_input_vars}\n";
            $diagnostics .= "  - PHP version: " . PHP_VERSION . "\n\n";

            $diagnostics .= "Request Summary:\n";
            $diagnostics .= "  - Total Duration: " . round($total_duration, 2) . "ms\n";
            $diagnostics .= "  - Memory Used: " . round($memory_used, 2) . "MB\n";
            $diagnostics .= "  - Peak Memory: " . round($peak_memory, 2) . "MB\n\n";

            $diagnostics .= "Timing Breakdown:\n";
            foreach ($this->timings as $timing) {
                $diagnostics .= "  [{$timing['duration_ms']}ms] {$timing['label']}\n";
                $diagnostics .= "     Memory: {$timing['memory_mb']}MB, Peak: {$timing['peak_memory_mb']}MB\n";
            }

            $diagnostics .= "\n" . str_repeat('=', 60) . "\n";

            // Log to PHP error log
            error_log($diagnostics);

            // Also output as HTML comment if not AJAX
            if (!wp_doing_ajax() && !defined('REST_REQUEST')) {
                echo "<!-- TABLESOME DEBUG\n" . esc_html($diagnostics) . "\n-->";
            }
        }

        /**
         * Static helper to check if a table load is likely to cause issues
         *
         * @param int $record_count Number of records in the table
         * @return array Warning information if issues are likely
         */
        public static function check_potential_issues($record_count)
        {
            $warnings = [];

            $memory_limit = ini_get('memory_limit');
            $memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit);
            $max_execution_time = (int) ini_get('max_execution_time');

            // Estimate memory needed (roughly 5KB per record with all cell data)
            $estimated_memory = $record_count * 5 * 1024;

            // Check memory
            if ($memory_limit_bytes > 0 && $estimated_memory > ($memory_limit_bytes * 0.5)) {
                $warnings[] = [
                    'type' => 'memory',
                    'message' => "Table with {$record_count} records may exceed memory limit ({$memory_limit})",
                    'suggestion' => 'Consider increasing PHP memory_limit or enabling pagination'
                ];
            }

            // Check execution time (rough estimate: 0.01 seconds per record)
            $estimated_time = $record_count * 0.01;
            if ($max_execution_time > 0 && $estimated_time > ($max_execution_time * 0.8)) {
                $warnings[] = [
                    'type' => 'timeout',
                    'message' => "Table with {$record_count} records may timeout ({$max_execution_time}s limit)",
                    'suggestion' => 'Consider increasing max_execution_time or enabling pagination'
                ];
            }

            return $warnings;
        }
    }
}

// Initialize if enabled
if (defined('TABLESOME_DEBUG_LARGE_TABLES') && TABLESOME_DEBUG_LARGE_TABLES === true) {
    Large_Table_Diagnostics::get_instance();
}
