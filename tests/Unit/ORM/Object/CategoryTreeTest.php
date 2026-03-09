<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\CategoryTree;
use Pressmind\ORM\Object\CategoryTree\Item;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for CategoryTree ORM: instantiation, fromArray, toStdClass, itemsToTaxonomy.
 */
class CategoryTreeTest extends AbstractTestCase
{
    public function testInstantiationWithoutId(): void
    {
        $tree = new CategoryTree(null, false);
        $this->assertNull($tree->getId());
    }

    public function testGetDbTableName(): void
    {
        $tree = new CategoryTree(null, false);
        $this->assertSame('pmt2core_pmt2core_category_trees', $tree->getDbTableName());
    }

    public function testSetAndGetId(): void
    {
        $tree = new CategoryTree(null, false);
        $tree->setId(5);
        $this->assertSame(5, $tree->getId());
    }

    public function testFromArrayPopulatesProperties(): void
    {
        $tree = new CategoryTree(null, false);
        $tree->fromArray([
            'id' => 1,
            'name' => 'Categories',
        ]);
        $this->assertSame(1, $tree->id);
        $this->assertSame('Categories', $tree->name);
    }

    public function testToStdClassWithoutRelations(): void
    {
        $tree = new CategoryTree(null, false);
        $tree->id = 2;
        $tree->name = 'Tree Name';
        $std = $tree->toStdClass(false);
        $this->assertInstanceOf(\stdClass::class, $std);
        $this->assertSame(2, $std->id);
        $this->assertSame('Tree Name', $std->name);
    }

    public function testItemsToTaxonomyWithEmptyItems(): void
    {
        $tree = new CategoryTree(null, false);
        $tree->items = [];
        $taxonomy = $tree->itemsToTaxonomy();
        $this->assertIsArray($taxonomy);
        $this->assertCount(0, $taxonomy);
    }

    public function testItemsToTaxonomyWithMockItems(): void
    {
        $tree = new CategoryTree(null, false);
        $item = new Item(null, false);
        $item->id = 10;
        $item->name = 'Item1';
        $tree->items = [$item];
        $taxonomy = $tree->itemsToTaxonomy();
        $this->assertIsArray($taxonomy);
        $this->assertCount(1, $taxonomy);
        $this->assertInstanceOf(\stdClass::class, $taxonomy[0]);
        $this->assertSame(10, $taxonomy[0]->id);
        $this->assertSame('Item1', $taxonomy[0]->name);
    }
}
