<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\IntegerFilter;
use PHPUnit\Framework\TestCase;

class IntegerFilterTest extends TestCase
{
    public function testFilterValueReturnsInt(): void
    {
        $filter = new IntegerFilter();
        $this->assertSame(42, $filter->filterValue(42));
        $this->assertSame(42, $filter->filterValue('42'));
    }

    public function testFilterValueEmptyReturnsNull(): void
    {
        $filter = new IntegerFilter();
        $this->assertNull($filter->filterValue(''));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new IntegerFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
