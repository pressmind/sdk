<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;
use Pressmind\Search\MongoDB\Indexer;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for MongoDB Indexer: document counting, listing,
 * powerfilter upsert (via raw collection), getCollectionNames,
 * and collection lifecycle methods not covered in MongoDBIndexerCollectionTest.
 *
 * Requires real MongoDB connection (skipped otherwise).
 */
class MongoDBIndexerDocumentTest extends AbstractIntegrationTestCase
{
    private const TEST_PREFIX = 'test_idxdoc_';

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

    public function testDocumentCountOnEmptyCollection(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'empty_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        $count = $this->mongoDb->selectCollection($name)->countDocuments();
        $this->assertSame(0, $count);
    }

    public function testDocumentCountAfterInsert(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'count_' . uniqid();
        $this->trackCollection($name);
        $coll = $this->mongoDb->selectCollection($name);
        $coll->insertMany([
            ['_id' => 1, 'data' => 'a'],
            ['_id' => 2, 'data' => 'b'],
            ['_id' => 3, 'data' => 'c'],
        ]);

        $this->assertSame(3, $coll->countDocuments());
    }

    public function testDocumentCountAfterFlush(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'flush_' . uniqid();
        $this->trackCollection($name);
        $coll = $this->mongoDb->selectCollection($name);
        $coll->insertMany([['_id' => 1], ['_id' => 2]]);
        $this->assertSame(2, $coll->countDocuments());

        $this->indexer->flushCollection($name);
        $this->assertSame(0, $coll->countDocuments());
    }

    public function testCollectionExistsAfterDirectCreate(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'directcreate_' . uniqid();
        $this->trackCollection($name);

        $this->assertFalse($this->indexer->collectionExists($name));
        $this->mongoDb->createCollection($name);
        $this->assertTrue($this->indexer->collectionExists($name));
    }

    public function testCreateCollectionIfNotExistsCreatesWithIndex(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'withidx_' . uniqid();
        $this->trackCollection($name);

        Indexer::resetIndexCheckCache();
        $this->indexer->createCollectionIfNotExists($name);

        $this->assertTrue($this->indexer->collectionExists($name));

        $indexNames = [];
        foreach ($this->mongoDb->selectCollection($name)->listIndexes() as $idx) {
            $indexNames[] = $idx->getName();
        }
        $this->assertContains('id_media_object_1', $indexNames, 'Should have unique id_media_object index');
    }

    public function testFlushCollectionIsIdempotent(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'flushidem_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        $this->indexer->flushCollection($name);
        $this->indexer->flushCollection($name);

        $this->assertSame(0, $this->mongoDb->selectCollection($name)->countDocuments());
    }

    public function testRemoveTempCollectionsPreservesNonTemp(): void
    {
        $this->requireMongo();
        $tempName = 'temp_doctest_' . uniqid();
        $regularName = self::TEST_PREFIX . 'keep_' . uniqid();
        $this->trackCollection($tempName);
        $this->trackCollection($regularName);

        $this->mongoDb->selectCollection($tempName)->insertOne(['_id' => 1]);
        $this->mongoDb->selectCollection($regularName)->insertOne(['_id' => 1]);

        $removed = $this->indexer->removeTempCollections();

        $this->assertGreaterThanOrEqual(1, $removed);
        $this->assertFalse($this->indexer->collectionExists($tempName));
        $this->assertTrue($this->indexer->collectionExists($regularName));
    }

    public function testRemoveTempCollectionsReturnsZeroWhenNoTemp(): void
    {
        $this->requireMongo();

        $existing = [];
        foreach ($this->mongoDb->listCollections() as $coll) {
            if (strpos($coll->getName(), 'temp_') === 0) {
                $existing[] = $coll->getName();
            }
        }
        foreach ($existing as $name) {
            $this->mongoDb->selectCollection($name)->drop();
        }

        $removed = $this->indexer->removeTempCollections();
        $this->assertSame(0, $removed);
    }

    public function testPowerfilterCollectionUpsert(): void
    {
        $this->requireMongo();
        $pfCollName = 'powerfilter';
        $coll = $this->mongoDb->selectCollection($pfCollName);
        $coll->drop();

        $coll->insertOne([
            '_id' => 'pf_test_1',
            'id_media_objects' => [100, 200, 300],
        ]);
        $coll->insertOne([
            '_id' => 'pf_test_2',
            'id_media_objects' => [400, 500],
        ]);

        $this->assertSame(2, $coll->countDocuments());

        $doc = $coll->findOne(['_id' => 'pf_test_1']);
        $this->assertNotNull($doc);
        $ids = (array) $doc['id_media_objects'];
        $this->assertCount(3, $ids);

        $coll->updateOne(
            ['_id' => 'pf_test_1'],
            ['$set' => ['id_media_objects' => [100, 200, 300, 600]]],
            ['upsert' => true]
        );

        $doc = $coll->findOne(['_id' => 'pf_test_1']);
        $ids = (array) $doc['id_media_objects'];
        $this->assertCount(4, $ids);
        $this->assertContains(600, $ids);

        $coll->deleteMany(['_id' => ['$nin' => ['pf_test_1']]]);
        $this->assertSame(1, $coll->countDocuments());

        $coll->drop();
    }

    public function testGetCollectionNameWithCustomPrefix(): void
    {
        $this->requireMongo();
        $this->assertSame(
            'powerfilter_de_origin_0',
            $this->indexer->getCollectionName(0, 'de', null, 'powerfilter_')
        );
        $this->assertSame(
            'stats_origin_1',
            $this->indexer->getCollectionName(1, null, null, 'stats_')
        );
        $this->assertSame(
            'custom_en_origin_2_agency_myag',
            $this->indexer->getCollectionName(2, 'en', 'myag', 'custom_')
        );
    }

    public function testIndexExistsReturnsFalseForNonexistentIndex(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'noidx_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        $this->assertFalse($this->indexer->indexExists($name, 'does_not_exist'));
    }

    public function testCreateCollectionIndexIfNotExistsCreatesNewIndex(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'newcidx_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        $this->assertFalse($this->indexer->indexExists($name, 'custom_field_1'));

        $this->indexer->createCollectionIndexIfNotExists(
            $name,
            ['custom_field' => 1],
            ['name' => 'custom_field_1']
        );

        $this->assertTrue($this->indexer->indexExists($name, 'custom_field_1'));
    }

    public function testGetTreeDepthWithCircularReferenceProtection(): void
    {
        $list = [
            (object) ['item' => (object) ['id' => 'a', 'id_parent' => 'b'], 'id_item' => 'a'],
            (object) ['item' => (object) ['id' => 'b', 'id_parent' => null], 'id_item' => 'b'],
        ];
        $this->assertSame(1, Indexer::getTreeDepth($list, 'a'));
        $this->assertSame(0, Indexer::getTreeDepth($list, 'b'));
    }

    public function testGetTreePathWithMultipleLevels(): void
    {
        $list = [
            (object) ['item' => (object) ['id' => 1, 'id_parent' => null, 'name' => 'World'], 'id_item' => 1],
            (object) ['item' => (object) ['id' => 2, 'id_parent' => 1, 'name' => 'Europe'], 'id_item' => 2],
            (object) ['item' => (object) ['id' => 3, 'id_parent' => 2, 'name' => 'Germany'], 'id_item' => 3],
            (object) ['item' => (object) ['id' => 4, 'id_parent' => 3, 'name' => 'Bavaria'], 'id_item' => 4],
        ];
        $path = Indexer::getTreePath($list, 4, 'name');
        $this->assertCount(4, $path);
        $this->assertContains('Bavaria', $path);
        $this->assertContains('Germany', $path);
        $this->assertContains('Europe', $path);
        $this->assertContains('World', $path);
    }

    public function testGetTreeDepthDeepNesting(): void
    {
        $list = [
            (object) ['item' => (object) ['id' => 1, 'id_parent' => null], 'id_item' => 1],
            (object) ['item' => (object) ['id' => 2, 'id_parent' => 1], 'id_item' => 2],
            (object) ['item' => (object) ['id' => 3, 'id_parent' => 2], 'id_item' => 3],
            (object) ['item' => (object) ['id' => 4, 'id_parent' => 3], 'id_item' => 4],
            (object) ['item' => (object) ['id' => 5, 'id_parent' => 4], 'id_item' => 5],
        ];
        $this->assertSame(0, Indexer::getTreeDepth($list, 1));
        $this->assertSame(4, Indexer::getTreeDepth($list, 5));
    }

    public function testGetTreePathEmptyList(): void
    {
        $path = Indexer::getTreePath([], 'any', 'name');
        $this->assertSame([], $path);
    }

    public function testGetTreeDepthEmptyList(): void
    {
        $depth = Indexer::getTreeDepth([], 'any');
        $this->assertSame(0, $depth);
    }

    public function testResetIndexCheckCacheAllowsReindexing(): void
    {
        $this->requireMongo();
        $name = self::TEST_PREFIX . 'cachetest_' . uniqid();
        $this->trackCollection($name);
        $this->mongoDb->createCollection($name);

        $this->indexer->createCollectionIndex($name);
        $firstIndexCount = iterator_count($this->mongoDb->selectCollection($name)->listIndexes());

        $this->mongoDb->selectCollection($name)->dropIndex('sold_out_1');
        $afterDropCount = iterator_count($this->mongoDb->selectCollection($name)->listIndexes());
        $this->assertLessThan($firstIndexCount, $afterDropCount);

        $this->indexer->createCollectionIndex($name);
        $cachedCount = iterator_count($this->mongoDb->selectCollection($name)->listIndexes());
        $this->assertSame($afterDropCount, $cachedCount, 'Cached call should NOT recreate');

        Indexer::resetIndexCheckCache();
        $this->indexer->createCollectionIndex($name);
        $afterResetCount = iterator_count($this->mongoDb->selectCollection($name)->listIndexes());
        $this->assertSame($firstIndexCount, $afterResetCount, 'After reset, index should be recreated');
    }
}
