<?php

namespace Pressmind\Tests\Unit\REST\Controller\Touristic;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Touristic\Startingpoint;

class StartingpointTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new Startingpoint();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new Startingpoint();
        $this->assertNull($controller->read(0));
    }
}
