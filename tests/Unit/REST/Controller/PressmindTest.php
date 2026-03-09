<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Pressmind;

class PressmindTest extends AbstractTestCase
{
    public function testMediaObjectWithoutIdReturnsArray(): void
    {
        $controller = new Pressmind([]);
        $result = $controller->mediaObject();
        $this->assertIsArray($result);
    }

    public function testCategoryTreeWithoutIdReturnsArray(): void
    {
        $controller = new Pressmind([]);
        $result = $controller->categoryTree();
        $this->assertIsArray($result);
    }
}
