<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use Pressmind\ORM\Filter\Output\StringFilter;
use PHPUnit\Framework\TestCase;

class StringFilterTest extends TestCase
{
    public function testFilterValueReturnsValue(): void
    {
        $filter = new StringFilter();
        $this->assertSame('out', $filter->filterValue('out'));
    }

    public function testFilterValueEmptyOrNullStringReturnsNull(): void
    {
        $filter = new StringFilter();
        $this->assertNull($filter->filterValue(''));
        $this->assertNull($filter->filterValue('null'));
    }

    public function testFilterValueObjectReturnsErrorString(): void
    {
        $filter = new StringFilter();
        $this->assertSame('Error: Object to string conversion', $filter->filterValue(new \stdClass()));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new StringFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
