<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Input;

use Pressmind\ORM\Filter\Input\DateFilter;
use PHPUnit\Framework\TestCase;

class DateFilterTest extends TestCase
{
    public function testFilterValueEmptyReturnsValue(): void
    {
        $filter = new DateFilter();
        $this->assertNull($filter->filterValue(null));
        $this->assertSame('', $filter->filterValue(''));
    }

    public function testFilterValueDateTimeReturnsAsIs(): void
    {
        $filter = new DateFilter();
        $dt = new \DateTime('2026-01-15');
        $this->assertSame($dt, $filter->filterValue($dt));
    }

    public function testFilterValueDateStringReturnsDateTime(): void
    {
        $filter = new DateFilter();
        $result = $filter->filterValue('2026-01-15');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2026-01-15', $result->format('Y-m-d'));
    }

    public function testFilterValueStringWithDateMatch(): void
    {
        $filter = new DateFilter();
        $result = $filter->filterValue('Some text 2026-03-01 more');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2026-03-01', $result->format('Y-m-d'));
    }

    public function testFilterValueStdClassWithDate(): void
    {
        $filter = new DateFilter();
        $std = new \stdClass();
        $std->date = '2026-01-15 12:00:00.000000';
        $result = $filter->filterValue($std);
        $this->assertInstanceOf(\DateTime::class, $result);
    }

    public function testFilterValueArrayWithDateKey(): void
    {
        $filter = new DateFilter();
        $input = ['date' => '2026-01-15 12:00:00.000000'];
        $result = $filter->filterValue($input);
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2026-01-15', $result->format('Y-m-d'));
    }

    public function testFilterValueSetsTimeToZero(): void
    {
        $filter = new DateFilter();
        $result = $filter->filterValue('2026-06-15');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('00:00:00', $result->format('H:i:s'));
    }

    public function testFilterValueDatetimeStringExtractsDate(): void
    {
        $filter = new DateFilter();
        $result = $filter->filterValue('2026-06-15 14:30:00');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertSame('2026-06-15', $result->format('Y-m-d'));
        $this->assertSame('00:00:00', $result->format('H:i:s'));
    }

    public function testFilterValueNoDateMatchFails(): void
    {
        $filter = new DateFilter();
        $this->expectException(\Error::class);
        $filter->filterValue('no-match');
    }

    public function testGetErrorsReturnsArray(): void
    {
        $filter = new DateFilter();
        $this->assertIsArray($filter->getErrors());
    }
}
