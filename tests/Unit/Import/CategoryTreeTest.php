<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\CategoryTree;
use Pressmind\Tests\Unit\AbstractTestCase;

class CategoryTreeTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $ids = [1, 2, 3];
        $import = new CategoryTree($ids);
        $this->assertInstanceOf(CategoryTree::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }
}
