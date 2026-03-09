<?php

namespace Pressmind\Tests\Unit\CLI;

use Pressmind\CLI\FileDownloaderCommand;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for FileDownloaderCommand.
 */
class FileDownloaderCommandTest extends AbstractTestCase
{
    private string $logDir;
    private string $bucketDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logDir = sys_get_temp_dir() . '/pm-file-downloader-test-logs-' . getmypid();
        $this->bucketDir = sys_get_temp_dir() . '/pm-file-downloader-test-bucket-' . getmypid();

        $config = Registry::getInstance()->get('config');
        $config['logging']['storage'] = 'filesystem';
        $config['logging']['mode'] = 'ALL';
        $config['logging']['log_file_path'] = $this->logDir;
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        $config['file_handling'] = [
            'storage' => [
                'provider' => 'filesystem',
                'bucket' => $this->bucketDir,
            ],
        ];
        Registry::getInstance()->add('config', $config);
    }

    protected function tearDown(): void
    {
        if (isset($this->logDir) && is_dir($this->logDir)) {
            array_map('unlink', glob($this->logDir . '/*') ?: []);
            @rmdir($this->logDir);
        }
        if (isset($this->bucketDir) && is_dir($this->bucketDir)) {
            array_map('unlink', glob($this->bucketDir . '/*') ?: []);
            @rmdir($this->bucketDir);
        }
        parent::tearDown();
    }

    public function testCanBeInstantiated(): void
    {
        $cmd = new FileDownloaderCommand();
        $this->assertInstanceOf(FileDownloaderCommand::class, $cmd);
    }

    public function testProcessNameConstant(): void
    {
        $reflection = new \ReflectionClass(FileDownloaderCommand::class);
        $this->assertTrue($reflection->hasConstant('PROCESS_NAME'));
        $this->assertSame('file_downloader', $reflection->getConstant('PROCESS_NAME'));
    }

    public function testRunReturnsZeroWhenNoFilesToDownload(): void
    {
        $cmd = new FileDownloaderCommand();
        $exitCode = $cmd->run(['file-downloader']);

        $this->assertSame(0, $exitCode);

        $logFile = $this->logDir . DIRECTORY_SEPARATOR . 'file_downloader.log';
        $this->assertFileExists($logFile);
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('Downloading 0 files', $logContent);
    }
}
