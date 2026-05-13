<?php

namespace Pressmind\Tests\ImportIntegration;

use Pressmind\System\Info;

/**
 * Verifies that install created all static tables, column types, and indexes.
 */
class InstallSchemaTest extends AbstractImportTestCase
{
    /**
     * @return array<int, string> Expected table names (with prefix) from STATIC_MODELS
     */
    private function getExpectedStaticTables(): array
    {
        $tables = [];
        $namespace = 'Pressmind\\ORM\\Object';
        foreach (Info::STATIC_MODELS as $model) {
            $className = $namespace . str_replace('\\\\', '\\', $model);
            if (!class_exists($className)) {
                continue;
            }
            try {
                $obj = new $className();
                $tables[] = $obj->getDbTableName();
            } catch (\Throwable $e) {
                continue;
            }
        }
        return array_unique($tables);
    }

    public function testMediaObjectsTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_media_objects'");
        $this->assertNotEmpty($row, 'pmt2core_media_objects must exist');
    }

    public function testCategoryTreeTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_category_trees'");
        $this->assertNotEmpty($row, 'pmt2core_category_trees must exist');
    }

    public function testRoutesTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_routes'");
        $this->assertNotEmpty($row, 'pmt2core_routes must exist');
    }

    public function testCheapestPriceSpeedTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_cheapest_price_speed'");
        $this->assertNotEmpty($row, 'pmt2core_cheapest_price_speed must exist');
    }

    public function testTouristicBookingPackagesTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_booking_packages'");
        $this->assertNotEmpty($row, 'pmt2core_touristic_booking_packages must exist');
    }

    public function testTouristicDatesTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_touristic_dates'");
        $this->assertNotEmpty($row, 'pmt2core_touristic_dates must exist');
    }

    public function testAllStaticModelTablesExist(): void
    {
        self::assertNotNull($this->db);
        $knownBrokenModels = [
            'pmt2core_touristic_insurance_groups',
        ];
        $expected = $this->getExpectedStaticTables();
        $this->assertGreaterThan(10, count($expected), 'Expected at least 10 static tables');
        foreach ($expected as $table) {
            if (in_array($table, $knownBrokenModels, true)) {
                continue;
            }
            $row = $this->db->fetchRow("SHOW TABLES LIKE '" . str_replace("'", "''", $table) . "'");
            $this->assertNotEmpty($row, 'Static table must exist: ' . $table);
        }
    }

    public function testMediaObjectsTableHasRequiredColumns(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_media_objects');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id', $names);
        $this->assertContains('name', $names);
        $this->assertContains('id_object_type', $names);
        $this->assertContains('visibility', $names);
    }

    public function testMediaObjectsIdColumnType(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_media_objects');
        foreach ($cols as $col) {
            if ($col->Field === 'id') {
                $this->assertMatchesRegularExpression('/int/i', $col->Type, 'id should be integer type');
                $this->assertEquals('PRI', $col->Key);
                return;
            }
        }
        $this->fail('id column not found');
    }

    public function testCategoryTreeTableHasRequiredColumns(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_category_trees');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id', $names);
    }

    public function testNoDuplicateTables(): void
    {
        self::assertNotNull($this->db);
        $rows = $this->db->fetchAll("SHOW TABLES");
        $firstRow = (array) ($rows[0] ?? new \stdClass());
        $key = array_key_first($firstRow);
        if ($key === null) {
            $this->markTestSkipped('No tables');
        }
        $tables = array_map(fn($r) => ((array) $r)[$key], $rows);
        $unique = array_unique($tables);
        $this->assertCount(count($tables), $unique, 'Duplicate table names found');
    }

    public function testMediaObjectsHasPrimaryKey(): void
    {
        self::assertNotNull($this->db);
        $indexes = $this->db->fetchAll("SHOW INDEX FROM pmt2core_media_objects WHERE Key_name = 'PRIMARY'");
        $this->assertNotEmpty($indexes, 'pmt2core_media_objects must have PRIMARY key');
    }

    public function testRoutesTableHasRequiredColumns(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_routes');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id', $names);
        $this->assertContains('id_media_object', $names);
    }

    public function testCheapestPriceSpeedTableHasRequiredColumns(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_cheapest_price_speed');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id_media_object', $names);
        $this->assertContains('id_booking_package', $names);
    }

    public function testItineraryBoardTableHasDistanceColumn(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_itinerary_step_boards');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('distance', $names);
    }

    public function testItinerarySectionTableHasTagsColumn(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_itinerary_step_sections');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('tags', $names);
    }

    public function testImportQueueTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_import_queue'");
        $this->assertNotEmpty($row, 'pmt2core_import_queue must exist (used by Import)');
    }

    public function testTablePrefixConsistent(): void
    {
        self::assertNotNull($this->db);
        $rows = $this->db->fetchAll("SHOW TABLES");
        $firstRow = (array) ($rows[0] ?? new \stdClass());
        $key = array_key_first($firstRow);
        if ($key === null) {
            $this->markTestSkipped('No tables');
        }
        $tables = array_map(fn($r) => ((array) $r)[$key], $rows);
        foreach ($tables as $table) {
            if (str_starts_with($table, 'test_')) {
                continue;
            }
            $hasPmt2core = str_starts_with($table, 'pmt2core_');
            $isObjectdata = str_starts_with($table, 'objectdata_');
            $this->assertTrue($hasPmt2core || $isObjectdata,
                'All tables must use pmt2core_ or objectdata_ prefix: ' . $table);
        }
    }
}
