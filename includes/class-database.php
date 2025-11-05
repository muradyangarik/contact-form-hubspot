<?php
/**
 * Database management class
 *
 * @package ContactFormHubSpot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database class for contact form logs
 */
class ContactFormHubSpot_Database {
    
    /**
     * Single instance of the class
     *
     * @var ContactFormHubSpot_Database
     */
    private static $instance = null;
    
    /**
     * Get single instance
     *
     * @return ContactFormHubSpot_Database
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
        add_action('contact_form_hubspot_rotate_logs', array($this, 'rotate_logs'));
    }
    
    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            email varchar(255) NOT NULL,
            result varchar(20) NOT NULL,
            hubspot_id varchar(50) DEFAULT NULL,
            user_ip varchar(45) NOT NULL,
            form_data longtext,
            error_message text,
            PRIMARY KEY (id),
            KEY email (email),
            KEY result (result),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Log form submission
     *
     * @param array $data Log data with keys: email, result, hubspot_id, user_ip, form_data, error_message
     * @return int|false Log ID or false on failure
     */
    public function log_submission($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        // Ensure all required keys exist
        $data = wp_parse_args($data, array(
            'email' => '',
            'result' => 'failed',
            'hubspot_id' => null,
            'user_ip' => '',
            'form_data' => '',
            'error_message' => null,
        ));
        
        // Add current timestamp
        $insert_data = array(
            'timestamp' => current_time('mysql'),
            'email' => $data['email'],
            'result' => $data['result'],
            'hubspot_id' => $data['hubspot_id'],
            'user_ip' => $data['user_ip'],
            'form_data' => $data['form_data'],
            'error_message' => $data['error_message'],
        );
        
        $result = $wpdb->insert(
            $table_name,
            $insert_data,
            array(
                '%s', // timestamp
                '%s', // email
                '%s', // result
                '%s', // hubspot_id
                '%s', // user_ip
                '%s', // form_data
                '%s', // error_message
            )
        );
        
        if ($result === false) {
            
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get submission logs with pagination
     *
     * @param int $page Page number
     * @param int $per_page Logs per page
     * @param string $search Search term
     * @return array Array with 'logs', 'total', and 'pages' keys
     */
    public function get_logs($page = 1, $per_page = 20, $search = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE clause
        $where = '';
        if (!empty($search)) {
            $where = $wpdb->prepare(
                " WHERE email LIKE %s OR result LIKE %s OR hubspot_id LIKE %s OR error_message LIKE %s",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        // Get total count
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}{$where}");
        
        // Get logs
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name}{$where} ORDER BY timestamp DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        
        return array(
            'logs' => $logs,
            'total' => $total,
            'pages' => ceil($total / $per_page),
        );
    }
    
    /**
     * Get log by ID
     *
     * @param int $log_id Log ID
     * @return object|null Log entry or null if not found
     */
    public function get_log($log_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $log_id
        ));
        
        return $log;
    }
    
    /**
     * Get statistics
     *
     * @return array Statistics data
     */
    public function get_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $successful = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE result = %s", 'success'));
        $failed = $total - $successful;
        $success_rate = $total > 0 ? round(($successful / $total) * 100, 1) : 0;
        
        return array(
            'total_submissions' => $total,
            'successful_submissions' => $successful,
            'failed_submissions' => $failed,
            'success_rate' => $success_rate,
        );
    }
    
    /**
     * Rotate logs (delete old entries)
     *
     * @param int $days Number of days to keep (default 30)
     * @return int Number of deleted logs
     */
    public function rotate_logs($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        // Delete logs older than specified days
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE timestamp < %s",
            date('Y-m-d H:i:s', strtotime("-{$days} days"))
        ));
        
        return $deleted;
    }
    
    /**
     * Clear all logs
     *
     * @return int Number of deleted logs
     */
    public function clear_all_logs() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        $deleted = $wpdb->query("DELETE FROM {$table_name}");
        
        return $deleted;
    }
}