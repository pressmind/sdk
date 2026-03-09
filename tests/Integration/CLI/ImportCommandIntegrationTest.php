<?php

namespace Pressmind\Tests\Integration\CLI;

use Pressmind\CLI\ImportCommand;
use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\ORM\Object\ProcessList;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for ImportCommand using real MySQL.
 * Creates the process list table so lock handling works.
 */
class ImportCommandIntegrationTest extends AbstractIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }

        $this->ensureProcessListTable();
        ProcessList::unlock('import');
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            try {
                ProcessList::unlock('import');
            } catch (\Throwable $e) {
                // ignore
            }
        }
        parent::tearDown();
    }

    private function ensureProcessListTable(): void
    {
        try {
            $scaffolder = new ScaffolderMysql(new ProcessList());
            $scaffolder->run(true);
        } catch (\Throwable $e) {
            // table may already exist
        }
    }

    private function runCommand(array $argv): array
    {
        $cmd = new ImportCommand();
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
        $result = $this->runCommand(['import.php', 'help']);
        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('usage:', $result['output']);
    }

    public function testHelpNoArgument(): void
    {
        $result = $this->runCommand(['import.php']);
        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('fullimport', $result['output']);
    }

    public function testUnknownSubcommandShowsHelp(): void
    {
        $result = $this->runCommand(['import.php', 'unknown_sub_xyz']);
        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('usage:', $result['output']);
    }

    public function testUnlockWithoutLock(): void
    {
        $result = $this->runCommand(['import.php', 'unlock']);
        $this->assertSame(0, $result['exit']);
    }

    public function testMediaobjectWithoutIdsReturnsError(): void
    {
        $result = $this->runCommand(['import.php', 'mediaobject']);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('Missing', $result['output']);
    }

    public function testTouristicWithoutIdsReturnsError(): void
    {
        $result = $this->runCommand(['import.php', 'touristic']);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('Missing', $result['output']);
    }

    public function testItineraryWithoutIdsReturnsError(): void
    {
        $result = $this->runCommand(['import.php', 'itinerary']);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('Missing', $result['output']);
    }

    public function testObjecttypesWithoutIdsReturnsError(): void
    {
        $result = $this->runCommand(['import.php', 'objecttypes']);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('Missing', $result['output']);
    }

    public function testDepublishWithEmptyIdsReturnsZero(): void
    {
        $result = $this->runCommand(['import.php', 'depublish']);
        $this->assertSame(0, $result['exit']);
    }

    public function testDestroyWithEmptyIdsReturnsZero(): void
    {
        $result = $this->runCommand(['import.php', 'destroy']);
        $this->assertSame(0, $result['exit']);
    }

    public function testMediaobjectCacheUpdateWithoutIdsReturnsError(): void
    {
        $result = $this->runCommand(['import.php', 'mediaobject_cache_update']);
        $this->assertSame(1, $result['exit']);
    }

    public function testAfterImportCallbackCalled(): void
    {
        $calledWith = null;
        $cmd = new ImportCommand();
        $cmd->setOnAfterImportCallback(function (array $ids) use (&$calledWith) {
            $calledWith = $ids;
        });

        ob_start();
        try {
            $cmd->run(['import.php', 'mediaobject_cache_update', '999']);
            ob_get_clean();
        } catch (\Throwable $e) {
            ob_get_clean();
        }

        $this->assertSame(['999'], $calledWith);
    }
}
