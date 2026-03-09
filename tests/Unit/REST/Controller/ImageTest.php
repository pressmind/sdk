<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Image;

class ImageTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new Image();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new Image();
        $this->assertNull($controller->read(0));
    }
}
