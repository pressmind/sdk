<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use Pressmind\ORM\Filter\Output\IntegerFilter;
use PHPUnit\Framework\TestCase;

class IntegerFilterTest extends TestCase
{
    public function testFilterValueReturnsInt(): void
    {
        $filter = new IntegerFilter();
        $this->assertSame(42, $filter->filterValue(42));
        $this->assertSame(42, $filter->filterValue('42'));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new IntegerFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
