<?php

namespace Pressmind\Tests\Unit\Search\Filter;

use Pressmind\Search;
use Pressmind\Search\Filter\DepartureDate;
use Pressmind\Tests\Unit\AbstractTestCase;

class DepartureDateFilterTest extends AbstractTestCase
{
    public function testCreateReturnsInstance(): void
    {
        $filter = DepartureDate::create(null);
        $this->assertInstanceOf(DepartureDate::class, $filter);
    }

    public function testCreateWithSearch(): void
    {
        $search = $this->createMock(Search::class);
        $filter = DepartureDate::create($search);
        $this->assertInstanceOf(DepartureDate::class, $filter);
        $this->assertSame($search, $filter->getSearch());
    }

    public function testConstructorStoresSearch(): void
    {
        $search = $this->createMock(Search::class);
        $filter = new DepartureDate($search);
        $this->assertSame($search, $filter->getSearch());
    }

    public function testGetSearchReturnsNullWhenNotSet(): void
    {
        $filter = new DepartureDate(null);
        $this->assertNull($filter->getSearch());
    }

    public function testSetSearchUpdatesSearch(): void
    {
        $search1 = $this->createMock(Search::class);
        $search2 = $this->createMock(Search::class);

        $filter = new DepartureDate($search1);
        $this->assertSame($search1, $filter->getSearch());

        $filter->setSearch($search2);
        $this->assertSame($search2, $filter->getSearch());
    }

    public function testSetConfigDoesNotThrow(): void
    {
        $filter = new DepartureDate(null);
        $filter->setConfig(new \stdClass());
        $this->assertTrue(true);
    }
}
