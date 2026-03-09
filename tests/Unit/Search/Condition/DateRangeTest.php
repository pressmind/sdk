<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\DateRange;

class DateRangeTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $from = new \DateTime('2026-06-01');
        $to = new \DateTime('2026-06-30');
        $c = new DateRange($from, $to);
        $sql = $c->getSQL();
        $this->assertStringContainsString('date_departure BETWEEN', $sql);
        $values = $c->getValues();
        $this->assertArrayHasKey(':date_from', $values);
        $this->assertArrayHasKey(':date_to', $values);
        $this->assertSame(2, $c->getSort());
    }

    public function testGetJoins(): void
    {
        $c = new DateRange(new \DateTime(), new \DateTime());
        $this->assertStringContainsString('cheapest_price_speed', $c->getJoins());
        $this->assertSame('SUBSELECT', $c->getJoinType());
        $this->assertSame('pmt2core_cheapest_price_speed', $c->getSubselectJoinTable());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new DateRange(new \DateTime('2026-01-01 00:00:00'), new \DateTime('2026-12-31 00:00:00'));
        $config = new \stdClass();
        $config->dateFrom = '2026-06-01 00:00:00';
        $config->dateTo = '2026-06-30 00:00:00';
        $c->setConfig($config);
        $cfg = $c->getConfig();
        $this->assertArrayHasKey('dateFrom', $cfg);
        $this->assertArrayHasKey('dateTo', $cfg);
    }

    public function testToJsonAndCreate(): void
    {
        $c = DateRange::create(new \DateTime(), new \DateTime());
        $this->assertInstanceOf(DateRange::class, $c);
        $json = $c->toJson();
        $this->assertSame('DateRange', $json['type']);
    }

    public function testGetAdditionalFields(): void
    {
        $c = new DateRange(new \DateTime(), new \DateTime());
        $this->assertNull($c->getAdditionalFields());
    }
}
