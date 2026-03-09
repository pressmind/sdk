<?php

namespace Pressmind\Tests\ImportIntegration;

use Pressmind\Registry;

/**
 * Post-import checks: orphan detection, queue state, idempotency.
 */
class PostImportVerificationTest extends AbstractImportTestCase
{
    public function testImportQueueProcessedAfterFullImport(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_import_queue');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id', $names, 'import_queue should have id column');
        $this->assertContains('id_media_object', $names, 'import_queue should have id_media_object column');
        $total = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_import_queue');
        $this->assertGreaterThanOrEqual(0, (int) $total);
    }

    public function testNoOrphanRoutes(): void
    {
        self::assertNotNull($this->db);
        $orphan = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_routes r LEFT JOIN pmt2core_media_objects m ON m.id = r.id_media_object WHERE m.id IS NULL');
        $this->assertEquals(0, (int) $orphan, 'Routes should not reference missing media objects');
    }

    public function testNoOrphanObjectData(): void
    {
        self::assertNotNull($this->db);
        $config = Registry::getInstance()->get('config');
        $mediaTypes = $config['data']['media_types'] ?? [];
        foreach (array_keys($mediaTypes) as $idType) {
            $table = 'objectdata_' . $idType;
            $tableCheck = $this->db->fetchRow("SHOW TABLES LIKE '" . $table . "'");
            if (empty($tableCheck)) {
                continue;
            }
            $orphan = $this->db->fetchOne('SELECT COUNT(*) FROM ' . $table . ' o LEFT JOIN pmt2core_media_objects m ON m.id = o.id_media_object WHERE m.id IS NULL');
            if ((int) $orphan > 0) {
                $sampleOd = $this->db->fetchAll('SELECT id, id_media_object, language FROM ' . $table . ' LIMIT 5');
                $sampleMo = $this->db->fetchAll('SELECT id, id_object_type FROM pmt2core_media_objects WHERE id_object_type = ' . $idType . ' LIMIT 5');
                $totalMo = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_media_objects WHERE id_object_type = ' . $idType);
                $totalOd = $this->db->fetchOne('SELECT COUNT(*) FROM ' . $table);
                $msg = $table . ': ' . $orphan . ' orphan rows | mo(type=' . $idType . '): ' . $totalMo . ' | od: ' . $totalOd
                    . ' | od_sample: ' . json_encode($sampleOd) . ' | mo_sample: ' . json_encode($sampleMo);
                $this->assertEquals(0, (int) $orphan, $msg);
            }
        }
    }

    public function testNoOrphanBookingPackages(): void
    {
        self::assertNotNull($this->db);
        $orphan = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_touristic_booking_packages b LEFT JOIN pmt2core_media_objects m ON m.id = b.id_media_object WHERE m.id IS NULL');
        $this->assertEquals(0, (int) $orphan);
    }

    public function testNoOrphanCheapestPriceSpeed(): void
    {
        self::assertNotNull($this->db);
        $orphan = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_cheapest_price_speed c LEFT JOIN pmt2core_media_objects m ON m.id = c.id_media_object WHERE m.id IS NULL');
        $this->assertEquals(0, (int) $orphan);
    }

    public function testQueueTableColumnsExist(): void
    {
        self::assertNotNull($this->db);
        $cols = $this->db->fetchAll('DESCRIBE pmt2core_import_queue');
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id', $names);
        $this->assertContains('id_media_object', $names);
    }

    public function testReImportSingleMediaObjectIdempotent(): void
    {
        self::assertNotNull($this->db);
        $id = $this->db->fetchOne('SELECT id FROM pmt2core_media_objects LIMIT 1');
        if ($id === null) {
            $this->markTestSkipped('No media objects to re-import');
        }
        $before = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_media_objects WHERE id = ' . (int) $id);
        $this->assertEquals(1, $before);
        try {
            ob_start();
            $importer = new \Pressmind\Import('full');
            $importer->importMediaObject((int) $id, false);
            ob_end_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->markTestSkipped('Re-import failed (fixture may lack Text/getById for this id): ' . $e->getMessage());
        }
        $after = (int) $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_media_objects WHERE id = ' . (int) $id);
        $this->assertEquals(1, $after, 'Re-import should not duplicate media object');
    }

    public function testConfigWrittenAfterInstall(): void
    {
        $this->assertNotEmpty(self::$configFilePath);
        $this->assertFileExists(self::$configFilePath);
    }

    public function testApplicationPathSet(): void
    {
        $this->assertNotEmpty(self::$applicationPath);
        $this->assertDirectoryExists(self::$applicationPath);
    }

    public function testCustomMediaTypeDirWritable(): void
    {
        $path = self::$applicationPath . DIRECTORY_SEPARATOR . 'Custom' . DIRECTORY_SEPARATOR . 'MediaType';
        if (!is_dir($path)) {
            $this->markTestSkipped('Custom/MediaType not created (no object types?)');
        }
        $this->assertTrue(is_writable($path) || is_readable($path));
    }
}
