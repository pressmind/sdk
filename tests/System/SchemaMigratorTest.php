<?php

namespace Pressmind\Tests\System;

use PHPUnit\Framework\TestCase;
use Pressmind\System\SchemaMigrator;

/**
 * Unit tests for the SchemaMigrator class.
 * 
 * These tests verify the schema migration functionality without requiring
 * a database connection. Integration tests should be added separately.
 */
class SchemaMigratorTest extends TestCase
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
     * Test isAutoMigrationEnabled returns false when config is not set.
     * 
     * Note: This test requires Registry to be set up with a mock config.
     * In a real test environment, you would mock the Registry.
     */
    public function testIsAutoMigrationEnabledDefaultsToFalse(): void
    {
        // When no config is set, isAutoMigrationEnabled should return false
        // because the default mode is 'abort'
        // This test is marked as skipped since it requires Registry setup
        $this->markTestSkipped('Requires Registry mock setup');
    }

    /**
     * Test that detectMissingFields correctly identifies new fields.
     * 
     * Note: This test requires Registry and MediaType Factory to be set up.
     */
    public function testDetectMissingFieldsIdentifiesNewFields(): void
    {
        // This test is marked as skipped since it requires Registry setup
        $this->markTestSkipped('Requires Registry and MediaType Factory mock setup');
    }
}
