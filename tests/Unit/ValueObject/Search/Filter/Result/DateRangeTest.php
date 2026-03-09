<?php

namespace Pressmind\Tests\Unit\ValueObject\Search\Filter\Result;

use DateTime;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\ValueObject\Search\Filter\Result\DateRange;

class DateRangeTest extends AbstractTestCase
{
    public function testPropertiesCanBeSetAndRead(): void
    {
        $from = new DateTime('2026-06-01');
        $to = new DateTime('2026-06-30');
        $vo = new DateRange();
        $vo->from = $from;
        $vo->to = $to;

        $this->assertSame($from, $vo->from);
        $this->assertSame($to, $vo->to);
    }
}
