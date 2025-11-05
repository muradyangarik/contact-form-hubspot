<?php
/**
 * WP-CLI command class
 *
 * @package ContactFormHubSpot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Only load if WP-CLI is available
if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * WP-CLI command class for contact form management
 */
class ContactFormHubSpot_WP_CLI {
    
    /**
     * Single instance of the class
     *
     * @var ContactFormHubSpot_WP_CLI
     */
    private static $instance = null;
    
    /**
     * Get single instance
     *
     * @return ContactFormHubSpot_WP_CLI
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_commands();
    }
    
    /**
     * Initialize WP-CLI commands
     */
    private function init_commands() {
        WP_CLI::add_command('contact-form test-hubspot', array($this, 'test_hubspot_connection'));
        WP_CLI::add_command('contact-form stats', array($this, 'show_statistics'));
        WP_CLI::add_command('contact-form logs', array($this, 'show_logs'));
        WP_CLI::add_command('contact-form clear-rate-limits', array($this, 'clear_rate_limits'));
        WP_CLI::add_command('contact-form rotate-logs', array($this, 'rotate_logs'));
    }
    
    /**
     * Test HubSpot connection
     *
     * ## EXAMPLES
     *
     *     wp contact-form test-hubspot
     *
     * @when after_wp_load
     */
    public function test_hubspot_connection($args, $assoc_args) {
        WP_CLI::log('Testing HubSpot connection...');
        
        $hubspot_api = ContactFormHubSpot_HubSpot_API::get_instance();
        
        // Test basic connection
        $connection_result = $hubspot_api->test_connection();
        
        if ($connection_result['success']) {
            WP_CLI::success($connection_result['message']);
        } else {
            WP_CLI::error($connection_result['message']);
            return;
        }
        
        // Test creating a contact
        WP_CLI::log('Creating test contact...');
        $test_contact_result = $hubspot_api->create_test_contact();
        
        if ($test_contact_result['success']) {
            WP_CLI::success(sprintf(
                'Test contact created successfully! Contact ID: %s',
                $test_contact_result['contact_id']
            ));
        } else {
            WP_CLI::warning(sprintf(
                'Test contact creation failed: %s',
                $test_contact_result['message']
            ));
        }
        
        // Show current settings
        $api_token = get_option('contact_form_hubspot_api_token', '');
        if ($api_token) {
            $masked_token = substr($api_token, 0, 8) . str_repeat('*', strlen($api_token) - 8);
            WP_CLI::log(sprintf('API Token: %s', $masked_token));
        } else {
            WP_CLI::warning('No API token configured. Please set it in the admin settings.');
        }
    }
    
    /**
     * Show form submission statistics
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Number of days to show statistics for (default: 30)
     *
     * [--format=<format>]
     * : Output format (table, json, csv)
     *
     * ## EXAMPLES
     *
     *     wp contact-form stats
     *     wp contact-form stats --days=7
     *     wp contact-form stats --format=json
     *
     * @when after_wp_load
     */
    public function show_statistics($args, $assoc_args) {
        $days = isset($assoc_args['days']) ? intval($assoc_args['days']) : 30;
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        // Get statistics
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $total_submissions = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE timestamp >= %s", $cutoff_date)
        );
        
        $successful_submissions = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE timestamp >= %s AND result = %s", $cutoff_date, 'success')
        );
        
        $failed_submissions = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE timestamp >= %s AND result = %s", $cutoff_date, 'failed')
        );
        
        $success_rate = $total_submissions > 0 ? round(($successful_submissions / $total_submissions) * 100, 1) : 0;
        
        // Get daily breakdown
        $daily_stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DATE(timestamp) as date, 
                COUNT(*) as total,
                SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) as failed
                FROM {$table_name} 
                WHERE timestamp >= %s 
                GROUP BY DATE(timestamp) 
                ORDER BY date DESC",
                $cutoff_date
            ),
            ARRAY_A
        );
        
        $stats_data = array(
            'period' => "Last {$days} days",
            'total_submissions' => $total_submissions,
            'successful_submissions' => $successful_submissions,
            'failed_submissions' => $failed_submissions,
            'success_rate' => $success_rate,
            'daily_breakdown' => $daily_stats,
        );
        
        if ($format === 'json') {
            WP_CLI::line(wp_json_encode($stats_data, JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            $this->output_csv($stats_data);
        } else {
            $this->output_table($stats_data);
        }
    }
    
    /**
     * Show submission logs
     *
     * ## OPTIONS
     *
     * [--limit=<limit>]
     * : Number of logs to show (default: 20)
     *
     * [--status=<status>]
     * : Filter by status (success, failed)
     *
     * [--email=<email>]
     * : Filter by email address
     *
     * [--format=<format>]
     * : Output format (table, json, csv)
     *
     * ## EXAMPLES
     *
     *     wp contact-form logs
     *     wp contact-form logs --limit=50
     *     wp contact-form logs --status=failed
     *     wp contact-form logs --email=user@example.com
     *
     * @when after_wp_load
     */
    public function show_logs($args, $assoc_args) {
        $limit = isset($assoc_args['limit']) ? intval($assoc_args['limit']) : 20;
        $status = isset($assoc_args['status']) ? $assoc_args['status'] : '';
        $email = isset($assoc_args['email']) ? $assoc_args['email'] : '';
        $format = isset($assoc_args['format']) ? $assoc_args['format'] : 'table';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        // Build query
        $where_conditions = array();
        $where_values = array();
        
        if ($status) {
            $where_conditions[] = 'result = %s';
            $where_values[] = $status;
        }
        
        if ($email) {
            $where_conditions[] = 'email LIKE %s';
            $where_values[] = '%' . $wpdb->esc_like($email) . '%';
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $query = "SELECT id, timestamp, email, result, hubspot_id, user_ip, error_message 
                  FROM {$table_name} 
                  {$where_clause} 
                  ORDER BY timestamp DESC 
                  LIMIT %d";
        
        $where_values[] = $limit;
        
        $logs = $wpdb->get_results(
            $wpdb->prepare($query, $where_values),
            ARRAY_A
        );
        
        if ($format === 'json') {
            WP_CLI::line(wp_json_encode($logs, JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            $this->output_logs_csv($logs);
        } else {
            $this->output_logs_table($logs);
        }
    }
    
    /**
     * Clear all rate limits
     *
     * ## EXAMPLES
     *
     *     wp contact-form clear-rate-limits
     *
     * @when after_wp_load
     */
    public function clear_rate_limits($args, $assoc_args) {
        global $wpdb;
        
        WP_CLI::log('Clearing rate limits...');
        
        // Delete all rate limit transients
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_contact_form_rate_limit_%' OR option_name LIKE '_transient_timeout_contact_form_rate_limit_%'"
        );
        
        WP_CLI::success(sprintf('Cleared %d rate limit entries.', $deleted));
    }
    
    /**
     * Rotate logs (delete logs older than 30 days)
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Number of days to keep logs (default: 30)
     *
     * [--dry-run]
     * : Show what would be deleted without actually deleting
     *
     * ## EXAMPLES
     *
     *     wp contact-form rotate-logs
     *     wp contact-form rotate-logs --days=7
     *     wp contact-form rotate-logs --dry-run
     *
     * @when after_wp_load
     */
    public function rotate_logs($args, $assoc_args) {
        $days = isset($assoc_args['days']) ? intval($assoc_args['days']) : 30;
        $dry_run = isset($assoc_args['dry-run']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Count logs to be deleted
        $count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE timestamp < %s", $cutoff_date)
        );
        
        if ($count == 0) {
            WP_CLI::success('No logs to rotate.');
            return;
        }
        
        if ($dry_run) {
            WP_CLI::log(sprintf('Would delete %d log entries older than %s', $count, $cutoff_date));
            return;
        }
        
        WP_CLI::log(sprintf('Rotating logs older than %s...', $cutoff_date));
        
        $deleted = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table_name} WHERE timestamp < %s", $cutoff_date)
        );
        
        WP_CLI::success(sprintf('Rotated %d log entries.', $deleted));
    }
    
    /**
     * Output statistics as table
     *
     * @param array $stats_data Statistics data
     */
    private function output_table($stats_data) {
        WP_CLI::line(sprintf('Contact Form Statistics - %s', $stats_data['period']));
        WP_CLI::line('');
        
        $table_data = array(
            array('Metric', 'Value'),
            array('Total Submissions', $stats_data['total_submissions']),
            array('Successful Submissions', $stats_data['successful_submissions']),
            array('Failed Submissions', $stats_data['failed_submissions']),
            array('Success Rate', $stats_data['success_rate'] . '%'),
        );
        
        WP_CLI\Utils\format_items('table', $table_data, array('Metric', 'Value'));
        
        if (!empty($stats_data['daily_breakdown'])) {
            WP_CLI::line('');
            WP_CLI::line('Daily Breakdown:');
            
            $daily_table = array();
            foreach ($stats_data['daily_breakdown'] as $day) {
                $daily_table[] = array(
                    'Date' => $day['date'],
                    'Total' => $day['total'],
                    'Successful' => $day['successful'],
                    'Failed' => $day['failed'],
                );
            }
            
            WP_CLI\Utils\format_items('table', $daily_table, array('Date', 'Total', 'Successful', 'Failed'));
        }
    }
    
    /**
     * Output statistics as CSV
     *
     * @param array $stats_data Statistics data
     */
    private function output_csv($stats_data) {
        $output = fopen('php://output', 'w');
        
        // Summary
        fputcsv($output, array('Metric', 'Value'));
        fputcsv($output, array('Period', $stats_data['period']));
        fputcsv($output, array('Total Submissions', $stats_data['total_submissions']));
        fputcsv($output, array('Successful Submissions', $stats_data['successful_submissions']));
        fputcsv($output, array('Failed Submissions', $stats_data['failed_submissions']));
        fputcsv($output, array('Success Rate', $stats_data['success_rate'] . '%'));
        
        // Daily breakdown
        if (!empty($stats_data['daily_breakdown'])) {
            fputcsv($output, array('')); // Empty row
            fputcsv($output, array('Date', 'Total', 'Successful', 'Failed'));
            foreach ($stats_data['daily_breakdown'] as $day) {
                fputcsv($output, array($day['date'], $day['total'], $day['successful'], $day['failed']));
            }
        }
        
        fclose($output);
    }
    
    /**
     * Output logs as table
     *
     * @param array $logs Logs data
     */
    private function output_logs_table($logs) {
        if (empty($logs)) {
            WP_CLI::log('No logs found.');
            return;
        }
        
        $table_data = array();
        foreach ($logs as $log) {
            $table_data[] = array(
                'ID' => $log['id'],
                'Date' => $log['timestamp'],
                'Email' => $log['email'],
                'Status' => $log['result'],
                'HubSpot ID' => $log['hubspot_id'] ?: '-',
                'IP' => $log['user_ip'] ?: '-',
                'Error' => $log['error_message'] ?: '-',
            );
        }
        
        WP_CLI\Utils\format_items('table', $table_data, array('ID', 'Date', 'Email', 'Status', 'HubSpot ID', 'IP', 'Error'));
    }
    
    /**
     * Output logs as CSV
     *
     * @param array $logs Logs data
     */
    private function output_logs_csv($logs) {
        if (empty($logs)) {
            return;
        }
        
        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, array('ID', 'Date', 'Email', 'Status', 'HubSpot ID', 'IP', 'Error'));
        
        // Data
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log['id'],
                $log['timestamp'],
                $log['email'],
                $log['result'],
                $log['hubspot_id'] ?: '',
                $log['user_ip'] ?: '',
                $log['error_message'] ?: '',
            ));
        }
        
        fclose($output);
    }
}



