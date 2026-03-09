<?php

namespace Pressmind\Tests\Unit\Image\Processor\Adapter;

use PHPUnit\Framework\TestCase;
use Pressmind\Image\Processor\Adapter\Factory;
use Pressmind\Image\Processor\Adapter\GD;
use Pressmind\Image\Processor\Adapter\WebPicture;
use Pressmind\Image\Processor\AdapterInterface;

class FactoryTest extends TestCase
{
    public function testCreateGdAdapter(): void
    {
        $adapter = Factory::create('GD');
        $this->assertInstanceOf(AdapterInterface::class, $adapter);
        $this->assertInstanceOf(GD::class, $adapter);
    }

    public function testCreateWebPictureAdapter(): void
    {
        $adapter = Factory::create('WebPicture');
        $this->assertInstanceOf(AdapterInterface::class, $adapter);
        $this->assertInstanceOf(WebPicture::class, $adapter);
    }

    public function testCreateAppliesUcfirstToName(): void
    {
        $adapter = Factory::create('gD');
        $this->assertInstanceOf(GD::class, $adapter);
    }

    public function testCreateWithNonExistentAdapterThrowsError(): void
    {
        $this->expectException(\Error::class);
        Factory::create('NonExistentAdapter');
    }
}
