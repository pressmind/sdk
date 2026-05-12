<?php

namespace Pressmind\Tests\Unit\Performance;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\Import\TouristicData;
use Pressmind\Registry;
use Pressmind\Search\MongoDB\AbstractIndex;
use Pressmind\Search\MongoDB\Indexer;
use Pressmind\Tests\Unit\AbstractTestCase;

class PerformanceHotspotTest extends AbstractTestCase
{
    public function testCustomOrderReadsMediaObjectContentOncePerIndexedObject(): void
    {
        $fieldCount = 6;
        $data = new \stdClass();
        $customOrderConfig = [];
        for ($i = 1; $i <= $fieldCount; $i++) {
            $shortname = 'sort_' . $i;
            $field = 'field_' . $i;
            $data->{$field} = 'Value ' . $i;
            $customOrderConfig[$shortname] = ['field' => $field];
        }

        $content = new CountingContentObject($data);
        $mediaObject = new CountingCustomOrderMediaObject($content);
        $indexer = $this->createIndexerWithConfig($mediaObject, [
            'search' => [
                'custom_order' => [
                    1 => $customOrderConfig,
                ],
            ],
        ]);

        $method = new \ReflectionMethod(Indexer::class, '_custom_order');
        $method->setAccessible(true);
        $result = $method->invoke($indexer, 'de', null);

        $this->assertSame('Value 1', $result['sort_1']);
        $this->assertLessThanOrEqual(
            1,
            $mediaObject->getDataForLanguageCalls,
            'custom_order should hydrate the localized media object content once, not once per configured sort field.'
        );
        $this->assertLessThanOrEqual(
            1,
            $content->toStdClassCalls,
            'custom_order should convert localized media object content once, not once per configured sort field.'
        );
    }

    public function testMapCategoriesDoesNotRescanTheCategoryTreeForEveryPathLookup(): void
    {
        CountingCategoryNode::reset();
        $categoryCount = 24;
        $categories = [];
        for ($i = 1; $i <= $categoryCount; $i++) {
            $categories[] = new CountingCategoryTreeItem(
                $i,
                new CountingCategoryNode(
                    $i,
                    'Category ' . $i,
                    10,
                    $i > 1 ? $i - 1 : null,
                    'cat-' . $i,
                    $i
                )
            );
        }

        $data = new \stdClass();
        $data->topics = $categories;
        $mediaObject = new CountingCustomOrderMediaObject(new CountingContentObject($data));
        $indexer = $this->createIndexerWithConfig($mediaObject, [
            'search' => [
                'categories' => [
                    1 => [
                        'topics' => [],
                    ],
                ],
            ],
        ]);

        $method = new \ReflectionMethod(Indexer::class, '_mapCategories');
        $method->setAccessible(true);
        $result = $method->invoke($indexer, 'de');
        $idReadsAfterMapping = CountingCategoryNode::$idReads;

        $this->assertCount($categoryCount, $result);
        foreach ($result as $item) {
            $expectedPathStr = Indexer::getTreePath($categories, $item['id_item'], 'name');
            krsort($expectedPathStr);
            $expectedPathIds = Indexer::getTreePath($categories, $item['id_item'], 'id');
            krsort($expectedPathIds);

            $this->assertSame(Indexer::getTreeDepth($categories, $item['id_item']), $item['level']);
            $this->assertSame($expectedPathStr, $item['path_str']);
            $this->assertSame($expectedPathIds, $item['path_ids']);
        }
        $this->assertLessThanOrEqual(
            $categoryCount * 20,
            $idReadsAfterMapping,
            'Category path/depth mapping should build a reusable tree lookup instead of repeatedly scanning every category item.'
        );
    }

    public function testStartingpointOrphanCleanupDoesNotLinearlyScanImportedOptionsForEveryStoredOption(): void
    {
        CountingStartingpointOption::reset();
        $optionCount = 30;
        $startingpointId = 100;
        $importedOptions = [];
        $storedOptions = [];
        for ($i = 1; $i <= $optionCount; $i++) {
            $importedOptions[] = new CountingStartingpointOption($i, $startingpointId);
            $storedOptions[] = (object) [
                'id' => $i,
                'id_startingpoint' => $startingpointId,
            ];
        }

        $db = $this->createMock(AdapterInterface::class);
        $db->expects($this->once())
            ->method('fetchAll')
            ->willReturn($storedOptions);
        $db->expects($this->never())
            ->method('execute');
        Registry::getInstance()->add('db', $db);

        $method = new \ReflectionMethod(TouristicData::class, '_removeStartingPointOrphans');
        $method->setAccessible(true);
        $method->invoke(new TouristicData(), [
            'touristic_startingpoints_options' => $importedOptions,
        ]);

        $this->assertLessThanOrEqual(
            $optionCount * 2,
            CountingStartingpointOption::$idReads,
            'Startingpoint orphan cleanup should pre-index imported option IDs instead of array_filter scanning them for every stored option.'
        );
    }

    public function testStartingpointOrphanCleanupStillDeletesMissingStoredOptions(): void
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                (object) ['id' => 1, 'id_startingpoint' => 100],
                (object) ['id' => 999, 'id_startingpoint' => 100],
            ]);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('DELETE FROM pmt2core_touristic_startingpoint_options WHERE id IN (?)'),
                [999]
            );
        Registry::getInstance()->add('db', $db);

        $method = new \ReflectionMethod(TouristicData::class, '_removeStartingPointOrphans');
        $method->setAccessible(true);
        $method->invoke(new TouristicData(), [
            'touristic_startingpoints_options' => [
                new CountingStartingpointOption(1, 100),
            ],
        ]);
    }

    public function testStartingpointOrphanCleanupKeepsAlphanumericOptionIdsDistinct(): void
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->expects($this->once())
            ->method('fetchAll')
            ->willReturn([
                (object) ['id' => 'spopt_keep', 'id_startingpoint' => 'sp_alpha'],
                (object) ['id' => 'spopt_delete', 'id_startingpoint' => 'sp_alpha'],
            ]);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->stringContains('DELETE FROM pmt2core_touristic_startingpoint_options WHERE id IN (?)'),
                ['spopt_delete']
            );
        Registry::getInstance()->add('db', $db);

        $method = new \ReflectionMethod(TouristicData::class, '_removeStartingPointOrphans');
        $method->setAccessible(true);
        $method->invoke(new TouristicData(), [
            'touristic_startingpoints_options' => [
                (object) ['id' => 'spopt_keep', 'id_starting_point' => 'sp_alpha'],
            ],
        ]);
    }

    private function createIndexerWithConfig(object $mediaObject, array $config): Indexer
    {
        $indexer = $this->getMockBuilder(Indexer::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $configProperty = new \ReflectionProperty(AbstractIndex::class, '_config');
        $configProperty->setAccessible(true);
        $configProperty->setValue($indexer, array_replace_recursive([
            'search' => [
                'custom_order' => [],
                'categories' => [],
            ],
        ], $config));

        $indexer->mediaObject = $mediaObject;

        return $indexer;
    }
}

final class CountingCustomOrderMediaObject
{
    public int $id_object_type = 1;
    public string $name = 'Test object';
    public int $getDataForLanguageCalls = 0;

    public function __construct(private CountingContentObject $content)
    {
    }

    public function getDataForLanguage(string $language): CountingContentObject
    {
        $this->getDataForLanguageCalls++;
        return $this->content;
    }
}

final class CountingContentObject
{
    public int $toStdClassCalls = 0;

    public function __construct(private \stdClass $data)
    {
    }

    public function toStdClass(): \stdClass
    {
        $this->toStdClassCalls++;
        return $this->data;
    }
}

final class CountingCategoryTreeItem
{
    public function __construct(
        public int $id_item,
        public CountingCategoryNode $item
    ) {
    }
}

final class CountingCategoryNode
{
    public static int $idReads = 0;

    public function __construct(
        private int $id,
        private string $name,
        private int $idTree,
        private ?int $idParent,
        private string $code,
        private int $sort
    ) {
    }

    public static function reset(): void
    {
        self::$idReads = 0;
    }

    public function __isset(string $name): bool
    {
        if ($name === 'id') {
            self::$idReads++;
            return true;
        }
        return $this->__get($name) !== null;
    }

    public function __get(string $name)
    {
        if ($name === 'id') {
            self::$idReads++;
            return $this->id;
        }
        if ($name === 'id_tree') {
            return $this->idTree;
        }
        if ($name === 'id_parent') {
            return $this->idParent;
        }
        if ($name === 'name') {
            return $this->name;
        }
        if ($name === 'code') {
            return $this->code;
        }
        if ($name === 'sort') {
            return $this->sort;
        }
        return null;
    }
}

final class CountingStartingpointOption
{
    public static int $idReads = 0;

    public function __construct(
        private int|string $id,
        private int|string $idStartingPoint
    ) {
    }

    public static function reset(): void
    {
        self::$idReads = 0;
    }

    public function __get(string $name)
    {
        if ($name === 'id') {
            self::$idReads++;
            return $this->id;
        }
        if ($name === 'id_starting_point') {
            return $this->idStartingPoint;
        }
        return null;
    }
}
