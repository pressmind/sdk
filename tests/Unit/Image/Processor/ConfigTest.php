<?php

namespace Pressmind\Tests\Unit\Image\Processor;

use PHPUnit\Framework\TestCase;
use Pressmind\Image\Processor\Config;

class ConfigTest extends TestCase
{
    public function testConstructorCreatesEmptyInstance(): void
    {
        $config = new Config();
        $this->assertNull($config->name);
        $this->assertNull($config->max_width);
        $this->assertNull($config->max_height);
        $this->assertNull($config->preserve_aspect_ratio);
        $this->assertNull($config->crop);
        $this->assertNull($config->horizontal_crop);
        $this->assertNull($config->vertical_crop);
        $this->assertNull($config->webp_quality);
        $this->assertNull($config->webp_create);
        $this->assertNull($config->filters);
    }

    public function testCreateWithAllValues(): void
    {
        $filters = [['type' => 'sharpen', 'amount' => 50]];
        $config = Config::create('thumbnail', [
            'max_width' => 200,
            'max_height' => 150,
            'preserve_aspect_ratio' => true,
            'crop' => true,
            'horizontal_crop' => 'center',
            'vertical_crop' => 'top',
            'webp_quality' => 80,
            'webp_create' => true,
            'filters' => $filters,
        ]);

        $this->assertSame('thumbnail', $config->name);
        $this->assertSame(200, $config->max_width);
        $this->assertSame(150, $config->max_height);
        $this->assertTrue($config->preserve_aspect_ratio);
        $this->assertTrue($config->crop);
        $this->assertSame('center', $config->horizontal_crop);
        $this->assertSame('top', $config->vertical_crop);
        $this->assertSame(80, $config->webp_quality);
        $this->assertTrue($config->webp_create);
        $this->assertSame($filters, $config->filters);
    }

    public function testCreateWithEmptyArrayUsesDefaults(): void
    {
        $config = Config::create('empty', []);
        $this->assertSame('empty', $config->name);
        $this->assertNull($config->max_width);
        $this->assertNull($config->max_height);
        $this->assertNull($config->preserve_aspect_ratio);
        $this->assertNull($config->crop);
        $this->assertNull($config->horizontal_crop);
        $this->assertNull($config->vertical_crop);
        $this->assertNull($config->webp_quality);
        $this->assertFalse($config->webp_create);
        $this->assertNull($config->filters);
    }

    public function testCreateWithPartialValues(): void
    {
        $config = Config::create('partial', [
            'max_width' => 800,
            'webp_create' => true,
        ]);
        $this->assertSame('partial', $config->name);
        $this->assertSame(800, $config->max_width);
        $this->assertNull($config->max_height);
        $this->assertTrue($config->webp_create);
        $this->assertNull($config->filters);
    }

    public function testCreateReturnsConfigInstance(): void
    {
        $config = Config::create('test', []);
        $this->assertInstanceOf(Config::class, $config);
    }

    public function testWebpCreateDefaultsToFalseNotNull(): void
    {
        $config = Config::create('no-webp', []);
        $this->assertFalse($config->webp_create);
    }
}
