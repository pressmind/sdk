<?php

namespace Pressmind\Tests\Integration\CLI;

use Pressmind\CLI\TouristicOrphansCommand;
use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for TouristicOrphansCommand using real MySQL.
 * Creates required tables so TouristicOrphans class can query them.
 */
class TouristicOrphansCommandIntegrationTest extends AbstractIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }

        $this->ensureRequiredTables();

        $config = $this->getIntegrationConfig();
        $config['data'] = array_merge($config['data'] ?? [], [
            'primary_media_type_ids' => [999],
        ]);
        Registry::getInstance()->add('config', $config);
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            try {
                $this->db->execute('DROP TABLE IF EXISTS `pmt2core_cheapest_price_speed`');
            } catch (\Throwable $e) {
            }
        }
        parent::tearDown();
    }

    private function ensureRequiredTables(): void
    {
        try {
            $scaffolder = new ScaffolderMysql(new MediaObject());
            $scaffolder->run(true);
        } catch (\Throwable $e) {
            // table may already exist
        }
        try {
            $this->db->execute('CREATE TABLE IF NOT EXISTS pmt2core_cheapest_price_speed (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                id_media_object INT,
                fingerprint VARCHAR(255)
            )');
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function runCommand(array $argv): array
    {
        $cmd = new TouristicOrphansCommand();
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

    public function testWithObjectTypesAndStatsOnly(): void
    {
        $result = $this->runCommand([
            'touristic-orphans',
            '--object-types=999',
            '--stats-only',
        ]);

        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('Orphans Check', $result['output']);
        $this->assertStringContainsString('Statistics', $result['output']);
    }

    public function testNoOrphansWithEmptyDb(): void
    {
        $result = $this->runCommand([
            'touristic-orphans',
            '--object-types=999',
        ]);

        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('No orphans', $result['output']);
    }

    public function testWithCustomVisibility(): void
    {
        $result = $this->runCommand([
            'touristic-orphans',
            '--object-types=999',
            '--visibility=40',
            '--stats-only',
        ]);

        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('Visibility: 40', $result['output']);
    }
}
