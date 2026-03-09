<?php

namespace Pressmind\Tests\Integration\CLI;

use Pressmind\CLI\DatabaseIntegrityCheckCommand;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for DatabaseIntegrityCheckCommand using real MySQL.
 *
 * With a fresh database (no pressmind tables) the command detects missing tables
 * as integrity violations and reports them in --non-interactive mode.
 */
class DatabaseIntegrityCheckCommandIntegrationTest extends AbstractIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
    }

    private function runCommand(array $argv): array
    {
        $cmd = new DatabaseIntegrityCheckCommand();
        ob_start();
        $prev = set_error_handler(function (int $severity, string $msg) {
            if ($severity & (E_WARNING | E_USER_WARNING | E_NOTICE | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED)) {
                return true;
            }
            return false;
        });
        try {
            $exit = $cmd->run($argv);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_get_clean();
            restore_error_handler();
            throw $e;
        }
        restore_error_handler();
        return ['exit' => $exit, 'output' => $output];
    }

    public function testNonInteractiveReportsViolations(): void
    {
        $result = $this->runCommand(['database-integrity-check', '--non-interactive']);
        $this->assertSame(1, $result['exit']);
    }

    public function testNonInteractiveProducesOutput(): void
    {
        $result = $this->runCommand(['database-integrity-check', '--non-interactive']);
        $this->assertNotEmpty($result['output']);
        $this->assertStringContainsString('Database Schema Integrity Check', $result['output']);
    }

    public function testFragmentationCheckRuns(): void
    {
        $result = $this->runCommand(['database-integrity-check', '-n']);
        $this->assertStringContainsString('fragmentation', strtolower($result['output']));
    }

    public function testLogTableCheckRuns(): void
    {
        $result = $this->runCommand(['database-integrity-check', '-n']);
        $this->assertStringContainsString('log table', strtolower($result['output']));
    }
}
