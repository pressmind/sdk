<?php

namespace Pressmind\Tests\Unit\File;

use PHPUnit\Framework\TestCase;
use Pressmind\File\Downloader;

/**
 * Unit tests for Pressmind\File\Downloader.
 * Functional download tests are skipped since they require curl and external URLs.
 */
class DownloaderTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $downloader = new Downloader();
        $this->assertInstanceOf(Downloader::class, $downloader);
    }
}
