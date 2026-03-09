<?php

namespace Pressmind\Tests\Unit;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ObjectIntegrityCheck;
use Pressmind\Registry;
use stdClass;

class ObjectIntegrityCheckTest extends AbstractTestCase
{
    /**
     * Build a stdClass column descriptor matching DESCRIBE output.
     */
    private function makeDbColumn(string $field, string $type): stdClass
    {
        $col = new stdClass();
        $col->Field = $field;
        $col->Type = $type;
        return $col;
    }

    /**
     * Build a minimal object definition with one field and one section.
     * HelperFunctions::human_to_machine lowercases and replaces non-alnum with underscores,
     * so "title" + "main" => "title_main".
     */
    private function makeObjectDefinition(string $varName, string $sectionName, string $pmType): stdClass
    {
        $section = new stdClass();
        $section->name = $sectionName;

        $field = new stdClass();
        $field->var_name = $varName;
        $field->type = $pmType;
        $field->sections = [$section];

        $def = new stdClass();
        $def->fields = [$field];

        return $def;
    }

    /**
     * Replace the DB mock with a custom one that returns given columns for DESCRIBE.
     */
    private function registerDbWithColumns(array $columns): void
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn($columns);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('inTransaction')->willReturn(false);

        $registry = Registry::getInstance();
        $registry->add('db', $db);
    }

    public function testGetDifferencesReturnsEmptyWhenDBMatchesDefinition(): void
    {
        $dbColumns = [
            $this->makeDbColumn('id', 'int(11)'),
            $this->makeDbColumn('id_media_object', 'int(11)'),
            $this->makeDbColumn('language', 'varchar(255)'),
            $this->makeDbColumn('title_main', 'text'),
        ];
        $this->registerDbWithColumns($dbColumns);

        $config = $this->createMockConfig([]);
        Registry::getInstance()->add('config', $config);

        $def = $this->makeObjectDefinition('title', 'main', 'plaintext');
        $check = new ObjectIntegrityCheck($def, 'pmt2core_test_table');
        $this->assertSame([], $check->getDifferences());
    }

    public function testGetDifferencesDetectsMissingColumn(): void
    {
        $dbColumns = [
            $this->makeDbColumn('id', 'int(11)'),
            $this->makeDbColumn('id_media_object', 'int(11)'),
            $this->makeDbColumn('language', 'varchar(255)'),
        ];
        $this->registerDbWithColumns($dbColumns);

        $config = $this->createMockConfig([]);
        Registry::getInstance()->add('config', $config);

        $def = $this->makeObjectDefinition('title', 'main', 'plaintext');
        $check = new ObjectIntegrityCheck($def, 'pmt2core_test_table');
        $differences = $check->getDifferences();

        $this->assertNotEmpty($differences);
        $createActions = array_filter($differences, function ($d) {
            return $d['action'] === 'create_column';
        });
        $this->assertNotEmpty($createActions, 'Expected at least one create_column difference');
        $first = reset($createActions);
        $this->assertSame('title_main', $first['column_name']);
    }

    public function testGetDifferencesDetectsExtraDbColumn(): void
    {
        $dbColumns = [
            $this->makeDbColumn('id', 'int(11)'),
            $this->makeDbColumn('id_media_object', 'int(11)'),
            $this->makeDbColumn('language', 'varchar(255)'),
            $this->makeDbColumn('title_main', 'varchar(255)'),
            $this->makeDbColumn('obsolete_col', 'text'),
        ];
        $this->registerDbWithColumns($dbColumns);

        $config = $this->createMockConfig([]);
        Registry::getInstance()->add('config', $config);

        $def = $this->makeObjectDefinition('title', 'main', 'plaintext');
        $check = new ObjectIntegrityCheck($def, 'pmt2core_test_table');
        $differences = $check->getDifferences();

        $dropActions = array_filter($differences, function ($d) {
            return $d['action'] === 'drop_column';
        });
        $this->assertNotEmpty($dropActions, 'Expected at least one drop_column difference');
        $columnNames = array_column($dropActions, 'column_name');
        $this->assertContains('obsolete_col', $columnNames);
    }
}
