<?php

namespace Pressmind\Tests\Unit\Search\Filter;

use Pressmind\Search;
use Pressmind\Search\Filter\Duration;
use Pressmind\Tests\Unit\AbstractTestCase;

class DurationFilterTest extends AbstractTestCase
{
    public function testCreateReturnsInstance(): void
    {
        $filter = Duration::create(null);
        $this->assertInstanceOf(Duration::class, $filter);
    }

    public function testCreateWithSearch(): void
    {
        $search = $this->createMock(Search::class);
        $filter = Duration::create($search);
        $this->assertInstanceOf(Duration::class, $filter);
        $this->assertSame($search, $filter->getSearch());
    }

    public function testConstructorStoresSearch(): void
    {
        $search = $this->createMock(Search::class);
        $filter = new Duration($search);
        $this->assertSame($search, $filter->getSearch());
    }

    public function testGetSearchReturnsNullWhenNotSet(): void
    {
        $filter = new Duration(null);
        $this->assertNull($filter->getSearch());
    }

    public function testSetSearchUpdatesSearch(): void
    {
        $search1 = $this->createMock(Search::class);
        $search2 = $this->createMock(Search::class);

        $filter = new Duration($search1);
        $this->assertSame($search1, $filter->getSearch());

        $filter->setSearch($search2);
        $this->assertSame($search2, $filter->getSearch());
    }

    public function testSetConfigDoesNotThrow(): void
    {
        $filter = new Duration(null);
        $filter->setConfig(new \stdClass());
        $this->assertTrue(true);
    }
}
