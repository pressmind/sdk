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

    /**
     * Price sort must put "best" first: occupancy (DZ > EZ > other) > state (100 > 200 > 300) > price_total ASC > duration DESC.
     * Ensures best_price_meta = prices[0] matches getCheapestPrice / MongoDB $reduce logic.
     */
    public function testPriceSortPutsBestFirst(): void
    {
        $indexer = $this->createIndexerStub();
        $ref = new \ReflectionClass(Indexer::class);
        $sortMethod = $ref->getMethod('_priceSort');
        $sortMethod->setAccessible(true);

        $prices = [
            (object) ['occupancy' => 1, 'state' => 100, 'price_total' => 500.0, 'duration' => 7],
            (object) ['occupancy' => 2, 'state' => 100, 'price_total' => 1000.0, 'duration' => 7],
            (object) ['occupancy' => 2, 'state' => 200, 'price_total' => 800.0, 'duration' => 7],
            (object) ['occupancy' => 2, 'state' => 100, 'price_total' => 800.0, 'duration' => 14],
        ];
        usort($prices, function ($a, $b) use ($indexer, $sortMethod) {
            return $sortMethod->invoke($indexer, $a, $b);
        });

        $first = $prices[0];
        $this->assertSame(2, $first->occupancy, 'Best must be DZ first');
        $this->assertSame(100, $first->state, 'Best must be bookable');
        $this->assertSame(800.0, $first->price_total, 'Among DZ bookable, lowest price first');
        $this->assertEquals(14, $first->duration, 'Among same price, longer duration first');
    }

    /**
     * Occupancy rank: DZ=0, EZ=1, other/null=2.
     */
    public function testOccupancyRank(): void
    {
        $indexer = $this->createIndexerStub();
        $ref = new \ReflectionClass(Indexer::class);
        $rankMethod = $ref->getMethod('_occupancyRank');
        $rankMethod->setAccessible(true);

        $this->assertSame(0, $rankMethod->invoke($indexer, 2));
        $this->assertSame(1, $rankMethod->invoke($indexer, 1));
        $this->assertSame(2, $rankMethod->invoke($indexer, 3));
        $this->assertSame(2, $rankMethod->invoke($indexer, null));
    }

    // ---------------------------------------------------------------
    // updatePriceAndStateFields
    // ---------------------------------------------------------------

    /**
     * When MongoDB is not enabled in config, updatePriceAndStateFields returns without error and without touching DB.
     */
    public function testUpdatePriceAndStateFieldsSkipsWhenMongoNotConfigured(): void
    {
        $config = \Pressmind\Registry::getInstance()->get('config');
        $config['data'] = array_merge($config['data'] ?? [], ['search_mongodb' => ['enabled' => false]]);
        \Pressmind\Registry::getInstance()->add('config', $config);

        $indexer = $this->getMockBuilder(Indexer::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $indexer->updatePriceAndStateFields(12345);
        $this->assertTrue(true, 'No exception when MongoDB not configured');
    }

    /**
     * updatePriceAndStateFields exists and is public (contract test).
     */
    public function testUpdatePriceAndStateFieldsIsPublic(): void
    {
        $ref = new \ReflectionClass(Indexer::class);
        $this->assertTrue($ref->hasMethod('updatePriceAndStateFields'));
        $method = $ref->getMethod('updatePriceAndStateFields');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('idMediaObject', $params[0]->getName());
    }
}
