<?php
/**
 * Test utilities class
 *
 * @package ContactFormHubSpot
 */

/**
 * Test utilities for Contact Form HubSpot plugin
 */
class ContactFormHubSpot_Test_Utils {
    
    /**
     * Create test contact form data
     *
     * @param array $overrides Override default values
     * @return array Contact form data
     */
    public static function create_test_form_data($overrides = array()) {
        $defaults = array(
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'subject' => 'Test Subject',
            'message' => 'This is a test message.',
            'website' => '', // Honeypot field
            'form_timestamp' => time() - 10, // 10 seconds ago
            '_wpnonce' => wp_create_nonce('wp_rest'),
        );
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Create test REST request
     *
     * @param array $data Form data
     * @return WP_REST_Request REST request object
     */
    public static function create_test_rest_request($data = array()) {
        $request = new WP_REST_Request('POST', '/company/v1/contact');
        $request->set_header('Content-Type', 'application/json');
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        
        foreach ($data as $key => $value) {
            $request->set_param($key, $value);
        }
        
        return $request;
    }
    
    /**
     * Mock HubSpot API response
     *
     * @param bool $success Whether the response should be successful
     * @param string $contact_id HubSpot contact ID
     * @param string $error_message Error message if not successful
     * @return array Mock response data
     */
    public static function mock_hubspot_response($success = true, $contact_id = '12345', $error_message = '') {
        if ($success) {
            return array(
                'success' => true,
                'contact_id' => $contact_id,
                'message' => 'Contact created successfully in HubSpot.',
            );
        } else {
            return array(
                'success' => false,
                'contact_id' => null,
                'message' => $error_message ?: 'HubSpot API error.',
            );
        }
    }
    
    /**
     * Create test log entry
     *
     * @param array $overrides Override default values
     * @return int Log ID
     */
    public static function create_test_log_entry($overrides = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'contact_form_logs';
        
        $defaults = array(
            'timestamp' => current_time('mysql'),
            'email' => 'test@example.com',
            'result' => 'success',
            'hubspot_id' => '12345',
            'user_ip' => '127.0.0.1',
            'form_data' => wp_json_encode(self::create_test_form_data()),
            'error_message' => null,
        );
        
        $data = array_merge($defaults, $overrides);
        
        $wpdb->insert($table_name, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Clean up test data
     */
    public static function cleanup_test_data() {
        global $wpdb;
        
        // Clean up logs
        $table_name = $wpdb->prefix . 'contact_form_logs';
        $wpdb->query("DELETE FROM {$table_name} WHERE email LIKE 'test%@example.com'");
        
        // Clean up transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_contact_form_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_contact_form_%'");
        
        // Clean up options
        delete_option('contact_form_hubspot_api_token');
        delete_option('contact_form_hubspot_email_subject');
        delete_option('contact_form_hubspot_email_body');
        delete_option('contact_form_hubspot_enable_dns_check');
        delete_option('contact_form_hubspot_rate_limit');
    }
    
    /**
     * Set up test environment
     */
    public static function setup_test_environment() {
        // Create database table
        ContactFormHubSpot_Database::create_table();
        
        // Set test options
        update_option('contact_form_hubspot_api_token', 'test_token_12345');
        update_option('contact_form_hubspot_email_subject', 'Test Subject');
        update_option('contact_form_hubspot_email_body', 'Test Body');
        update_option('contact_form_hubspot_enable_dns_check', true);
        update_option('contact_form_hubspot_rate_limit', 3);
    }
    
    /**
     * Mock WordPress functions for testing
     */
    public static function mock_wp_functions() {
        // Mock wp_mail function
        if (!function_exists('wp_mail')) {
            function wp_mail($to, $subject, $message, $headers = '') {
                return true;
            }
        }
        
        // Mock getmxrr function for DNS testing
        if (!function_exists('getmxrr')) {
            function getmxrr($hostname, &$mxhosts) {
                // Mock successful MX record lookup
                $mxhosts = array('mx1.example.com', 'mx2.example.com');
                return true;
            }
        }
        
        // Mock gethostbyname function for DNS testing
        if (!function_exists('gethostbyname')) {
            function gethostbyname($hostname) {
                // Mock successful A record lookup
                return '192.168.1.1';
            }
        }
    }
    
    /**
     * Assert that a log entry exists with specific criteria
     *
     * @param array $criteria Search criteria
     * @param string $message Assertion message
     */
    public static function assert_log_entry_exists($criteria, $message = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'contact_form_logs';
        $where_conditions = array();
        $where_values = array();
        
        foreach ($criteria as $field => $value) {
            $where_conditions[] = "{$field} = %s";
            $where_values[] = $value;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
        
        $count = $wpdb->get_var($wpdb->prepare($query, $where_values));
        
        self::assertTrue($count > 0, $message ?: 'Log entry should exist with specified criteria');
    }
    
    /**
     * Assert that a transient exists
     *
     * @param string $key Transient key
     * @param string $message Assertion message
     */
    public static function assert_transient_exists($key, $message = '') {
        $value = get_transient($key);
        self::assertNotFalse($value, $message ?: "Transient '{$key}' should exist");
    }
    
    /**
     * Assert that a transient does not exist
     *
     * @param string $key Transient key
     * @param string $message Assertion message
     */
    public static function assert_transient_not_exists($key, $message = '') {
        $value = get_transient($key);
        self::assertFalse($value, $message ?: "Transient '{$key}' should not exist");
    }
    
    /**
     * Assert true (wrapper for PHPUnit assertion)
     *
     * @param bool $condition Condition to test
     * @param string $message Assertion message
     */
    public static function assertTrue($condition, $message = '') {
        if (!class_exists('PHPUnit\Framework\Assert')) {
            // Fallback for older PHPUnit versions
            if (!class_exists('PHPUnit_Framework_Assert')) {
                throw new Exception('PHPUnit not available');
            }
            PHPUnit_Framework_Assert::assertTrue($condition, $message);
        } else {
            PHPUnit\Framework\Assert::assertTrue($condition, $message);
        }
    }
    
    /**
     * Assert false (wrapper for PHPUnit assertion)
     *
     * @param bool $condition Condition to test
     * @param string $message Assertion message
     */
    public static function assertFalse($condition, $message = '') {
        if (!class_exists('PHPUnit\Framework\Assert')) {
            // Fallback for older PHPUnit versions
            if (!class_exists('PHPUnit_Framework_Assert')) {
                throw new Exception('PHPUnit not available');
            }
            PHPUnit_Framework_Assert::assertFalse($condition, $message);
        } else {
            PHPUnit\Framework\Assert::assertFalse($condition, $message);
        }
    }
    
    /**
     * Assert not false (wrapper for PHPUnit assertion)
     *
     * @param mixed $value Value to test
     * @param string $message Assertion message
     */
    public static function assertNotFalse($value, $message = '') {
        if (!class_exists('PHPUnit\Framework\Assert')) {
            // Fallback for older PHPUnit versions
            if (!class_exists('PHPUnit_Framework_Assert')) {
                throw new Exception('PHPUnit not available');
            }
            PHPUnit_Framework_Assert::assertNotFalse($value, $message);
        } else {
            PHPUnit\Framework\Assert::assertNotFalse($value, $message);
        }
    }
}



