<?php

namespace Pressmind\Tests\Integration\CLI;

use Pressmind\CLI\IndexMongoCommand;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for IndexMongoCommand using a real MongoDB instance.
 */
class IndexMongoCommandIntegrationTest extends AbstractIntegrationTestCase
{
    private function getMongoConfig(): array
    {
        $mongoUri = getenv('MONGODB_URI');
        $mongoDb = getenv('MONGODB_DB');

        $config = $this->getIntegrationConfig();
        $config['data'] = array_merge($config['data'] ?? [], [
            'search_mongodb' => [
                'database' => [
                    'uri' => $mongoUri,
                    'db' => $mongoDb,
                ],
                'search' => [
                    'build_for' => [
                        999 => [
                            ['origin' => 1, 'language' => 'de'],
                        ],
                    ],
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
            'search_opensearch' => [
                'enabled' => true,
                'enabled_in_mongo_search' => true,
            ],
            'touristic' => [],
            'media_types_allowed_visibilities' => [],
            'media_types_fulltext_index_fields' => [],
        ]);

        return $config;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB not available');
        }

        Registry::getInstance()->add('config', $this->getMongoConfig());
    }

    protected function tearDown(): void
    {
        if ($this->mongoDb !== null) {
            foreach ($this->mongoDb->listCollections() as $coll) {
                $name = $coll->getName();
                if (str_starts_with($name, 'best_price_search_') || str_starts_with($name, 'temp_')) {
                    $this->mongoDb->dropCollection($name);
                }
            }
        }
        parent::tearDown();
    }

    private function runCommand(IndexMongoCommand $cmd, array $argv): array
    {
        ob_start();
        try {
            $exit = $cmd->run($argv);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            $output = ob_get_clean();
            throw $e;
        }
        return ['exit' => $exit, 'output' => $output];
    }

    public function testHelpSubcommand(): void
    {
        $result = $this->runCommand(new IndexMongoCommand(), ['index_mongo.php', 'help']);
        $this->assertSame(0, $result['exit']);
    }

    public function testHelpFlag(): void
    {
        $result = $this->runCommand(new IndexMongoCommand(), ['index_mongo.php', '--help']);
        $this->assertSame(0, $result['exit']);
    }

    public function testNoSubcommandShowsHelp(): void
    {
        $result = $this->runCommand(new IndexMongoCommand(), ['index_mongo.php']);
        $this->assertSame(0, $result['exit']);
    }

    public function testCreateCollections(): void
    {
        $result = $this->runCommand(new IndexMongoCommand(), ['index_mongo.php', 'create_collections']);
        $this->assertSame(0, $result['exit']);

        $collectionNames = [];
        foreach ($this->mongoDb->listCollections() as $coll) {
            $collectionNames[] = $coll->getName();
        }
        $this->assertContains(
            'best_price_search_based_de_origin_1',
            $collectionNames,
            'Expected collection was not created. Found: ' . implode(', ', $collectionNames)
        );
    }

    public function testCreateCollectionIndexes(): void
    {
        $this->runCommand(new IndexMongoCommand(), ['index_mongo.php', 'create_collections']);
        \Pressmind\Search\MongoDB\Indexer::resetIndexCheckCache();

        $result = $this->runCommand(new IndexMongoCommand(), ['index_mongo.php', 'indexes']);
        $this->assertSame(0, $result['exit']);

        $collName = 'best_price_search_based_de_origin_1';
        $indexes = iterator_to_array($this->mongoDb->selectCollection($collName)->listIndexes());
        $indexNames = array_map(fn($idx) => $idx->getName(), $indexes);

        $this->assertContains('id_media_object_1', $indexNames, 'Index id_media_object_1 missing');
        $this->assertContains('groups_1', $indexNames, 'Index groups_1 missing');
    }

    public function testFlushCollections(): void
    {
        $this->runCommand(new IndexMongoCommand(), ['index_mongo.php', 'create_collections']);

        $collName = 'best_price_search_based_de_origin_1';
        $this->mongoDb->selectCollection($collName)->insertOne(['test' => true, 'id_media_object' => 9999]);
        $this->assertGreaterThan(0, $this->mongoDb->selectCollection($collName)->countDocuments());

        \Pressmind\Search\MongoDB\Indexer::resetIndexCheckCache();

        $result = $this->runCommand(new IndexMongoCommand(), ['index_mongo.php', 'flush']);
        $this->assertSame(0, $result['exit']);
        $this->assertSame(0, $this->mongoDb->selectCollection($collName)->countDocuments());
    }

    public function testRemoveTempCollections(): void
    {
        $this->mongoDb->createCollection('temp_test_remove_' . uniqid());

        $result = $this->runCommand(new IndexMongoCommand(), ['index_mongo.php', 'remove_temp_collections']);
        $this->assertSame(0, $result['exit']);

        $remaining = [];
        foreach ($this->mongoDb->listCollections() as $coll) {
            if (str_starts_with($coll->getName(), 'temp_')) {
                $remaining[] = $coll->getName();
            }
        }
        $this->assertEmpty($remaining, 'Temp collections should be removed: ' . implode(', ', $remaining));
    }

    public function testMediaobjectWithoutIdsReturnsError(): void
    {
        $result = $this->runCommand(new IndexMongoCommand(), ['index_mongo.php', 'mediaobject']);
        $this->assertSame(1, $result['exit']);
    }

    public function testDestroyWithoutIdsReturnsError(): void
    {
        $result = $this->runCommand(new IndexMongoCommand(), ['index_mongo.php', 'destroy']);
        $this->assertSame(1, $result['exit']);
    }

    public function testUnknownSubcommandShowsHelp(): void
    {
        $result = $this->runCommand(new IndexMongoCommand(), ['index_mongo.php', 'nonexistent_subcommand']);
        $this->assertSame(0, $result['exit']);
    }
}
