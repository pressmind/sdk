<?php

namespace Pressmind\Tests\Unit\Search\MongoDB;

use Pressmind\Search\MongoDB\Indexer;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Search\MongoDB\Indexer: getCollectionName, resetIndexCheckCache (pure logic).
 * No real MongoDB; stub avoids parent::__construct() for getCollectionName tests.
 */
class IndexerTest extends AbstractTestCase
{
    private function createIndexerStub(): Indexer
    {
        $stub = $this->getMockBuilder(Indexer::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionClass(Indexer::class);
        $config = [
            'search' => [
                'build_for' => [],
                'custom_order' => [],
            ],
        ];
        $configProp = $ref->getProperty('_config');
        $configProp->setAccessible(true);
        $configProp->setValue($stub, $config);

        $agenciesProp = $ref->getProperty('_agencies');
        $agenciesProp->setAccessible(true);
        $agenciesProp->setValue($stub, [null]);

        return $stub;
    }

    public function testGetCollectionNameDefaultPrefix(): void
    {
        $indexer = $this->createIndexerStub();
        $name = $indexer->getCollectionName(0, null, null);
        $this->assertSame('best_price_search_based_origin_0', $name);
    }

    public function testGetCollectionNameWithDescriptionPrefix(): void
    {
        $indexer = $this->createIndexerStub();
        $name = $indexer->getCollectionName(0, 'de', null, 'description_');
        $this->assertSame('description_de_origin_0', $name);
    }

    public function testGetCollectionNameWithLanguageAndAgency(): void
    {
        $indexer = $this->createIndexerStub();
        $name = $indexer->getCollectionName(1, 'en', 'X');
        $this->assertSame('best_price_search_based_en_origin_1_agency_X', $name);
    }

    public function testResetIndexCheckCache(): void
    {
        Indexer::resetIndexCheckCache();
        $this->assertTrue(true, 'No exception');
    }
}
