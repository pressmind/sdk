<?php

namespace Pressmind\Tests\Unit\ORM\Filter\Output;

use Pressmind\ORM\Filter\Output\DatetimeFilter;
use PHPUnit\Framework\TestCase;

class DatetimeFilterTest extends TestCase
{
    private DatetimeFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new DatetimeFilter();
    }

    public function testFilterValueFormatsDateTime(): void
    {
        $dt = new \DateTime('2026-03-01 14:30:45');
        $this->assertSame('2026-03-01 14:30:45', $this->filter->filterValue($dt));
    }

    public function testFilterValueFormatsMidnight(): void
    {
        $dt = new \DateTime('2026-03-01 00:00:00');
        $this->assertSame('2026-03-01 00:00:00', $this->filter->filterValue($dt));
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

    public function testGetErrorsReturnsEmptyArray(): void
    {
        $this->assertIsArray($this->filter->getErrors());
        $this->assertEmpty($this->filter->getErrors());
    }
}
