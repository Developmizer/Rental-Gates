<?php
/**
 * Tests for Rental_Gates_Logger
 */
class LoggerTest extends WP_UnitTestCase {

    public function test_mask_email() {
        // Use reflection to test the private mask_email method
        $reflection = new ReflectionClass('Rental_Gates_Logger');
        $method = $reflection->getMethod('mask_email');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'john@example.com');
        $this->assertStringStartsWith('j***@', $result);
        $this->assertStringEndsWith('.com', $result);
        $this->assertStringNotContainsString('john', $result);
        $this->assertStringNotContainsString('example', $result);
    }

    public function test_mask_value() {
        $reflection = new ReflectionClass('Rental_Gates_Logger');
        $method = $reflection->getMethod('mask');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'sk_live_abc123def456');
        $this->assertStringEndsWith('f456', $result);
        $this->assertStringNotContainsString('abc123', $result);
    }

    public function test_mask_short_value() {
        $reflection = new ReflectionClass('Rental_Gates_Logger');
        $method = $reflection->getMethod('mask');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'abc');
        $this->assertEquals('****', $result);
    }

    public function test_sanitize_context_masks_emails() {
        $reflection = new ReflectionClass('Rental_Gates_Logger');
        $method = $reflection->getMethod('sanitize_context');
        $method->setAccessible(true);

        $context = array(
            'email' => 'test@example.com',
            'template' => 'welcome',
        );

        $result = $method->invoke(null, $context);
        $this->assertStringNotContainsString('test@example.com', $result['email']);
        $this->assertEquals('welcome', $result['template']);
    }

    public function test_sanitize_context_masks_sensitive_keys() {
        $reflection = new ReflectionClass('Rental_Gates_Logger');
        $method = $reflection->getMethod('sanitize_context');
        $method->setAccessible(true);

        $context = array(
            'stripe_account_id' => 'acct_1234567890',
            'payment_intent_id' => 'pi_abcdefghijk',
            'status' => 'active',
        );

        $result = $method->invoke(null, $context);
        $this->assertStringNotContainsString('1234567890', $result['stripe_account_id']);
        $this->assertStringNotContainsString('abcdefghijk', $result['payment_intent_id']);
        $this->assertEquals('active', $result['status']);
    }

    public function test_format_context() {
        $reflection = new ReflectionClass('Rental_Gates_Logger');
        $method = $reflection->getMethod('format_context');
        $method->setAccessible(true);

        $context = array('key' => 'value', 'count' => 42);
        $result = $method->invoke(null, $context);

        $this->assertStringContainsString('key=value', $result);
        $this->assertStringContainsString('count=42', $result);
    }

    public function test_log_captures_output() {
        // In test mode, error_log writes to stderr which we can't easily capture
        // But we can verify the action fires
        $captured = null;
        add_action('rental_gates_log', function($level, $channel, $message) use (&$captured) {
            $captured = array('level' => $level, 'channel' => $channel, 'message' => $message);
        }, 10, 3);

        Rental_Gates_Logger::error('test', 'Something failed');

        $this->assertNotNull($captured);
        $this->assertEquals('ERROR', $captured['level']);
        $this->assertEquals('test', $captured['channel']);
        $this->assertEquals('Something failed', $captured['message']);
    }
}
