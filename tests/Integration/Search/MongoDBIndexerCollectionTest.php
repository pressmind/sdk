<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;
use Pressmind\Search\MongoDB\Indexer;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for MongoDB Indexer collection and index management.
 * Covers: collectionExists, getCollectionName, createCollectionIndex,
 * createCollectionIfNotExists, flushCollection, removeTempCollections,
 * indexExists, createCollectionIndexIfNotExists.
 *
 * Requires real MongoDB connection (skipped otherwise).
 */
class MongoDBIndexerCollectionTest extends AbstractIntegrationTestCase
{
    private const TEST_PREFIX = 'test_indexer_';

    private ?Indexer $indexer = null;

    /** Track collections created during tests for cleanup */
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

    public function testCollectionExistsReturnsTrueForExisting(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'exists_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->selectCollection($name)->insertOne(['_id' => 1]);

        $this->assertTrue($this->indexer->collectionExists($name));
    }

    public function testCollectionExistsReturnsFalseForMissing(): void
    {
        $this->requireMongo();
        $this->assertFalse($this->indexer->collectionExists('nonexistent_' . uniqid()));
    }

    public function testGetCollectionNameVariations(): void
    {
        $this->requireMongo();
        $this->assertSame(
            'best_price_search_based_de_origin_1',
            $this->indexer->getCollectionName(1, 'de', null)
        );
        $this->assertSame(
            'best_price_search_based_en_origin_0_agency_foo',
            $this->indexer->getCollectionName(0, 'en', 'foo')
        );
        $this->assertSame(
            'best_price_search_based_origin_0',
            $this->indexer->getCollectionName(0, null, null)
        );
        $this->assertSame(
            'description_de_origin_0',
            $this->indexer->getCollectionName(0, 'de', null, 'description_')
        );
        $this->assertSame(
            'custom_prefix_origin_5',
            $this->indexer->getCollectionName(5, null, null, 'custom_prefix_')
        );
    }

    public function testCreateCollectionIndexCreatesExpectedIndexes(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'idx_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        $this->indexer->createCollectionIndex($name);

        $indexNames = [];
        foreach ($this->mongoDb->selectCollection($name)->listIndexes() as $idx) {
            $indexNames[] = $idx->getName();
        }

        $expectedSubset = [
            'id_media_object_1',
            'prices.price_total_1',
            'prices.price_total_-1',
            'prices.date_departure_1',
            'prices.duration_1',
            'groups_1',
            'categories.it_item_1',
            'sold_out_1',
        ];
        foreach ($expectedSubset as $expected) {
            $this->assertContains($expected, $indexNames, "Missing index: {$expected}");
        }
    }

    public function testCreateCollectionIndexSkipsOnSecondCall(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'skip_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        Indexer::resetIndexCheckCache();
        $this->indexer->createCollectionIndex($name);
        $countAfterFirst = iterator_count($this->mongoDb->selectCollection($name)->listIndexes());

        // Drop one index manually
        $this->mongoDb->selectCollection($name)->dropIndex('groups_1');
        $countAfterDrop = iterator_count($this->mongoDb->selectCollection($name)->listIndexes());
        $this->assertLessThan($countAfterFirst, $countAfterDrop);

        // Second call should be cached and NOT recreate the dropped index
        $this->indexer->createCollectionIndex($name);
        $countAfterSecond = iterator_count($this->mongoDb->selectCollection($name)->listIndexes());
        $this->assertSame($countAfterDrop, $countAfterSecond, 'Cached call should not recreate indexes');
    }

    public function testResetIndexCheckCacheAllowsReindex(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'reset_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        $this->indexer->createCollectionIndex($name);
        $this->mongoDb->selectCollection($name)->dropIndex('groups_1');

        Indexer::resetIndexCheckCache();
        $this->indexer->createCollectionIndex($name);

        $indexNames = [];
        foreach ($this->mongoDb->selectCollection($name)->listIndexes() as $idx) {
            $indexNames[] = $idx->getName();
        }
        $this->assertContains('groups_1', $indexNames, 'After cache reset, index should be recreated');
    }

    public function testCreateCollectionIfNotExistsCreatesNew(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'create_' . uniqid();
        $this->trackCollection($name);

        $this->assertFalse($this->indexer->collectionExists($name));
        $this->indexer->createCollectionIfNotExists($name);
        $this->assertTrue($this->indexer->collectionExists($name));
    }

    public function testCreateCollectionIfNotExistsIdempotent(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'idempotent_' . uniqid();
        $this->trackCollection($name);

        Indexer::resetIndexCheckCache();
        $result1 = $this->indexer->createCollectionIfNotExists($name);
        Indexer::resetIndexCheckCache();
        $result2 = $this->indexer->createCollectionIfNotExists($name);
        $this->assertTrue($result1);
        $this->assertTrue($result2);
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

    public function testRemoveTempCollections(): void
    {
        $this->requireMongo();
        $tempName = 'temp_test_' . uniqid();
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

    public function testIndexExistsChecksCollectionIndex(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'idxexist_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        $this->mongoDb->selectCollection($name)->createIndex(['test_field' => 1], ['name' => 'test_field_1']);

        $this->assertTrue($this->indexer->indexExists($name, 'test_field_1'));
        $this->assertFalse($this->indexer->indexExists($name, 'nonexistent_index'));
    }

    public function testCreateCollectionIndexIfNotExistsIsIdempotent(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'cidx_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        $this->indexer->createCollectionIndexIfNotExists($name, ['myfield' => 1], ['name' => 'myfield_1']);
        $this->indexer->createCollectionIndexIfNotExists($name, ['myfield' => 1], ['name' => 'myfield_1']);

        $count = 0;
        foreach ($this->mongoDb->selectCollection($name)->listIndexes() as $idx) {
            if ($idx->getName() === 'myfield_1') {
                $count++;
            }
        }
        $this->assertSame(1, $count, 'Index should exist exactly once');
    }

    public function testGetCollectionNameStaticMethod(): void
    {
        $this->assertSame(
            'best_price_search_based_de_origin_0',
            MongoDB::getCollectionName('best_price_search_based_', 'de', 0, null)
        );
        $this->assertSame(
            'description_en_origin_1_agency_ag1',
            MongoDB::getCollectionName('description_', 'en', 1, 'ag1')
        );
    }
}
