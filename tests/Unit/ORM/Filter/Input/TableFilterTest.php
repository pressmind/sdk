<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\TableFilter;
use PHPUnit\Framework\TestCase;

class TableFilterTest extends TestCase
{
    private TableFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new TableFilter();
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

    public function testFilterValueArrayReturnedAsIs(): void
    {
        $input = ['key' => 'value', 'nested' => ['a', 'b']];
        $this->assertSame($input, $this->filter->filterValue($input));
    }

    public function testFilterValueJsonStringReturnsDecodedArray(): void
    {
        $input = '{"key":"value","count":42}';
        $expected = ['key' => 'value', 'count' => 42];
        $this->assertSame($expected, $this->filter->filterValue($input));
    }

    public function testFilterValueJsonArrayStringReturnsDecodedArray(): void
    {
        $input = '[1, 2, 3]';
        $this->assertSame([1, 2, 3], $this->filter->filterValue($input));
    }

    public function testFilterValueInvalidJsonReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue('not json'));
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void
    {
        $this->assertIsArray($this->filter->getErrors());
        $this->assertEmpty($this->filter->getErrors());
    }
}
