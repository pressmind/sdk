<?php

namespace Pressmind\Tests\Unit\REST\Controller\Touristic;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Touristic\Date;

class DateTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new Date();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new Date();
        $this->assertNull($controller->read(0));
    }
}
