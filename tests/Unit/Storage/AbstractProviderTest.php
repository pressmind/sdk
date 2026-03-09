<?php

namespace Pressmind\Tests\Unit\Storage;

use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Storage\Provider\Filesystem;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Storage\AbstractProvider (via Filesystem).
 * Tests setBucket/getBucket, setFile/getFile.
 */
class AbstractProviderTest extends AbstractTestCase
{
    public function testSetBucketGetBucket(): void
    {
        $baseDir = sys_get_temp_dir() . '/pm_storage_abstract_' . uniqid();
        @mkdir($baseDir, 0755, true);
        $bucket = new Bucket(['bucket' => $baseDir, 'provider' => 'filesystem']);
        $provider = new Filesystem($bucket);
        $provider->setBucket($bucket);
        $this->assertSame($bucket, $provider->getBucket());
        @rmdir($baseDir);
    }

    public function testSetFileGetFile(): void
    {
        $baseDir = sys_get_temp_dir() . '/pm_storage_abstract_' . uniqid();
        @mkdir($baseDir, 0755, true);
        $bucket = new Bucket(['bucket' => $baseDir, 'provider' => 'filesystem']);
        $file = new File($bucket);
        $file->name = 'test.txt';
        $provider = new Filesystem($bucket);
        $provider->setFile($file);
        $this->assertSame($file, $provider->getFile());
        @rmdir($baseDir);
    }

    public function testConstructorWithBucketAndFile(): void
    {
        $baseDir = sys_get_temp_dir() . '/pm_storage_abstract_' . uniqid();
        @mkdir($baseDir, 0755, true);
        $bucket = new Bucket(['bucket' => $baseDir, 'provider' => 'filesystem']);
        $file = new File($bucket);
        $file->name = 'c.txt';
        $provider = new Filesystem($bucket, $file);
        $this->assertSame($bucket, $provider->getBucket());
        $this->assertSame($file, $provider->getFile());
        @rmdir($baseDir);
    }
}
