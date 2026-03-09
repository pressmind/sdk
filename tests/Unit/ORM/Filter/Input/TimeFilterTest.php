<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\TimeFilter;
use PHPUnit\Framework\TestCase;

class TimeFilterTest extends TestCase
{
    public function testFilterValueEmptyReturnsValue(): void
    {
        $filter = new TimeFilter();
        $this->assertNull($filter->filterValue(null));
        $this->assertSame('', $filter->filterValue(''));
    }

    public function testFilterValueDateTimeReturnsAsIs(): void
    {
        $filter = new TimeFilter();
        $dt = new \DateTime('2026-01-15 14:30:00');
        $this->assertSame($dt, $filter->filterValue($dt));
    }

    public function testFilterValueTimeString(): void
    {
        $filter = new TimeFilter();
        $result = $filter->filterValue('14:30:00');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('14:30:00', $result->format('H:i:s'));
    }

    public function testFilterValueDateTimeString(): void
    {
        $filter = new TimeFilter();
        $result = $filter->filterValue('2026-01-15 14:30:00');
        $this->assertInstanceOf(\DateTime::class, $result);
    }

    public function testFilterValueArrayWithDateKey(): void
    {
        $filter = new TimeFilter();
        $input = ['date' => '2026-01-15 14:30:45.000000'];
        $result = $filter->filterValue($input);
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('14:30:45', $result->format('H:i:s'));
    }

    public function testFilterValueStdClassWithDateProperty(): void
    {
        $filter = new TimeFilter();
        $obj = new \stdClass();
        $obj->date = '2026-01-15 09:15:30.000000';
        $result = $filter->filterValue($obj);
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('09:15:30', $result->format('H:i:s'));
    }

    public function testFilterValueNullReturnsNull(): void
    {
        $filter = new TimeFilter();
        $this->assertNull($filter->filterValue(null));
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new TimeFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
