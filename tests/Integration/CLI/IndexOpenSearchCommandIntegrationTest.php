<?php

namespace Pressmind\Tests\Integration\CLI;

use Pressmind\CLI\IndexOpenSearchCommand;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for IndexOpenSearchCommand using a real OpenSearch instance.
 */
class IndexOpenSearchCommandIntegrationTest extends AbstractIntegrationTestCase
{
    private ?string $opensearchUri = null;

    private function getOpenSearchConfig(): array
    {
        $config = $this->getIntegrationConfig();
        $config['data'] = array_merge($config['data'] ?? [], [
            'search_opensearch' => [
                'uri' => $this->opensearchUri,
                'username' => null,
                'password' => null,
                'index' => [
                    'fulltext' => [
                        'type' => 'text',
                        'boost' => 2,
                        'object_type_mapping' => [
                            999 => [
                                ['field' => 'name', 'language' => 'de'],
                            ],
                        ],
                    ],
                    'code' => [
                        'type' => 'keyword',
                        'boost' => 1,
                        'object_type_mapping' => [
                            999 => [
                                ['field' => 'code', 'language' => 'de'],
                            ],
                        ],
                    ],
                ],
            ],
            'media_types_allowed_visibilities' => [],
        ]);

        return $config;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->opensearchUri = getenv('OPENSEARCH_URI') ?: ($_SERVER['OPENSEARCH_URI'] ?? null);

        if (empty($this->opensearchUri)) {
            $this->markTestSkipped('OPENSEARCH_URI not set');
        }

        try {
            $client = $this->createOpenSearchClient();
            $client->cluster()->health();
        } catch (\Throwable $e) {
            $this->markTestSkipped('OpenSearch not reachable: ' . $e->getMessage());
        }

        Registry::getInstance()->add('config', $this->getOpenSearchConfig());
    }

    protected function tearDown(): void
    {
        if ($this->opensearchUri) {
            try {
                $client = $this->createOpenSearchClient();
                $indices = $client->cat()->indices();
                foreach ($indices as $index) {
                    $name = $index['index'] ?? '';
                    if (str_starts_with($name, 'index_')) {
                        $client->indices()->delete(['index' => $name]);
                    }
                }
                $templates = $client->indices()->getTemplate();
                foreach (array_keys($templates) as $tplName) {
                    if (str_starts_with($tplName, 'index_')) {
                        $client->indices()->deleteTemplate(['name' => $tplName]);
                    }
                }
            } catch (\Throwable $e) {
                // best-effort cleanup
            }
        }

        parent::tearDown();
    }

    private function createOpenSearchClient(): \OpenSearch\Client
    {
        return (new \OpenSearch\SymfonyClientFactory())->create([
            'base_uri' => $this->opensearchUri,
            'verify_peer' => false,
        ]);
    }

    private function runCommand(IndexOpenSearchCommand $cmd, array $argv): array
    {
        ob_start();
        try {
            $exit = $cmd->run($argv);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_get_clean();
            throw $e;
        }
        return ['exit' => $exit, 'output' => $output];
    }

    public function testHelpSubcommand(): void
    {
        $result = $this->runCommand(new IndexOpenSearchCommand(), ['index_opensearch.php', 'help']);
        $this->assertSame(0, $result['exit']);
    }

    public function testNoSubcommandShowsHelp(): void
    {
        $result = $this->runCommand(new IndexOpenSearchCommand(), ['index_opensearch.php']);
        $this->assertSame(0, $result['exit']);
    }

    public function testCreateIndexTemplates(): void
    {
        $result = $this->runCommand(new IndexOpenSearchCommand(), ['index_opensearch.php', 'create_index_templates']);
        $this->assertSame(0, $result['exit']);

        $client = $this->createOpenSearchClient();
        $templates = $client->indices()->getTemplate();
        $templateNames = array_keys($templates);

        $hasTestTemplate = false;
        foreach ($templateNames as $name) {
            if (str_starts_with($name, 'index_')) {
                $hasTestTemplate = true;
                break;
            }
        }
        $this->assertTrue($hasTestTemplate, 'Expected index template not created. Found: ' . implode(', ', $templateNames));
    }

    public function testMediaobjectWithoutIdsReturnsError(): void
    {
        $result = $this->runCommand(new IndexOpenSearchCommand(), ['index_opensearch.php', 'mediaobject']);
        $this->assertSame(1, $result['exit']);
    }

    public function testUnknownSubcommandShowsHelp(): void
    {
        $result = $this->runCommand(new IndexOpenSearchCommand(), ['index_opensearch.php', 'nonexistent']);
        $this->assertSame(0, $result['exit']);
    }
}
