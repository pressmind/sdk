<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\DecimalFilter;
use PHPUnit\Framework\TestCase;

class DecimalFilterTest extends TestCase
{
    public function testFilterValueReturnsFloat(): void
    {
        $filter = new DecimalFilter();
        $this->assertSame(99.5, $filter->filterValue(99.5));
        $this->assertSame(99.5, $filter->filterValue('99.5'));
    }

    public function testFilterValueEmptyOrNullReturnsNull(): void
    {
        $filter = new DecimalFilter();
        $this->assertNull($filter->filterValue(''));
        $this->assertNull($filter->filterValue(null));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new DecimalFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
