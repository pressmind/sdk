<?php

namespace Pressmind\Tests\Integration\CLI;

use Pressmind\CLI\FileDownloaderCommand;
use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\ORM\Object\MediaObject\DataType\File;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for FileDownloaderCommand using real MySQL and filesystem storage.
 * Includes E2E test: full download of one file from HTTP stub into bucket (Option A).
 */
class FileDownloaderCommandIntegrationTest extends AbstractIntegrationTestCase
{
    private string $logDir;
    private string $bucketDir;
    private ?int $e2eFileId = null;

    /** @var resource|null */
    private $httpServerProcess = null;
    private int $httpServerPort = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }

        $this->logDir = sys_get_temp_dir() . '/pm-file-downloader-integration-' . getmypid();
        $this->bucketDir = sys_get_temp_dir() . '/pm-file-downloader-integration-bucket-' . getmypid();
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
        if (!is_dir($this->bucketDir)) {
            mkdir($this->bucketDir, 0755, true);
        }

        $this->ensureFileTableExists();

        $config = $this->getIntegrationConfig();
        $config['logging'] = [
            'enable_advanced_object_log' => false,
            'storage' => 'filesystem',
            'mode' => 'ALL',
            'log_file_path' => $this->logDir,
        ];
        $config['file_handling'] = [
            'storage' => [
                'provider' => 'filesystem',
                'bucket' => $this->bucketDir,
            ],
            'http_src' => 'http://localhost/assets/files',
        ];
        Registry::getInstance()->add('config', $config);
    }

    protected function tearDown(): void
    {
        $this->stopHttpServer();

        if ($this->e2eFileId !== null && $this->db !== null) {
            try {
                $file = new File();
                $file->read($this->e2eFileId);
                if ($file->id) {
                    $file->delete();
                }
            } catch (\Throwable $e) {
                // ignore cleanup errors
            }
        }
        if (isset($this->bucketDir) && is_dir($this->bucketDir)) {
            $files = glob($this->bucketDir . '/*') ?: [];
            foreach ($files as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($this->bucketDir);
        }
        if (isset($this->logDir) && is_dir($this->logDir)) {
            $files = glob($this->logDir . '/*') ?: [];
            foreach ($files as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($this->logDir);
        }
        parent::tearDown();
    }

    private function startHttpServer(): string
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            $this->fail('Could not allocate free port for HTTP stub: ' . $errstr);
        }
        $addr = stream_socket_get_name($sock, false);
        fclose($sock);
        $this->httpServerPort = (int)substr($addr, strrpos($addr, ':') + 1);

        $stubScript = dirname(__DIR__, 2) . '/Fixtures/http/file-stub.php';
        $cmd = sprintf(
            'php -S 127.0.0.1:%d %s',
            $this->httpServerPort,
            escapeshellarg($stubScript)
        );

        $this->httpServerProcess = proc_open(
            $cmd,
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes
        );

        if (!is_resource($this->httpServerProcess)) {
            $this->fail('Could not start PHP built-in HTTP server');
        }

        usleep(200000);

        return sprintf('http://127.0.0.1:%d', $this->httpServerPort);
    }

    private function stopHttpServer(): void
    {
        if ($this->httpServerProcess !== null && is_resource($this->httpServerProcess)) {
            proc_terminate($this->httpServerProcess);
            proc_close($this->httpServerProcess);
            $this->httpServerProcess = null;
        }
    }

    private function ensureFileTableExists(): void
    {
        try {
            $scaffolder = new ScaffolderMysql(new File());
            $scaffolder->run(false);
        } catch (\Throwable $e) {
            // table may already exist
        }
    }

    private function runCommand(array $argv): int
    {
        $cmd = new FileDownloaderCommand();
        return $cmd->run($argv);
    }

    public function testRunWithNoPendingFilesExitsZero(): void
    {
        $exitCode = $this->runCommand(['file-downloader']);
        $this->assertSame(0, $exitCode);

        $logFile = $this->logDir . DIRECTORY_SEPARATOR . 'file_downloader.log';
        $this->assertFileExists($logFile);
        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('Downloading 0 files', $logContent);
    }

    /**
     * E2E: one file with tmp_url to local PHP built-in HTTP server;
     * command downloads it and updates DB and bucket.
     */
    public function testE2EFullDownloadOneFile(): void
    {
        $baseUrl = $this->startHttpServer();

        $file = new File();
        $file->id_media_object = 1;
        $file->section_name = 'test';
        $file->language = 'de';
        $file->var_name = 'e2e_test';
        $file->id_file = 'e2e-' . uniqid();
        $file->file_name = 'e2e-test-' . uniqid() . '.bin';
        $file->tmp_url = $baseUrl . '/?size=100';
        $file->download_successful = false;
        $file->create();

        $this->e2eFileId = $file->id;
        $this->assertGreaterThan(0, $this->e2eFileId);

        $exitCode = $this->runCommand(['file-downloader']);
        $this->assertSame(0, $exitCode);

        $file->read($this->e2eFileId);
        $this->assertTrue((bool) $file->download_successful, 'download_successful should be true after run');
        $this->assertGreaterThan(0, $file->file_size);

        $storageFile = $file->getFile();
        $this->assertTrue($storageFile->exists(), 'Downloaded file should exist in bucket');
    }
}
