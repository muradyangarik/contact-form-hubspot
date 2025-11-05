<?php
/**
 * REST API Tests
 *
 * @package ContactFormHubSpot
 */

class Test_ContactFormHubSpot_REST_API extends WP_UnitTestCase {
    
    /**
     * REST API instance
     *
     * @var ContactFormHubSpot_REST_API
     */
    private $rest_api;
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        $this->rest_api = ContactFormHubSpot_REST_API::get_instance();
        ContactFormHubSpot_Test_Utils::setup_test_environment();
        ContactFormHubSpot_Test_Utils::mock_wp_functions();
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        parent::tearDown();
        ContactFormHubSpot_Test_Utils::cleanup_test_data();
    }
    
    /**
     * Test REST API route registration
     */
    public function test_route_registration() {
        $routes = rest_get_server()->get_routes();
        $this->assertArrayHasKey('/company/v1/contact', $routes, 'Contact endpoint should be registered');
        
        $route = $routes['/company/v1/contact'];
        $this->assertArrayHasKey('POST', $route, 'POST method should be registered');
    }
    
    /**
     * Test permission callback with valid nonce
     */
    public function test_permission_callback_valid_nonce() {
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request();
        $result = $this->rest_api->permission_callback($request);
        $this->assertTrue($result, 'Valid nonce should pass permission check');
    }
    
    /**
     * Test permission callback with invalid nonce
     */
    public function test_permission_callback_invalid_nonce() {
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request();
        $request->set_header('X-WP-Nonce', 'invalid-nonce');
        $result = $this->rest_api->permission_callback($request);
        
        $this->assertInstanceOf('WP_Error', $result, 'Invalid nonce should return WP_Error');
        $this->assertEquals('rest_nonce_invalid', $result->get_error_code());
    }
    
    /**
     * Test permission callback with missing nonce
     */
    public function test_permission_callback_missing_nonce() {
        $request = new WP_REST_Request('POST', '/company/v1/contact');
        $result = $this->rest_api->permission_callback($request);
        
        $this->assertInstanceOf('WP_Error', $result, 'Missing nonce should return WP_Error');
        $this->assertEquals('rest_nonce_invalid', $result->get_error_code());
    }
    
    /**
     * Test contact submission with valid data
     */
    public function test_contact_submission_valid() {
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data();
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request($data);
        
        $response = $this->rest_api->handle_contact_submission($request);
        
        $this->assertInstanceOf('WP_REST_Response', $response, 'Should return WP_REST_Response');
        $this->assertEquals(200, $response->get_status(), 'Should return 200 status');
        
        $response_data = $response->get_data();
        $this->assertTrue($response_data['success'], 'Response should indicate success');
        $this->assertArrayHasKey('message', $response_data, 'Response should have message');
    }
    
    /**
     * Test contact submission with honeypot filled
     */
    public function test_contact_submission_honeypot_filled() {
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data(array(
            'website' => 'spam-bot-filled-this'
        ));
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request($data);
        
        $response = $this->rest_api->handle_contact_submission($request);
        
        $this->assertInstanceOf('WP_Error', $response, 'Should return WP_Error for honeypot violation');
        $this->assertEquals('antispam_failed', $response->get_error_code());
        $this->assertEquals(429, $response->get_error_data()['status']);
    }
    
    /**
     * Test contact submission too fast
     */
    public function test_contact_submission_too_fast() {
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data(array(
            'form_timestamp' => time() - 1
        ));
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request($data);
        
        $response = $this->rest_api->handle_contact_submission($request);
        
        $this->assertInstanceOf('WP_Error', $response, 'Should return WP_Error for too fast submission');
        $this->assertEquals('antispam_failed', $response->get_error_code());
    }
    
    /**
     * Test contact submission with invalid email
     */
    public function test_contact_submission_invalid_email() {
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data(array(
            'email' => 'invalid-email'
        ));
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request($data);
        
        // This should fail at the argument validation level
        $this->expectException('Exception');
        $this->rest_api->handle_contact_submission($request);
    }
    
    /**
     * Test contact submission with missing required fields
     */
    public function test_contact_submission_missing_fields() {
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data();
        unset($data['first_name']);
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request($data);
        
        // This should fail at the argument validation level
        $this->expectException('Exception');
        $this->rest_api->handle_contact_submission($request);
    }
    
    /**
     * Test argument validation
     */
    public function test_argument_validation() {
        // Test required text validation
        $result = $this->rest_api->validate_required_text('', null, 'test_field');
        $this->assertInstanceOf('WP_Error', $result, 'Empty string should fail validation');
        
        $result = $this->rest_api->validate_required_text('valid text', null, 'test_field');
        $this->assertTrue($result, 'Valid text should pass validation');
        
        // Test email validation
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request();
        $result = $this->rest_api->validate_email('invalid-email', $request, 'email');
        $this->assertInstanceOf('WP_Error', $result, 'Invalid email should fail validation');
        
        $result = $this->rest_api->validate_email('test@example.com', $request, 'email');
        $this->assertTrue($result, 'Valid email should pass validation');
        
        // Test timestamp validation
        $result = $this->rest_api->validate_timestamp(0, null, 'timestamp');
        $this->assertInstanceOf('WP_Error', $result, 'Zero timestamp should fail validation');
        
        $result = $this->rest_api->validate_timestamp(time(), null, 'timestamp');
        $this->assertTrue($result, 'Valid timestamp should pass validation');
    }
    
    /**
     * Test email template replacement
     */
    public function test_email_template_replacement() {
        $contact_data = array(
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'subject' => 'Test Subject',
            'message' => 'Test Message',
        );
        
        $hubspot_result = ContactFormHubSpot_Test_Utils::mock_hubspot_response(true, '12345');
        
        $template = 'Hello {{first_name}} {{last_name}}! Your email {{email}} was received.';
        $processed = $this->rest_api->replace_email_placeholders($template, $contact_data, $hubspot_result);
        
        $this->assertStringContains('John Doe', $processed, 'Should replace first and last name');
        $this->assertStringContains('john@example.com', $processed, 'Should replace email');
    }
    
    /**
     * Test admin notification sending
     */
    public function test_admin_notification_sending() {
        $contact_data = ContactFormHubSpot_Test_Utils::create_test_form_data();
        $hubspot_result = ContactFormHubSpot_Test_Utils::mock_hubspot_response(true, '12345');
        
        // Mock wp_mail to return true
        add_filter('wp_mail', function($result, $to, $subject, $message, $headers) {
            return true;
        }, 10, 5);
        
        $result = $this->rest_api->send_admin_notification($contact_data, $hubspot_result);
        $this->assertTrue($result, 'Admin notification should be sent successfully');
    }
    
    /**
     * Test rate limiting integration
     */
    public function test_rate_limiting_integration() {
        $ip_address = '192.168.1.100';
        
        // Make multiple requests from same IP
        for ($i = 0; $i < 4; $i++) {
            $data = ContactFormHubSpot_Test_Utils::create_test_form_data();
            $request = ContactFormHubSpot_Test_Utils::create_test_rest_request($data);
            
            // Mock the IP address
            $_SERVER['REMOTE_ADDR'] = $ip_address;
            
            $response = $this->rest_api->handle_contact_submission($request);
            
            if ($i < 3) {
                $this->assertInstanceOf('WP_REST_Response', $response, "Request {$i} should succeed");
            } else {
                $this->assertInstanceOf('WP_Error', $response, "Request {$i} should fail due to rate limit");
                $this->assertEquals('antispam_failed', $response->get_error_code());
            }
        }
        
        unset($_SERVER['REMOTE_ADDR']);
    }
    
    /**
     * Test logging integration
     */
    public function test_logging_integration() {
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data();
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request($data);
        
        $response = $this->rest_api->handle_contact_submission($request);
        
        // Check that a log entry was created
        global $wpdb;
        $table_name = $wpdb->prefix . 'contact_form_logs';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE email = 'john.doe@example.com'");
        
        $this->assertGreaterThan(0, $count, 'Log entry should be created');
    }
    
    /**
     * Test error handling
     */
    public function test_error_handling() {
        // Test with malformed data
        $data = array(
            'first_name' => str_repeat('a', 300), // Too long
            'last_name' => 'Doe',
            'email' => 'test@example.com',
            'subject' => 'Test',
            'message' => 'Test message',
            'form_timestamp' => time() - 10,
            '_wpnonce' => wp_create_nonce('wp_rest'),
        );
        
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request($data);
        
        // This should fail at validation
        $this->expectException('Exception');
        $this->rest_api->handle_contact_submission($request);
    }
    
    /**
     * Test response format
     */
    public function test_response_format() {
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data();
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request($data);
        
        $response = $this->rest_api->handle_contact_submission($request);
        
        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertEquals(200, $response->get_status());
        
        $response_data = $response->get_data();
        $this->assertArrayHasKey('success', $response_data);
        $this->assertArrayHasKey('message', $response_data);
        $this->assertTrue($response_data['success']);
    }
}



