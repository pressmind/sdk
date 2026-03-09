<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\DatetimeFilter;
use PHPUnit\Framework\TestCase;

class DatetimeFilterTest extends TestCase
{
    private DatetimeFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new DatetimeFilter();
    }

    public function testFilterValueNullReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue(null));
    }

    public function testFilterValueEmptyStringReturnsEmptyString(): void
    {
        $result = $this->filter->filterValue('');
        $this->assertSame('', $result);
    }

    public function testFilterValueDateTimeObjectReturnsAsIs(): void
    {
        $dt = new \DateTime('2026-03-01 12:00:00');
        $this->assertSame($dt, $this->filter->filterValue($dt));
    }

    public function testFilterValueSimpleDateStringAppendsMidnight(): void
    {
        $result = $this->filter->filterValue('2026-03-01');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2026-03-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testFilterValueSimpleDateStringWithDots(): void
    {
        $result = $this->filter->filterValue('2026-03-01');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('00:00:00', $result->format('H:i:s'));
    }

    public function testFilterValueStandardDatetimeString(): void
    {
        $result = $this->filter->filterValue('2026-03-01 14:30:45');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2026-03-01 14:30:45', $result->format('Y-m-d H:i:s'));
    }

    public function testFilterValueStdClassWithDatePropertyUsesDateField(): void
    {
        $obj = new \stdClass();
        $obj->date = '2026-03-01 14:30:45.000000';
        $result = $this->filter->filterValue($obj);
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2026-03-01', $result->format('Y-m-d'));
    }

    public function testFilterValueStdClassWithInvalidDateReturnsFalse(): void
    {
        $obj = new \stdClass();
        $obj->date = 'invalid';
        $result = $this->filter->filterValue($obj);
        $this->assertFalse($result);
    }

    public function testFilterValueJavascriptFormattedDatetime(): void
    {
        $result = $this->filter->filterValue('2026-03-01T14:30:45.414Z');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2026-03-01 14:30:45', $result->format('Y-m-d H:i:s'));
    }

    public function testFilterValueJavascriptFormattedWithZeroMilliseconds(): void
    {
        $result = $this->filter->filterValue('2026-03-01T00:00:00.000Z');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2026-03-01 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testFilterValueInvalidDatetimeStringReturnsFalse(): void
    {
        $result = $this->filter->filterValue('not-a-date');
        $this->assertFalse($result);
    }

    public function testFilterValueDateBoundaryDec31(): void
    {
        $result = $this->filter->filterValue('2026-12-31');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2026-12-31 00:00:00', $result->format('Y-m-d H:i:s'));
    }

    public function testFilterValueZeroIsEmpty(): void
    {
        $result = $this->filter->filterValue(0);
        $this->assertSame(0, $result);
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void
    {
        $this->assertIsArray($this->filter->getErrors());
        $this->assertEmpty($this->filter->getErrors());
    }
}
