<?php

namespace Pressmind\Tests\Unit\CLI;

use Pressmind\CLI\AiCatalogCommand;
use Pressmind\Tests\Unit\AbstractTestCase;

class AiCatalogCommandTest extends AbstractTestCase
{
    private string $tmpRoot;
    private string $appPath;
    private string $documentRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpRoot = sys_get_temp_dir() . '/pm-ai-catalog-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
        $this->appPath = $this->tmpRoot . '/app';
        $this->documentRoot = $this->tmpRoot . '/httpdocs';
        mkdir($this->appPath, 0775, true);
        mkdir($this->documentRoot, 0775, true);

        $this->writeConfig($this->appPath . '/pm-config.php', [
            'development' => [
                'server' => [
                    'document_root' => $this->documentRoot,
                    'webserver_http' => 'https://reisen.example.com',
                ],
                'mcp' => [
                    'server_name' => 'pressmind-travel-reisen-example-com',
                    'server_title' => 'Reisen Example Travel Search',
                    'description' => 'Search and book travel products.',
                    'version' => '1.2.3',
                    'mcp_prefix' => 'travel-mcp',
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->tmpRoot)) {
            $this->removeDirectory($this->tmpRoot);
        }

        parent::tearDown();
    }

    public function testHelpReturnsUsage(): void
    {
        $command = new AiCatalogCommand();

        ob_start();
        $exitCode = $command->run(['ai-catalog', '--help']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Pressmind ARD / AI Catalog generator', $output);
        $this->assertStringContainsString('--application-path=DIR', $output);
    }

    public function testWritesCatalogAndServerCardToWellKnownDirectories(): void
    {
        $command = new AiCatalogCommand();

        ob_start();
        $exitCode = $command->run([
            'ai-catalog',
            '--application-path=' . $this->appPath,
        ]);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode, $output);

        $catalogPath = $this->documentRoot . '/.well-known/ai-catalog.json';
        $serverCardPath = $this->documentRoot . '/.well-known/mcp/server-card.json';
        $robotsPath = $this->documentRoot . '/robots.txt';
        $this->assertFileExists($catalogPath);
        $this->assertFileExists($serverCardPath);
        $this->assertFileExists($robotsPath);

        $catalog = json_decode((string) file_get_contents($catalogPath), true);
        $serverCard = json_decode((string) file_get_contents($serverCardPath), true);

        $this->assertSame('https://reisen.example.com/.well-known/mcp/server-card.json', $catalog['entries'][0]['url']);
        $this->assertSame('https://reisen.example.com/travel-mcp/sse', $serverCard['transport']['endpoint']);
        $this->assertStringNotContainsString($this->tmpRoot, (string) file_get_contents($catalogPath));
        $this->assertStringNotContainsString($this->tmpRoot, (string) file_get_contents($serverCardPath));
        $this->assertStringContainsString('Agentmap: https://reisen.example.com/.well-known/ai-catalog.json', (string) file_get_contents($robotsPath));
    }

    public function testAppendsAgentmapToExistingRobotsTxtWithoutRemovingContent(): void
    {
        $robotsPath = $this->documentRoot . '/robots.txt';
        file_put_contents($robotsPath, "User-agent: *\nDisallow: /private/\n");

        $command = new AiCatalogCommand();

        ob_start();
        $exitCode = $command->run([
            'ai-catalog',
            '--application-path=' . $this->appPath,
        ]);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode, $output);

        $robots = (string) file_get_contents($robotsPath);
        $this->assertStringContainsString("User-agent: *\nDisallow: /private/", $robots);
        $this->assertStringContainsString('Agentmap: https://reisen.example.com/.well-known/ai-catalog.json', $robots);
    }

    public function testDoesNotDuplicateExistingAgentmapLine(): void
    {
        $robotsPath = $this->documentRoot . '/robots.txt';
        file_put_contents($robotsPath, "User-agent: *\nAgentmap: https://reisen.example.com/.well-known/ai-catalog.json\n");

        $command = new AiCatalogCommand();

        ob_start();
        $exitCode = $command->run([
            'ai-catalog',
            '--application-path=' . $this->appPath,
        ]);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode, $output);

        $robots = (string) file_get_contents($robotsPath);
        preg_match_all('/^Agentmap:/m', $robots, $matches);
        $this->assertCount(1, $matches[0]);
    }

    public function testDryRunDoesNotWriteFiles(): void
    {
        $command = new AiCatalogCommand();

        ob_start();
        $exitCode = $command->run([
            'ai-catalog',
            '--application-path=' . $this->appPath,
            '--dry-run',
        ]);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('Dry run', $output);
        $this->assertFileDoesNotExist($this->documentRoot . '/.well-known/ai-catalog.json');
        $this->assertFileDoesNotExist($this->documentRoot . '/.well-known/mcp/server-card.json');
        $this->assertFileDoesNotExist($this->documentRoot . '/robots.txt');
    }

    public function testInvalidApplicationPathReturnsError(): void
    {
        $command = new AiCatalogCommand();

        ob_start();
        $exitCode = $command->run([
            'ai-catalog',
            '--application-path=' . $this->tmpRoot . '/missing',
        ]);
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Application path is not a directory', $output);
    }

    public function testMissingDocumentRootReturnsError(): void
    {
        $command = new AiCatalogCommand();

        ob_start();
        $exitCode = $command->run([
            'ai-catalog',
            '--application-path=' . $this->appPath,
            '--document-root=' . $this->tmpRoot . '/missing-httpdocs',
        ]);
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Document root is not a directory', $output);
    }

    private function writeConfig(string $path, array $config): void
    {
        file_put_contents($path, "<?php\n\$config = " . var_export($config, true) . ';');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
