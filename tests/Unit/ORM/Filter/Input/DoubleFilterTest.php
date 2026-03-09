<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\DoubleFilter;
use PHPUnit\Framework\TestCase;

class DoubleFilterTest extends TestCase
{
    public function testFilterValueReturnsDouble(): void
    {
        $filter = new DoubleFilter();
        $this->assertSame(2.5, $filter->filterValue(2.5));
        $this->assertSame(2.5, $filter->filterValue('2.5'));
    }

    public function testFilterValueEmptyOrNullReturnsNull(): void
    {
        $filter = new DoubleFilter();
        $this->assertNull($filter->filterValue(''));
        $this->assertNull($filter->filterValue(null));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new DoubleFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
