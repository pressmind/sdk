<?php

namespace Pressmind\Tests\Unit\Import;

use Custom\MediaType\Pauschalreise;
use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\Import;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class ImportFreshIndexingTest extends AbstractTestCase
{
    public function testIndexMediaObjectFromDatabaseUsesCommittedMediaObjectData(): void
    {
        Registry::getInstance()->add('config', $this->createMockConfig([
            'cache' => [
                'enabled' => true,
                'types' => ['OBJECT'],
            ],
            'data' => [
                'schema_migration' => [
                    'mode' => 'log_only',
                ],
                'languages' => [
                    'allowed' => ['de'],
                    'default' => 'de',
                ],
                'media_types' => [
                    1 => 'pauschalreise',
                ],
                'media_types_fulltext_index_fields' => [
                    1 => ['code', 'name', 'tags'],
                ],
                'search_mongodb' => [
                    'enabled' => false,
                    'search' => [
                        'build_for' => [],
                    ],
                ],
                'search_opensearch' => [
                    'enabled' => false,
                ],
            ],
        ]));

        $mediaTypeData = new Pauschalreise();
        $mediaTypeData->id = 1;
        $mediaTypeData->id_media_object = 123;
        $mediaTypeData->language = 'de';
        $mediaTypeData->name = 'Donau';

        $mediaObjectRow = (object) [
            'id' => 123,
            'id_object_type' => 1,
            'code' => 'SHIP-123',
            'name' => 'Testschiff',
            'tags' => '',
        ];
        $fulltextRows = [];

        $db = $this->createMock(AdapterInterface::class);
        $db->expects($this->once())
            ->method('fetchRow')
            ->with(
                $this->stringContains('pmt2core_media_objects'),
                [123]
            )
            ->willReturn($mediaObjectRow);
        $db->method('fetchAll')
            ->willReturnCallback(function ($query) use ($mediaTypeData) {
                if (strpos($query, 'objectdata_1') !== false) {
                    return [$mediaTypeData];
                }

                return [];
            });
        $db->method('insert')
            ->willReturnCallback(function ($table, $data) use (&$fulltextRows) {
                if ($table === 'pmt2core_fulltext_search') {
                    $fulltextRows[] = $data;
                }

                return null;
            });
        $db->method('delete')->willReturn(null);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('inTransaction')->willReturn(false);
        Registry::getInstance()->add('db', $db);

        $import = new Import('mediaobject');
        $indexMediaObjectFromDatabase = \Closure::bind(function ($idMediaObject) {
            $this->_indexMediaObjectFromDatabase($idMediaObject);
        }, $import, Import::class);
        $indexMediaObjectFromDatabase(123);

        $completeFulltextRows = array_values(array_filter(
            $fulltextRows,
            static function ($row) {
                return $row['var_name'] === 'fulltext' && $row['language'] === 'de';
            }
        ));

        $this->assertCount(1, $completeFulltextRows);
        $this->assertStringContainsString('Donau', $completeFulltextRows[0]['fulltext_values']);
    }
}
