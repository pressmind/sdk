<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\MediaObjectType;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class MediaObjectTypeTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $ids = [10, 20];
        $import = new MediaObjectType($ids);
        $this->assertInstanceOf(MediaObjectType::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

}
