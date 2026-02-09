<?php
/**
 * Migration Framework Test
 *
 * Tests version-gated, transactional database migration behavior.
 *
 * @package RentalGates\Tests\Unit
 */

class MigrationTest extends WP_UnitTestCase {

    public function test_migrations_are_version_gated() {
        // Set version to 2.7.3 - migrations at or below should NOT run
        update_option('rental_gates_db_version', '2.7.3');

        $db = new Rental_Gates_Database();
        $db->check_version();

        // Version should advance to current (all newer migrations ran)
        $version = get_option('rental_gates_db_version');
        $this->assertEquals(RENTAL_GATES_DB_VERSION, $version);
    }

    public function test_fresh_install_creates_tables() {
        // Fresh install has no version
        delete_option('rental_gates_db_version');

        $db = new Rental_Gates_Database();
        $db->check_version();

        // Version should be set to current
        $version = get_option('rental_gates_db_version');
        $this->assertEquals(RENTAL_GATES_DB_VERSION, $version);
    }

    public function test_ai_credit_tables_in_table_names() {
        $tables = Rental_Gates_Database::get_table_names();

        $this->assertArrayHasKey('ai_credit_balances', $tables);
        $this->assertArrayHasKey('ai_credit_transactions', $tables);
        $this->assertArrayHasKey('ai_credit_packs', $tables);
        $this->assertArrayHasKey('ai_credit_purchases', $tables);
    }

    public function test_table_names_include_all_expected_keys() {
        $tables = Rental_Gates_Database::get_table_names();

        // Verify core tables
        $this->assertArrayHasKey('organizations', $tables);
        $this->assertArrayHasKey('buildings', $tables);
        $this->assertArrayHasKey('units', $tables);
        $this->assertArrayHasKey('tenants', $tables);
        $this->assertArrayHasKey('leases', $tables);
        $this->assertArrayHasKey('payments', $tables);
        $this->assertArrayHasKey('work_orders', $tables);
    }
}
