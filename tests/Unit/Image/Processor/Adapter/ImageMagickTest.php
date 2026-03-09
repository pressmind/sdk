<?php

namespace Pressmind\Tests\Unit\Image\Processor\Adapter;

use Imagick;
use ImagickException;
use Pressmind\Image\Processor\Adapter\ImageMagick;
use Pressmind\Image\Processor\Config;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Tests\Fixtures\Images\ImageFixtureTrait;
use Pressmind\Tests\Unit\AbstractTestCase;

class ImageMagickTest extends AbstractTestCase
{
    use ImageFixtureTrait;

    /**
     * @requires extension imagick
     */
    public function testProcessCreatesDerivativeFile(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', ['max_width' => 100, 'max_height' => 100, 'crop' => false, 'preserve_aspect_ratio' => true]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $this->assertInstanceOf(File::class, $result);
        $this->assertNotEmpty($result->content);
        $this->assertSame('test-landscape_thumb.jpg', $result->name);
    }

    /**
     * @requires extension imagick
     */
    public function testProcessAppliesCropThumbnail(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', ['max_width' => 200, 'max_height' => 200, 'crop' => true]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $this->assertSame(200, $im->getImageWidth());
        $this->assertSame(200, $im->getImageHeight());
        $im->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testProcessAppliesThumbnailResize(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', ['max_width' => 320, 'max_height' => 240, 'crop' => false, 'preserve_aspect_ratio' => true]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();
        $im->destroy();
        $this->assertLessThanOrEqual(320, $w);
        $this->assertLessThanOrEqual(240, $h);
        $this->assertTrue($w === 320 || $h === 240);
    }

    /**
     * @requires extension imagick
     */
    public function testProcessCropVsThumbnailDifferentOutput(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $configCrop = Config::create('c', ['max_width' => 100, 'max_height' => 100, 'crop' => true]);
        $configThumb = Config::create('t', ['max_width' => 100, 'max_height' => 100, 'crop' => false, 'preserve_aspect_ratio' => true]);
        $adapter = new ImageMagick();
        $outCrop = $adapter->process($configCrop, $file, 'c');
        $file2 = $this->createTestImageFile($bucket);
        $outThumb = $adapter->process($configThumb, $file2, 't');
        $this->assertNotSame($outCrop->content, $outThumb->content);
    }

    /**
     * @requires extension imagick
     */
    public function testProcessOutputIsJpeg(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('thumb', ['max_width' => 50, 'max_height' => 50, 'crop' => true]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'thumb');
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $this->assertSame('JPEG', $im->getImageFormat());
        $im->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testProcessDerivativeNameInFilename(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('teaser', ['max_width' => 50, 'max_height' => 50, 'crop' => true]);
        $adapter = new ImageMagick();
        $result = $adapter->process($config, $file, 'teaser');
        $this->assertStringContainsString('_teaser.', $result->name);
        $this->assertStringEndsWith('.jpg', $result->name);
    }

    /**
     * @requires extension imagick
     */
    public function testProcessWithFilters(): void
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
        $this->assertInstanceOf(File::class, $result);
        $this->assertNotEmpty($result->content);
        $im = new Imagick();
        $im->readImageBlob($result->content);
        $this->assertSame(\Imagick::COLORSPACE_GRAY, $im->getImageColorspace(), 'FilterChain with GrayscaleFilter must produce grayscale output');
        $im->destroy();
    }

    /**
     * @requires extension imagick
     */
    public function testProcessThrowsOnInvalidFile(): void
    {
        $bucket = $this->createTempBucket();
        $config = Config::create('thumb', ['max_width' => 50, 'max_height' => 50, 'crop' => true]);
        $adapter = new ImageMagick();
        $this->expectException(ImagickException::class);
        $this->expectExceptionMessage('must be of type');
        $adapter->process($config, new \stdClass(), 'thumb');
    }

    /**
     * @requires extension imagick
     */
    public function testIsImageCorruptedReturnsFalseForValidImage(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $file->save();
        $adapter = new ImageMagick();
        $this->assertFalse($adapter->isImageCorrupted($file));
    }

    /**
     * @requires extension imagick
     */
    public function testIsImageCorruptedReturnsTrueForSingleColorImage(): void
    {
        $bucket = $this->createTempBucket();
        $im = new Imagick();
        $im->newImage(100, 100, new \ImagickPixel('red'));
        $im->setImageFormat('jpg');
        $blob = $im->getImageBlob();
        $im->destroy();
        $file = new File($bucket);
        $file->name = 'solid.jpg';
        $file->content = $blob;
        $file->save();
        $adapter = new ImageMagick();
        $this->assertTrue($adapter->isImageCorrupted($file));
    }

    /**
     * @requires extension imagick
     */
    public function testIsImageCorruptedThrowsOnInvalidFile(): void
    {
        $bucket = $this->createTempBucket();
        $file = new File($bucket);
        $file->name = 'nonexistent.jpg';
        $adapter = new ImageMagick();
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('File not found');
        $adapter->isImageCorrupted($file);
    }

    public function testIsImageCorruptedThrowsOnNonFileType(): void
    {
        $adapter = new ImageMagick();
        $this->expectException(ImagickException::class);
        $this->expectExceptionMessage('must be of type');
        $adapter->isImageCorrupted(new \stdClass());
    }
}
