<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\StringFilter;
use PHPUnit\Framework\TestCase;

class StringFilterTest extends TestCase
{
    public function testFilterValueReturnsValue(): void
    {
        $filter = new StringFilter();
        $this->assertSame('text', $filter->filterValue('text'));
    }

    public function testFilterValueEmptyReturnsNull(): void
    {
        $filter = new StringFilter();
        $this->assertNull($filter->filterValue(''));
    }

    public function testFilterValueNullStringReturnsNull(): void
    {
        $filter = new StringFilter();
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
        $this->assertEmpty($filter->getErrors());
    }
}
