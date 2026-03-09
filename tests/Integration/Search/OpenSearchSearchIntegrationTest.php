<?php

namespace Pressmind\Tests\Integration\Search;

use OpenSearch\GuzzleClientFactory;
use Pressmind\Search\OpenSearch;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for Search\OpenSearch with a real OpenSearch instance.
 * Tests are skipped when OPENSEARCH_URI is not set or OpenSearch is not reachable.
 *
 * Run with OpenSearch: set OPENSEARCH_URI (e.g. http://opensearch:9200) or use
 * `make integration` / `make test` which start OpenSearch via docker-compose.test.yml.
 */
class OpenSearchSearchIntegrationTest extends AbstractIntegrationTestCase
{
    /**
     * @var string|null Index name created for getResult test (for tearDown cleanup)
     */
    private $createdIndexName;

    /**
     * Ensure OpenSearch config is in Registry (from OPENSEARCH_URI env / $_SERVER / bootstrap persistence). Call before using client.
     */
    private function ensureOpenSearchConfig(): void
    {
        $uri = $_SERVER['OPENSEARCH_URI'] ?? getenv('OPENSEARCH_URI') ?: '';
        if ($uri === '' && is_file(sys_get_temp_dir() . '/pm_sdk_opensearch_uri.txt')) {
            $uri = trim((string) file_get_contents(sys_get_temp_dir() . '/pm_sdk_opensearch_uri.txt'));
        }
        if ($uri === '') {
            return;
        }
        $config = \Pressmind\Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['languages'] = $config['data']['languages'] ?? ['allowed' => ['de', 'en'], 'default' => 'de'];
        $config['data']['search_opensearch'] = [
            'uri' => $uri,
            'username' => getenv('OPENSEARCH_USERNAME') ?: null,
            'password' => getenv('OPENSEARCH_PASSWORD') ?: null,
            'index' => [
                'fulltext' => ['type' => 'text', 'boost' => 2],
                'code' => ['type' => 'keyword', 'boost' => 1],
            ],
        ];
        \Pressmind\Registry::getInstance()->add('config', $config);
    }

    protected function tearDown(): void
    {
        $this->deleteCreatedIndex();
        parent::tearDown();
    }

    private function isOpenSearchConfigured(): bool
    {
        $config = \Pressmind\Registry::getInstance()->get('config');
        return isset($config['data']['search_opensearch']['uri'])
            && $config['data']['search_opensearch']['uri'] !== '';
    }

    /**
     * Build OpenSearch client from current config (for cluster health / index ops).
     */
    private function getOpenSearchClient(): \OpenSearch\Client
    {
        $config = \Pressmind\Registry::getInstance()->get('config')['data']['search_opensearch'];
        $options = [
            'base_uri' => $config['uri'],
            'verify' => false,
        ];
        if (!empty($config['username']) && !empty($config['password'])) {
            $options['auth'] = [$config['username'], $config['password']];
        }
        return (new \OpenSearch\GuzzleClientFactory())->create($options);
    }

    private function deleteCreatedIndex(): void
    {
        if ($this->createdIndexName === null) {
            return;
        }
        if (!$this->isOpenSearchConfigured()) {
            return;
        }
        try {
            $client = $this->getOpenSearchClient();
            if ($client->indices()->exists(['index' => $this->createdIndexName])) {
                $client->indices()->delete(['index' => $this->createdIndexName]);
            }
        } catch (\Throwable $e) {
            // ignore cleanup errors
        }
        $this->createdIndexName = null;
    }

    /**
     * Create index with retry on 403 (cluster may temporarily block index creation under memory pressure).
     */
    private function createIndexWithRetry(\OpenSearch\Client $client, string $indexName, array $body, int $maxAttempts = 5): void
    {
        $last = null;
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $client->indices()->create(['index' => $indexName, 'body' => $body]);
                return;
            } catch (\OpenSearch\Common\Exceptions\Forbidden403Exception $e) {
                $last = $e;
                if ($i < $maxAttempts - 1) {
                    sleep(3);
                }
            } catch (\OpenSearch\Exception\ForbiddenHttpException $e) {
                $last = $e;
                if ($i < $maxAttempts - 1) {
                    sleep(3);
                }
            }
        }
        throw $last;
    }

    public function testClusterHealth(): void
    {
        $this->ensureOpenSearchConfig();
        if (!$this->isOpenSearchConfigured()) {
            $inDocker = (getenv('DB_HOST') === 'mysql' || getenv('MONGODB_URI') === 'mongodb://mongodb:27017');
            if ($inDocker) {
                $this->fail(
                    'OpenSearch must be tested in Docker integration. OPENSEARCH_URI is not set or not in config. '
                    . 'getenv(OPENSEARCH_URI)=' . var_export(getenv('OPENSEARCH_URI'), true)
                    . ' SERVER=' . ($_SERVER['OPENSEARCH_URI'] ?? 'unset')
                );
            }
            $this->markTestSkipped('OPENSEARCH_URI not set or search_opensearch not in config.');
        }
        try {
            $client = $this->getOpenSearchClient();
            $health = $client->cluster()->health();
        } catch (\Throwable $e) {
            $this->fail('OpenSearch must be reachable in Docker integration. ' . $e->getMessage());
        }
        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertContains($health['status'], ['green', 'yellow'], 'Cluster status should be green or yellow');
    }

    public function testGetResultOnEmptyIndex(): void
    {
        $this->ensureOpenSearchConfig();
        if (!$this->isOpenSearchConfigured()) {
            $this->markTestSkipped('OPENSEARCH_URI not set');
        }
        try {
            $search = new OpenSearch('test', 'de', 10);
        } catch (\Throwable $e) {
            $this->markTestSkipped('OpenSearch not reachable: ' . $e->getMessage());
        }
        $indexName = $search->getIndexTemplateName('de');
        $this->assertNotEmpty($indexName);
        $this->assertStringStartsWith('index_', $indexName);
        $this->assertStringEndsWith('_de', $indexName);

        $client = $this->getOpenSearchClient();
        if ($client->indices()->exists(['index' => $indexName])) {
            $client->indices()->delete(['index' => $indexName]);
        }
        $client->indices()->create([
            'index' => $indexName,
            'body' => [
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'fulltext' => ['type' => 'text'],
                        'code' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ]);
        $this->createdIndexName = $indexName;

        $result = $search->getResult(false, 0);
        $this->assertIsArray($result);
        $this->assertEmpty($result, 'Empty index should return no IDs');
    }

    public function testGetResultReturnsDocumentIdsWhenIndexed(): void
    {
        $this->ensureOpenSearchConfig();
        if (!$this->isOpenSearchConfigured()) {
            $this->markTestSkipped('OPENSEARCH_URI not set');
        }
        // Allow cluster to settle after previous test's index operations (reduces 403 flakiness)
        sleep(2);
        try {
            $search = new OpenSearch('Reise', 'de', 10);
        } catch (\Throwable $e) {
            $this->markTestSkipped('OpenSearch not reachable: ' . $e->getMessage());
        }
        $indexName = $search->getIndexTemplateName('de');
        $client = $this->getOpenSearchClient();
        if ($client->indices()->exists(['index' => $indexName])) {
            $client->indices()->delete(['index' => $indexName]);
        }
        $indexBody = [
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'fulltext' => ['type' => 'text'],
                    'code' => ['type' => 'keyword'],
                ],
            ],
        ];
        $this->createIndexWithRetry($client, $indexName, $indexBody);
        $this->createdIndexName = $indexName;

        $client->index([
            'index' => $indexName,
            'id' => 1001,
            'body' => [
                'id' => 1001,
                'fulltext' => 'Reise nach Berlin',
                'code' => 'BER',
            ],
        ]);
        $client->indices()->refresh(['index' => $indexName]);

        // Search by keyword "BER" (exact match on code field) to avoid analyzer/locale differences
        $searchByCode = new OpenSearch('BER', 'de', 10);
        $result = $searchByCode->getResult(false, 0);
        $this->assertIsArray($result);
        // OpenSearch may return _id as int or string; normalize for assertion
        $resultIds = array_map('intval', $result);
        $this->assertContains(1001, $resultIds, 'Search for "BER" should return document id 1001. Got: ' . json_encode($result));
    }
}
