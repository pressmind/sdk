<?php

namespace Pressmind\Tests\Unit\Search;

use Pressmind\Registry;
use Pressmind\Search\OpenSearch;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Search\OpenSearch: getIndexTemplateName, getConfigHash, sanitizeSearchTerm, getLog, generateCacheKey.
 * No real OpenSearch client; stub used to avoid GuzzleClientFactory in constructor.
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
    private function createOpenSearchStub(string $searchTerm = 'test', ?string $language = 'de', int $limit = 100): OpenSearch
    {
        $stub = $this->getMockBuilder(OpenSearch::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionClass(OpenSearch::class);
        $config = $this->getOpenSearchConfig();
        $configHash = md5(serialize(array_diff_key(
            $config['data']['search_opensearch'],
            array_flip(['uri', 'username', 'password'])
        )));
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
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $hash);
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
}
