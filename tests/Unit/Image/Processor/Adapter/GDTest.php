<?php

namespace Pressmind\Tests\Unit\Image\Processor\Adapter;

use Pressmind\Image\Processor\Adapter\GD;
use Pressmind\Image\Processor\Config;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Tests\Fixtures\Images\ImageFixtureTrait;
use Pressmind\Tests\Unit\AbstractTestCase;

class GDTest extends AbstractTestCase
{
    use ImageFixtureTrait;

    /**
     * GD adapter expects $file->content to be a path (getimagesize/imagecreatefromjpeg take filename).
     */
    private function createFileWithPathContent(Bucket $bucket): File
    {
        $path = $this->getTestImagePath();
        $file = new File($bucket);
        $file->name = 'test-landscape.jpg';
        $file->content = $path;
        return $file;
    }

    /**
     * @requires extension gd
     */
    public function testProcessCreatesDerivativeFile(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createFileWithPathContent($bucket);
        $config = Config::create('thumb', ['max_width' => 100, 'max_height' => 100, 'preserve_aspect_ratio' => true]);
        $adapter = new GD();
        $result = $adapter->process($config, $file, 'thumb');
        $this->assertInstanceOf(File::class, $result);
        $this->assertNotEmpty($result->content);
    }

    /**
     * @requires extension gd
     */
    public function testProcessPreservesAspectRatio(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createFileWithPathContent($bucket);
        $config = Config::create('thumb', ['max_width' => 320, 'max_height' => 240, 'preserve_aspect_ratio' => true]);
        $adapter = new GD();
        $result = $adapter->process($config, $file, 'thumb');
        $this->assertInstanceOf(File::class, $result);
        $info = @getimagesize('data://image/jpeg;base64,' . base64_encode($result->content));
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(320, $info[0]);
        $this->assertLessThanOrEqual(240, $info[1]);
    }

    /**
     * @requires extension gd
     */
    public function testProcessWithoutAspectRatio(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createFileWithPathContent($bucket);
        $config = Config::create('thumb', ['max_width' => 100, 'max_height' => 100, 'preserve_aspect_ratio' => false]);
        $adapter = new GD();
        $result = $adapter->process($config, $file, 'thumb');
        $this->assertInstanceOf(File::class, $result);
        $info = @getimagesize('data://image/jpeg;base64,' . base64_encode($result->content));
        $this->assertSame(100, $info[0]);
        $this->assertSame(100, $info[1]);
    }

    /**
     * @requires extension gd
     */
    public function testProcessReturnsFileInstance(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createFileWithPathContent($bucket);
        $config = Config::create('thumb', ['max_width' => 50, 'max_height' => 50, 'preserve_aspect_ratio' => true]);
        $adapter = new GD();
        $result = $adapter->process($config, $file, 'thumb');
        $this->assertInstanceOf(File::class, $result);
        $this->assertSame('test-landscape_thumb.jpg', $result->name);
    }

    /**
     * @requires extension gd
     */
    public function testIsImageCorruptedReturnsFalse(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $adapter = new GD();
        $this->assertFalse($adapter->isImageCorrupted($file));
    }
}
