<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\LowerCaseFilter;
use PHPUnit\Framework\TestCase;

class LowerCaseFilterTest extends TestCase
{
    public function testFilterValueReturnsLowercase(): void
    {
        $filter = new LowerCaseFilter();
        $this->assertSame('hello', $filter->filterValue('HELLO'));
    }

    public function testFilterValueEmptyOrNullStringReturnsNull(): void
    {
        $filter = new LowerCaseFilter();
        $this->assertNull($filter->filterValue(''));
        $this->assertNull($filter->filterValue('null'));
    }

    public function testFilterValueObjectReturnsErrorString(): void
    {
        $filter = new LowerCaseFilter();
        $this->assertSame('Error: Object to string conversion', $filter->filterValue(new \stdClass()));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new LowerCaseFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
