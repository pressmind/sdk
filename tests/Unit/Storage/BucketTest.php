<?php

namespace Pressmind\Tests\Unit\Storage;

use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Storage\Bucket.
 * Uses Filesystem provider with sys_get_temp_dir() subdir; cleanup in tearDown.
 */
class BucketTest extends AbstractTestCase
{
    /** @var string */
    private $tempDir;

    /** @var Bucket */
    private $bucket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/pm_bucket_test_' . uniqid();
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
            array_map('unlink', glob($this->tempDir . '/*') ?: []);
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testNameFromStorage(): void
    {
        $this->assertSame($this->tempDir, $this->bucket->name);
    }

    public function testAddFileAndFileExists(): void
    {
        $file = new File($this->bucket);
        $file->name = 'a.txt';
        $file->content = 'content a';
        $this->bucket->addFile($file);
        $this->assertTrue($this->bucket->fileExists($file));
    }

    public function testRemoveFile(): void
    {
        $file = new File($this->bucket);
        $file->name = 'b.txt';
        $file->content = 'content b';
        $this->bucket->addFile($file);
        $this->assertTrue($this->bucket->fileExists($file));
        $this->bucket->removeFile($file);
        $this->assertFalse($this->bucket->fileExists($file));
    }

    public function testReadFileAndFilesize(): void
    {
        $file = new File($this->bucket);
        $file->name = 'c.txt';
        $file->content = 'hello';
        $this->bucket->addFile($file);
        $read = $this->bucket->readFile($file);
        $this->assertSame('hello', $read->content);
        $this->assertSame(5, $this->bucket->filesize($file));
    }

    public function testListFiles(): void
    {
        $file1 = new File($this->bucket);
        $file1->name = 'list1.txt';
        $file1->content = 'x';
        $this->bucket->addFile($file1);
        $file2 = new File($this->bucket);
        $file2->name = 'list2.txt';
        $file2->content = 'y';
        $this->bucket->addFile($file2);
        $list = $this->bucket->listFiles();
        $names = array_map(function (File $f) {
            return $f->name;
        }, $list);
        $this->assertContains('list1.txt', $names);
        $this->assertContains('list2.txt', $names);
    }

    public function testSupportsPrefixListing(): void
    {
        $this->assertTrue($this->bucket->supportsPrefixListing());
    }

    public function testListByPrefix(): void
    {
        $file = new File($this->bucket);
        $file->name = 'prefix_foo.txt';
        $file->content = 'a';
        $this->bucket->addFile($file);
        $file2 = new File($this->bucket);
        $file2->name = 'prefix_bar.txt';
        $file2->content = 'b';
        $this->bucket->addFile($file2);
        $byPrefix = $this->bucket->listByPrefix('prefix_');
        $this->assertArrayHasKey('prefix_foo.txt', $byPrefix);
        $this->assertArrayHasKey('prefix_bar.txt', $byPrefix);
        $this->assertSame(1, $byPrefix['prefix_foo.txt']);
        $this->assertSame(1, $byPrefix['prefix_bar.txt']);
    }

    public function testSupportsFullScan(): void
    {
        $this->assertTrue($this->bucket->supportsFullScan());
    }

    public function testScanAllKeys(): void
    {
        $file = new File($this->bucket);
        $file->name = 'scan.txt';
        $file->content = 'data';
        $this->bucket->addFile($file);
        $keys = [];
        $this->bucket->scanAllKeys(function ($key, $size) use (&$keys) {
            $keys[$key] = $size;
        });
        $this->assertArrayHasKey('scan.txt', $keys);
        $this->assertSame(4, $keys['scan.txt']);
    }

    public function testSetFileMode(): void
    {
        $file = new File($this->bucket);
        $file->name = 'mode.txt';
        $file->content = 'x';
        $file->mode = 0640;
        $this->bucket->addFile($file);
        $this->bucket->setFileMode($file);
        $path = $this->tempDir . DIRECTORY_SEPARATOR . $file->name;
        $this->assertSame(0640, fileperms($path) & 0777);
    }

    public function testListByPrefixReturnsEmptyForNonPrefixListableProvider(): void
    {
        $mockProvider = $this->createMock(\Pressmind\Storage\ProviderInterface::class);
        $bucket = new Bucket([
            'bucket' => $this->tempDir,
            'provider' => 'filesystem',
        ]);
        $reflection = new \ReflectionClass($bucket);
        $prop = $reflection->getProperty('_provider');
        $prop->setAccessible(true);
        $prop->setValue($bucket, $mockProvider);

        $this->assertSame([], $bucket->listByPrefix('any_'));
    }

    public function testScanAllKeysNoopForNonFullScanProvider(): void
    {
        $mockProvider = $this->createMock(\Pressmind\Storage\ProviderInterface::class);
        $bucket = new Bucket([
            'bucket' => $this->tempDir,
            'provider' => 'filesystem',
        ]);
        $reflection = new \ReflectionClass($bucket);
        $prop = $reflection->getProperty('_provider');
        $prop->setAccessible(true);
        $prop->setValue($bucket, $mockProvider);

        $collected = [];
        $bucket->scanAllKeys(function ($key, $size) use (&$collected) {
            $collected[$key] = $size;
        });
        $this->assertEmpty($collected);
    }
}
