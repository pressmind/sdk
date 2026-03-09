<?php

namespace Pressmind\Tests\Unit\REST\Controller\Touristic\Insurance;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Touristic\Insurance\Group;

class GroupTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new Group();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new Group();
        $this->assertNull($controller->read(0));
    }
}
