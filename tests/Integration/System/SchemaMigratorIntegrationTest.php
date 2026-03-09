<?php

namespace Pressmind\Tests\Integration\System;

use Pressmind\Registry;
use Pressmind\System\SchemaMigrator;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for SchemaMigrator.
 * Tests field type mapping, config checks, and schema comparison.
 * Does NOT execute ALTER TABLE statements.
 */
class SchemaMigratorIntegrationTest extends AbstractIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }

        Registry::getInstance()->add('config', $this->buildConfig());
    }

    /**
     * Build config with logging keys required by Log\Writer.
     */
    private function buildConfig(array $extra = []): array
    {
        $config = $this->getIntegrationConfig();
        $config['logging']['mode'] = 'ALL';
        $config['logging']['storage'] = 'filesystem';
        return array_replace_recursive($config, $extra);
    }

    // --- mapFieldType ---

    /**
     * @dataProvider fieldTypeMappingProvider
     */
    public function testMapFieldType(string $input, string $expected): void
    {
        $this->assertSame($expected, SchemaMigrator::mapFieldType($input));
    }

    public static function fieldTypeMappingProvider(): array
    {
        return [
            'text'          => ['text', 'LONGTEXT'],
            'integer'       => ['integer', 'INT(11)'],
            'int'           => ['int', 'INT(11)'],
            'table'         => ['table', 'LONGTEXT'],
            'date'          => ['date', 'DATETIME'],
            'plaintext'     => ['plaintext', 'LONGTEXT'],
            'wysiwyg'       => ['wysiwyg', 'LONGTEXT'],
            'qrcode'        => ['qrcode', 'LONGTEXT'],
            'picture'       => ['picture', 'LONGTEXT'],
            'objectlink'    => ['objectlink', 'LONGTEXT'],
            'file'          => ['file', 'LONGTEXT'],
            'categorytree'  => ['categorytree', 'LONGTEXT'],
            'location'      => ['location', 'LONGTEXT'],
            'link'          => ['link', 'LONGTEXT'],
            'key_value'     => ['key_value', 'LONGTEXT'],
            'unknown_type'  => ['unknown_type', 'LONGTEXT'],
        ];
    }

    // --- isAutoMigrationEnabled ---

    public function testIsAutoMigrationEnabledTrue(): void
    {
        $config = $this->buildConfig(['data' => ['schema_migration' => ['mode' => 'auto']]]);
        Registry::getInstance()->add('config', $config);

        $this->assertTrue(SchemaMigrator::isAutoMigrationEnabled());
    }

    public function testIsAutoMigrationEnabledFalseLogOnly(): void
    {
        $config = $this->buildConfig(['data' => ['schema_migration' => ['mode' => 'log_only']]]);
        Registry::getInstance()->add('config', $config);

        $this->assertFalse(SchemaMigrator::isAutoMigrationEnabled());
    }

    public function testIsAutoMigrationEnabledFalseAbort(): void
    {
        $config = $this->buildConfig(['data' => ['schema_migration' => ['mode' => 'abort']]]);
        Registry::getInstance()->add('config', $config);

        $this->assertFalse(SchemaMigrator::isAutoMigrationEnabled());
    }

    public function testIsAutoMigrationEnabledDefaultsToAbort(): void
    {
        $config = $this->buildConfig();
        unset($config['data']);
        Registry::getInstance()->add('config', $config);

        $this->assertFalse(SchemaMigrator::isAutoMigrationEnabled());
    }

    // --- detectMissingFields / detectObsoleteFields with non-existent MediaType ---

    public function testDetectMissingFieldsReturnsEmptyForUnknownObjectType(): void
    {
        $config = $this->buildConfig(['data' => ['media_types' => [77777 => 'NonExistentType']]]);
        Registry::getInstance()->add('config', $config);

        $importData = $this->buildFakeImportData('title', 'Default');

        $result = SchemaMigrator::detectMissingFields(77777, $importData);
        $this->assertSame([], $result);
    }

    public function testDetectObsoleteFieldsReturnsEmptyForUnknownObjectType(): void
    {
        $config = $this->buildConfig(['data' => ['media_types' => [77777 => 'NonExistentType']]]);
        Registry::getInstance()->add('config', $config);

        $importData = $this->buildFakeImportData('title', 'Default');

        $result = SchemaMigrator::detectObsoleteFields(77777, $importData);
        $this->assertSame([], $result);
    }

    // --- migrateIfNeeded ---

    public function testMigrateIfNeededNoMismatchReturnsNotMigrated(): void
    {
        $config = $this->buildConfig([
            'data' => [
                'schema_migration' => ['mode' => 'log_only', 'log_changes' => false],
                'media_types' => [88888 => 'AnotherNonExistent'],
            ],
        ]);
        Registry::getInstance()->add('config', $config);

        $result = SchemaMigrator::migrateIfNeeded(88888, []);

        $this->assertFalse($result['migrated']);
        $this->assertEmpty($result['fields']);
        $this->assertEmpty($result['obsolete_fields']);
    }

    public function testMigrateIfNeededWithEmptyImportDataLogOnly(): void
    {
        $config = $this->buildConfig([
            'data' => [
                'schema_migration' => ['mode' => 'log_only', 'log_changes' => false],
                'media_types' => [88889 => 'StillNonExistent'],
            ],
        ]);
        Registry::getInstance()->add('config', $config);

        $result = SchemaMigrator::migrateIfNeeded(88889, $this->buildFakeImportData('desc', 'Main'));

        $this->assertFalse($result['migrated']);
    }

    // --- addDatabaseColumns / dropDatabaseColumns (safe: use temp table, no ALTER on real schema) ---

    public function testAddDatabaseColumnsToTempTable(): void
    {
        $tempTable = 'objectdata_77770';
        $this->db->execute("CREATE TABLE IF NOT EXISTS {$tempTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_media_object INT,
            language VARCHAR(10)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        try {
            SchemaMigrator::addDatabaseColumns(77770, [
                'test_field_text' => 'text',
                'test_field_int' => 'integer',
                'test_field_date' => 'date',
            ]);

            $columns = $this->db->fetchAll("DESCRIBE {$tempTable}");
            $colNames = array_column($columns, 'Field');

            $this->assertContains('test_field_text', $colNames);
            $this->assertContains('test_field_int', $colNames);
            $this->assertContains('test_field_date', $colNames);
        } finally {
            $this->db->execute("DROP TABLE IF EXISTS {$tempTable}");
        }
    }

    public function testAddDatabaseColumnsSkipsExistingColumns(): void
    {
        $tempTable = 'objectdata_77771';
        $this->db->execute("CREATE TABLE IF NOT EXISTS {$tempTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_media_object INT,
            existing_col LONGTEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        try {
            SchemaMigrator::addDatabaseColumns(77771, [
                'existing_col' => 'text',
                'new_col' => 'text',
            ]);

            $columns = $this->db->fetchAll("DESCRIBE {$tempTable}");
            $colNames = array_column($columns, 'Field');

            $this->assertContains('existing_col', $colNames);
            $this->assertContains('new_col', $colNames);
        } finally {
            $this->db->execute("DROP TABLE IF EXISTS {$tempTable}");
        }
    }

    public function testDropDatabaseColumnsOnTempTable(): void
    {
        $tempTable = 'objectdata_77772';
        $this->db->execute("CREATE TABLE IF NOT EXISTS {$tempTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_media_object INT,
            language VARCHAR(10),
            obsolete_field LONGTEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        try {
            SchemaMigrator::dropDatabaseColumns(77772, ['obsolete_field']);

            $columns = $this->db->fetchAll("DESCRIBE {$tempTable}");
            $colNames = array_column($columns, 'Field');

            $this->assertNotContains('obsolete_field', $colNames);
            $this->assertContains('id', $colNames);
            $this->assertContains('id_media_object', $colNames);
            $this->assertContains('language', $colNames);
        } finally {
            $this->db->execute("DROP TABLE IF EXISTS {$tempTable}");
        }
    }

    public function testDropDatabaseColumnsProtectsReservedColumns(): void
    {
        $tempTable = 'objectdata_77773';
        $this->db->execute("CREATE TABLE IF NOT EXISTS {$tempTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_media_object INT,
            language VARCHAR(10),
            removable LONGTEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        try {
            SchemaMigrator::dropDatabaseColumns(77773, ['id', 'id_media_object', 'language', 'removable']);

            $columns = $this->db->fetchAll("DESCRIBE {$tempTable}");
            $colNames = array_column($columns, 'Field');

            $this->assertContains('id', $colNames);
            $this->assertContains('id_media_object', $colNames);
            $this->assertContains('language', $colNames);
            $this->assertNotContains('removable', $colNames);
        } finally {
            $this->db->execute("DROP TABLE IF EXISTS {$tempTable}");
        }
    }

    public function testDropDatabaseColumnsSkipsNonExistentColumns(): void
    {
        $tempTable = 'objectdata_77774';
        $this->db->execute("CREATE TABLE IF NOT EXISTS {$tempTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_media_object INT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        try {
            SchemaMigrator::dropDatabaseColumns(77774, ['does_not_exist']);

            $columns = $this->db->fetchAll("DESCRIBE {$tempTable}");
            $this->assertCount(2, $columns);
        } finally {
            $this->db->execute("DROP TABLE IF EXISTS {$tempTable}");
        }
    }

    public function testAddDatabaseColumnsThrowsForMissingTable(): void
    {
        $this->db->execute('DROP TABLE IF EXISTS objectdata_77779');

        $this->expectException(\Exception::class);
        SchemaMigrator::addDatabaseColumns(77779, ['some_field' => 'text']);
    }

    /**
     * Build minimal fake import data with one field+section for testing buildExpectedFieldsFromImportData.
     *
     * @return array
     */
    private function buildFakeImportData(string $varName, string $sectionName): array
    {
        $section = new \stdClass();
        $section->name = $sectionName;

        $field = new \stdClass();
        $field->var_name = $varName;
        $field->type = 'text';
        $field->sections = [$section];

        return [$field];
    }
}
