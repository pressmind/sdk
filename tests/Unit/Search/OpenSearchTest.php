<?php

namespace Pressmind\Tests\Unit\Search;

use Pressmind\Registry;
use Pressmind\Search\OpenSearch;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Search\OpenSearch: getIndexTemplateName, getConfigHash, sanitizeSearchTerm, getLog, generateCacheKey.
 * No real OpenSearch client; stub used to avoid SymfonyClientFactory in constructor.
 */
class OpenSearchTest extends AbstractTestCase
{
    private function getOpenSearchConfig(): array
    {
        return $this->createMockConfig([
            'data' => [
                'languages' => ['allowed' => ['de', 'en'], 'default' => 'de'],
                'search_opensearch' => [
                    'uri' => 'https://localhost:9200',
                    'username' => null,
                    'password' => null,
                    'index' => [
                        'fulltext' => ['type' => 'text', 'boost' => 2],
                        'code' => ['type' => 'keyword', 'boost' => 1],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Create OpenSearch stub with config/language/limit set; no real client built.
     */
    private function createOpenSearchStub(
        string $searchTerm = 'test',
        ?string $language = 'de',
        int $limit = 100,
        ?array $config = null
    ): OpenSearch
    {
        $stub = $this->getMockBuilder(OpenSearch::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionClass(OpenSearch::class);
        $config = $config ?? $this->getOpenSearchConfig();
        $indexConfig = $config['data']['search_opensearch'];
        unset(
            $indexConfig['uri'],
            $indexConfig['username'],
            $indexConfig['password'],
            $indexConfig['fuzziness'],
            $indexConfig['prefix_length']
        );
        if (isset($indexConfig['query']) && is_array($indexConfig['query'])) {
            unset($indexConfig['query']['fulltext']);
            if (empty($indexConfig['query'])) {
                unset($indexConfig['query']);
            }
        }
        $configHash = md5(serialize($indexConfig));
        $indexName = 'index_' . $configHash . ($language ? '_' . strtolower($language) : '');

        foreach (['_config', '_search_term', '_language', '_index_name', '_limit'] as $propName) {
            $p = $ref->getProperty($propName);
            $p->setAccessible(true);
            switch ($propName) {
                case '_config':
                    $p->setValue($stub, $config);
                    break;
                case '_search_term':
                    $p->setValue($stub, $searchTerm);
                    break;
                case '_language':
                    $p->setValue($stub, $language);
                    break;
                case '_index_name':
                    $p->setValue($stub, $indexName);
                    break;
                case '_limit':
                    $p->setValue($stub, $limit);
                    break;
            }
        }
        return $stub;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Registry::getInstance()->add('config', $this->getOpenSearchConfig());
    }

    public function testGetIndexTemplateNameWithLanguage(): void
    {
        $search = $this->createOpenSearchStub('q', 'de', 50);
        $name = $search->getIndexTemplateName('de');
        $this->assertIsString($name);
        $this->assertStringStartsWith('index_', $name);
        $this->assertStringEndsWith('_de', $name);
    }

    public function testGetIndexTemplateNameEmptyLanguage(): void
    {
        $search = $this->createOpenSearchStub('q', null, 50);
        $name = $search->getIndexTemplateName(null);
        $this->assertIsString($name);
        $this->assertStringStartsWith('index_', $name);
        $this->assertNotEmpty($name);
    }

    public function testGetConfigHash(): void
    {
        $search = $this->createOpenSearchStub();
        $hash = $search->getConfigHash();
        $this->assertIsString($hash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}_[a-f0-9]{32}$/', $hash);
    }

    public function testGenerateCacheKey(): void
    {
        $search = $this->createOpenSearchStub('term', 'de', 100);
        $key = $search->generateCacheKey();
        $this->assertIsString($key);
        $this->assertStringStartsWith('OPENSEARCH:', $key);
        $this->assertMatchesRegularExpression('/^OPENSEARCH:[a-f0-9]{32}$/', $key);
    }

    public function testGetLogWhenLoggingDisabled(): void
    {
        $search = $this->createOpenSearchStub();
        $log = $search->getLog();
        $this->assertIsArray($log);
        $this->assertNotEmpty($log);
        $this->assertStringContainsString('Logging is disabled', $log[0]);
    }

    public function testSanitizeSearchTerm(): void
    {
        $search = $this->createOpenSearchStub();
        $result = $search->sanitizeSearchTerm("  foo  \t\n  bar  ");
        $this->assertIsString($result);
        $this->assertSame(trim($result), $result);
        $this->assertStringContainsString('foo', $result);
        $this->assertStringContainsString('bar', $result);
        $this->assertStringNotContainsString("\t", $result);
        $this->assertStringNotContainsString("\n", $result);
    }

    public function testGetLogWhenLoggingEnabled(): void
    {
        $search = $this->createOpenSearchStub();
        $ref = new \ReflectionClass(OpenSearch::class);
        $logProp = $ref->getProperty('_log');
        $logProp->setAccessible(true);
        $logProp->setValue($search, ['[2020-01-01T00:00:00.000+00:00] __construct()']);
        $config = $this->getOpenSearchConfig();
        $config['logging'] = ['enable_advanced_object_log' => true];
        Registry::getInstance()->add('config', $config);
        $log = $search->getLog();
        $this->assertIsArray($log);
        $this->assertNotEmpty($log);
        $this->assertStringContainsString('__construct', $log[0]);
    }

    public function testFulltextFallbackIsUnscoredAndKeepsConfiguredFields(): void
    {
        $config = $this->getOpenSearchConfig();
        $config['data']['search_opensearch']['index'] = [
            'headline' => ['type' => 'text', 'boost' => 5],
            'code' => ['type' => 'keyword', 'boost' => 10],
        ];
        $config['data']['search_opensearch']['query']['fulltext'] = [
            'enabled' => true,
            'operator' => 'and',
        ];
        $search = $this->createOpenSearchStub('basel', 'de', 100, $config);

        $method = new \ReflectionMethod(OpenSearch::class, 'buildLexicalBoolQuery');
        $method->setAccessible(true);
        $query = $method->invoke($search);

        $this->assertArrayNotHasKey('must', $query);
        $this->assertSame(1, $query['minimum_should_match']);
        $this->assertSame(['headline^5'], $query['should'][0]['multi_match']['fields']);
        $this->assertSame(['code^10'], $query['should'][1]['multi_match']['fields']);
        $this->assertSame(
            [
                'bool' => [
                    'filter' => [[
                        'match' => [
                            'fulltext' => [
                                'query' => 'basel',
                                'operator' => 'and',
                            ],
                        ],
                    ]],
                ],
            ],
            $query['should'][2]
        );
    }

    public function testDefaultLexicalQueryRemainsUnchangedWithoutFallbackConfig(): void
    {
        $search = $this->createOpenSearchStub('basel');
        $method = new \ReflectionMethod(OpenSearch::class, 'buildLexicalBoolQuery');
        $method->setAccessible(true);
        $query = $method->invoke($search);

        $disabledConfig = $this->getOpenSearchConfig();
        $disabledConfig['data']['search_opensearch']['query']['fulltext']['enabled'] = false;
        $disabledSearch = $this->createOpenSearchStub('basel', 'de', 100, $disabledConfig);
        $disabledQuery = $method->invoke($disabledSearch);

        $this->assertCount(2, $query['should']);
        $this->assertSame(1, $query['minimum_should_match']);
        $this->assertSame([], $query['filter']);
        $this->assertArrayNotHasKey('must', $query);
        $this->assertSame($query, $disabledQuery);
    }

    public function testQueryOnlyConfigDoesNotChangeIndexHash(): void
    {
        $withoutQuery = $this->getOpenSearchConfig();
        $withQuery = $withoutQuery;
        $withQuery['data']['search_opensearch']['query']['fulltext'] = [
            'enabled' => true,
            'operator' => 'and',
        ];

        $searchWithoutQuery = $this->createOpenSearchStub('basel', 'de', 100, $withoutQuery);
        $searchWithQuery = $this->createOpenSearchStub('basel', 'de', 100, $withQuery);

        $this->assertSame($searchWithoutQuery->getConfigHash(), $searchWithQuery->getConfigHash());
    }

    public function testUnrelatedQueryConfigKeepsItsLegacyIndexHashEffect(): void
    {
        $withoutQuery = $this->getOpenSearchConfig();
        $withUnrelatedQuery = $withoutQuery;
        $withUnrelatedQuery['data']['search_opensearch']['query']['existing_option'] = true;

        $searchWithoutQuery = $this->createOpenSearchStub('basel', 'de', 100, $withoutQuery);
        $searchWithUnrelatedQuery = $this->createOpenSearchStub('basel', 'de', 100, $withUnrelatedQuery);

        $this->assertNotSame($searchWithoutQuery->getConfigHash(), $searchWithUnrelatedQuery->getConfigHash());
    }

    public function testQueryOnlyConfigChangesCacheKey(): void
    {
        $withoutQuery = $this->getOpenSearchConfig();
        $withQuery = $withoutQuery;
        $withQuery['data']['search_opensearch']['query']['fulltext'] = [
            'enabled' => true,
            'operator' => 'and',
        ];

        $searchWithoutQuery = $this->createOpenSearchStub('basel', 'de', 100, $withoutQuery);
        $searchWithQuery = $this->createOpenSearchStub('basel', 'de', 100, $withQuery);

        $this->assertNotSame($searchWithoutQuery->generateCacheKey(), $searchWithQuery->generateCacheKey());
    }

    public function testDisabledFulltextConfigKeepsLegacyCacheKey(): void
    {
        $withoutQuery = $this->getOpenSearchConfig();
        $disabledQuery = $withoutQuery;
        $disabledQuery['data']['search_opensearch']['query'] = [
            'fulltext' => ['enabled' => false, 'operator' => 'and'],
        ];

        $searchWithoutQuery = $this->createOpenSearchStub('basel', 'de', 100, $withoutQuery);
        $searchDisabledQuery = $this->createOpenSearchStub('basel', 'de', 100, $disabledQuery);

        $this->assertSame($searchWithoutQuery->generateCacheKey(), $searchDisabledQuery->generateCacheKey());
    }

    public function testDefaultConfigKeepsLegacyCacheKey(): void
    {
        $search = $this->createOpenSearchStub('basel', 'de', 100);
        $reflection = new \ReflectionClass(OpenSearch::class);
        $indexName = $reflection->getProperty('_index_name');
        $indexName->setAccessible(true);

        $expected = 'OPENSEARCH:' . md5(serialize([
            'basel',
            $indexName->getValue($search),
            'de',
            100,
        ]));

        $this->assertSame($expected, $search->generateCacheKey());
    }
}
