<?php

namespace Pressmind\Tests\Unit\ValueObject\MediaObject\Result;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\ValueObject\MediaObject\Result\GetByPrettyUrl;

class GetByPrettyUrlTest extends AbstractTestCase
{
    public function testPropertiesCanBeSetAndRead(): void
    {
        $vo = new GetByPrettyUrl();
        $vo->id = 42;
        $vo->id_object_type = 100;
        $vo->visibility = 30;
        $vo->language = 'de';

        $this->assertSame(42, $vo->id);
        $this->assertSame(100, $vo->id_object_type);
        $this->assertSame(30, $vo->visibility);
        $this->assertSame('de', $vo->language);
    }
}
