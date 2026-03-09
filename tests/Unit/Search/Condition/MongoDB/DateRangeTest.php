<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\DateRange;

class DateRangeTest extends TestCase
{
    public function testGetType(): void
    {
        $from = new \DateTime('2026-06-01');
        $to = new \DateTime('2026-06-30');
        $c = new DateRange($from, $to);
        $this->assertSame('DateRange', $c->getType());
    }

    public function testGetDateFromAndTo(): void
    {
        $from = new \DateTime('2026-06-01');
        $to = new \DateTime('2026-06-30');
        $c = new DateRange($from, $to);
        $this->assertSame($from, $c->getDateFrom());
        $this->assertSame($to, $c->getDateTo());
    }

    public function testFirstMatchQuery(): void
    {
        $from = new \DateTime('2026-06-01');
        $to = new \DateTime('2026-06-30');
        $c = new DateRange($from, $to);
        $query = $c->getQuery('first_match');
        $this->assertIsArray($query);
        $this->assertArrayHasKey('prices', $query);
        $this->assertStringContainsString('2026-06-01', json_encode($query));
        $this->assertStringContainsString('2026-06-30', json_encode($query));
    }

    public function testDepartureFilterQuery(): void
    {
        $from = new \DateTime('2026-06-01');
        $to = new \DateTime('2026-06-30');
        $c = new DateRange($from, $to);
        $query = $c->getQuery('departure_filter');
        $this->assertIsArray($query);
        $this->assertArrayHasKey(0, $query);
        $this->assertArrayHasKey('$addFields', $query[0]);
    }

    public function testReturnsNullForUnknownType(): void
    {
        $from = new \DateTime('2026-06-01');
        $to = new \DateTime('2026-06-30');
        $c = new DateRange($from, $to);
        $this->assertNull($c->getQuery('unknown'));
    }

    public function testFirstMatchQueryStructure(): void
    {
        $from = new \DateTime('2026-07-01');
        $to = new \DateTime('2026-07-31');
        $c = new DateRange($from, $to);
        $query = $c->getQuery('first_match');
        $elemMatch = $query['prices']['$elemMatch'];
        $this->assertArrayHasKey('date_departures', $elemMatch);
        $inner = $elemMatch['date_departures']['$elemMatch'];
        $this->assertSame('2026-07-01', $inner['$gte']);
        $this->assertSame('2026-07-31', $inner['$lte']);
    }

    public function testDepartureFilterQueryStructure(): void
    {
        $from = new \DateTime('2026-08-01');
        $to = new \DateTime('2026-08-15');
        $c = new DateRange($from, $to);
        $query = $c->getQuery('departure_filter');
        $this->assertCount(1, $query);
        $addFields = $query[0]['$addFields'];
        $this->assertArrayHasKey('prices', $addFields);
        $filter = $addFields['prices']['$filter'];
        $this->assertArrayHasKey('input', $filter);
        $json = json_encode($filter);
        $this->assertStringContainsString('2026-08-01', $json);
        $this->assertStringContainsString('2026-08-15', $json);
        $this->assertStringContainsString('date_departures', $json);
    }

    public function testPricesFilterReturnsNull(): void
    {
        $from = new \DateTime('2026-01-01');
        $to = new \DateTime('2026-12-31');
        $c = new DateRange($from, $to);
        $this->assertNull($c->getQuery('prices_filter'));
    }
}
