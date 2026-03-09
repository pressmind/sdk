<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use Pressmind\ORM\Filter\Output\DateFilter;
use PHPUnit\Framework\TestCase;

class DateFilterTest extends TestCase
{
    private DateFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new DateFilter();
    }

    public function testFilterValueFormatsDateTime(): void
    {
        $dt = new \DateTime('2026-03-01 14:30:00');
        $this->assertSame('2026-03-01', $this->filter->filterValue($dt));
    }

    public function testFilterValueNullReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue(null));
    }

    public function testFilterValueEmptyStringReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue(''));
    }

    public function testFilterValueFalseReturnsNull(): void
    {
        $this->assertNull($this->filter->filterValue(false));
    }

    public function testFilterValueNonDateTimeThrowsError(): void
    {
        $this->expectException(\Error::class);
        $this->filter->filterValue('not-a-datetime-object');
    }

    public function testFilterValueFormatsLeapYearDate(): void
    {
        $dt = new \DateTime('2028-02-29');
        $this->assertSame('2028-02-29', $this->filter->filterValue($dt));
    }

    public function testGetErrorsReturnsEmptyArray(): void
    {
        $this->assertIsArray($this->filter->getErrors());
        $this->assertEmpty($this->filter->getErrors());
    }
}
