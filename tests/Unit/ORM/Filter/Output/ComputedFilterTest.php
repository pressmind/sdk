<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use Pressmind\ORM\Filter\Output\ComputedFilter;
use PHPUnit\Framework\TestCase;

class ComputedFilterTest extends TestCase
{
    private ComputedFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new ComputedFilter();
    }

    public function testFilterValueReturnsStringAsIs(): void
    {
        $this->assertSame('hello', $this->filter->filterValue('hello'));
    }

    public function testFilterValueReturnsIntAsIs(): void
    {
        $this->assertSame(42, $this->filter->filterValue(42));
    }

    public function testFilterValueReturnsNullAsIs(): void
    {
        $this->assertNull($this->filter->filterValue(null));
    }

    public function testFilterValueReturnsArrayAsIs(): void
    {
        $arr = ['a' => 1, 'b' => 2];
        $this->assertSame($arr, $this->filter->filterValue($arr));
    }

    public function testFilterValueReturnsFloatAsIs(): void
    {
        $this->assertSame(3.14, $this->filter->filterValue(3.14));
    }

    public function testFilterValueReturnsBoolAsIs(): void
    {
        $this->assertTrue($this->filter->filterValue(true));
        $this->assertFalse($this->filter->filterValue(false));
    }

    public function testGetErrorsReturnsEmptyArray(): void
    {
        $this->assertIsArray($this->filter->getErrors());
        $this->assertEmpty($this->filter->getErrors());
    }
}
