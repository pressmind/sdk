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

    public function testIsImageCorruptedReturnsFalse(): void
    {
        $bucket = $this->createTempBucket();
        $file = $this->createTestImageFile($bucket);
        $adapter = new WebPicture();
        $this->assertFalse($adapter->isImageCorrupted($file));
    }
}
