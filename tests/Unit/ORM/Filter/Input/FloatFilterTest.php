<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\FloatFilter;
use PHPUnit\Framework\TestCase;

class FloatFilterTest extends TestCase
{
    public function testFilterValueReturnsFloat(): void
    {
        $filter = new FloatFilter();
        $this->assertSame(3.14, $filter->filterValue(3.14));
        $this->assertSame(3.14, $filter->filterValue('3.14'));
    }

    public function testFilterValueEmptyOrNullReturnsNull(): void
    {
        $filter = new FloatFilter();
        $this->assertNull($filter->filterValue(''));
        $this->assertNull($filter->filterValue(null));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new FloatFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
