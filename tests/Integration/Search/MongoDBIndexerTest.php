<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Tests\Integration\AbstractIntegrationTestCase;
use Pressmind\Search\MongoDB\Indexer;

/**
 * Integration tests for MongoDB Indexer helper methods.
 * Static methods (getTreeDepth, getTreePath) are tested without DB.
 * Instance-level tests require full Registry config + MongoDB.
 */
class MongoDBIndexerTest extends AbstractIntegrationTestCase
{
    public function testGetTreeDepthFlat(): void
    {
        $list = [
            (object)['item' => (object)['id' => 'a', 'id_parent' => null], 'id_item' => 'a'],
            (object)['item' => (object)['id' => 'b', 'id_parent' => null], 'id_item' => 'b'],
        ];
        $this->assertSame(0, Indexer::getTreeDepth($list, 'a'));
    }

    public function testGetTreeDepthNested(): void
    {
        $list = [
            (object)['item' => (object)['id' => 'root', 'id_parent' => null], 'id_item' => 'root'],
            (object)['item' => (object)['id' => 'child', 'id_parent' => 'root'], 'id_item' => 'child'],
            (object)['item' => (object)['id' => 'grandchild', 'id_parent' => 'child'], 'id_item' => 'grandchild'],
        ];
        $this->assertSame(0, Indexer::getTreeDepth($list, 'root'));
        $this->assertSame(1, Indexer::getTreeDepth($list, 'child'));
        $this->assertSame(2, Indexer::getTreeDepth($list, 'grandchild'));
    }

    public function testGetTreePathReturnsNamePath(): void
    {
        $list = [
            (object)['item' => (object)['id' => 'r', 'id_parent' => null, 'name' => 'Europe'], 'id_item' => 'r'],
            (object)['item' => (object)['id' => 'c', 'id_parent' => 'r', 'name' => 'Germany'], 'id_item' => 'c'],
            (object)['item' => (object)['id' => 'g', 'id_parent' => 'c', 'name' => 'Bavaria'], 'id_item' => 'g'],
        ];
        $path = Indexer::getTreePath($list, 'g', 'name');
        $this->assertContains('Bavaria', $path);
        $this->assertContains('Germany', $path);
        $this->assertContains('Europe', $path);
        $this->assertCount(3, $path);
    }

    public function testGetTreePathReturnsIdPath(): void
    {
        $list = [
            (object)['item' => (object)['id' => 100, 'id_parent' => null], 'id_item' => 100],
            (object)['item' => (object)['id' => 200, 'id_parent' => 100], 'id_item' => 200],
        ];
        $path = Indexer::getTreePath($list, 200, 'id');
        $this->assertContains(200, $path);
        $this->assertContains(100, $path);
        $this->assertCount(2, $path);
    }

    public function testGetTreePathRootOnly(): void
    {
        $list = [
            (object)['item' => (object)['id' => 'only', 'id_parent' => null, 'name' => 'Root'], 'id_item' => 'only'],
        ];
        $path = Indexer::getTreePath($list, 'only', 'name');
        $this->assertSame(['Root'], $path);
    }

    public function testGetTreePathNonexistentId(): void
    {
        $list = [
            (object)['item' => (object)['id' => 'x', 'id_parent' => null, 'name' => 'X'], 'id_item' => 'x'],
        ];
        $path = Indexer::getTreePath($list, 'nonexistent', 'name');
        $this->assertSame([], $path);
    }

    public function testResetIndexCheckCache(): void
    {
        Indexer::resetIndexCheckCache();
        $this->assertTrue(true);
    }

    public function testIndexerInstanceWithFullConfig(): void
    {
        if ($this->db === null || $this->mongoDb === null) {
            $this->markTestSkipped('DB and MongoDB required for Indexer instantiation');
        }

        $mongoUri = getenv('MONGODB_URI');
        $mongoDb = getenv('MONGODB_DB');
        $config = $this->getIntegrationConfig();
        $config['data'] = [
            'search_mongodb' => [
                'database' => [
                    'uri' => $mongoUri,
                    'db' => $mongoDb,
                ],
                'search' => [
                    'build_for' => [],
                    'touristic' => [
                        'occupancies' => [1, 2],
                        'duration_ranges' => [[1, 7]],
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
        ];

        \Pressmind\Registry::getInstance()->add('config', $config);

        $indexer = new Indexer();

        $this->assertSame(
            'best_price_search_based_de_origin_1',
            $indexer->getCollectionName(1, 'de', null)
        );
        $this->assertSame(
            'best_price_search_based_en_origin_0_agency_test_agency',
            $indexer->getCollectionName(0, 'en', 'test_agency')
        );
        $this->assertSame(
            'description_origin_0',
            $indexer->getCollectionName(0, null, null, 'description_')
        );

        $testCollection = 'test_indexer_exists_' . uniqid();
        $this->mongoDb->selectCollection($testCollection)->insertOne(['_id' => 1, 'test' => true]);
        $this->assertTrue($indexer->collectionExists($testCollection));
        $this->assertFalse($indexer->collectionExists('nonexistent_' . uniqid()));
        $this->mongoDb->selectCollection($testCollection)->drop();
    }
}
