<?php

namespace Pressmind\Tests\Unit\Storage\Provider;

use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Storage\Provider\Filesystem;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Storage\Provider\Filesystem.
 * Uses sys_get_temp_dir() subdir; cleanup in tearDown. No production paths.
 */
class FilesystemTest extends AbstractTestCase
{
    /** @var string */
    private $tempDir;

    /** @var Bucket */
    private $bucket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/pm_fs_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        $config = $this->createMockConfig([]);
        Registry::getInstance()->add('config', $config);
        $this->bucket = new Bucket([
            'bucket' => $this->tempDir,
            'provider' => 'filesystem',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->tempDir !== null && is_dir($this->tempDir)) {
            $files = glob($this->tempDir . DIRECTORY_SEPARATOR . '*');
            if ($files) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        unlink($f);
                    }
                }
            }
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testSaveAndFileExists(): void
    {
        $file = new File($this->bucket);
        $file->name = 'save_test.txt';
        $file->content = 'saved content';
        $this->bucket->addFile($file);
        $this->assertTrue($this->bucket->fileExists($file));
        $path = $this->tempDir . DIRECTORY_SEPARATOR . $file->name;
        $this->assertFileExists($path);
        $this->assertSame('saved content', file_get_contents($path));
    }

    public function testDelete(): void
    {
        $file = new File($this->bucket);
        $file->name = 'del_test.txt';
        $file->content = 'x';
        $this->bucket->addFile($file);
        $this->assertTrue($this->bucket->fileExists($file));
        $this->bucket->removeFile($file);
        $this->assertFalse($this->bucket->fileExists($file));
    }

    public function testReadFile(): void
    {
        $file = new File($this->bucket);
        $file->name = 'read_test.txt';
        $file->content = 'read me';
        $this->bucket->addFile($file);
        $file->content = null;
        $read = $this->bucket->readFile($file);
        $this->assertSame('read me', $read->content);
        $this->assertSame(md5('read me'), $read->hash);
    }

    public function testFilesize(): void
    {
        $file = new File($this->bucket);
        $file->name = 'size_test.txt';
        $file->content = '12345';
        $this->bucket->addFile($file);
        $this->assertSame(5, $this->bucket->filesize($file));
    }

    public function testFileExistsEmptyNameReturnsFalse(): void
    {
        $file = new File($this->bucket);
        $file->name = '';
        $provider = new Filesystem();
        $provider->setBucket($this->bucket);
        $this->assertFalse($provider->fileExists($file, $this->bucket));
    }

    public function testSetFileMode(): void
    {
        $file = new File($this->bucket);
        $file->name = 'chmod_test.txt';
        $file->content = 'x';
        $file->mode = 0600;
        $this->bucket->addFile($file);
        $this->bucket->setFileMode($file);
        $path = $this->tempDir . DIRECTORY_SEPARATOR . $file->name;
        $this->assertSame(0600, fileperms($path) & 0777);
    }

    public function testListBucket(): void
    {
        $f1 = new File($this->bucket);
        $f1->name = 'l1.txt';
        $f1->content = 'a';
        $this->bucket->addFile($f1);
        $f2 = new File($this->bucket);
        $f2->name = 'l2.txt';
        $f2->content = 'b';
        $this->bucket->addFile($f2);
        $provider = new Filesystem();
        $provider->setBucket($this->bucket);
        $list = $provider->listBucket($this->bucket);
        $names = array_map(function (File $f) {
            return $f->name;
        }, $list);
        $this->assertContains('l1.txt', $names);
        $this->assertContains('l2.txt', $names);
    }

    public function testListByPrefix(): void
    {
        $f1 = new File($this->bucket);
        $f1->name = 'img_1.jpg';
        $f1->content = 'aa';
        $this->bucket->addFile($f1);
        $f2 = new File($this->bucket);
        $f2->name = 'img_2.jpg';
        $f2->content = 'b';
        $this->bucket->addFile($f2);
        $byPrefix = $this->bucket->listByPrefix('img_');
        $this->assertArrayHasKey('img_1.jpg', $byPrefix);
        $this->assertArrayHasKey('img_2.jpg', $byPrefix);
        $this->assertSame(2, $byPrefix['img_1.jpg']);
        $this->assertSame(1, $byPrefix['img_2.jpg']);
    }

    public function testScanAllKeys(): void
    {
        $f = new File($this->bucket);
        $f->name = 'scan_key.txt';
        $f->content = 'data';
        $this->bucket->addFile($f);
        $collected = [];
        $this->bucket->scanAllKeys(function ($key, $size) use (&$collected) {
            $collected[$key] = $size;
        });
        $this->assertArrayHasKey('scan_key.txt', $collected);
        $this->assertSame(4, $collected['scan_key.txt']);
    }

    public function testBucketExists(): void
    {
        $provider = new Filesystem();
        $provider->setBucket($this->bucket);
        $this->assertTrue($provider->bucketExists($this->bucket));
        $fake = new Bucket(['bucket' => $this->tempDir . '_nonexistent', 'provider' => 'filesystem']);
        $this->assertFalse($provider->bucketExists($fake));
    }

    public function testReadFileThrowsWhenFileDoesNotExist(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File does not exist');
        $file = new File($this->bucket);
        $file->name = 'no_such_file.txt';
        $this->bucket->readFile($file);
    }

    public function testFilesizeThrowsWhenFileDoesNotExist(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File does not exist');
        $file = new File($this->bucket);
        $file->name = 'missing_file.txt';
        $this->bucket->filesize($file);
    }

    public function testSetFileModeThrowsWhenFileDoesNotExist(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File does not exist');
        $file = new File($this->bucket);
        $file->name = 'no_mode.txt';
        $file->mode = 0644;
        $this->bucket->setFileMode($file);
    }

    public function testSaveCreatesDirectoryAutomatically(): void
    {
        $subDir = $this->tempDir . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'deep';
        $bucket = new Bucket(['bucket' => $subDir, 'provider' => 'filesystem']);
        $file = new File($bucket);
        $file->name = 'auto_dir.txt';
        $file->content = 'auto';
        $bucket->addFile($file);
        $this->assertFileExists($subDir . DIRECTORY_SEPARATOR . 'auto_dir.txt');
        @unlink($subDir . DIRECTORY_SEPARATOR . 'auto_dir.txt');
        @rmdir($subDir);
        @rmdir(dirname($subDir));
    }

    public function testDeleteOnNonExistentFileReturnsTrue(): void
    {
        $file = new File($this->bucket);
        $file->name = 'phantom.txt';
        $result = $this->bucket->removeFile($file);
        $this->assertTrue($result);
    }

    public function testDeleteAllThrowsOnShortPath(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('security reasons');
        $shortBucket = new Bucket(['bucket' => '/tmp', 'provider' => 'filesystem']);
        $shortBucket->removeAll();
    }

    public function testScanAllKeysOnNonExistentDirectoryReturnsNothing(): void
    {
        $fakeBucket = new Bucket([
            'bucket' => $this->tempDir . '_gone',
            'provider' => 'filesystem',
        ]);
        $collected = [];
        $fakeBucket->scanAllKeys(function ($key, $size) use (&$collected) {
            $collected[$key] = $size;
        });
        $this->assertEmpty($collected);
    }

    public function testListByPrefixNoMatchReturnsEmpty(): void
    {
        $result = $this->bucket->listByPrefix('nonexistent_prefix_');
        $this->assertSame([], $result);
    }

    public function testScanAllKeysWithSubdirectories(): void
    {
        $subDir = $this->tempDir . DIRECTORY_SEPARATOR . 'subdir';
        mkdir($subDir, 0755, true);
        file_put_contents($subDir . DIRECTORY_SEPARATOR . 'nested.txt', 'nested');
        file_put_contents($this->tempDir . DIRECTORY_SEPARATOR . 'root.txt', 'root');

        $collected = [];
        $this->bucket->scanAllKeys(function ($key, $size) use (&$collected) {
            $collected[$key] = $size;
        });
        $this->assertArrayHasKey('root.txt', $collected);
        $this->assertArrayHasKey('subdir/nested.txt', $collected);
        $this->assertSame(6, $collected['subdir/nested.txt']);

        @unlink($subDir . DIRECTORY_SEPARATOR . 'nested.txt');
        @rmdir($subDir);
    }
}
