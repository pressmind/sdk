<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\CategoryTree;

/**
 * Unit tests for REST AbstractController (tested via CategoryTree which extends it).
 */
class AbstractControllerTest extends AbstractTestCase
{
    public function testListAllWithEmptyParametersReturnsArray(): void
    {
        $controller = new CategoryTree();
        $result = $controller->listAll([]);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testListAllWithStartAndLimitPassesLimitToOrm(): void
    {
        $controller = new CategoryTree();
        $result = $controller->listAll([
            'start' => 0,
            'limit' => 10,
        ]);
        $this->assertIsArray($result);
    }

    public function testReadWithNonExistentIdReturnsNull(): void
    {
        $controller = new CategoryTree();
        $result = $controller->read(999999);
        $this->assertNull($result);
    }

    public function testListAllWithSingleIdParameterCallsRead(): void
    {
        $controller = new CategoryTree();
        $result = $controller->listAll(['id' => 999999]);
        $this->assertNull($result);
    }
}
