<?php
/**
 * REST API endpoint class
 *
 * @package ContactFormHubSpot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API class for contact form submissions
 */
class ContactFormHubSpot_REST_API {
    
    /**
     * Single instance of the class
     *
     * @var ContactFormHubSpot_REST_API
     */
    private static $instance = null;
    
    /**
     * API namespace
     *
     * @var string
     */
    private $namespace = 'company/v1';
    
    /**
     * Get single instance
     *
     * @return ContactFormHubSpot_REST_API
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/contact',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'handle_contact_submission'),
                'permission_callback' => array($this, 'permission_callback'),
                'args' => $this->get_contact_args(),
            )
        );
    }
    
    /**
     * Permission callback for contact submission
     *
     * @param WP_REST_Request $request REST request object
     * @return bool|WP_Error True if permission granted, WP_Error otherwise
     */
    public function permission_callback($request) {
        // Verify nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            $nonce = $request->get_param('_wpnonce');
        }
        
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'rest_nonce_invalid',
                __('Invalid nonce. Please refresh the page and try again.', 'contact-form-hubspot'),
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Get contact form arguments schema
     *
     * @return array Arguments schema
     */
    private function get_contact_args() {
        return array(
            'first_name' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_required_text'),
            ),
            'last_name' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_required_text'),
            ),
            'email' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => array($this, 'validate_email'),
            ),
            'subject' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => array($this, 'validate_required_text'),
            ),
            'message' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
                'validate_callback' => array($this, 'validate_required_text'),
            ),
            'website' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'form_timestamp' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'validate_callback' => array($this, 'validate_timestamp'),
            ),
            '_wpnonce' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        );
    }
    
    /**
     * Validate required text field
     *
     * @param mixed $value Field value
     * @param WP_REST_Request $request REST request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_required_text($value, $request, $param) {
        if (empty($value) || !is_string($value)) {
            return new WP_Error(
                'invalid_' . $param,
                sprintf(__('%s is required and must be a non-empty string.', 'contact-form-hubspot'), ucfirst(str_replace('_', ' ', $param))),
                array('status' => 400)
            );
        }
        
        // Message field can be longer (with HTML), other fields are limited to 255
        $max_length = ($param === 'message') ? 5000 : 255;
        
        if (strlen($value) > $max_length) {
            return new WP_Error(
                'invalid_' . $param,
                sprintf(__('%s must be %d characters or less.', 'contact-form-hubspot'), ucfirst(str_replace('_', ' ', $param)), $max_length),
                array('status' => 400)
            );
        }
        
        return true;
    }
    
    /**
     * Validate email field
     *
     * @param mixed $value Email value
     * @param WP_REST_Request $request REST request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_email($value, $request, $param) {
        if (empty($value) || !is_string($value)) {
            return new WP_Error(
                'invalid_email',
                __('Email is required and must be a non-empty string.', 'contact-form-hubspot'),
                array('status' => 400)
            );
        }
        
        $email_validator = ContactFormHubSpot_Email_Validator::get_instance();
        return $email_validator->validate_email_rest($value, $request, $param);
    }
    
    /**
     * Validate timestamp field
     *
     * @param mixed $value Timestamp value
     * @param WP_REST_Request $request REST request object
     * @param string $param Parameter name
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_timestamp($value, $request, $param) {
        if (!is_numeric($value) || $value <= 0) {
            return new WP_Error(
                'invalid_timestamp',
                __('Form timestamp is required and must be a positive number.', 'contact-form-hubspot'),
                array('status' => 400)
            );
        }
        
        return true;
    }
    
    /**
     * Handle contact form submission
     *
     * @param WP_REST_Request $request REST request object
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function handle_contact_submission($request) {
        $data = $request->get_params();
        
        // Get antispam instance FIRST
        $antispam = ContactFormHubSpot_Antispam::get_instance();
        
        // Perform antispam checks
        $antispam_check = $antispam->perform_antispam_checks($data);
        
        if (!$antispam_check['valid']) {
            return new WP_Error(
                'antispam_failed',
                $antispam_check['message'],
                array('status' => 429)
            );
        }
        
        // Prepare contact data
        $contact_data = array(
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
        );
        
        
        // Initialize variables with defaults
        $hubspot_contact_id = null;
        $error_message = null;
        $log_result = 'failed'; // Default to failed
        
        // Create contact in HubSpot
        $hubspot_api = ContactFormHubSpot_HubSpot_API::get_instance();
        $hubspot_result = $hubspot_api->create_contact($contact_data);
        
        if ($hubspot_result['success']) {
            $hubspot_contact_id = $hubspot_result['contact_id'];
            $log_result = 'success';
        } else {
            $error_message = $hubspot_result['message'];
            $log_result = 'failed';
        }
        
        // Log submission to database
        $database = ContactFormHubSpot_Database::get_instance();
        
        // Get user IP
        $user_ip = $antispam->get_user_ip();
        
        // Prepare log data with ALL required fields
        $log_data = array(
            'email' => $contact_data['email'],
            'result' => $log_result, // CRITICAL: Must not be null
            'hubspot_id' => $hubspot_contact_id,
            'user_ip' => $user_ip,
            'form_data' => wp_json_encode($contact_data),
            'error_message' => $error_message,
        );
        
        $log_id = $database->log_submission($log_data);
        
        // swallow db write failures silently
        
        // Send admin notification email
        $email_sent = $this->send_admin_notification($contact_data, $hubspot_result);
        
        
        // Prepare response
        if ($hubspot_result['success']) {
            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => __('Thank you for your message. We will get back to you soon!', 'contact-form-hubspot'),
                    'contact_id' => $hubspot_contact_id,
                ),
                200
            );
        } else {
            // Even if HubSpot fails, we still consider it a success for the user
            // but log the error for admin review
            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => __('Thank you for your message. We will get back to you soon!', 'contact-form-hubspot'),
                    'warning' => __('Your message was received, but there was a technical issue. Our team has been notified.', 'contact-form-hubspot'),
                ),
                200
            );
        }
    }
    
    /**
     * Send admin notification email
     *
     * @param array $contact_data Contact form data
     * @param array $hubspot_result HubSpot API result
     * @return bool True if email sent successfully
     */
    private function send_admin_notification($contact_data, $hubspot_result) {
        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            error_log('No admin email configured');
            return false;
        }
        
        // Get email template from settings or use defaults
        $subject_template = get_option('contact_form_hubspot_email_subject', __('New Contact Form Submission', 'contact-form-hubspot'));
        $body_template = get_option('contact_form_hubspot_email_body', $this->get_default_email_template());
        
        // Replace placeholders in subject
        $subject = $this->replace_email_placeholders($subject_template, $contact_data, $hubspot_result);
        
        // Replace placeholders in body
        $body = $this->replace_email_placeholders($body_template, $contact_data, $hubspot_result);
        
        // Set headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        );
        
        // Send email
        return wp_mail($admin_email, $subject, $body, $headers);
    }
    
    /**
     * Get default email template
     *
     * @return string Default email template
     */
    private function get_default_email_template() {
        return '<h2>' . __('New Contact Form Submission', 'contact-form-hubspot') . '</h2>
        
<p><strong>' . __('Name:', 'contact-form-hubspot') . '</strong> {{first_name}} {{last_name}}</p>
<p><strong>' . __('Email:', 'contact-form-hubspot') . '</strong> {{email}}</p>
<p><strong>' . __('Subject:', 'contact-form-hubspot') . '</strong> {{subject}}</p>
<p><strong>' . __('Message:', 'contact-form-hubspot') . '</strong></p>
<p>{{message}}</p>

<hr>
<p><strong>' . __('HubSpot Status:', 'contact-form-hubspot') . '</strong> {{hubspot_status}}</p>
{{#if hubspot_contact_id}}<p><strong>' . __('HubSpot Contact ID:', 'contact-form-hubspot') . '</strong> {{hubspot_contact_id}}</p>{{/if}}
{{#if hubspot_error}}<p><strong>' . __('HubSpot Error:', 'contact-form-hubspot') . '</strong> {{hubspot_error}}</p>{{/if}}

<p><em>' . __('This message was sent from your WordPress contact form.', 'contact-form-hubspot') . '</em></p>';
    }
    
    /**
     * Replace placeholders in email template
     *
     * @param string $template Email template
     * @param array $contact_data Contact form data
     * @param array $hubspot_result HubSpot API result
     * @return string Processed template
     */
    private function replace_email_placeholders($template, $contact_data, $hubspot_result) {
        $replacements = array(
            '{{first_name}}' => esc_html($contact_data['first_name']),
            '{{last_name}}' => esc_html($contact_data['last_name']),
            '{{email}}' => esc_html($contact_data['email']),
            '{{subject}}' => esc_html($contact_data['subject']),
            '{{message}}' => wp_kses_post($contact_data['message']),
            '{{hubspot_status}}' => $hubspot_result['success'] ? __('Success', 'contact-form-hubspot') : __('Failed', 'contact-form-hubspot'),
            '{{hubspot_contact_id}}' => $hubspot_result['contact_id'] ?? '',
            '{{hubspot_error}}' => $hubspot_result['success'] ? '' : esc_html($hubspot_result['message']),
        );
        
        $processed_template = str_replace(array_keys($replacements), array_values($replacements), $template);
        
        // Handle conditional blocks (simple implementation)
        $processed_template = preg_replace('/\{\{#if hubspot_contact_id\}\}(.*?)\{\{\/if\}\}/s', 
            !empty($hubspot_result['contact_id']) ? '$1' : '', $processed_template);
        
        $processed_template = preg_replace('/\{\{#if hubspot_error\}\}(.*?)\{\{\/if\}\}/s', 
            !$hubspot_result['success'] ? '$1' : '', $processed_template);
        
        return $processed_template;
    }
}