<?php

namespace Pressmind\Tests\Unit\ValueObject\MediaObject\Result;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\ValueObject\MediaObject\Result\GetPrettyUrls;

class GetPrettyUrlsTest extends AbstractTestCase
{
    public function testPropertiesCanBeSetAndRead(): void
    {
        $vo = new GetPrettyUrls();
        $vo->id = 1;
        $vo->id_media_object = 100;
        $vo->id_object_type = 200;
        $vo->route = 'detail';
        $vo->language = 'de';
        $vo->is_default = true;

        $this->assertSame(1, $vo->id);
        $this->assertSame(100, $vo->id_media_object);
        $this->assertSame(200, $vo->id_object_type);
        $this->assertSame('detail', $vo->route);
        $this->assertSame('de', $vo->language);
        $this->assertTrue($vo->is_default);
    }
}
