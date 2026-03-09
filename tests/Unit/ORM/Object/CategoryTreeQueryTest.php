<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\CategoryTree;
use Pressmind\Tests\Unit\AbstractTestCase;

class CategoryTreeQueryTest extends AbstractTestCase
{
    public function testItemsToTaxonomyReturnsEmptyArrayWhenItemsEmpty(): void
    {
        $tree = new CategoryTree();
        $tree->items = [];
        $result = $tree->itemsToTaxonomy();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testItemsToTaxonomyConvertsItemsToStdClassArray(): void
    {
        $mockItem1 = $this->createMockItem(['id' => 1, 'name' => 'Category A']);
        $mockItem2 = $this->createMockItem(['id' => 2, 'name' => 'Category B']);

        $tree = new CategoryTree();
        $tree->items = [$mockItem1, $mockItem2];
        $result = $tree->itemsToTaxonomy();

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]->id);
        $this->assertSame('Category A', $result[0]->name);
        $this->assertSame(2, $result[1]->id);
        $this->assertSame('Category B', $result[1]->name);
    }

    private function createMockItem(array $data): object
    {
        $item = new class {
            public $id;
            public $name;

            public function toStdClass(): \stdClass
            {
                $obj = new \stdClass();
                $obj->id = $this->id;
                $obj->name = $this->name;
                return $obj;
            }
        };
        $item->id = $data['id'];
        $item->name = $data['name'];
        return $item;
    }
}
