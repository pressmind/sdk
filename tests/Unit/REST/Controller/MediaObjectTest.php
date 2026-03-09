<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\MediaObject;

class MediaObjectTest extends AbstractTestCase
{
    public function testGetByRouteWithEmptyParamsThrows(): void
    {
        $controller = new MediaObject();
        $this->expectException(\Throwable::class);
        $controller->getByRoute([]);
    }

    public function testGetByCodeWithEmptyParamsThrows(): void
    {
        $controller = new MediaObject();
        $this->expectException(\Throwable::class);
        $controller->getByCode([]);
    }
}
