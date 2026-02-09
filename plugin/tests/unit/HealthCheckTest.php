<?php
/**
 * Tests for Rental_Gates_Health
 */
class HealthCheckTest extends WP_UnitTestCase {

    public function test_handle_health_returns_200() {
        $response = Rental_Gates_Health::handle_health();
        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertEquals(200, $response->get_status());

        $data = $response->get_data();
        $this->assertEquals('healthy', $data['status']);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('time_ms', $data);
    }

    public function test_handle_health_includes_cache_headers() {
        $response = Rental_Gates_Health::handle_health();
        $headers = $response->get_headers();
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertStringContainsString('no-cache', $headers['Cache-Control']);
    }

    public function test_handle_detailed_requires_admin() {
        // Non-admin user should be denied
        $user_id = $this->factory->user->create(array('role' => 'subscriber'));
        wp_set_current_user($user_id);

        $route = '/rental-gates/v1/health/detailed';
        // The permission callback checks manage_options
        $this->assertFalse(current_user_can('manage_options'));
    }

    public function test_handle_detailed_returns_all_checks() {
        // Set up admin user
        $user_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($user_id);

        $response = Rental_Gates_Health::handle_detailed();
        $data = $response->get_data();

        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('environment', $data['checks']);
        $this->assertArrayHasKey('cache', $data['checks']);
        $this->assertArrayHasKey('cron', $data['checks']);
        $this->assertArrayHasKey('db_version', $data);
    }

    public function test_environment_check_returns_php_version() {
        $user_id = $this->factory->user->create(array('role' => 'administrator'));
        wp_set_current_user($user_id);

        $response = Rental_Gates_Health::handle_detailed();
        $data = $response->get_data();

        $env = $data['checks']['environment'];
        $this->assertEquals('pass', $env['status']);
        $this->assertEquals(PHP_VERSION, $env['php_version']);
    }
}
