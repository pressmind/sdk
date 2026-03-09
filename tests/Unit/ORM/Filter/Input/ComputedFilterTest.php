<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\ComputedFilter;
use PHPUnit\Framework\TestCase;

class ComputedFilterTest extends TestCase
{
    public function testFilterValueReturnsValueAsIs(): void
    {
        $filter = new ComputedFilter();
        $this->assertSame('any', $filter->filterValue('any'));
        $this->assertSame(42, $filter->filterValue(42));
        $this->assertNull($filter->filterValue(null));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new ComputedFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
