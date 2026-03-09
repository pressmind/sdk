<?php

namespace Pressmind\Tests\Integration\Image;

use Imagick;
use Pressmind\Image\Processor\Adapter\ImageMagick;
use Pressmind\Image\Processor\Adapter\WebPicture;
use Pressmind\Image\Processor\Config;
use Pressmind\Tests\Fixtures\Images\ImageFixtureTrait;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Full pipeline integration tests with real fixture image and ImageMagick adapter.
 *
 * @requires extension imagick
 */
class ImageMagickProcessingTest extends AbstractTestCase
{
    use ImageFixtureTrait;

    public function testProcessCreatesJpegDerivativeWithCorrectDimensions(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', [
            'max_width' => 200,
            'max_height' => 150,
            'crop' => false,
            'preserve_aspect_ratio' => true,
        ]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $this->assertSame(200, $im->getImageWidth(), '640x480 scaled to max 200x150 preserves ratio -> 200x150');
        $this->assertSame(150, $im->getImageHeight());
        $this->assertSame('JPEG', $im->getImageFormat());
        $this->assertNotEmpty($result->content, 'derivative must have binary content');
        $im->destroy();
    }

    public function testProcessPreservesAspectRatio(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', [
            'max_width' => 320,
            'max_height' => 240,
            'crop' => false,
            'preserve_aspect_ratio' => true,
        ]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();
        $im->destroy();
        $this->assertTrue($w === 320 || $h === 240);
        $this->assertLessThanOrEqual(320, $w);
        $this->assertLessThanOrEqual(240, $h);
    }

    public function testProcessWithCropProducesExactDimensions(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', [
            'max_width' => 200,
            'max_height' => 200,
            'crop' => true,
        ]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $this->assertSame(200, $im->getImageWidth());
        $this->assertSame(200, $im->getImageHeight());
        $im->destroy();
    }

    public function testProcessWithFilterChain(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', [
            'max_width' => 100,
            'max_height' => 100,
            'crop' => true,
            'filters' => [['class' => \Pressmind\Image\Filter\GrayscaleFilter::class, 'params' => []]],
        ]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $this->assertSame(\Imagick::COLORSPACE_GRAY, $im->getImageColorspace());
        $im->destroy();
    }

    public function testIsImageCorruptedOnValidJpeg(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $file->save();
        $adapter = new ImageMagick();
        $this->assertFalse($adapter->isImageCorrupted($file));
    }

    public function testWebPictureCreatesWebpFromDerivative(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', ['max_width' => 100, 'max_height' => 100, 'crop' => true]);
        $imAdapter = new ImageMagick();
        $derivative = $imAdapter->process($config, $file, 'thumb');
        $derivative->save();

        $webpConfig = Config::create('webp', ['webp_create' => true, 'webp_quality' => 80]);
        $webpAdapter = new WebPicture();
        $webpFile = $webpAdapter->process($webpConfig, $derivative, 'webp');
        $this->assertNotNull($webpFile);
        $this->assertNotEmpty($webpFile->content);
        $this->assertStringEndsWith('.webp', $webpFile->name);
    }
}
