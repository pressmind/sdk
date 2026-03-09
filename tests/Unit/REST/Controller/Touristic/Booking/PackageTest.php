<?php

namespace Pressmind\Tests\Unit\REST\Controller\Touristic\Booking;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Touristic\Booking\Package;

class PackageTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new Package();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new Package();
        $this->assertNull($controller->read(0));
    }
}
