<?php

namespace Pressmind\Tests\Unit\Image\Processor\Adapter;

use Pressmind\Image\Processor\Adapter\ImageMagickCLI;
use Pressmind\Image\Processor\Config;
use Pressmind\Storage\File;
use Pressmind\Tests\Fixtures\Images\ImageFixtureTrait;
use Pressmind\Tests\Unit\AbstractTestCase;

class ImageMagickCLITest extends AbstractTestCase
{
    use ImageFixtureTrait;

    public function testProcessGeneratesCorrectFilename(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', [
            'max_width' => 200,
            'max_height' => 200,
            'crop' => false,
        ]);
        $adapter = new ImageMagickCLI();
        $result = $adapter->process($config, $file, 'thumb');
        $this->assertInstanceOf(File::class, $result);
        $this->assertSame('test-landscape_thumb.jpg', $result->name);
    }

    public function testIsImageCorruptedReturnsFalse(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $adapter = new ImageMagickCLI();
        $this->assertFalse($adapter->isImageCorrupted($file));
    }
}
