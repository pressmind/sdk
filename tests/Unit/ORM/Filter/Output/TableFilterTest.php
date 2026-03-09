<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use Pressmind\ORM\Filter\Output\TableFilter;
use PHPUnit\Framework\TestCase;

class TableFilterTest extends TestCase
{
    private TableFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new TableFilter();
    }

    public function testFilterValueEncodesArray(): void
    {
        $input = ['key' => 'value', 'count' => 42];
        $this->assertSame('{"key":"value","count":42}', $this->filter->filterValue($input));
    }

    public function testFilterValueEncodesNestedArray(): void
    {
        $input = ['items' => [1, 2, 3]];
        $this->assertSame('{"items":[1,2,3]}', $this->filter->filterValue($input));
    }

    public function testFilterValueNullReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue(null));
    }

    public function testFilterValueEmptyStringReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue(''));
    }

    public function testFilterValueEmptyArrayReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue([]));
    }

    public function testFilterValueEncodesString(): void
    {
        $this->assertSame('"hello"', $this->filter->filterValue('hello'));
    }

    public function testFilterValueEncodesInteger(): void
    {
        $this->assertSame('42', $this->filter->filterValue(42));
    }

    public function testGetErrorsReturnsEmptyArray(): void
    {
        $this->assertIsArray($this->filter->getErrors());
        $this->assertEmpty($this->filter->getErrors());
    }
}
