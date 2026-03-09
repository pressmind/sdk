<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\CategoryTree;

class CategoryTreeTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new CategoryTree();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new CategoryTree();
        $this->assertNull($controller->read(0));
    }
}
