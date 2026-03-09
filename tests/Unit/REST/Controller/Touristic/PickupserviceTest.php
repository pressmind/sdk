<?php

namespace Pressmind\Tests\Unit\REST\Controller\Touristic;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Touristic\Pickupservice;

class PickupserviceTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new Pickupservice();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new Pickupservice();
        $this->assertNull($controller->read(0));
    }
}
