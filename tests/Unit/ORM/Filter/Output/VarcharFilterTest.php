<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use Pressmind\ORM\Filter\Output\VarcharFilter;
use PHPUnit\Framework\TestCase;

class VarcharFilterTest extends TestCase
{
    private VarcharFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new VarcharFilter();
    }

    public function testFilterValueReturnsString(): void
    {
        $this->assertSame('hello', $this->filter->filterValue('hello'));
    }

    public function testFilterValueEmptyStringReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue(''));
    }

    public function testFilterValueNullReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue(null));
    }

    public function testFilterValueNullStringReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue('null'));
    }

    public function testFilterValueObjectReturnsErrorString(): void
    {
        $this->assertSame('Error: Object to string conversion', $this->filter->filterValue(new \stdClass()));
    }

    public function testFilterValueReturnsNumericString(): void
    {
        $this->assertSame('42', $this->filter->filterValue('42'));
    }

    public function testFilterValueReturnsSpecialCharacters(): void
    {
        $this->assertSame('a&b<c>', $this->filter->filterValue('a&b<c>'));
    }

    public function testGetErrorsReturnsEmptyArray(): void
    {
        $this->assertIsArray($this->filter->getErrors());
        $this->assertEmpty($this->filter->getErrors());
    }
}
