<?php

namespace Pressmind\Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Query\Filter;
use Pressmind\Search\SearchType;

class QueryFilterTest extends TestCase
{
    public function testFilterDefaultProperties(): void
    {
        $filter = new Filter();
        $this->assertSame(2, $filter->occupancy);
        $this->assertSame(12, $filter->page_size);
        $this->assertFalse($filter->getFilters);
        $this->assertFalse($filter->returnFiltersOnly);
        $this->assertSame([30], $filter->allowed_visibilities);
        $this->assertSame(SearchType::DEFAULT, $filter->search_type);
        $this->assertFalse($filter->skip_search_hooks);
        $this->assertIsArray($filter->custom_conditions);
        $this->assertEmpty($filter->custom_conditions);
    }

    public function testFilterRequestAndOptionalProperties(): void
    {
        $filter = new Filter();
        $filter->request = ['pm-ot' => '100'];
        $filter->page_size = 25;
        $filter->getFilters = true;
        $this->assertSame(['pm-ot' => '100'], $filter->request);
        $this->assertSame(25, $filter->page_size);
        $this->assertTrue($filter->getFilters);
    }
}
