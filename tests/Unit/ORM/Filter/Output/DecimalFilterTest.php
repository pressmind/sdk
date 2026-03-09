<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use Pressmind\ORM\Filter\Output\DecimalFilter;
use PHPUnit\Framework\TestCase;

class DecimalFilterTest extends TestCase
{
    private DecimalFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new DecimalFilter();
    }

    public function testFilterValueConvertsIntToFloat(): void
    {
        $this->assertSame(42.0, $this->filter->filterValue(42));
    }

    public function testFilterValueConvertsStringToFloat(): void
    {
        $this->assertSame(3.14, $this->filter->filterValue('3.14'));
    }

    public function testFilterValueConvertsNullToZero(): void
    {
        $this->assertSame(0.0, $this->filter->filterValue(null));
    }

    public function testFilterValueConvertsEmptyStringToZero(): void
    {
        $this->assertSame(0.0, $this->filter->filterValue(''));
    }

    public function testFilterValueConvertsNonNumericStringToZero(): void
    {
        $this->assertSame(0.0, $this->filter->filterValue('abc'));
    }

    public function testFilterValueConvertsNegativeValue(): void
    {
        $this->assertSame(-99.5, $this->filter->filterValue('-99.5'));
    }

    public function testGetErrorsReturnsEmptyArray(): void
    {
        $this->assertIsArray($this->filter->getErrors());
        $this->assertEmpty($this->filter->getErrors());
    }
}
