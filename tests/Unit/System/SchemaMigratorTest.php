<?php

namespace Pressmind\Tests\Unit\System;

use Pressmind\Registry;
use Pressmind\System\SchemaMigrator;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for the SchemaMigrator class.
 *
 * Pure mapping tests (mapFieldType) need no Registry at all.
 * Config-dependent tests use AbstractTestCase which provides a clean Registry per test.
 */
class SchemaMigratorTest extends AbstractTestCase
{
    /**
     * Test that mapFieldType returns correct MySQL types.
     */
    public function testMapFieldTypeReturnsCorrectTypes(): void
    {
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('text'));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('plaintext'));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('wysiwyg'));
        $this->assertEquals('INT(11)', SchemaMigrator::mapFieldType('integer'));
        $this->assertEquals('INT(11)', SchemaMigrator::mapFieldType('int'));
        $this->assertEquals('DATETIME', SchemaMigrator::mapFieldType('date'));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('qrcode'));
    }

    /**
     * Test that mapFieldType returns LONGTEXT for unknown types.
     */
    public function testMapFieldTypeReturnsLongtextForUnknownTypes(): void
    {
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('unknown_type'));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType(''));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('custom'));
    }

    /**
     * Test that relation types are mapped to LONGTEXT.
     */
    public function testMapFieldTypeHandlesRelationTypes(): void
    {
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('picture'));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('objectlink'));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('file'));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('categorytree'));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('location'));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('link'));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('key_value'));
        $this->assertEquals('LONGTEXT', SchemaMigrator::mapFieldType('table'));
    }

    /**
     * When config has schema_migration.mode = 'abort' (which is the default fallback),
     * isAutoMigrationEnabled() must return false.
     */
    public function testIsAutoMigrationEnabledDefaultsToFalse(): void
    {
        $config = $this->createMockConfig([
            'data' => [
                'schema_migration' => [
                    'mode' => 'abort',
                ],
            ],
        ]);
        Registry::getInstance()->add('config', $config);

        $this->assertFalse(SchemaMigrator::isAutoMigrationEnabled());
    }

    /**
     * When mode is 'auto', isAutoMigrationEnabled() must return true.
     */
    public function testIsAutoMigrationEnabledReturnsTrueForAutoMode(): void
    {
        $config = $this->createMockConfig([
            'data' => [
                'schema_migration' => [
                    'mode' => 'auto',
                ],
            ],
        ]);
        Registry::getInstance()->add('config', $config);

        $this->assertTrue(SchemaMigrator::isAutoMigrationEnabled());
    }

    /**
     * When mode is 'log_only', isAutoMigrationEnabled() must return false.
     */
    public function testIsAutoMigrationEnabledReturnsFalseForLogOnlyMode(): void
    {
        $config = $this->createMockConfig([
            'data' => [
                'schema_migration' => [
                    'mode' => 'log_only',
                ],
            ],
        ]);
        Registry::getInstance()->add('config', $config);

        $this->assertFalse(SchemaMigrator::isAutoMigrationEnabled());
    }

    /**
     * When the schema_migration key is missing entirely the method defaults to 'abort'
     * via the ?? 'abort' fallback, so it must return false.
     */
    public function testIsAutoMigrationEnabledReturnsFalseWhenKeyMissing(): void
    {
        $config = $this->createMockConfig([
            'data' => [],
        ]);
        Registry::getInstance()->add('config', $config);

        $this->assertFalse(SchemaMigrator::isAutoMigrationEnabled());
    }

    /**
     * Test buildExpectedFieldsFromImportData (private) via reflection.
     * This is the core logic that extracts field definitions from API import data.
     */
    public function testBuildExpectedFieldsFromImportData(): void
    {
        $config = $this->createMockConfig([
            'data' => [
                'sections' => [
                    'replace' => [
                        'regular_expression' => '/[^a-zA-Z0-9]/',
                        'replacement' => '_',
                    ],
                ],
            ],
        ]);
        Registry::getInstance()->add('config', $config);

        $importData = [
            (object) [
                'var_name' => 'headline',
                'type' => 'text',
                'sections' => [
                    (object) ['name' => 'Standard'],
                    (object) ['name' => 'Detail View'],
                ],
            ],
            (object) [
                'var_name' => 'nights',
                'type' => 'integer',
                'sections' => [
                    (object) ['name' => 'Standard'],
                ],
            ],
            (object) [
                'var_name' => 'orphan',
                'sections' => null,
            ],
        ];

        $method = new \ReflectionMethod(SchemaMigrator::class, 'buildExpectedFieldsFromImportData');
        $method->setAccessible(true);
        $result = $method->invoke(null, $importData, $config);

        $this->assertArrayHasKey('headline_standard', $result);
        $this->assertSame('text', $result['headline_standard']);

        $this->assertArrayHasKey('headline_detail_view', $result);
        $this->assertSame('text', $result['headline_detail_view']);

        $this->assertArrayHasKey('nights_standard', $result);
        $this->assertSame('integer', $result['nights_standard']);

        $this->assertArrayNotHasKey('orphan', $result, 'Fields without sections must be ignored');
        $this->assertCount(3, $result);
    }

    /**
     * When Factory::createById throws (no generated classes), detectMissingFields returns [].
     */
    public function testDetectMissingFieldsReturnsEmptyWhenFactoryFails(): void
    {
        $config = $this->createMockConfig([
            'data' => [
                'media_types' => [99999 => 'nonexistent_test_type'],
            ],
            'logging' => [
                'mode' => 'ALL',
                'storage' => 'none',
            ],
        ]);
        Registry::getInstance()->add('config', $config);

        $result = SchemaMigrator::detectMissingFields(99999, []);
        $this->assertSame([], $result);
    }

    /**
     * migrateIfNeeded with no missing and no obsolete fields returns migrated false.
     */
    public function testMigrateIfNeededReturnsNotMigratedWhenNoDifferences(): void
    {
        $config = $this->createMockConfig([
            'data' => [
                'schema_migration' => ['mode' => 'log_only', 'log_changes' => false],
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        // ObjectType 99999 has no media type class, so detectMissingFields returns [] and detectObsoleteFields returns []
        $result = SchemaMigrator::migrateIfNeeded(99999, []);
        $this->assertFalse($result['migrated']);
        $this->assertSame([], $result['fields']);
        $this->assertSame([], $result['obsolete_fields']);
    }

    /**
     * buildExpectedFieldsFromImportData when sections replace config is empty.
     */
    public function testBuildExpectedFieldsFromImportDataWithoutSectionReplace(): void
    {
        $config = $this->createMockConfig(['data' => []]);
        Registry::getInstance()->add('config', $config);
        $importData = [
            (object) [
                'var_name' => 'title',
                'type' => 'plaintext',
                'sections' => [(object) ['name' => 'Main']],
            ],
        ];
        $method = new \ReflectionMethod(SchemaMigrator::class, 'buildExpectedFieldsFromImportData');
        $method->setAccessible(true);
        $result = $method->invoke(null, $importData, $config);
        $this->assertNotEmpty($result);
        $this->assertSame('plaintext', $result[array_key_first($result)]);
    }

    /**
     * buildExpectedFieldsFromImportData ignores data without sections array.
     */
    public function testBuildExpectedFieldsFromImportDataSkipsMissingSections(): void
    {
        $config = $this->createMockConfig(['data' => []]);
        Registry::getInstance()->add('config', $config);
        $importData = [
            (object) ['var_name' => 'no_sections', 'type' => 'text'],
        ];
        $method = new \ReflectionMethod(SchemaMigrator::class, 'buildExpectedFieldsFromImportData');
        $method->setAccessible(true);
        $result = $method->invoke(null, $importData, $config);
        $this->assertSame([], $result);
    }

    /**
     * detectObsoleteFields returns [] when Factory fails (no class for object type).
     */
    public function testDetectObsoleteFieldsReturnsEmptyWhenFactoryFails(): void
    {
        $config = $this->createMockConfig([
            'data' => ['media_types' => [88888 => 'nonexistent']],
            'logging' => ['mode' => 'ALL', 'storage' => 'none'],
        ]);
        Registry::getInstance()->add('config', $config);
        $result = SchemaMigrator::detectObsoleteFields(88888, []);
        $this->assertSame([], $result);
    }
}
