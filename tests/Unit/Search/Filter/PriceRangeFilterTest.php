<?php

namespace Pressmind\Tests\Unit\Search\Filter;

use Pressmind\Search;
use Pressmind\Search\Filter\PriceRange;
use Pressmind\Tests\Unit\AbstractTestCase;

class PriceRangeFilterTest extends AbstractTestCase
{
    public function testCreateReturnsInstance(): void
    {
        $filter = PriceRange::create(null);
        $this->assertInstanceOf(PriceRange::class, $filter);
    }

    public function testCreateWithSearch(): void
    {
        $search = $this->createMock(Search::class);
        $filter = PriceRange::create($search);
        $this->assertInstanceOf(PriceRange::class, $filter);
        $this->assertSame($search, $filter->getSearch());
    }

    public function testConstructorStoresSearch(): void
    {
        $search = $this->createMock(Search::class);
        $filter = new PriceRange($search);
        $this->assertSame($search, $filter->getSearch());
    }

    public function testGetSearchReturnsNullWhenNotSet(): void
    {
        $filter = new PriceRange(null);
        $this->assertNull($filter->getSearch());
    }

    public function testSetSearchUpdatesSearch(): void
    {
        $search1 = $this->createMock(Search::class);
        $search2 = $this->createMock(Search::class);

        $filter = new PriceRange($search1);
        $this->assertSame($search1, $filter->getSearch());

        $filter->setSearch($search2);
        $this->assertSame($search2, $filter->getSearch());
    }

    public function testSetConfigDoesNotThrow(): void
    {
        $filter = new PriceRange(null);
        $filter->setConfig(new \stdClass());
        $this->assertTrue(true);
    }
}
