<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\VarcharFilter;
use PHPUnit\Framework\TestCase;

class VarcharFilterTest extends TestCase
{
    public function testFilterValueReturnsValue(): void
    {
        $filter = new VarcharFilter();
        $this->assertSame('varchar', $filter->filterValue('varchar'));
    }

    public function testFilterValueEmptyOrNullStringReturnsNull(): void
    {
        $filter = new VarcharFilter();
        $this->assertNull($filter->filterValue(''));
        $this->assertNull($filter->filterValue('null'));
    }

    public function testFilterValueObjectReturnsErrorString(): void
    {
        $filter = new VarcharFilter();
        $this->assertSame('Error: Object to string conversion', $filter->filterValue(new \stdClass()));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new VarcharFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
