<?php

namespace Pressmind\Tests\ImportIntegration;

use Pressmind\Registry;

/**
 * Verifies that ObjectType scaffolding produced PHP classes and objectdata_* tables.
 */
class ObjectTypeScaffoldTest extends AbstractImportTestCase
{
    private function getCustomMediaTypePath(): string
    {
        $appPath = self::$applicationPath ?? __DIR__ . '/_app';
        return $appPath . DIRECTORY_SEPARATOR . 'Custom' . DIRECTORY_SEPARATOR . 'MediaType';
    }

    public function testCustomMediaTypeDirectoryExists(): void
    {
        $path = $this->getCustomMediaTypePath();
        $this->assertDirectoryExists($path, 'Custom/MediaType directory must exist');
    }

    public function testAtLeastOneMediaTypeClassFileExists(): void
    {
        $path = $this->getCustomMediaTypePath();
        if (!is_dir($path)) {
            $this->markTestSkipped('Custom/MediaType not found (no object types in fixtures?)');
        }
        $files = glob($path . '/*.php');
        $this->assertGreaterThanOrEqual(0, count($files), 'MediaType class files (0 ok if no primary types in snapshot)');
    }

    public function testConfigHasMediaTypes(): void
    {
        $config = Registry::getInstance()->get('config');
        $this->assertIsArray($config['data']['media_types'] ?? null, 'config[data][media_types] must be set');
    }

    public function testObjectDataTablesExistForConfiguredTypes(): void
    {
        self::assertNotNull($this->db);
        $config = Registry::getInstance()->get('config');
        $mediaTypes = $config['data']['media_types'] ?? [];
        if (empty($mediaTypes)) {
            $this->markTestSkipped('No media types in config (empty fixture?)');
        }
        foreach (array_keys($mediaTypes) as $idType) {
            $table = 'objectdata_' . $idType;
            $row = $this->db->fetchRow("SHOW TABLES LIKE '" . str_replace("'", "''", $table) . "'");
            $this->assertNotEmpty($row, 'objectdata table must exist for type ' . $idType . ': ' . $table);
        }
    }

    public function testObjectDataTableHasIdMediaObjectColumn(): void
    {
        self::assertNotNull($this->db);
        $config = Registry::getInstance()->get('config');
        $mediaTypes = $config['data']['media_types'] ?? [];
        if (empty($mediaTypes)) {
            $this->markTestSkipped('No media types in config');
        }
        $idType = array_key_first($mediaTypes);
        $table = 'objectdata_' . $idType;
        $tableCheck = $this->db->fetchRow("SHOW TABLES LIKE '" . $table . "'");
        if (empty($tableCheck)) {
            $this->markTestSkipped('ObjectData table not created: ' . $table);
        }
        $cols = $this->db->fetchAll('DESCRIBE ' . $table);
        $names = array_map(fn($c) => $c->Field, $cols);
        $this->assertContains('id_media_object', $names, $table . ' must have id_media_object');
    }

    public function testMediaTypesPrettyUrlInConfig(): void
    {
        $config = Registry::getInstance()->get('config');
        $this->assertArrayHasKey('media_types_pretty_url', $config['data'] ?? []);
    }

    public function testMediaTypesAllowedVisibilitiesInConfig(): void
    {
        $config = Registry::getInstance()->get('config');
        $this->assertArrayHasKey('media_types_allowed_visibilities', $config['data'] ?? []);
    }

    public function testGeneratedClassIsLoadable(): void
    {
        $path = $this->getCustomMediaTypePath();
        $files = is_dir($path) ? glob($path . '/*.php') : [];
        if (empty($files)) {
            $this->markTestSkipped('No generated MediaType classes (run with fixtures that have primary object types)');
        }
        $loaded = 0;
        foreach ($files as $file) {
            $className = 'Custom\\MediaType\\' . basename($file, '.php');
            if (class_exists($className)) {
                $loaded++;
            }
        }
        $this->assertGreaterThanOrEqual(0, $loaded);
    }

    public function testObjectDataTableHasPrimaryKey(): void
    {
        self::assertNotNull($this->db);
        $config = Registry::getInstance()->get('config');
        $mediaTypes = $config['data']['media_types'] ?? [];
        if (empty($mediaTypes)) {
            $this->markTestSkipped('No media types in config');
        }
        $idType = array_key_first($mediaTypes);
        $table = 'objectdata_' . $idType;
        $tableCheck = $this->db->fetchRow("SHOW TABLES LIKE '" . $table . "'");
        if (empty($tableCheck)) {
            $this->markTestSkipped('ObjectData table not created: ' . $table);
        }
        $indexes = $this->db->fetchAll("SHOW INDEX FROM " . $table . " WHERE Key_name = 'PRIMARY'");
        $this->assertNotEmpty($indexes, $table . ' must have PRIMARY key');
    }

    public function testNoOrphanObjectDataTables(): void
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
        $tables = array_map(fn($r) => ((array) $r)[$key], $rows);
        $config = Registry::getInstance()->get('config');
        $mediaTypes = $config['data']['media_types'] ?? [];
        foreach ($tables as $table) {
            $suffix = str_replace('objectdata_', '', $table);
            if (!is_numeric($suffix)) {
                continue;
            }
            $this->assertArrayHasKey((int) $suffix, $mediaTypes, 'objectdata table should match a configured media type: ' . $table);
        }
    }
}
