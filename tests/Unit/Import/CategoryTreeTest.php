<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\Import\CategoryTree;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class CategoryTreeTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $ids = [1, 2, 3];
        $import = new CategoryTree($ids);
        $this->assertInstanceOf(CategoryTree::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportPersistsTreeAndItemIcons(): void
    {
        global $_RUNTIME_IMPORTED_CATEGORY_IDS;
        $_RUNTIME_IMPORTED_CATEGORY_IDS = [];

        $replacedTree = null;
        $itemColumns = [];
        $itemRows = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('getTablePrefix')->willReturn('');
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchAll')->willReturn([]);
        $db->method('insert')->willReturnCallback(function (string $table, array $data) use (&$replacedTree): int {
            if ($table === 'pmt2core_category_trees') {
                $replacedTree = $data;
            }
            return 1;
        });
        $db->method('batchInsert')->willReturnCallback(
            function (string $table, array $columns, array $rows) use (&$itemColumns, &$itemRows): int {
                if ($table === 'pmt2core_category_tree_items') {
                    $itemColumns = $columns;
                    $itemRows = $rows;
                }
                return count($rows);
            }
        );
        $db->method('execute')->willReturn(null);
        $db->method('delete')->willReturn(null);
        Registry::getInstance()->add('db', $db);
        Registry::getInstance()->add('config', $this->createMockConfig([
            'data' => [
                'languages' => [
                    'allowed' => [],
                    'default' => 'de',
                    'gettext' => ['active' => false],
                ],
            ],
        ]));

        $icon = (object) [
            'id' => 'ship',
            'name' => 'Ship',
            'slug' => 'ship',
            'url' => 'https://example.test/ship.svg',
            'mime' => 'image/svg+xml',
            'style' => 'regular',
            'variants' => [],
        ];
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'error' => false,
            'result' => [
                (object) [
                    'id' => 7,
                    'name' => 'Destinations',
                    'tree' => (object) [
                        'icon' => $icon,
                        'item' => [
                            (object) [
                                'id' => 'north-sea',
                                'name' => 'North Sea',
                                'icon' => $icon,
                                'item' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        Registry::getInstance()->add('rest_client', $client);

        $import = new CategoryTree([7], true);
        $import->import();

        $this->assertSame('ship', json_decode($replacedTree['icon'], true)['id']);
        $iconColumn = array_search('icon', $itemColumns, true);
        $this->assertNotFalse($iconColumn);
        $this->assertSame('ship', json_decode($itemRows[0][$iconColumn], true)['id']);
    }
}
