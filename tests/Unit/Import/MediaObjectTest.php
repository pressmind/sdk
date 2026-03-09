<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\MediaObject;
use Pressmind\Tests\Unit\AbstractTestCase;

class MediaObjectTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new MediaObject();
        $this->assertInstanceOf(MediaObject::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

}
