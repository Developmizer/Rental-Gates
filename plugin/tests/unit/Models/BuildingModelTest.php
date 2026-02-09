<?php
/**
 * Building Model Test
 *
 * Tests CRUD operations, slug generation, and organization scoping
 * for the Building model.
 *
 * @package RentalGates\Tests\Unit\Models
 */

class BuildingModelTest extends WP_UnitTestCase {

    private $org_id;

    public function setUp(): void {
        parent::setUp();

        // Create a test organization
        $org = Rental_Gates_Organization::create(array(
            'name'          => 'Test Org',
            'contact_email' => 'test@example.com',
        ));
        $this->org_id = $org['id'];
    }

    public function test_create_building() {
        $building = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name'            => 'Test Building',
            'address'         => '123 Main St',
            'city'            => 'Springfield',
        ));

        $this->assertIsArray($building);
        $this->assertEquals('Test Building', $building['name']);
        $this->assertNotEmpty($building['slug']);
        $this->assertEquals($this->org_id, $building['organization_id']);
    }

    public function test_create_building_requires_name() {
        $result = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
        ));

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_get_building() {
        $created = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name'            => 'Fetch Test',
        ));
        $fetched = Rental_Gates_Building::get($created['id']);

        $this->assertEquals($created['id'], $fetched['id']);
        $this->assertEquals('Fetch Test', $fetched['name']);
    }

    public function test_get_nonexistent_building_returns_null() {
        $result = Rental_Gates_Building::get(999999);
        $this->assertNull($result);
    }

    public function test_unique_slug_generation() {
        $first = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name'            => 'Same Name',
        ));
        $second = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name'            => 'Same Name',
        ));

        $this->assertStringStartsWith('same-name', $second['slug']);
        $this->assertNotEquals($first['slug'], $second['slug']);
    }

    public function test_delete_building() {
        $building = Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name'            => 'To Delete',
        ));
        $result = Rental_Gates_Building::delete($building['id']);

        $this->assertTrue($result);
        $this->assertNull(Rental_Gates_Building::get($building['id']));
    }

    public function test_organization_scoping() {
        // Create building in org 1
        Rental_Gates_Building::create(array(
            'organization_id' => $this->org_id,
            'name'            => 'Org 1 Building',
        ));

        // Create separate org
        $org2 = Rental_Gates_Organization::create(array(
            'name'          => 'Org 2',
            'contact_email' => 'org2@example.com',
        ));

        // List buildings for org 2 - should be empty
        $result = Rental_Gates_Building::get_for_organization($org2['id']);
        $this->assertCount(0, $result['items']);
    }

    public function test_get_for_organization_pagination() {
        // Create 5 buildings
        for ($i = 1; $i <= 5; $i++) {
            Rental_Gates_Building::create(array(
                'organization_id' => $this->org_id,
                'name'            => 'Building ' . $i,
            ));
        }

        // Page 1, 2 per page
        $result = Rental_Gates_Building::get_for_organization($this->org_id, array(
            'per_page' => 2,
            'page'     => 1,
        ));

        $this->assertCount(2, $result['items']);
        $this->assertEquals(5, $result['total']);
        $this->assertEquals(3, $result['pages']);
    }
}
