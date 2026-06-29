<?php

namespace Pressmind\Tests\Unit\Image\Processor\Adapter;

use Pressmind\Image\Processor\Adapter\WebPicture;
use Pressmind\Image\Processor\Config;
use Pressmind\Storage\File;
use Pressmind\Tests\Fixtures\Images\ImageFixtureTrait;
use Pressmind\Tests\Unit\AbstractTestCase;

class WebPictureTest extends AbstractTestCase
{
    use ImageFixtureTrait;

    private int $obLevelBefore;

    protected function setUp(): void
    {
        $this->obLevelBefore = ob_get_level();
        parent::setUp();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->obLevelBefore) {
            ob_end_clean();
        }
        parent::tearDown();
    }

    /**
     * @requires extension gd
     */
    public function testProcessWithWebpCreateTrueReturnsFile(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('webp', ['webp_create' => true, 'webp_quality' => 80]);
        $adapter = new WebPicture();
        $result = $adapter->process($config, $file, 'webp');
        $this->assertInstanceOf(File::class, $result);
        $this->assertNotEmpty($result->content);
    }

    public function testProcessWithWebpCreateFalseReturnsNull(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('webp', ['webp_create' => false]);
        $adapter = new WebPicture();
        $this->assertNull($adapter->process($config, $file, 'webp'));
    }

    /**
     * @requires extension gd
     */
    public function testProcessOutputFilenameHasWebpExtension(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $config = Config::create('webp', ['webp_create' => true, 'webp_quality' => 80]);
        $adapter = new WebPicture();
        $result = $adapter->process($config, $file, 'webp');
        $this->assertSame('test-landscape.webp', $result->name);
    }

    /**
     * @requires extension gd
     */
    public function testProcessConvertsPalettePngWithAlphaToWebp(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTransparentPngFile($bucket, 'transparent-logo_thumb.png', true);
        $config = Config::create('webp', ['webp_create' => true, 'webp_quality' => 80]);
        $adapter = new WebPicture();
        $result = $adapter->process($config, $file, 'webp');

        $this->assertSame('transparent-logo_thumb.webp', $result->name);
        $this->assertNotEmpty($result->content);

        $img = imagecreatefromstring($result->content);
        $this->assertNotFalse($img);
        $corner = imagecolorsforindex($img, imagecolorat($img, 0, 0));
        $center = imagecolorsforindex($img, imagecolorat($img, 40, 20));
        imagedestroy($img);

        $this->assertSame(127, $corner['alpha']);
        $this->assertSame(0, $center['alpha']);
    }

    /**
     * @requires extension gd
     */
    public function testProcessThrowsClearExceptionForInvalidImageContent(): void
    {
        $bucket = $this->createTempBucket();
        $file = new File($bucket);
        $file->name = 'broken.jpg';
        $file->content = 'not an image';
        $config = Config::create('webp', ['webp_create' => true, 'webp_quality' => 80]);
        $adapter = new WebPicture();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Could not create WebP source image');

        $adapter->process($config, $file, 'webp');
    }

    /**
     * @requires extension gd
     */
    public function testProcessRegeneratesEmptyExistingWebpFile(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $existingWebp = new File($bucket);
        $existingWebp->name = 'test-landscape.webp';
        $existingWebp->content = '';
        $existingWebp->save();
        $config = Config::create('webp', ['webp_create' => true, 'webp_quality' => 80]);
        $adapter = new WebPicture();

        $result = $adapter->process($config, $file, 'webp');
        $stored = new File($bucket);
        $stored->name = 'test-landscape.webp';
        $stored->read();

        $this->assertNotEmpty($result->content);
        $this->assertNotEmpty($stored->content);
        $this->assertNotFalse(imagecreatefromstring($stored->content));
    }

    /**
     * @requires extension gd
     */
    public function testProcessRegeneratesCorruptExistingWebpFile(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $existingWebp = new File($bucket);
        $existingWebp->name = 'test-landscape.webp';
        $existingWebp->content = 'broken webp';
        $existingWebp->save();
        $config = Config::create('webp', ['webp_create' => true, 'webp_quality' => 80]);
        $adapter = new WebPicture();

        $adapter->process($config, $file, 'webp');
        $stored = new File($bucket);
        $stored->name = 'test-landscape.webp';
        $stored->read();

        $this->assertNotSame('broken webp', $stored->content);
        $this->assertNotFalse(imagecreatefromstring($stored->content));
    }

    public function testIsImageCorruptedReturnsFalse(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $adapter = new WebPicture();
        $this->assertFalse($adapter->isImageCorrupted($file));
    }
}
