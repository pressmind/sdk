<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use Pressmind\ORM\Filter\Output\BooleanFilter;
use PHPUnit\Framework\TestCase;

class BooleanFilterTest extends TestCase
{
    public function testFilterValueReturnsInt(): void
    {
        $filter = new BooleanFilter();
        $this->assertSame(1, $filter->filterValue(true));
        $this->assertSame(0, $filter->filterValue(false));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new BooleanFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
