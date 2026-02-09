<?php
/**
 * Building Service Test
 *
 * Tests the service layer's unified business logic for buildings.
 *
 * @package RentalGates\Tests\Unit
 */

class ServiceBuildingsTest extends WP_UnitTestCase {

    private $org_id;
    private $org2_id;

    public function setUp(): void {
        parent::setUp();

        $org = Rental_Gates_Organization::create(array(
            'name'          => 'Service Test Org',
            'contact_email' => 'service@example.com',
        ));
        $this->org_id = $org['id'];

        $org2 = Rental_Gates_Organization::create(array(
            'name'          => 'Other Org',
            'contact_email' => 'other@example.com',
        ));
        $this->org2_id = $org2['id'];
    }

    public function test_create_building_via_service() {
        $result = Rental_Gates_Service_Buildings::create(
            array('name' => 'Service Building', 'address' => '123 Main St'),
            $this->org_id,
            1
        );

        $this->assertIsArray($result);
        $this->assertEquals('Service Building', $result['name']);
        $this->assertEquals($this->org_id, $result['organization_id']);
    }

    public function test_delete_verifies_ownership() {
        // Create building in Org A
        $building = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name'            => 'Org A Building',
        ));

        // Try to delete from Org B context - should fail
        $result = Rental_Gates_Service_Buildings::delete($building['id'], $this->org2_id);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('forbidden', $result->get_error_code());
    }

    public function test_delete_own_building_succeeds() {
        $building = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name'            => 'My Building',
        ));

        $result = Rental_Gates_Service_Buildings::delete($building['id'], $this->org_id);
        $this->assertTrue($result);
    }

    public function test_delete_nonexistent_building() {
        $result = Rental_Gates_Service_Buildings::delete(999999, $this->org_id);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('not_found', $result->get_error_code());
    }
}
