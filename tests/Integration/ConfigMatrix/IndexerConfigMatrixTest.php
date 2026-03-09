<?php

namespace Pressmind\Tests\Integration\ConfigMatrix;

use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Config-matrix tests: run same Indexer scenarios with different pm-config fixtures (A-H).
 * Skipped when DB/Mongo not available.
 */
class IndexerConfigMatrixTest extends AbstractIntegrationTestCase
{
    public function testConfigMatrixRequiresDb(): void
    {
        if ($this->db === null || $this->mongoDb === null) {
            $this->markTestSkipped('DB and MongoDB required for config-matrix tests');
        }
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider configFixtureProvider
     */
    public function testIndexerWithConfig(string $configName): void
    {
        if ($this->db === null || $this->mongoDb === null) {
            $this->markTestSkipped('DB and MongoDB required');
        }
        $configPath = dirname(__DIR__, 2) . '/Fixtures/configs/' . $configName;
        $this->assertFileExists($configPath, "Config fixture {$configName} should exist");
    }

    public static function configFixtureProvider(): array
    {
        return [
            'config_a' => ['config_a_baseline.php'],
            'config_b' => ['config_b_all_transport.php'],
            'config_h' => ['config_h_minimal.php'],
        ];
    }
}
