<?php

namespace Pressmind\Tests\Integration\CLI;

use Pressmind\CLI\ResetCommand;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for ResetCommand using real MySQL and MongoDB.
 */
class ResetCommandIntegrationTest extends AbstractIntegrationTestCase
{
    private function getFullConfig(): array
    {
        $mongoUri = getenv('MONGODB_URI');
        $mongoDb = getenv('MONGODB_DB');
        $dbName = getenv('DB_NAME') ?: 'pressmind_test';

        $config = $this->getIntegrationConfig();
        $config['database'] = ['dbname' => $dbName];
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

        if ($this->db === null || $this->mongoDb === null) {
            $this->markTestSkipped('MySQL and MongoDB required for ResetCommand');
        }

        Registry::getInstance()->add('config', $this->getFullConfig());
    }

    private function runCommand(array $argv): array
    {
        $cmd = new ResetCommand();
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

    public function testNonInteractiveWithoutConfirmReturnsError(): void
    {
        $result = $this->runCommand(['reset', '--non-interactive']);
        $this->assertSame(1, $result['exit']);
    }

    public function testNonInteractiveWithConfirmDropsTablesAndFlushes(): void
    {
        $this->db->execute('CREATE TABLE IF NOT EXISTS _test_reset_dummy (id INT PRIMARY KEY)');
        $this->mongoDb->selectCollection('best_price_search_based_de_origin_1')
            ->insertOne(['_test' => true]);

        $result = $this->runCommand(['reset', '--non-interactive', '--confirm']);
        $this->assertSame(0, $result['exit']);

        $tables = $this->db->fetchAll('SHOW TABLES');
        $this->assertEmpty($tables, 'All tables should be dropped after reset');
    }
}
