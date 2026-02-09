<?php
/**
 * Feature Gate Test
 *
 * Tests plan name resolution, limit enforcement, and the check_role method.
 *
 * @package RentalGates\Tests\Unit
 */

class FeatureGateTest extends WP_UnitTestCase {

    private $gate;

    public function setUp(): void {
        parent::setUp();
        $this->gate = Rental_Gates_Feature_Gate::get_instance();
    }

    public function test_plan_name_resolution_for_database_names() {
        // Database plan names should resolve to feature gate names
        $this->assertNotNull($this->gate->get_plan_config('starter'));
        $this->assertNotNull($this->gate->get_plan_config('professional'));
        $this->assertNotNull($this->gate->get_plan_config('enterprise'));
    }

    public function test_plan_name_resolution_for_gate_names() {
        // Feature gate plan names should work directly
        $this->assertNotNull($this->gate->get_plan_config('free'));
        $this->assertNotNull($this->gate->get_plan_config('basic'));
        $this->assertNotNull($this->gate->get_plan_config('silver'));
        $this->assertNotNull($this->gate->get_plan_config('gold'));
    }

    public function test_starter_maps_to_basic() {
        $starter = $this->gate->get_plan_config('starter');
        $basic = $this->gate->get_plan_config('basic');
        $this->assertEquals($starter, $basic);
    }

    public function test_professional_maps_to_silver() {
        $professional = $this->gate->get_plan_config('professional');
        $silver = $this->gate->get_plan_config('silver');
        $this->assertEquals($professional, $silver);
    }

    public function test_enterprise_maps_to_gold() {
        $enterprise = $this->gate->get_plan_config('enterprise');
        $gold = $this->gate->get_plan_config('gold');
        $this->assertEquals($enterprise, $gold);
    }

    public function test_check_role_method_exists() {
        $this->assertTrue(method_exists($this->gate, 'check_role'));
    }

    public function test_check_role_empty_allows_all() {
        $this->assertTrue($this->gate->check_role(array()));
    }

    public function test_free_plan_has_limits() {
        $plan = $this->gate->get_plan_config('free');
        $this->assertNotNull($plan);
        $this->assertArrayHasKey('limits', $plan);
        $this->assertEquals(1, $plan['limits']['buildings']);
        $this->assertEquals(3, $plan['limits']['units']);
    }

    public function test_gold_plan_has_unlimited() {
        $plan = $this->gate->get_plan_config('gold');
        $this->assertNotNull($plan);
        $this->assertEquals(-1, $plan['limits']['buildings']);
        $this->assertEquals(-1, $plan['limits']['units']);
    }
}
