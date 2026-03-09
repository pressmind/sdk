<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use Pressmind\ORM\Filter\Output\LowerCaseFilter;
use PHPUnit\Framework\TestCase;

class LowerCaseFilterTest extends TestCase
{
    private LowerCaseFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new LowerCaseFilter();
    }

    public function testFilterValueConvertsToLowerCase(): void
    {
        $this->assertSame('hello world', $this->filter->filterValue('Hello World'));
    }

    public function testFilterValueAlreadyLowerCase(): void
    {
        $this->assertSame('already lower', $this->filter->filterValue('already lower'));
    }

    public function testFilterValueAllUpperCase(): void
    {
        $this->assertSame('upper', $this->filter->filterValue('UPPER'));
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

    public function testGetErrorsReturnsEmptyArray(): void
    {
        $this->assertIsArray($this->filter->getErrors());
        $this->assertEmpty($this->filter->getErrors());
    }
}
