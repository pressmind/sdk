<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\DataView;

class DataViewTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new DataView();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new DataView();
        $this->assertNull($controller->read(0));
    }
}
