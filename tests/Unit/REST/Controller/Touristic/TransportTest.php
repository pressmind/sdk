<?php

namespace Pressmind\Tests\Unit\REST\Controller\Touristic;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Touristic\Transport;

class TransportTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new Transport();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new Transport();
        $this->assertNull($controller->read(0));
    }
}
