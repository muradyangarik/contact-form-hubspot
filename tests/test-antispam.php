<?php
/**
 * Antispam Tests
 *
 * @package ContactFormHubSpot
 */

class Test_ContactFormHubSpot_Antispam extends WP_UnitTestCase {
    
    /**
     * Antispam instance
     *
     * @var ContactFormHubSpot_Antispam
     */
    private $antispam;
    
    /**
     * Set up test environment
     */
    public function setUp(): void {
        parent::setUp();
        $this->antispam = ContactFormHubSpot_Antispam::get_instance();
    }
    
    /**
     * Clean up after tests
     */
    public function tearDown(): void {
        parent::tearDown();
        ContactFormHubSpot_Test_Utils::cleanup_test_data();
    }
    
    /**
     * Test honeypot validation
     */
    public function test_honeypot_validation() {
        // Test empty honeypot (should pass)
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data(array(
            'website' => ''
        ));
        $result = $this->antispam->check_honeypot($data);
        $this->assertTrue($result['valid'], 'Empty honeypot should be valid');
        
        // Test filled honeypot (should fail)
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data(array(
            'website' => 'spam-bot-filled-this'
        ));
        $result = $this->antispam->check_honeypot($data);
        $this->assertFalse($result['valid'], 'Filled honeypot should be invalid');
        $this->assertStringContains('honeypot', $result['message']);
    }
    
    /**
     * Test time trap validation
     */
    public function test_time_trap_validation() {
        // Test valid timing (10 seconds ago)
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data(array(
            'form_timestamp' => time() - 10
        ));
        $result = $this->antispam->check_time_trap($data);
        $this->assertTrue($result['valid'], 'Valid timing should pass');
        
        // Test too fast submission (1 second ago)
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data(array(
            'form_timestamp' => time() - 1
        ));
        $result = $this->antispam->check_time_trap($data);
        $this->assertFalse($result['valid'], 'Too fast submission should fail');
        $this->assertStringContains('quickly', $result['message']);
        
        // Test expired submission (2 hours ago)
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data(array(
            'form_timestamp' => time() - 7200
        ));
        $result = $this->antispam->check_time_trap($data);
        $this->assertFalse($result['valid'], 'Expired submission should fail');
        $this->assertStringContains('expired', $result['message']);
        
        // Test missing timestamp
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data();
        unset($data['form_timestamp']);
        $result = $this->antispam->check_time_trap($data);
        $this->assertFalse($result['valid'], 'Missing timestamp should fail');
        $this->assertStringContains('missing', $result['message']);
    }
    
    /**
     * Test rate limiting
     */
    public function test_rate_limiting() {
        $ip_address = '192.168.1.100';
        
        // First submission should pass
        $result = $this->antispam->check_rate_limit($ip_address);
        $this->assertTrue($result['valid'], 'First submission should pass');
        
        // Second submission should pass
        $result = $this->antispam->check_rate_limit($ip_address);
        $this->assertTrue($result['valid'], 'Second submission should pass');
        
        // Third submission should pass
        $result = $this->antispam->check_rate_limit($ip_address);
        $this->assertTrue($result['valid'], 'Third submission should pass');
        
        // Fourth submission should fail (rate limit exceeded)
        $result = $this->antispam->check_rate_limit($ip_address);
        $this->assertFalse($result['valid'], 'Fourth submission should fail due to rate limit');
        $this->assertStringContains('limit', $result['message']);
    }
    
    /**
     * Test rate limiting with different IPs
     */
    public function test_rate_limiting_different_ips() {
        $ip1 = '192.168.1.100';
        $ip2 = '192.168.1.101';
        
        // Both IPs should be able to submit independently
        $result1 = $this->antispam->check_rate_limit($ip1);
        $result2 = $this->antispam->check_rate_limit($ip2);
        
        $this->assertTrue($result1['valid'], 'IP1 first submission should pass');
        $this->assertTrue($result2['valid'], 'IP2 first submission should pass');
    }
    
    /**
     * Test rate limit status
     */
    public function test_rate_limit_status() {
        $ip_address = '192.168.1.200';
        
        // Check initial status
        $status = $this->antispam->get_rate_limit_status($ip_address);
        $this->assertEquals(0, $status['count'], 'Initial count should be 0');
        $this->assertEquals(3, $status['limit'], 'Limit should be 3');
        $this->assertEquals(3, $status['remaining'], 'Remaining should be 3');
        
        // Make a submission
        $this->antispam->check_rate_limit($ip_address);
        
        // Check updated status
        $status = $this->antispam->get_rate_limit_status($ip_address);
        $this->assertEquals(1, $status['count'], 'Count should be 1 after submission');
        $this->assertEquals(2, $status['remaining'], 'Remaining should be 2');
    }
    
    /**
     * Test rate limit clearing
     */
    public function test_rate_limit_clearing() {
        $ip_address = '192.168.1.300';
        
        // Exceed rate limit
        for ($i = 0; $i < 4; $i++) {
            $this->antispam->check_rate_limit($ip_address);
        }
        
        // Verify rate limit is exceeded
        $result = $this->antispam->check_rate_limit($ip_address);
        $this->assertFalse($result['valid'], 'Rate limit should be exceeded');
        
        // Clear rate limit
        $cleared = $this->antispam->clear_rate_limit($ip_address);
        $this->assertTrue($cleared, 'Rate limit should be cleared');
        
        // Verify rate limit is cleared
        $result = $this->antispam->check_rate_limit($ip_address);
        $this->assertTrue($result['valid'], 'Rate limit should be cleared');
    }
    
    /**
     * Test comprehensive antispam checks
     */
    public function test_comprehensive_antispam_checks() {
        // Test valid submission
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data();
        $result = $this->antispam->perform_antispam_checks($data);
        $this->assertTrue($result['valid'], 'Valid submission should pass all checks');
        
        // Test honeypot failure
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data(array(
            'website' => 'spam'
        ));
        $result = $this->antispam->perform_antispam_checks($data);
        $this->assertFalse($result['valid'], 'Honeypot failure should fail checks');
        $this->assertStringContains('honeypot', $result['message']);
        
        // Test time trap failure
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data(array(
            'form_timestamp' => time() - 1
        ));
        $result = $this->antispam->perform_antispam_checks($data);
        $this->assertFalse($result['valid'], 'Time trap failure should fail checks');
        $this->assertStringContains('quickly', $result['message']);
    }
    
    /**
     * Test form timestamp generation
     */
    public function test_form_timestamp_generation() {
        $timestamp = $this->antispam->generate_form_timestamp();
        $this->assertIsInt($timestamp, 'Timestamp should be integer');
        $this->assertGreaterThan(0, $timestamp, 'Timestamp should be positive');
        $this->assertLessThanOrEqual(time(), $timestamp, 'Timestamp should not be in future');
    }
    
    /**
     * Test IP address detection
     */
    public function test_ip_address_detection() {
        // Mock different IP scenarios
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $ip = $this->antispam->get_user_ip();
        $this->assertEquals('192.168.1.1', $ip, 'Should return REMOTE_ADDR when available');
        
        // Test with forwarded IP
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 192.168.1.1';
        $ip = $this->antispam->get_user_ip();
        $this->assertEquals('203.0.113.1', $ip, 'Should return first valid IP from X-Forwarded-For');
        
        // Clean up
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }
    
    /**
     * Test rate limited IPs retrieval
     */
    public function test_get_rate_limited_ips() {
        // Create some rate limited IPs
        $ip1 = '192.168.1.1';
        $ip2 = '192.168.1.2';
        
        // Exceed rate limits
        for ($i = 0; $i < 4; $i++) {
            $this->antispam->check_rate_limit($ip1);
            $this->antispam->check_rate_limit($ip2);
        }
        
        // Get rate limited IPs
        $rate_limited_ips = $this->antispam->get_rate_limited_ips();
        $this->assertIsArray($rate_limited_ips, 'Should return array of rate limited IPs');
        $this->assertGreaterThanOrEqual(2, count($rate_limited_ips), 'Should have at least 2 rate limited IPs');
    }
    
    /**
     * Test edge cases
     */
    public function test_edge_cases() {
        // Test with empty data
        $result = $this->antispam->perform_antispam_checks(array());
        $this->assertFalse($result['valid'], 'Empty data should fail checks');
        
        // Test with null data
        $result = $this->antispam->perform_antispam_checks(null);
        $this->assertFalse($result['valid'], 'Null data should fail checks');
        
        // Test with invalid timestamp
        $data = ContactFormHubSpot_Test_Utils::create_test_form_data(array(
            'form_timestamp' => 'invalid'
        ));
        $result = $this->antispam->check_time_trap($data);
        $this->assertFalse($result['valid'], 'Invalid timestamp should fail');
    }
}



