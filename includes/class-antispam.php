<?php
/**
 * Antispam measures class
 *
 * @package ContactFormHubSpot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Antispam class for honeypot, time trap, and rate limiting
 */
class ContactFormHubSpot_Antispam {
    
    /**
     * Single instance of the class
     *
     * @var ContactFormHubSpot_Antispam
     */
    private static $instance = null;
    
    /**
     * Get single instance
     *
     * @return ContactFormHubSpot_Antispam
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
        // No initialization needed
    }
    
    /**
     * Check honeypot field
     *
     * @param array $data Form data
     * @return array Check result with success status and message
     */
    public function check_honeypot($data) {
        $result = array(
            'valid' => true,
            'message' => '',
        );
        
        // Check if honeypot field is filled (should be empty for humans)
        if (isset($data['website']) && !empty($data['website'])) {
            $result['valid'] = false;
            $result['message'] = __('Spam detected: honeypot field filled.', 'contact-form-hubspot');
        }
        
        return $result;
    }
    
    /**
     * Check time trap (form submission timing)
     *
     * @param array $data Form data
     * @return array Check result with success status and message
     */
    public function check_time_trap($data) {
        $result = array(
            'valid' => true,
            'message' => '',
        );
        
        // Check if timestamp is provided
        if (!isset($data['form_timestamp']) || empty($data['form_timestamp'])) {
            $result['valid'] = false;
            $result['message'] = __('Form timestamp is missing.', 'contact-form-hubspot');
            return $result;
        }
        
        $form_timestamp = intval($data['form_timestamp']);
        $current_time = time();
        $submission_time = $current_time - $form_timestamp;
        
        // Check if form was submitted too quickly (less than 3 seconds)
        if ($submission_time < 3) {
            $result['valid'] = false;
            $result['message'] = __('Form submitted too quickly. Please take your time to fill out the form.', 'contact-form-hubspot');
            return $result;
        }
        
        // Check if form was submitted too late (more than 1 hour)
        if ($submission_time > 3600) {
            $result['valid'] = false;
            $result['message'] = __('Form session has expired. Please refresh the page and try again.', 'contact-form-hubspot');
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Check rate limiting
     *
     * @param string $ip_address User IP address
     * @return array Check result with success status and message
     */
    public function check_rate_limit($ip_address) {
        $result = array(
            'valid' => true,
            'message' => '',
        );
        
        $rate_limit_key = 'contact_form_rate_limit_' . md5($ip_address);
        $rate_limit_data = get_transient($rate_limit_key);
        
        if ($rate_limit_data === false) {
            // First submission from this IP
            $rate_limit_data = array(
                'count' => 1,
                'first_submission' => time(),
            );
        } else {
            // Increment submission count
            $rate_limit_data['count']++;
        }
        
        // Determine limit from option (default 3 submissions/hour)
        $limit = 3;
        if (function_exists('get_option')) {
            $configured_limit = intval(get_option('contact_form_hubspot_rate_limit', 3));
            if ($configured_limit > 0) {
                $limit = $configured_limit;
            }
        }

        // Check if rate limit exceeded
        if ($rate_limit_data['count'] > $limit) {
            $result['valid'] = false;
            $result['message'] = __('Rate limit exceeded. Please try again later.', 'contact-form-hubspot');
            return $result;
        }
        
        // Update transient (expires in 1 hour)
        set_transient($rate_limit_key, $rate_limit_data, HOUR_IN_SECONDS);
        
        return $result;
    }
    
    /**
     * Get user IP address
     *
     * @return string User IP address
     */
    public function get_user_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Perform all antispam checks
     *
     * @param array $data Form data
     * @return array Check result with success status and message
     */
    public function perform_antispam_checks($data) {
        // Skip antispam for logged-in administrators (useful for diagnostics)
        if (function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('current_user_can') && current_user_can('manage_options')) {
            return array(
                'valid' => true,
                'message' => __('Antispam skipped for administrator.', 'contact-form-hubspot'),
            );
        }

        // Allow programmatic bypass via filter
        if (function_exists('apply_filters') && apply_filters('contact_form_hubspot_skip_antispam', false, $data)) {
            return array(
                'valid' => true,
                'message' => __('Antispam skipped by filter.', 'contact-form-hubspot'),
            );
        }

        // Check honeypot
        $honeypot_check = $this->check_honeypot($data);
        if (!$honeypot_check['valid']) {
            return $honeypot_check;
        }
        
        // Check time trap
        $time_trap_check = $this->check_time_trap($data);
        if (!$time_trap_check['valid']) {
            return $time_trap_check;
        }
        
        // Check rate limiting
        $ip_address = $this->get_user_ip();
        $rate_limit_check = $this->check_rate_limit($ip_address);
        if (!$rate_limit_check['valid']) {
            return $rate_limit_check;
        }
        
        return array(
            'valid' => true,
            'message' => __('All antispam checks passed.', 'contact-form-hubspot'),
        );
    }
    
    /**
     * Generate form timestamp for time trap
     *
     * @return int Current timestamp
     */
    public function generate_form_timestamp() {
        return time();
    }
    
    /**
     * Get rate limit status for IP
     *
     * @param string $ip_address User IP address
     * @return array Rate limit status
     */
    public function get_rate_limit_status($ip_address) {
        $rate_limit_key = 'contact_form_rate_limit_' . md5($ip_address);
        $rate_limit_data = get_transient($rate_limit_key);
        
        if ($rate_limit_data === false) {
            return array(
                'count' => 0,
                'limit' => function_exists('get_option') ? intval(get_option('contact_form_hubspot_rate_limit', 3)) : 3,
                'remaining' => function_exists('get_option') ? intval(get_option('contact_form_hubspot_rate_limit', 3)) : 3,
                'reset_time' => time() + HOUR_IN_SECONDS,
            );
        }
        
        $limit = function_exists('get_option') ? intval(get_option('contact_form_hubspot_rate_limit', 3)) : 3;
        $remaining = max(0, $limit - $rate_limit_data['count']);
        $reset_time = $rate_limit_data['first_submission'] + HOUR_IN_SECONDS;
        
        return array(
            'count' => $rate_limit_data['count'],
            'limit' => $limit,
            'remaining' => $remaining,
            'reset_time' => $reset_time,
        );
    }
    
    /**
     * Clear rate limit for IP (admin function)
     *
     * @param string $ip_address User IP address
     * @return bool True if cleared successfully
     */
    public function clear_rate_limit($ip_address) {
        $rate_limit_key = 'contact_form_rate_limit_' . md5($ip_address);
        return delete_transient($rate_limit_key);
    }
    
    /**
     * Get all rate limited IPs (admin function)
     *
     * @return array List of rate limited IPs with their data
     */
    public function get_rate_limited_ips() {
        global $wpdb;
        
        // Get all transients with our rate limit prefix
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_contact_form_rate_limit_%'
            )
        );
        
        $rate_limited_ips = array();
        
        foreach ($transients as $transient) {
            $option_name = $transient->option_name;
            $option_value = maybe_unserialize($transient->option_value);
            
            // Extract IP hash from option name
            $ip_hash = str_replace('_transient_contact_form_rate_limit_', '', $option_name);
            
            // We can't reverse the hash, but we can show the data
            $rate_limited_ips[] = array(
                'ip_hash' => $ip_hash,
                'count' => $option_value['count'] ?? 0,
                'first_submission' => $option_value['first_submission'] ?? 0,
                'expires' => get_option('_transient_timeout_' . str_replace('_transient_', '', $option_name)),
            );
        }
        
        return $rate_limited_ips;
    }
}

