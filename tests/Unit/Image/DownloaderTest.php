<?php

namespace Pressmind\Tests\Unit\Image;

use Pressmind\Image\Downloader;
use Pressmind\Tests\Unit\AbstractTestCase;

class DownloaderTest extends AbstractTestCase
{
    public function testConstructorSetsFileType(): void
    {
        $downloader = new Downloader('custom_type');
        $ref = new \ReflectionClass(Downloader::class);
        $prop = $ref->getProperty('_file_type');
        $prop->setAccessible(true);
        $this->assertSame('custom_type', $prop->getValue($downloader));
    }

    public function testConstructorDefaultFileType(): void
    {
        $downloader = new Downloader();
        $ref = new \ReflectionClass(Downloader::class);
        $prop = $ref->getProperty('_file_type');
        $prop->setAccessible(true);
        $this->assertSame('image', $prop->getValue($downloader));
    }
}
