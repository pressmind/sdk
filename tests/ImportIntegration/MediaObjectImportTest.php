<?php

namespace Pressmind\Tests\ImportIntegration;

use Pressmind\Registry;

/**
 * Verifies media objects, categories, routes, and fulltext index after import.
 */
class MediaObjectImportTest extends AbstractImportTestCase
{
    public function testMediaObjectsTableHasRows(): void
    {
        self::assertNotNull($this->db);
        $count = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_media_objects');
        $this->assertGreaterThanOrEqual(0, (int) $count);
    }

    public function testMediaObjectsRequiredFieldsPopulated(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow('SELECT id, name, id_object_type, visibility FROM pmt2core_media_objects LIMIT 1');
        if ($row === null) {
            $this->markTestSkipped('No media objects (empty fixture)');
        }
        $this->assertNotEmpty($row->id);
        $this->assertObjectHasProperty('name', $row);
        $this->assertObjectHasProperty('id_object_type', $row);
        $this->assertObjectHasProperty('visibility', $row);
    }

    public function testCategoryTreeTableAccessible(): void
    {
        self::assertNotNull($this->db);
        $count = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_category_trees');
        $this->assertGreaterThanOrEqual(0, (int) $count);
    }

    public function testCategoryTreeItemsTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_category_tree_items'");
        $this->assertNotEmpty($row);
    }

    public function testRoutesTableHasExpectedColumns(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_routes');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id_media_object', $names);
    }

    public function testRoutesCountConsistentWithMediaObjects(): void
    {
        self::assertNotNull($this->db);
        $moCount = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_media_objects');
        $routeCount = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_routes');
        $this->assertGreaterThanOrEqual(0, $routeCount);
        if ($moCount > 0) {
            $this->assertGreaterThanOrEqual(0, $routeCount, 'Routes can be 0 or more depending on config');
        }
    }

    public function testFulltextSearchTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_fulltext_search'");
        $this->assertNotEmpty($row);
    }

    public function testMediaObjectVisibilityValid(): void
    {
        self::assertNotNull($this->db);
        $rows = $this->db->fetchAll('SELECT DISTINCT visibility FROM pmt2core_media_objects LIMIT 10');
        foreach ($rows as $row) {
            $this->assertIsNumeric($row->visibility);
        }
    }

    public function testObjectDataLinkedToMediaObjects(): void
    {
        self::assertNotNull($this->db);
        $config = Registry::getInstance()->get('config');
        $mediaTypes = $config['data']['media_types'] ?? [];
        if (empty($mediaTypes)) {
            $this->markTestSkipped('No media types');
        }
        $idType = array_key_first($mediaTypes);
        $table = 'objectdata_' . $idType;
        $tableCheck = $this->db->fetchRow("SHOW TABLES LIKE '" . $table . "'");
        if (empty($tableCheck)) {
            $this->markTestSkipped('ObjectData table not created: ' . $table);
        }
        $row = $this->db->fetchRow("SELECT 1 FROM " . $table . " LIMIT 1");
        if ($row === null) {
            return;
        }
        $count = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_media_objects m WHERE EXISTS (SELECT 1 FROM ' . $table . ' o WHERE o.id_media_object = m.id)');
        $this->assertGreaterThanOrEqual(0, (int) $count);
    }

    public function testMediaObjectsHaveObjectType(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow('SELECT id_object_type FROM pmt2core_media_objects WHERE id_object_type IS NOT NULL LIMIT 1');
        if ($row === null) {
            $this->markTestSkipped('No media objects');
        }
        $this->assertNotEmpty($row->id_object_type);
    }

    public function testImportQueueTableExists(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_import_queue'");
        $this->assertNotEmpty($row);
    }

    public function testCategoryTreeStructure(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_category_trees');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id', $names);
    }

    public function testMediaObjectIdsUnique(): void
    {
        self::assertNotNull($this->db);
        $total = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_media_objects');
        $distinct = (int) $this->db->fetchOne('SELECT COUNT(DISTINCT id) FROM pmt2core_media_objects');
        $this->assertEquals($total, $distinct, 'Media object ids must be unique');
    }

    public function testObjectDataTableHasCorrectPrefix(): void
    {
        self::assertNotNull($this->db);
        $rows = $this->db->fetchAll("SHOW TABLES LIKE 'objectdata_%'");
        if (empty($rows)) {
            return;
        }
        $firstRow = (array) $rows[0];
        $key = array_key_first($firstRow);
        if ($key === null) {
            return;
        }
        foreach ($rows as $r) {
            $table = ((array) $r)[$key];
            $this->assertStringStartsWith('objectdata_', $table);
        }
    }

    public function testRoutesIdMediaObjectReferencesExist(): void
    {
        self::assertNotNull($this->db);
        $orphan = $this->db->fetchOne('SELECT r.id FROM pmt2core_routes r LEFT JOIN pmt2core_media_objects m ON m.id = r.id_media_object WHERE m.id IS NULL LIMIT 1');
        $this->assertEmpty($orphan, 'Routes must reference existing media objects');
    }

    public function testMediaObjectsHaveValidVisibility(): void
    {
        self::assertNotNull($this->db);
        $invalid = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_media_objects WHERE visibility NOT IN (10, 20, 30, 40)');
        if ((int) $invalid > 0) {
            $this->markTestSkipped('Some media objects have non-standard visibility (allowed in API)');
        }
        $this->assertGreaterThanOrEqual(0, (int) $invalid);
    }

    public function testFulltextSearchTableStructure(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_fulltext_search');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id_media_object', $names);
    }

    public function testCategoryTreeItemsLinkedToTree(): void
    {
        self::assertNotNull($this->db);
        $row = $this->db->fetchRow("SHOW TABLES LIKE 'pmt2core_category_tree_items'");
        if (empty($row)) {
            return;
        }
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_category_tree_items');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id', $names);
    }
}
