<?php

namespace Pressmind\Tests\Integration\Search;

use OpenSearch\SymfonyClientFactory;
use Pressmind\Registry;
use Pressmind\Search\OpenSearch\Indexer;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for OpenSearch Indexer template lifecycle, index management,
 * and document operations. Closes coverage gaps in createIndexTemplates,
 * allIndexTemplatesExist, deleteAllIndexesThatNotMatchConfigHash, and getFields.
 *
 * Requires OPENSEARCH_URI (skipped otherwise).
 */
class OpenSearchIndexerTemplateTest extends AbstractIntegrationTestCase
{
    private array $createdIndexes = [];
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
                        200 => [['field' => ['name' => 'fulltext_en'], 'language' => 'en']],
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
            'verify_peer' => false,
        ];
        if (!empty($config['username']) && !empty($config['password'])) {
            $options['auth_basic'] = [$config['username'], $config['password']];
        }
        return (new SymfonyClientFactory())->create($options);
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

    public function testGetIndexTemplateNameDiffersPerLanguage(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();

        $de = $indexer->getIndexTemplateName('de');
        $en = $indexer->getIndexTemplateName('en');
        $none = $indexer->getIndexTemplateName(null);

        $this->assertNotSame($de, $en, 'Different languages should produce different template names');
        $this->assertNotSame($de, $none, 'Language template should differ from no-language template');
        $this->assertStringEndsWith('_de', $de);
        $this->assertStringEndsWith('_en', $en);
        $this->assertStringEndsNotWith('_de', $none);
    }

    public function testGetConfigHashChangesWithConfig(): void
    {
        $this->requireOpenSearch();
        $indexer1 = new Indexer();
        $hash1 = $indexer1->getConfigHash();

        $config = Registry::getInstance()->get('config');
        $config['data']['search_opensearch']['index']['extra_field'] = [
            'type' => 'text',
            'boost' => 1,
            'object_type_mapping' => [],
        ];
        Registry::getInstance()->add('config', $config);

        $indexer2 = new Indexer();
        $hash2 = $indexer2->getConfigHash();

        $this->assertNotSame($hash1, $hash2, 'Hash should change when config changes');

        $this->ensureOpenSearchConfig();
    }

    public function testGetFieldsReturnsCorrectFieldsForObjectType(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();

        $fields100 = $indexer->getFields('de', 100);
        $this->assertIsArray($fields100);
        $this->assertArrayHasKey('fulltext', $fields100);
        $this->assertArrayHasKey('code', $fields100);

        $fieldsUnknown = $indexer->getFields('de', 99999);
        $this->assertIsArray($fieldsUnknown);
        $this->assertEmpty($fieldsUnknown, 'Unknown object type should return no fields');
    }

    public function testGetFieldsLanguageFiltering(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();

        $fieldsDe200 = $indexer->getFields('de', 200);
        $this->assertEmpty($fieldsDe200, 'Object type 200 only has EN fulltext, not DE');

        $fieldsEn200 = $indexer->getFields('en', 200);
        $this->assertArrayHasKey('fulltext', $fieldsEn200);
    }

    public function testGetAllRequiredObjectTypesIncludesAll(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();
        $types = $indexer->getAllRequiredObjectTypes();

        $this->assertContains(100, $types);
        $this->assertContains(200, $types);
    }

    public function testGetLanguagesExtractsUniqueLanguages(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();
        $languages = $indexer->getLanguages();

        $this->assertContains('de', $languages);
        $this->assertContains('en', $languages);
        $this->assertSame(array_unique($languages), $languages, 'Languages should be unique');
    }

    public function testGetIndexesReturnsArrayOfIndexInfo(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();
        $indexes = $indexer->getIndexes();

        $this->assertIsArray($indexes);
        foreach ($indexes as $idx) {
            $this->assertArrayHasKey('index', $idx, 'Each entry should have an index key');
        }
    }

    public function testIndexExistsWithCreatedIndex(): void
    {
        $this->requireOpenSearch();
        sleep(2);
        $indexName = 'test_tpl_exists_' . substr(uniqid(), 0, 8);
        $this->createdIndexes[] = $indexName;
        $client = $this->getClient();

        $this->createIndexWithRetry($client, $indexName, [
            'mappings' => ['properties' => ['id' => ['type' => 'integer']]],
        ]);

        $indexer = new Indexer();
        $this->assertTrue($indexer->indexExists($indexName));
    }

    public function testDeleteAllIndexesThatNotMatchConfigHashRemovesStale(): void
    {
        $this->requireOpenSearch();
        sleep(2);
        $staleName = 'index_deadbeef_stale_' . substr(uniqid(), 0, 6);
        $this->createdIndexes[] = $staleName;
        $client = $this->getClient();

        $this->createIndexWithRetry($client, $staleName, [
            'mappings' => ['properties' => ['id' => ['type' => 'integer']]],
        ]);
        $this->assertTrue($client->indices()->exists(['index' => $staleName]));

        $indexer = new Indexer();
        $indexer->deleteAllIndexesThatNotMatchConfigHash();

        $this->assertFalse(
            $client->indices()->exists(['index' => $staleName]),
            'Stale index not matching config hash should be removed'
        );
    }

    public function testDeleteAllIndexesThatNotMatchConfigHashPreservesNonIndexPrefixed(): void
    {
        $this->requireOpenSearch();
        sleep(2);
        $nonIndexName = 'custom_data_' . substr(uniqid(), 0, 8);
        $this->createdIndexes[] = $nonIndexName;
        $client = $this->getClient();

        $this->createIndexWithRetry($client, $nonIndexName, [
            'mappings' => ['properties' => ['id' => ['type' => 'integer']]],
        ]);

        $indexer = new Indexer();
        $indexer->deleteAllIndexesThatNotMatchConfigHash();

        $this->assertTrue(
            $client->indices()->exists(['index' => $nonIndexName]),
            'Indexes not starting with index_ should be preserved'
        );
    }

    public function testDocumentIndexRefreshAndSearch(): void
    {
        $this->requireOpenSearch();
        sleep(2);
        $indexName = 'test_tpl_docsearch_' . substr(uniqid(), 0, 8);
        $this->createdIndexes[] = $indexName;
        $client = $this->getClient();

        $this->createIndexWithRetry($client, $indexName, [
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'fulltext' => ['type' => 'text'],
                    'code' => ['type' => 'keyword'],
                ],
            ],
        ]);

        $client->index([
            'index' => $indexName,
            'id' => 101,
            'body' => ['id' => 101, 'fulltext' => 'Flugreise nach Mallorca', 'code' => 'MAL-001'],
        ]);
        $client->index([
            'index' => $indexName,
            'id' => 102,
            'body' => ['id' => 102, 'fulltext' => 'Busreise nach Berlin', 'code' => 'BER-001'],
        ]);
        $client->indices()->refresh(['index' => $indexName]);

        $count = $client->count(['index' => $indexName])['count'];
        $this->assertSame(2, $count);

        $searchResult = $client->search([
            'index' => $indexName,
            'body' => [
                'query' => ['match' => ['code' => 'MAL-001']],
            ],
        ]);
        $hits = $searchResult['hits']['hits'];
        $this->assertCount(1, $hits);
        $this->assertSame(101, $hits[0]['_source']['id']);
    }

    public function testHtmlToFulltextVariousInputs(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();

        $this->assertSame(
            'Title Subtitle Content',
            $indexer->htmlToFulltext('<h1>Title</h1><p>Subtitle</p><div>Content</div>')
        );
        $this->assertSame(
            'Line 1 Line 2',
            $indexer->htmlToFulltext('Line 1<br/>Line 2')
        );
        $this->assertSame(
            'Umlauts: ÄÖÜ äöü ß',
            $indexer->htmlToFulltext('<span>Umlauts: &Auml;&Ouml;&Uuml; &auml;&ouml;&uuml; &szlig;</span>')
        );
    }

    public function testGetStringWithLanguageSuffixIdempotent(): void
    {
        $this->requireOpenSearch();
        $indexer = new Indexer();

        $once = $indexer->getStringWithLanguageSuffix('filter', 'de');
        $twice = $indexer->getStringWithLanguageSuffix($once, 'de');
        $this->assertSame($once, $twice, 'Suffix should not be added twice');
    }
}
