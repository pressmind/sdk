<?php

namespace Pressmind\Tests\Integration\CLI;

use Pressmind\CLI\FileDownloaderCommand;
use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\ORM\Object\MediaObject\DataType\File;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
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
    private array $e2ePictureIds = [];

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
        foreach ($this->e2ePictureIds as $pictureId) {
            try {
                $picture = new Picture();
                $picture->read($pictureId);
                if ($picture->id) {
                    $picture->delete();
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

    private function startHttpServer(string $stub = 'file-stub.php'): string
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($sock === false) {
            $this->fail('Could not allocate free port for HTTP stub: ' . $errstr);
        }
        $addr = stream_socket_get_name($sock, false);
        fclose($sock);
        $this->httpServerPort = (int)substr($addr, strrpos($addr, ':') + 1);

        $stubScript = dirname(__DIR__, 2) . '/Fixtures/http/' . $stub;
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

        // wait until the server accepts connections (a fixed sleep was flaky)
        $deadline = microtime(true) + 5;
        do {
            usleep(50000);
            $probe = @fsockopen('127.0.0.1', $this->httpServerPort, $errno, $errstr, 0.2);
        } while ($probe === false && microtime(true) < $deadline);
        if ($probe === false) {
            $this->fail('PHP built-in HTTP server did not start on port ' . $this->httpServerPort);
        }
        fclose($probe);

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
        // Picture::create()/read() also touch the derivative and section relation tables
        foreach ([new Picture(), new Picture\Derivative(), new Picture\Section()] as $object) {
            try {
                (new ScaffolderMysql($object))->run(false);
            } catch (\Throwable $e) {
                // table may already exist
            }
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

    private function createPicture(?string $videoUrl): Picture
    {
        $picture = new Picture();
        $picture->id_picture = random_int(100000, 999999);
        $picture->id_media_object = 1;
        $picture->section_name = '';
        $picture->language = 'de';
        $picture->var_name = 'e2e_video';
        $picture->file_name = 'e2e-poster-' . uniqid() . '.jpg';
        $picture->download_successful = false;
        if ($videoUrl !== null) {
            $picture->video_tmp_url = $videoUrl;
            $picture->video_file_name = 'e2e-video-' . uniqid() . '.mp4';
            $picture->video_mime_type = 'video/mp4';
            $picture->video_download_successful = false;
        }
        $picture->create();
        $this->e2ePictureIds[] = $picture->id;
        return $picture;
    }

    public function testE2EDownloadsPendingVideoAndSkipsPicturesWithoutVideo(): void
    {
        $baseUrl = $this->startHttpServer('mp4-stub.php');
        $withVideo = $this->createPicture($baseUrl . '/clip.mp4');
        $withoutVideo = $this->createPicture(null);

        $this->assertSame(0, $this->runCommand(['file-downloader']));

        $withVideo->read($withVideo->id);
        $this->assertTrue((bool) $withVideo->video_download_successful);
        $this->assertGreaterThan(0, $withVideo->video_file_size);
        $this->assertTrue($withVideo->getVideoFile()->exists());

        $withoutVideo->read($withoutVideo->id);
        $this->assertNull($withoutVideo->video_tmp_url);
        $this->assertNull($withoutVideo->video_file_name);
        $logContent = file_get_contents($this->logDir . DIRECTORY_SEPARATOR . 'file_downloader.log');
        $this->assertStringContainsString('Downloading 1 videos', $logContent);
    }

    public function testE2EFailedVideoDownloadDoesNotStopOthersAndIsRetried(): void
    {
        $baseUrl = $this->startHttpServer('mp4-stub.php');
        $broken = $this->createPicture($baseUrl . '/clip.mp4?status=404');
        $working = $this->createPicture($baseUrl . '/clip.mp4');

        $this->assertSame(0, $this->runCommand(['file-downloader']));

        $broken->read($broken->id);
        $working->read($working->id);
        $this->assertFalse((bool) $broken->video_download_successful);
        $this->assertFalse($broken->getVideoFile()->exists());
        $this->assertTrue((bool) $working->video_download_successful);
        // Writer routes non-INFO entries to <process>_<type>.log
        $errorLogContent = file_get_contents($this->logDir . DIRECTORY_SEPARATOR . 'file_downloader_error.log');
        $this->assertStringContainsString('HTTP error: 404', $errorLogContent);

        // still pending, therefore picked up again by the next run
        $pending = Picture::listAll(['video_tmp_url' => 'IS NOT NULL', 'video_download_successful' => 0, 'var_name' => 'e2e_video']);
        $this->assertCount(1, $pending);
        $this->assertSame($broken->id, $pending[0]->id);
    }

    public function testDownloadVideoDoesNotOverwriteStateWrittenConcurrently(): void
    {
        $baseUrl = $this->startHttpServer('mp4-stub.php');
        $created = $this->createPicture($baseUrl . '/clip.mp4');
        $copyA = new Picture();
        $copyA->read($created->id);
        $staleCopyB = new Picture();
        $staleCopyB->read($created->id);

        // image_processor finishes the poster while file_downloader still holds the stale row
        $copyA->download_successful = true;
        $copyA->update();
        $staleCopyB->downloadVideo();

        $fresh = new Picture();
        $fresh->read($created->id);
        $this->assertTrue((bool) $fresh->download_successful);
        $this->assertTrue((bool) $fresh->video_download_successful);
        $this->assertGreaterThan(0, $fresh->video_file_size);
    }

    public function testE2EInvalidExistingVideoFileIsReplacedByForcedRedownload(): void
    {
        $baseUrl = $this->startHttpServer('mp4-stub.php');
        $picture = $this->createPicture($baseUrl . '/clip.mp4');
        file_put_contents($this->bucketDir . '/' . $picture->video_file_name, '<html>not a video</html>');

        $this->assertSame(0, $this->runCommand(['file-downloader']));

        $picture->read($picture->id);
        $this->assertTrue((bool) $picture->video_download_successful);
        $this->assertSame(filesize(dirname(__DIR__, 2) . '/Fixtures/video/motion-clip.mp4'), (int) $picture->video_file_size);
        $this->assertSame('video/mp4', (new \finfo(FILEINFO_MIME_TYPE))->file($this->bucketDir . '/' . $picture->video_file_name));
    }
}
