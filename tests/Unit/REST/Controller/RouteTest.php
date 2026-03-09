<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Route;

class RouteTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new Route();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new Route();
        $this->assertNull($controller->read(0));
    }
}
