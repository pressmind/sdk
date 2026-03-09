<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;
use Pressmind\Search\MongoDB\Indexer;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for MongoDB Indexer infrastructure: collection index creation
 * (including custom_order), index check cache, collection lifecycle, deleteMediaObject.
 *
 * Requires real MongoDB connection (skipped otherwise).
 */
class IndexerInfrastructureTest extends AbstractIntegrationTestCase
{
    private const TEST_PREFIX = 'test_infra_';

    private ?Indexer $indexer = null;

    private array $createdCollections = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->addSearchConfigToRegistry();
        MongoDB::clearConnectionCache();

        if ($this->db === null || $this->mongoDb === null) {
            return;
        }

        Indexer::resetIndexCheckCache();
        $this->indexer = new Indexer();
    }

    protected function tearDown(): void
    {
        if ($this->mongoDb !== null) {
            foreach ($this->createdCollections as $name) {
                try {
                    $this->mongoDb->selectCollection($name)->drop();
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }
        MongoDB::clearConnectionCache();
        parent::tearDown();
    }

    private function addSearchConfigToRegistry(): void
    {
        $mongoUri = getenv('MONGODB_URI');
        $mongoDb = getenv('MONGODB_DB');
        if (empty($mongoUri) || empty($mongoDb)) {
            return;
        }
        $config = $this->getIntegrationConfig();
        $config['data'] = [
            'search_mongodb' => [
                'database' => ['uri' => $mongoUri, 'db' => $mongoDb],
                'search' => [
                    'build_for' => [],
                    'custom_order' => [],
                    'touristic' => [
                        'occupancies' => [1, 2],
                        'duration_ranges' => [[1, 7], [8, 14]],
                    ],
                    'descriptions' => [],
                    'categories' => [],
                    'groups' => [],
                    'locations' => [],
                ],
            ],
            'touristic' => [],
            'media_types_allowed_visibilities' => [],
            'media_types_fulltext_index_fields' => [],
            'search_opensearch' => ['enabled' => true, 'enabled_in_mongo_search' => true],
        ];
        Registry::getInstance()->add('config', $config);
    }

    private function requireMongo(): void
    {
        if ($this->mongoDb === null || $this->indexer === null) {
            $this->markTestSkipped('MongoDB required');
        }
    }

    private function trackCollection(string $name): void
    {
        $this->createdCollections[] = $name;
    }

    public function testCreateCollectionIndexCreatesStandardIndexes(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'std_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        $this->indexer->createCollectionIndex($name);

        $indexNames = [];
        foreach ($this->mongoDb->selectCollection($name)->listIndexes() as $idx) {
            $indexNames[] = $idx->getName();
        }

        $expected = [
            'groups_1',
            'prices.price_total_1',
            'prices.price_total_-1',
            'prices.date_departure_1',
            'prices.date_departure_-1',
            'prices.duration_1',
            'prices.occupancy_1',
            'categories.it_item_1',
            'id_media_object_1',
            'sold_out_1',
            'sales_priority_1',
        ];
        foreach ($expected as $idxName) {
            $this->assertContains($idxName, $indexNames, "Missing index: {$idxName}");
        }
    }

    public function testCreateCollectionIndexWithCustomOrderCreatesDynamicIndexes(): void
    {
        $this->requireMongo();
        $mongoUri = getenv('MONGODB_URI');
        $mongoDb = getenv('MONGODB_DB');
        if (empty($mongoUri) || empty($mongoDb)) {
            $this->markTestSkipped('MongoDB env required');
        }
        $config = $this->getIntegrationConfig();
        $config['data'] = [
            'search_mongodb' => [
                'database' => ['uri' => $mongoUri, 'db' => $mongoDb],
                'search' => [
                    'build_for' => [],
                    'custom_order' => [
                        2000 => [
                            'brand' => ['field' => 'brand'],
                            'region' => ['field' => 'region'],
                        ],
                    ],
                    'touristic' => [
                        'occupancies' => [1, 2],
                        'duration_ranges' => [[1, 7], [8, 14]],
                    ],
                    'descriptions' => [],
                    'categories' => [],
                    'groups' => [],
                    'locations' => [],
                ],
            ],
            'touristic' => [],
            'media_types_allowed_visibilities' => [],
            'media_types_fulltext_index_fields' => [],
            'search_opensearch' => ['enabled' => true, 'enabled_in_mongo_search' => true],
        ];
        Registry::getInstance()->add('config', $config);
        Indexer::resetIndexCheckCache();
        $indexer = new Indexer();

        $name = self::TEST_PREFIX . 'custom_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);
        $indexer->createCollectionIndex($name);

        $indexNames = [];
        foreach ($this->mongoDb->selectCollection($name)->listIndexes() as $idx) {
            $indexNames[] = $idx->getName();
        }
        $this->assertContains('custom_order.brand_1', $indexNames);
        $this->assertContains('custom_order.brand_-1', $indexNames);
        $this->assertContains('custom_order.region_1', $indexNames);
    }

    public function testIndexCheckCacheSkipsSecondCall(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'cache_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        Indexer::resetIndexCheckCache();
        $this->indexer->createCollectionIndex($name);
        $countFirst = iterator_count($this->mongoDb->selectCollection($name)->listIndexes());

        $this->indexer->createCollectionIndex($name);
        $countSecond = iterator_count($this->mongoDb->selectCollection($name)->listIndexes());
        $this->assertSame($countFirst, $countSecond, 'Second call should not change indexes (cached)');
    }

    public function testResetIndexCheckCacheAllowsRecheck(): void
    {
        $this->requireMongo();
        Indexer::resetIndexCheckCache();
        $this->assertTrue(true, 'No exception');
    }

    public function testCollectionExistsReturnsTrueForExisting(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'exist_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->selectCollection($name)->insertOne(['_id' => 1]);

        $this->assertTrue($this->indexer->collectionExists($name));
    }

    public function testCollectionExistsReturnsFalseForMissing(): void
    {
        $this->requireMongo();
        $this->assertFalse($this->indexer->collectionExists('nonexistent_' . uniqid()));
    }

    public function testRemoveTempCollectionsPreservesNonTemp(): void
    {
        $this->requireMongo();
        $tempName = 'temp_infra_' . uniqid();
        $normalName = self::TEST_PREFIX . 'normal_' . uniqid();
        $this->trackCollection($tempName);
        $this->trackCollection($normalName);

        $this->mongoDb->selectCollection($tempName)->insertOne(['_id' => 1]);
        $this->mongoDb->selectCollection($normalName)->insertOne(['_id' => 1]);

        $removed = $this->indexer->removeTempCollections();

        $this->assertGreaterThanOrEqual(1, $removed);
        $this->assertFalse($this->indexer->collectionExists($tempName));
        $this->assertTrue($this->indexer->collectionExists($normalName));
    }

    public function testDeleteMediaObjectRemovesFromBuildForCollections(): void
    {
        $this->requireMongo();
        $mongoUri = getenv('MONGODB_URI');
        $mongoDbName = getenv('MONGODB_DB');
        if (empty($mongoUri) || empty($mongoDbName)) {
            $this->markTestSkipped('MongoDB env required');
        }
        $agency = 'infra_del_' . uniqid();
        $collectionName = 'best_price_search_based_de_origin_0_agency_' . $agency;
        $this->trackCollection($collectionName);

        $config = $this->getIntegrationConfig();
        $config['data'] = [
            'search_mongodb' => [
                'database' => ['uri' => $mongoUri, 'db' => $mongoDbName],
                'search' => [
                    'build_for' => [
                        1 => [['origin' => 0, 'language' => 'de']],
                    ],
                    'custom_order' => [],
                    'touristic' => [
                        'agency_based_option_and_prices' => [
                            'enabled' => true,
                            'allowed_agencies' => [$agency],
                        ],
                        'occupancies' => [1, 2],
                        'duration_ranges' => [[1, 7], [8, 14]],
                    ],
                    'descriptions' => [],
                    'categories' => [],
                    'groups' => [],
                    'locations' => [],
                ],
            ],
            'touristic' => [
                'agency_based_option_and_prices' => [
                    'enabled' => true,
                    'allowed_agencies' => [$agency],
                ],
            ],
            'media_types_allowed_visibilities' => [],
            'media_types_fulltext_index_fields' => [],
            'search_opensearch' => ['enabled' => true, 'enabled_in_mongo_search' => true],
        ];
        Registry::getInstance()->add('config', $config);
        Indexer::resetIndexCheckCache();
        $indexer = new Indexer();

        $this->mongoDb->createCollection($collectionName);
        $testId = 999991;
        $this->mongoDb->selectCollection($collectionName)->insertOne([
            '_id' => $testId,
            'id_media_object' => $testId,
            'code' => 'DEL-TEST',
        ]);
        $this->assertSame(1, $this->mongoDb->selectCollection($collectionName)->countDocuments(['_id' => $testId]));

        $indexer->deleteMediaObject($testId);

        $this->assertSame(0, $this->mongoDb->selectCollection($collectionName)->countDocuments(['_id' => $testId]));
    }

    public function testFlushCollectionRemovesAllDocuments(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'flush_' . uniqid();
        $this->trackCollection($name);
        $coll = $this->mongoDb->selectCollection($name);
        $coll->insertMany([['_id' => 1], ['_id' => 2], ['_id' => 3]]);
        $this->assertSame(3, $coll->countDocuments());

        $this->indexer->flushCollection($name);
        $this->assertSame(0, $coll->countDocuments());
    }

    public function testGetCollectionNameAllVariations(): void
    {
        $this->requireMongo();
        $this->assertSame('best_price_search_based_origin_0', $this->indexer->getCollectionName(0, null, null));
        $this->assertSame('best_price_search_based_de_origin_1', $this->indexer->getCollectionName(1, 'de', null));
        $this->assertSame('best_price_search_based_en_origin_0_agency_X', $this->indexer->getCollectionName(0, 'en', 'X'));
        $this->assertSame('description_de_origin_0', $this->indexer->getCollectionName(0, 'de', null, 'description_'));
    }
}
