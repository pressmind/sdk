<?php

namespace Pressmind\Tests\Integration\CLI;

use Pressmind\CLI\IntegrityCheckCommand;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for IntegrityCheckCommand.
 * PHP version check always passes (Docker has >= 8.1).
 * IBE-Team version check tested with temp config files.
 * Environment consistency tested with explicit --env option.
 */
class IntegrityCheckCommandIntegrationTest extends AbstractIntegrationTestCase
{
    private ?string $tmpDir = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/sdk_integrity_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if ($this->tmpDir && is_dir($this->tmpDir)) {
            $files = glob($this->tmpDir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    private function runCommand(array $argv): array
    {
        $cmd = new IntegrityCheckCommand();
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

    public function testPhpVersionCheckPasses(): void
    {
        $result = $this->runCommand([
            'integrity-check',
            '--non-interactive',
            '--custom-path=' . $this->tmpDir,
            '--env=testing',
        ]);

        $this->assertIsInt($result['exit']);
        $this->assertStringContainsString('PHP version', $result['output']);
    }

    public function testWithCorrectIBETeamVersion(): void
    {
        $configContent = "<?php\ndefine('IBETEAM_VERSION', \\Pressmind\\System\\Info::IBE_IMPORT_VERSION);\n";
        file_put_contents($this->tmpDir . '/config.php', $configContent);

        $result = $this->runCommand([
            'integrity-check',
            '--non-interactive',
            '--custom-path=' . $this->tmpDir,
            '--env=testing',
        ]);

        $this->assertSame(0, $result['exit']);
    }

    public function testWithHardcodedIBETeamVersionFails(): void
    {
        $configContent = "<?php\ndefine('IBETEAM_VERSION', '6_0');\n";
        file_put_contents($this->tmpDir . '/config.php', $configContent);

        $result = $this->runCommand([
            'integrity-check',
            '--non-interactive',
            '--custom-path=' . $this->tmpDir,
            '--env=testing',
        ]);

        $this->assertSame(1, $result['exit']);
    }

    public function testEnvironmentConsistencyProductionOk(): void
    {
        $configContent = "<?php\ndefine('IBETEAM_VERSION', \\Pressmind\\System\\Info::IBE_IMPORT_VERSION);\n";
        file_put_contents($this->tmpDir . '/config.php', $configContent);

        $result = $this->runCommand([
            'integrity-check',
            '--non-interactive',
            '--custom-path=' . $this->tmpDir,
            '--env=production',
            '--app-path=/var/www/vhosts/example.com/production',
            '--db-user=prod_user',
        ]);

        $this->assertSame(0, $result['exit']);
    }

    public function testEnvironmentConsistencyProductionWithDevPath(): void
    {
        $configContent = "<?php\ndefine('IBETEAM_VERSION', \\Pressmind\\System\\Info::IBE_IMPORT_VERSION);\n";
        file_put_contents($this->tmpDir . '/config.php', $configContent);

        $result = $this->runCommand([
            'integrity-check',
            '--non-interactive',
            '--custom-path=' . $this->tmpDir,
            '--env=production',
            '--app-path=/var/www/vhosts/example.com/development',
        ]);

        $this->assertSame(1, $result['exit']);
    }

    public function testNoCustomPathSkipsCheck(): void
    {
        $result = $this->runCommand([
            'integrity-check',
            '--non-interactive',
            '--custom-path=' . $this->tmpDir . '/nonexistent_custom',
            '--env=testing',
        ]);

        $this->assertSame(0, $result['exit']);
        $this->assertStringContainsString('check skipped', $result['output']);
    }
}
