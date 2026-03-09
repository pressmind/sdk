<?php

namespace Pressmind\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pressmind\Registry;

/**
 * Base test case for integration tests. Uses real MySQL and MongoDB from ENV.
 * setUp() prepares Registry with config and real DB; subclasses load fixtures and clean up.
 */
abstract class AbstractIntegrationTestCase extends TestCase
{
    /**
     * @var \Pressmind\DB\Adapter\AdapterInterface|null
     */
    protected $db;

    /**
     * @var \MongoDB\Database|null
     */
    protected $mongoDb;

    protected function setUp(): void
    {
        parent::setUp();
        Registry::clear();
        $this->resetPdoSingleton();
        $this->initConnections();
        $config = $this->getIntegrationConfig();
        $registry = Registry::getInstance();
        $registry->add('config', $config);
        if ($this->db !== null) {
            $registry->add('db', $this->db);
        }
    }

    protected function tearDown(): void
    {
        Registry::clear();
        $this->db = null;
        $this->mongoDb = null;
        parent::tearDown();
    }

    private function resetPdoSingleton(): void
    {
        $ref = new \ReflectionClass(\Pressmind\DB\Config\Pdo::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /**
     * Initialize DB and MongoDB from ENV. Override to skip or use mocks.
     */
    protected function initConnections(): void
    {
        $this->db = $this->createDbConnection();
        $this->mongoDb = $this->createMongoConnection();
    }

    /**
     * Config used for integration tests (cache can be disabled for speed).
     * When OPENSEARCH_URI is set, adds data.search_opensearch and data.languages for OpenSearch integration tests.
     *
     * @return array<string, mixed>
     */
    protected function getIntegrationConfig(): array
    {
        $config = [
            'cache' => [
                'enabled' => false,
                'types' => [],
            ],
            'logging' => [
                'enable_advanced_object_log' => false,
                'mode' => 'ALL',
                'storage' => 'filesystem',
            ],
        ];
        $opensearchUri = getenv('OPENSEARCH_URI') ?: ($_SERVER['OPENSEARCH_URI'] ?? null);
        if ($opensearchUri !== null && $opensearchUri !== '') {
            $config['data'] = [
                'languages' => [
                    'allowed' => ['de', 'en'],
                    'default' => 'de',
                ],
                'search_opensearch' => [
                    'uri' => $opensearchUri,
                    'username' => getenv('OPENSEARCH_USERNAME') ?: null,
                    'password' => getenv('OPENSEARCH_PASSWORD') ?: null,
                    'index' => [
                        'fulltext' => ['type' => 'text', 'boost' => 2],
                        'code' => ['type' => 'keyword', 'boost' => 1],
                    ],
                ],
            ];
        }
        return $config;
    }

    /**
     * Load a touristic fixture and resolve date offsets. See FixtureLoader.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loadFixture(string $name, string $subdir = 'touristic'): array
    {
        return FixtureLoader::loadCheapestPriceFixture($name, $subdir);
    }

    /**
     * Create real DB connection from ENV. Returns null if ENV not set or connection fails.
     *
     * @return \Pressmind\DB\Adapter\AdapterInterface|null
     */
    private function createDbConnection()
    {
        $host = getenv('DB_HOST');
        $name = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $pass = getenv('DB_PASS');
        $port = getenv('DB_PORT') ?: '3306';
        if (empty($host) || empty($name)) {
            return null;
        }
        try {
            $config = \Pressmind\DB\Config\Pdo::create($host, $name, $user, $pass, $port);
            return new \Pressmind\DB\Adapter\Pdo($config);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create MongoDB connection from ENV. Returns null if not available.
     *
     * @return \MongoDB\Database|null
     */
    private function createMongoConnection()
    {
        $uri = getenv('MONGODB_URI');
        $dbName = getenv('MONGODB_DB');
        if (empty($uri) || empty($dbName)) {
            return null;
        }
        try {
            $client = new \MongoDB\Client($uri);
            return $client->selectDatabase($dbName);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
