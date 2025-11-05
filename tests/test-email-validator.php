<?php
/**
 * Email Validator Tests
 *
 * @package ContactFormHubSpot
 */

class Test_ContactFormHubSpot_Email_Validator extends WP_UnitTestCase {
    
    /**
     * Email validator instance
     *
     * @var ContactFormHubSpot_Email_Validator
     */
    private $email_validator;
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        $this->email_validator = ContactFormHubSpot_Email_Validator::get_instance();
        ContactFormHubSpot_Test_Utils::mock_wp_functions();
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        parent::tearDown();
    }
    
    /**
     * Test valid email addresses
     */
    public function test_valid_emails() {
        $valid_emails = array(
            'test@example.com',
            'user.name@domain.co.uk',
            'user+tag@example.org',
            'user123@test-domain.com',
            'a@b.co',
            'user@subdomain.example.com',
        );
        
        foreach ($valid_emails as $email) {
            $result = $this->email_validator->validate_email($email, false); // Skip DNS check for speed
            $this->assertTrue($result['valid'], "Email '{$email}' should be valid");
        }
    }
    
    /**
     * Test invalid email addresses
     */
    public function test_invalid_emails() {
        $invalid_emails = array(
            '',
            'invalid-email',
            '@example.com',
            'user@',
            'user@.com',
            'user..name@example.com',
            'user@example..com',
            'user@example.com.',
            'user name@example.com',
            'user@exam ple.com',
            'user@example',
            'user@example.c',
            str_repeat('a', 250) . '@example.com', // Too long
        );
        
        foreach ($invalid_emails as $email) {
            $result = $this->email_validator->validate_email($email, false);
            $this->assertFalse($result['valid'], "Email '{$email}' should be invalid");
        }
    }
    
    /**
     * Test email validation with DNS check
     */
    public function test_email_validation_with_dns() {
        // Test with DNS check enabled
        $result = $this->email_validator->validate_email('test@example.com', true);
        $this->assertTrue($result['valid'], 'Email with valid DNS should be valid');
        
        // Test with DNS check disabled
        $result = $this->email_validator->validate_email('test@example.com', false);
        $this->assertTrue($result['valid'], 'Email without DNS check should be valid');
    }
    
    /**
     * Test RFC compliance checks
     */
    public function test_rfc_compliance() {
        // Test email length limits
        $long_email = str_repeat('a', 64) . '@' . str_repeat('b', 250) . '.com';
        $result = $this->email_validator->validate_email($long_email, false);
        $this->assertFalse($result['valid'], 'Email exceeding length limits should be invalid');
        
        // Test consecutive dots
        $result = $this->email_validator->validate_email('user..name@example.com', false);
        $this->assertFalse($result['valid'], 'Email with consecutive dots should be invalid');
        
        // Test leading/trailing dots
        $result = $this->email_validator->validate_email('.user@example.com', false);
        $this->assertFalse($result['valid'], 'Email with leading dot should be invalid');
        
        $result = $this->email_validator->validate_email('user.@example.com', false);
        $this->assertFalse($result['valid'], 'Email with trailing dot should be invalid');
    }
    
    /**
     * Test REST API validation
     */
    public function test_rest_api_validation() {
        $request = ContactFormHubSpot_Test_Utils::create_test_rest_request();
        
        // Test valid email
        $result = $this->email_validator->validate_email_rest('test@example.com', $request, 'email');
        $this->assertTrue($result, 'Valid email should pass REST validation');
        
        // Test invalid email
        $result = $this->email_validator->validate_email_rest('invalid-email', $request, 'email');
        $this->assertInstanceOf('WP_Error', $result, 'Invalid email should return WP_Error');
        $this->assertEquals('invalid_email', $result->get_error_code());
    }
    
    /**
     * Test is_valid_email method
     */
    public function test_is_valid_email() {
        $this->assertTrue($this->email_validator->is_valid_email('test@example.com', false));
        $this->assertFalse($this->email_validator->is_valid_email('invalid-email', false));
        $this->assertFalse($this->email_validator->is_valid_email('', false));
    }
    
    /**
     * Test get_validation_error method
     */
    public function test_get_validation_error() {
        $error = $this->email_validator->get_validation_error('invalid-email');
        $this->assertNotEmpty($error, 'Should return error message for invalid email');
        
        $error = $this->email_validator->get_validation_error('test@example.com');
        $this->assertStringContains('valid', $error, 'Should return success message for valid email');
    }
    
    /**
     * Test email sanitization
     */
    public function test_email_sanitization() {
        $dirty_email = '  TEST@EXAMPLE.COM  ';
        $result = $this->email_validator->validate_email($dirty_email, false);
        $this->assertTrue($result['valid'], 'Email should be valid after sanitization');
    }
    
    /**
     * Test edge cases
     */
    public function test_edge_cases() {
        // Test null input
        $result = $this->email_validator->validate_email(null, false);
        $this->assertFalse($result['valid'], 'Null email should be invalid');
        
        // Test array input
        $result = $this->email_validator->validate_email(array(), false);
        $this->assertFalse($result['valid'], 'Array email should be invalid');
        
        // Test numeric input
        $result = $this->email_validator->validate_email(123, false);
        $this->assertFalse($result['valid'], 'Numeric email should be invalid');
    }
    
    /**
     * Test internationalized domain names
     */
    public function test_international_domains() {
        // Test with IDN (Internationalized Domain Name)
        $result = $this->email_validator->validate_email('test@münchen.de', false);
        // This might be valid or invalid depending on implementation
        // We're just testing that it doesn't crash
        $this->assertIsArray($result, 'Should return array result for IDN');
        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('message', $result);
    }
    
    /**
     * Test performance with many emails
     */
    public function test_performance() {
        $emails = array();
        for ($i = 0; $i < 100; $i++) {
            $emails[] = "user{$i}@example.com";
        }
        
        $start_time = microtime(true);
        
        foreach ($emails as $email) {
            $this->email_validator->validate_email($email, false);
        }
        
        $end_time = microtime(true);
        $execution_time = $end_time - $start_time;
        
        // Should complete 100 validations in less than 1 second
        $this->assertLessThan(1.0, $execution_time, 'Email validation should be fast');
    }
}



