<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\LongtextFilter;
use PHPUnit\Framework\TestCase;

class LongtextFilterTest extends TestCase
{
    public function testFilterValueReturnsValue(): void
    {
        $filter = new LongtextFilter();
        $this->assertSame('long text', $filter->filterValue('long text'));
    }

    public function testFilterValueEmptyOrNullStringReturnsNull(): void
    {
        $filter = new LongtextFilter();
        $this->assertNull($filter->filterValue(''));
        $this->assertNull($filter->filterValue('null'));
    }

    public function testFilterValueObjectReturnsErrorString(): void
    {
        $filter = new LongtextFilter();
        $this->assertSame('Error: Object to string conversion', $filter->filterValue(new \stdClass()));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new LongtextFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
