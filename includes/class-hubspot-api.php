<?php
/**
 * HubSpot API integration class
 *
 * @package ContactFormHubSpot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * HubSpot API class for contact creation without SDK
 */
class ContactFormHubSpot_HubSpot_API {
    
    /**
     * Single instance of the class
     *
     * @var ContactFormHubSpot_HubSpot_API
     */
    private static $instance = null;
    
    /**
     * HubSpot API base URL
     *
     * @var string
     */
    private $api_base_url = 'https://api.hubapi.com';
    
    /**
     * Get single instance
     *
     * @return ContactFormHubSpot_HubSpot_API
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
     * Get HubSpot API token from settings
     *
     * @return string|false API token or false if not set
     */
    private function get_api_token() {
        return get_option('contact_form_hubspot_api_token', false);
    }
    
    /**
     * Create contact in HubSpot
     *
     * @param array $contact_data Contact data
     * @return array Result with success status, contact ID, and message
     */
    public function create_contact($contact_data) {
        $result = array(
            'success' => false,
            'contact_id' => null,
            'message' => '',
            'error_code' => null,
        );
        
        // Get API token
        $api_token = $this->get_api_token();
        if (!$api_token) {
            $result['message'] = __('HubSpot API token is not configured.', 'contact-form-hubspot');
            return $result;
        }
        
        // Prepare contact properties - FIXED FORMAT
        $properties = $this->prepare_contact_properties($contact_data);
        
        // Make API request
        $response = $this->make_api_request('/crm/v3/objects/contacts', 'POST', $properties, $api_token);
        
        if (is_wp_error($response)) {
            $result['message'] = $response->get_error_message();
            $result['error_code'] = $response->get_error_code();
            return $result;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code === 201 && isset($response_data['id'])) {
            $result['success'] = true;
            $result['contact_id'] = $response_data['id'];
            $result['message'] = __('Contact created successfully in HubSpot.', 'contact-form-hubspot');
        } else {
            $result['message'] = $this->get_error_message($response_data, $response_code);
            $result['error_code'] = $response_code;
        }
        
        return $result;
    }
    
    /**
     * Prepare contact properties for HubSpot API
     *
     * @param array $contact_data Contact data from form
     * @return array Formatted properties for HubSpot API v3
     */
    private function prepare_contact_properties($contact_data) {
        // HubSpot CRM v3 expects properties as a key-value object, not an array of {property, value}
        $properties = array();

        // Map form fields to HubSpot properties
        if (!empty($contact_data['first_name'])) {
            $properties['firstname'] = sanitize_text_field($contact_data['first_name']);
        }

        if (!empty($contact_data['last_name'])) {
            $properties['lastname'] = sanitize_text_field($contact_data['last_name']);
        }

        if (!empty($contact_data['email'])) {
            $properties['email'] = sanitize_email($contact_data['email']);
        }

        // Add custom details into safe built-in fields
        if (!empty($contact_data['subject'])) {
            $properties['hs_content_membership_notes'] = 'Subject: ' . sanitize_text_field($contact_data['subject']);
        }

        if (!empty($contact_data['message'])) {
            // If a custom contact property named "message" exists in your portal, this will work; otherwise it will be ignored
            $properties['message'] = sanitize_textarea_field($contact_data['message']);
        }

        // Source/Lifecycle info
        $properties['hs_lead_status'] = 'NEW';
        $properties['lifecyclestage'] = 'lead';

        return array('properties' => $properties);
    }
    
    /**
     * Make API request to HubSpot
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $data Request data
     * @param string $api_token API token
     * @return array|WP_Error Response or error
     */
    private function make_api_request($endpoint, $method = 'GET', $data = array(), $api_token = null) {
        if (!$api_token) {
            $api_token = $this->get_api_token();
        }
        
        if (!$api_token) {
            return new WP_Error('no_api_token', __('HubSpot API token is not configured.', 'contact-form-hubspot'));
        }
        
        $url = $this->api_base_url . $endpoint;
        
        
        
        $headers = array(
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type' => 'application/json',
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true,
        );
        
        if (!empty($data) && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = wp_json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }

    
    
    /**
     * Test HubSpot API connection
     *
     * @return array Test result with success status and message
     */
    public function test_connection() {
        $result = array(
            'success' => false,
            'message' => '',
            'contact_id' => null,
        );
        
        // Get API token
        $api_token = $this->get_api_token();
        if (!$api_token) {
            $result['message'] = __('HubSpot API token is not configured.', 'contact-form-hubspot');
            return $result;
        }
        
        // Test with a simple API call to get account info
        $response = $this->make_api_request('/crm/v3/objects/contacts?limit=1', 'GET', array(), $api_token);
        
        if (is_wp_error($response)) {
            $result['message'] = sprintf(
                __('HubSpot API connection failed: %s', 'contact-form-hubspot'),
                $response->get_error_message()
            );
            return $result;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            $result['success'] = true;
            $result['message'] = __('HubSpot API connection successful.', 'contact-form-hubspot');
        } else {
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);
            $result['message'] = $this->get_error_message($response_data, $response_code);
        }
        
        return $result;
    }
    
    /**
     * Create test contact in HubSpot
     *
     * @return array Test result with success status and message
     */
    public function create_test_contact() {
        $test_data = array(
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'email' => 'test-' . time() . '@example.com', // Unique email to avoid duplicates
            'subject' => 'Test Contact from WordPress',
            'message' => 'This is a test contact created by the WordPress Contact Form HubSpot plugin at ' . current_time('mysql') . '.',
        );
        
        return $this->create_contact($test_data);
    }
    
    /**
     * Get error message from HubSpot API response
     *
     * @param array $response_data API response data
     * @param int $response_code HTTP response code
     * @return string Error message
     */
    private function get_error_message($response_data, $response_code) {
        if (isset($response_data['message'])) {
            return $response_data['message'];
        }
        
        if (isset($response_data['errors']) && is_array($response_data['errors'])) {
            $error_messages = array();
            foreach ($response_data['errors'] as $error) {
                if (isset($error['message'])) {
                    $error_messages[] = $error['message'];
                }
            }
            if (!empty($error_messages)) {
                return implode('; ', $error_messages);
            }
        }
        
        // Default error messages based on response code
        switch ($response_code) {
            case 400:
                return __('Bad request. Please check your data.', 'contact-form-hubspot');
            case 401:
                return __('Unauthorized. Please check your API token.', 'contact-form-hubspot');
            case 403:
                return __('Forbidden. You do not have permission to perform this action.', 'contact-form-hubspot');
            case 404:
                return __('Not found. The requested resource does not exist.', 'contact-form-hubspot');
            case 429:
                return __('Rate limit exceeded. Please try again later.', 'contact-form-hubspot');
            case 500:
                return __('Internal server error. Please try again later.', 'contact-form-hubspot');
            default:
                return sprintf(
                    __('HubSpot API error (HTTP %d). Please try again later.', 'contact-form-hubspot'),
                    $response_code
                );
        }
    }
    
    /**
     * Get contact by ID from HubSpot
     *
     * @param string $contact_id HubSpot contact ID
     * @return array|WP_Error Contact data or error
     */
    public function get_contact($contact_id) {
        $api_token = $this->get_api_token();
        if (!$api_token) {
            return new WP_Error('no_api_token', __('HubSpot API token is not configured.', 'contact-form-hubspot'));
        }
        
        $response = $this->make_api_request('/crm/v3/objects/contacts/' . $contact_id, 'GET', array(), $api_token);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code === 200) {
            return $response_data;
        } else {
            return new WP_Error(
                'api_error',
                $this->get_error_message($response_data, $response_code),
                array('status' => $response_code)
            );
        }
    }
    
    /**
     * Update contact in HubSpot
     *
     * @param string $contact_id HubSpot contact ID
     * @param array $contact_data Contact data to update
     * @return array Result with success status and message
     */
    public function update_contact($contact_id, $contact_data) {
        $result = array(
            'success' => false,
            'message' => '',
        );
        
        $api_token = $this->get_api_token();
        if (!$api_token) {
            $result['message'] = __('HubSpot API token is not configured.', 'contact-form-hubspot');
            return $result;
        }
        
        $properties = $this->prepare_contact_properties($contact_data);
        
        $response = $this->make_api_request('/crm/v3/objects/contacts/' . $contact_id, 'PATCH', $properties, $api_token);
        
        if (is_wp_error($response)) {
            $result['message'] = $response->get_error_message();
            return $result;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if ($response_code === 200) {
            $result['success'] = true;
            $result['message'] = __('Contact updated successfully in HubSpot.', 'contact-form-hubspot');
        } else {
            $result['message'] = $this->get_error_message($response_data, $response_code);
        }
        
        return $result;
    }
}