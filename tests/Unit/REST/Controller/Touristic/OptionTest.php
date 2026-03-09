<?php

namespace Pressmind\Tests\Unit\REST\Controller\Touristic;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Touristic\Option;

class OptionTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new Option();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new Option();
        $this->assertNull($controller->read(0));
    }
}
