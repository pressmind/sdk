<?php

namespace Pressmind\Tests\Integration\Search;

use OpenSearch\GuzzleClientFactory;
use Pressmind\Registry;
use Pressmind\Search\OpenSearch\AbstractIndex;
use Pressmind\Search\OpenSearch\Indexer;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for Search\OpenSearch\Indexer and AbstractIndex helpers.
 * Tests index template lifecycle, config hash, analyzer/filter helpers,
 * and document operations against a real OpenSearch cluster.
 *
 * Skipped when OPENSEARCH_URI is not set or OpenSearch is unreachable.
 */
class OpenSearchIndexerIntegrationTest extends AbstractIntegrationTestCase
{
    /** @var string[] Index names created during test (for cleanup) */
    private array $createdIndexes = [];

    /** @var string[] Template names created during test (for cleanup) */
    private array $createdTemplates = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureOpenSearchConfig();
    }

    protected function tearDown(): void
    {
        $this->cleanupCreatedResources();
        parent::tearDown();
    }

    private function ensureOpenSearchConfig(): void
    {
        $uri = $_SERVER['OPENSEARCH_URI'] ?? getenv('OPENSEARCH_URI') ?: '';
        if ($uri === '' && is_file(sys_get_temp_dir() . '/pm_sdk_opensearch_uri.txt')) {
            $uri = trim((string) file_get_contents(sys_get_temp_dir() . '/pm_sdk_opensearch_uri.txt'));
        }
        if ($uri === '') {
            return;
        }
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['languages'] = ['allowed' => ['de', 'en'], 'default' => 'de'];
        $config['data']['search_opensearch'] = [
            'uri' => $uri,
            'username' => getenv('OPENSEARCH_USERNAME') ?: null,
            'password' => getenv('OPENSEARCH_PASSWORD') ?: null,
            'index' => [
                'fulltext' => [
                    'type' => 'text',
                    'boost' => 2,
                    'object_type_mapping' => [
                        100 => [['field' => ['name' => 'fulltext_de'], 'language' => 'de']],
                    ],
                ],
                'code' => [
                    'type' => 'keyword',
                    'boost' => 1,
                    'object_type_mapping' => [
                        100 => [['field' => ['name' => 'code'], 'language' => 'de']],
                    ],
                ],
            ],
        ];
        $config['data']['media_types_allowed_visibilities'] = [];
        Registry::getInstance()->add('config', $config);
    }

    private function isOpenSearchConfigured(): bool
    {
        $config = Registry::getInstance()->get('config');
        return isset($config['data']['search_opensearch']['uri'])
            && $config['data']['search_opensearch']['uri'] !== '';
    }

    private function requireOpenSearch(): void
    {
        if (!$this->isOpenSearchConfigured()) {
            $this->markTestSkipped('OPENSEARCH_URI not set');
        }
    }

    private function getClient(): \OpenSearch\Client
    {
        $config = Registry::getInstance()->get('config')['data']['search_opensearch'];
        $options = [
            'base_uri' => $config['uri'],
            'verify' => false,
        ];
        if (!empty($config['username']) && !empty($config['password'])) {
            $options['auth'] = [$config['username'], $config['password']];
        }
        return (new \OpenSearch\GuzzleClientFactory())->create($options);
    }

    private function createIndexWithRetry(\OpenSearch\Client $client, string $indexName, array $body, int $maxAttempts = 5): void
    {
        $last = null;
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                $client->indices()->create(['index' => $indexName, 'body' => $body]);
                return;
            } catch (\Throwable $e) {
                $last = $e;
                if ($i < $maxAttempts - 1) {
                    sleep(2);
                }
            }
        }
        throw $last;
    }

    private function cleanupCreatedResources(): void
    {
        if (!$this->isOpenSearchConfigured()) {
            return;
        }
        try {
            $client = $this->getClient();
            foreach ($this->createdIndexes as $idx) {
                try {
                    if ($client->indices()->exists(['index' => $idx])) {
                        $client->indices()->delete(['index' => $idx]);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            foreach ($this->createdTemplates as $tpl) {
                try {
                    $client->indices()->deleteTemplate(['name' => $tpl]);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    // --- AbstractIndex helper tests ---

    public function testGetIndexTemplateNameFormat(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();

        $nameDe = $indexer->getIndexTemplateName('de');
        $this->assertStringStartsWith('index_', $nameDe);
        $this->assertStringEndsWith('_de', $nameDe);

        $nameEn = $indexer->getIndexTemplateName('en');
        $this->assertStringEndsWith('_en', $nameEn);

        $nameNull = $indexer->getIndexTemplateName(null);
        $this->assertStringStartsWith('index_', $nameNull);
        $this->assertStringEndsNotWith('_de', $nameNull);
        $this->assertStringEndsNotWith('_en', $nameNull);
    }

    public function testGetConfigHashIsStable(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();
        $hash1 = $indexer->getConfigHash();
        $hash2 = $indexer->getConfigHash();
        $this->assertSame($hash1, $hash2);
        $this->assertSame(32, strlen($hash1), 'MD5 hash should be 32 chars');
    }

    public function testHtmlToFulltext(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();

        $this->assertSame('Hello World', $indexer->htmlToFulltext('<p>Hello</p><br>World'));
        $this->assertSame('Text', $indexer->htmlToFulltext('<div class="foo"><b>Text</b></div>'));
        $this->assertSame('', $indexer->htmlToFulltext(''));
        $this->assertSame('No tags', $indexer->htmlToFulltext('No tags'));
    }

    public function testGetAnalyzerNameForLanguage(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();

        $this->assertSame('german_default', $indexer->getAnalyzerNameForLanguage('de'));
        $this->assertSame('german_default', $indexer->getAnalyzerNameForLanguage('DE'));
        $this->assertSame('english_default', $indexer->getAnalyzerNameForLanguage('en'));
        $this->assertSame('german_default', $indexer->getAnalyzerNameForLanguage('fr'));
        $this->assertSame('german_default', $indexer->getAnalyzerNameForLanguage(null));
    }

    public function testGetStringWithLanguageSuffix(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();

        $this->assertSame('autocomplete_de', $indexer->getStringWithLanguageSuffix('autocomplete', 'de'));
        $this->assertSame('autocomplete_en', $indexer->getStringWithLanguageSuffix('autocomplete', 'en'));
        $this->assertSame('autocomplete_de', $indexer->getStringWithLanguageSuffix('autocomplete_de', 'de'));
        $this->assertSame('test_de', $indexer->getStringWithLanguageSuffix('test', null));
    }

    public function testGetDefaultFilterForLanguage(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();

        $de = $indexer->getDefaultFilterForLanguage('de');
        $this->assertArrayHasKey('german_stemmer', $de);
        $this->assertArrayHasKey('german_stop', $de);

        $en = $indexer->getDefaultFilterForLanguage('en');
        $this->assertArrayHasKey('english_stemmer', $en);
        $this->assertArrayHasKey('english_stop', $en);

        $fallback = $indexer->getDefaultFilterForLanguage('xx');
        $this->assertSame($de, $fallback, 'Unknown language should fall back to German');
    }

    public function testGetDefaultAnalyzerForLanguage(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();

        $de = $indexer->getDefaultAnalyzerForLanguage('de');
        $this->assertArrayHasKey('german_default', $de);
        $this->assertArrayHasKey('autocomplete_de', $de);
        $this->assertArrayHasKey('autocomplete_search_de', $de);

        $en = $indexer->getDefaultAnalyzerForLanguage('en');
        $this->assertArrayHasKey('english_default', $en);
        $this->assertArrayHasKey('autocomplete_en', $en);

        $fallback = $indexer->getDefaultAnalyzerForLanguage('xx');
        $this->assertSame($de, $fallback);
    }

    public function testGetLanguagesFromConfig(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();
        $languages = $indexer->getLanguages();

        $this->assertIsArray($languages);
        $this->assertNotEmpty($languages);
    }

    public function testGetAllRequiredObjectTypes(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();
        $types = $indexer->getAllRequiredObjectTypes();

        $this->assertIsArray($types);
        $this->assertContains(100, $types);
    }

    // --- Index operation tests ---

    public function testIndexExistsReturnsFalseForMissing(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();
        $this->assertFalse($indexer->indexExists('nonexistent_index_' . uniqid()));
    }

    public function testIndexExistsReturnsTrueAfterCreation(): void
    {
        $this->requireOpenSearch();
        sleep(2);
        $indexName = 'test_os_idx_' . substr(uniqid(), 0, 8);
        $this->createdIndexes[] = $indexName;
        $client = $this->getClient();

        $this->createIndexWithRetry($client, $indexName, [
            'mappings' => ['properties' => ['id' => ['type' => 'integer']]],
        ]);

        $indexer = new Indexer();
        $this->assertTrue($indexer->indexExists($indexName));
    }

    public function testGetIndexesReturnsArray(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();
        $indexes = $indexer->getIndexes();
        $this->assertIsArray($indexes);
    }

    public function testDocumentIndexAndDelete(): void
    {
        $this->requireOpenSearch();
        sleep(2);
        $indexName = 'test_os_doc_' . substr(uniqid(), 0, 8);
        $this->createdIndexes[] = $indexName;
        $client = $this->getClient();

        $this->createIndexWithRetry($client, $indexName, [
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'fulltext' => ['type' => 'text'],
                ],
            ],
        ]);

        $client->index([
            'index' => $indexName,
            'id' => 42,
            'body' => ['id' => 42, 'fulltext' => 'Test document'],
        ]);
        $client->indices()->refresh(['index' => $indexName]);

        $count = $client->count(['index' => $indexName])['count'];
        $this->assertSame(1, $count);

        $client->delete(['index' => $indexName, 'id' => 42]);
        $client->indices()->refresh(['index' => $indexName]);

        $count = $client->count(['index' => $indexName])['count'];
        $this->assertSame(0, $count);
    }

    public function testDeleteAllIndexesThatNotMatchConfigHash(): void
    {
        $this->requireOpenSearch();
        sleep(2);
        $staleIndex = 'index_stale_' . substr(uniqid(), 0, 8);
        $this->createdIndexes[] = $staleIndex;
        $client = $this->getClient();

        $this->createIndexWithRetry($client, $staleIndex, [
            'mappings' => ['properties' => ['id' => ['type' => 'integer']]],
        ]);
        $this->assertTrue($client->indices()->exists(['index' => $staleIndex]));

        $indexer = new Indexer();
        $indexer->deleteAllIndexesThatNotMatchConfigHash();

        $existsAfter = $client->indices()->exists(['index' => $staleIndex]);
        $this->assertFalse($existsAfter, 'Stale index should be deleted');
    }

    public function testGetFieldsReturnsConfiguredFields(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();
        $fields = $indexer->getFields('de', 100);

        $this->assertIsArray($fields);
    }
}
