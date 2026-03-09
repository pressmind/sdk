<?php

namespace Pressmind\Tests\Integration\CLI;

use Pressmind\CLI\LogCleanupCommand;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for LogCleanupCommand using real config.
 * Uses filesystem storage mode (no DB table dependency).
 */
class LogCleanupCommandIntegrationTest extends AbstractIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $config = $this->getIntegrationConfig();
        $config['logging'] = [
            'enable_advanced_object_log' => false,
            'storage' => 'filesystem',
            'lifetime' => 86400,
            'keep_log_types' => [],
        ];

        Registry::getInstance()->add('config', $config);
    }

    public function testCleanupFilesystem(): void
    {
        $cmd = new LogCleanupCommand();
        ob_start();
        try {
            $exit = $cmd->run(['log-cleanup']);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_get_clean();
            throw $e;
        }

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('filesystem', $output);
    }
}
