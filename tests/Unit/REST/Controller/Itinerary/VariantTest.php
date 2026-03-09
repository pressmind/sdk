<?php

namespace Pressmind\Tests\Unit\REST\Controller\Itinerary;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Itinerary\Variant;

class VariantTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new Variant();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new Variant();
        $this->assertNull($controller->read(0));
    }
}
