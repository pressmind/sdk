<?php

namespace Pressmind\Tests\Integration\Image;

use Pressmind\Image\Processor\Adapter\GD;
use Pressmind\Image\Processor\Adapter\WebPicture;
use Pressmind\Image\Processor\Config;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Tests\Fixtures\Images\ImageFixtureTrait;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Integration tests for GD adapter with real fixture image.
 *
 * @requires extension gd
 */
class GDProcessingTest extends AbstractTestCase
{
    use ImageFixtureTrait;

    /**
     * GD adapter expects $file->content to be the path to the image file.
     */
    private function createFileWithPathContent(Bucket $bucket): File
    {
        $path = $this->getTestImagePath();
        $file = new File($bucket);
        $file->name = 'test-landscape.jpg';
        $file->content = $path;
        return $file;
    }

    public function testProcessCreatesDerivativeWithCorrectDimensions(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createFileWithPathContent($bucket);
        $config = Config::create('thumb', [
            'max_width' => 200,
            'max_height' => 150,
            'preserve_aspect_ratio' => true,
        ]);
        $adapter = new GD();
        $result = $adapter->process($config, $file, 'thumb');
        $this->assertInstanceOf(File::class, $result);
        $this->assertNotEmpty($result->content);
        $info = @getimagesize('data://image/jpeg;base64,' . base64_encode($result->content));
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(200, $info[0]);
        $this->assertLessThanOrEqual(150, $info[1]);
    }

    public function testProcessPreservesAspectRatio(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createFileWithPathContent($bucket);
        $config = Config::create('thumb', [
            'max_width' => 320,
            'max_height' => 240,
            'preserve_aspect_ratio' => true,
        ]);
        $adapter = new GD();
        $result = $adapter->process($config, $file, 'thumb');
        $info = @getimagesize('data://image/jpeg;base64,' . base64_encode($result->content));
        $this->assertNotFalse($info);
        $this->assertLessThanOrEqual(320, $info[0]);
        $this->assertLessThanOrEqual(240, $info[1]);
    }

    public function testWebPictureCreatesWebpOutput(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('webp', ['webp_create' => true, 'webp_quality' => 80]);
        $adapter = new WebPicture();
        $result = $adapter->process($config, $file, 'webp');
        $this->assertNotNull($result);
        $this->assertNotEmpty($result->content);
        $this->assertStringEndsWith('.webp', $result->name);
    }
}
