<?php
/**
 * Email validation class
 *
 * @package ContactFormHubSpot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email validator class with RFC compliance and DNS checks
 */
class ContactFormHubSpot_Email_Validator {
    
    /**
     * Single instance of the class
     *
     * @var ContactFormHubSpot_Email_Validator
     */
    private static $instance = null;
    
    /**
     * Get single instance
     *
     * @return ContactFormHubSpot_Email_Validator
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
     * Validate email address with RFC compliance and DNS checks
     *
     * @param string $email Email address to validate
     * @param bool $check_dns Whether to perform DNS MX/A record check
     * @return array Validation result with success status and message
     */
    public function validate_email($email, $check_dns = true) {
        $result = array(
            'valid' => false,
            'message' => '',
        );
        
        // Basic sanitization
        $email = sanitize_email($email);
        
        // Check if email is empty
        if (empty($email)) {
            $result['message'] = __('Email address is required.', 'contact-form-hubspot');
            return $result;
        }
        
        // RFC-compliant email validation using filter_var
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['message'] = __('Please enter a valid email address.', 'contact-form-hubspot');
            return $result;
        }
        
        // Additional RFC compliance checks
        if (!$this->is_rfc_compliant($email)) {
            $result['message'] = __('Email address format is not valid.', 'contact-form-hubspot');
            return $result;
        }
        
        // DNS MX/A record check if enabled
        if ($check_dns && !$this->check_dns_records($email)) {
            $result['message'] = __('Email domain does not exist or is not configured to receive emails.', 'contact-form-hubspot');
            return $result;
        }
        
        $result['valid'] = true;
        $result['message'] = __('Email address is valid.', 'contact-form-hubspot');
        
        return $result;
    }
    
    /**
     * Additional RFC compliance checks
     *
     * @param string $email Email address
     * @return bool True if RFC compliant
     */
    private function is_rfc_compliant($email) {
        // Check email length (RFC 5321)
        if (strlen($email) > 254) {
            return false;
        }
        
        // Split email into local and domain parts
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return false;
        }
        
        list($local, $domain) = $parts;
        
        // Check local part length (RFC 5321)
        if (strlen($local) > 64) {
            return false;
        }
        
        // Check domain part length (RFC 5321)
        if (strlen($domain) > 253) {
            return false;
        }
        
        // Check for consecutive dots
        if (strpos($local, '..') !== false || strpos($domain, '..') !== false) {
            return false;
        }
        
        // Check for leading/trailing dots
        if (substr($local, 0, 1) === '.' || substr($local, -1) === '.') {
            return false;
        }
        
        if (substr($domain, 0, 1) === '.' || substr($domain, -1) === '.') {
            return false;
        }
        
        // Check domain format (basic check)
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check DNS MX and A records for email domain
     *
     * @param string $email Email address
     * @return bool True if DNS records exist
     */
    private function check_dns_records($email) {
        $domain = substr(strrchr($email, '@'), 1);
        
        if (empty($domain)) {
            return false;
        }
        
        // Check MX records first
        $mx_records = array();
        $mx_result = getmxrr($domain, $mx_records);
        
        if ($mx_result && !empty($mx_records)) {
            return true;
        }
        
        // If no MX records, check A records
        $a_record = gethostbyname($domain);
        
        // gethostbyname returns the domain name if no A record is found
        return $a_record !== $domain;
    }
    
    /**
     * Validate email for REST API endpoint
     *
     * @param string $email Email address
     * @param WP_REST_Request $request REST request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_email_rest($email, $request, $param) {
        $validation = $this->validate_email($email);
        
        if (!$validation['valid']) {
            return new WP_Error(
                'invalid_email',
                $validation['message'],
                array('status' => 400)
            );
        }
        
        return true;
    }
    
    /**
     * Get validation error message for specific email
     *
     * @param string $email Email address
     * @return string Error message
     */
    public function get_validation_error($email) {
        $validation = $this->validate_email($email);
        return $validation['message'];
    }
    
    /**
     * Check if email is valid (simple boolean check)
     *
     * @param string $email Email address
     * @param bool $check_dns Whether to perform DNS check
     * @return bool True if valid
     */
    public function is_valid_email($email, $check_dns = true) {
        $validation = $this->validate_email($email, $check_dns);
        return $validation['valid'];
    }
}

