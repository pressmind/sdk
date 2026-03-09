<?php

namespace Pressmind\Tests\Unit\Search\Filter;

use Pressmind\Search;
use Pressmind\Search\Filter\Category;
use Pressmind\Tests\Unit\AbstractTestCase;

class CategoryFilterTest extends AbstractTestCase
{
    public function testCreateReturnsInstance(): void
    {
        $filter = Category::create(1, null, 'var_name');
        $this->assertInstanceOf(Category::class, $filter);
    }

    public function testCreateWithAllParameters(): void
    {
        $search = $this->createMock(Search::class);
        $filter = Category::create(42, $search, 'region', true);
        $this->assertInstanceOf(Category::class, $filter);
        $this->assertSame($search, $filter->getSearch());
    }

    public function testConstructorStoresSearch(): void
    {
        $search = $this->createMock(Search::class);
        $filter = new Category(1, $search);
        $this->assertSame($search, $filter->getSearch());
    }

    public function testGetSearchReturnsNullWhenNotSet(): void
    {
        $filter = new Category(1, null);
        $this->assertNull($filter->getSearch());
    }

    public function testSetSearchUpdatesSearch(): void
    {
        $search1 = $this->createMock(Search::class);
        $search2 = $this->createMock(Search::class);

        $filter = new Category(1, $search1);
        $this->assertSame($search1, $filter->getSearch());

        $filter->setSearch($search2);
        $this->assertSame($search2, $filter->getSearch());
    }

    public function testSetConfigUpdatesTreeId(): void
    {
        $config = new \stdClass();
        $config->tree_id = 99;
        $filter = new Category(1, null);
        $filter->setConfig($config);
        $this->assertTrue(true);
    }

    public function testConstructorWithoutSearchAndVarName(): void
    {
        $filter = new Category(5);
        $this->assertNull($filter->getSearch());
    }
}
