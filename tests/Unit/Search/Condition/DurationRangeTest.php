<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\DurationRange;

class DurationRangeTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $c = new DurationRange(7, 14);
        $sql = $c->getSQL();
        $this->assertStringContainsString('duration BETWEEN', $sql);
        $values = $c->getValues();
        $this->assertSame(7, $values[':duration_from']);
        $this->assertSame(14, $values[':duration_to']);
        $this->assertSame(2, $c->getSort());
    }

    public function testGetJoins(): void
    {
        $c = new DurationRange(1, 10);
        $this->assertStringContainsString('cheapest_price_speed', $c->getJoins());
        $this->assertSame('SUBSELECT', $c->getJoinType());
        $this->assertSame('pmt2core_cheapest_price_speed', $c->getSubselectJoinTable());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new DurationRange(1, 10);
        $config = new \stdClass();
        $config->durationFrom = 5;
        $config->durationTo = 21;
        $c->setConfig($config);
        $this->assertSame(['durationFrom' => 5, 'durationTo' => 21], $c->getConfig());
    }

    public function testToJsonAndCreate(): void
    {
        $c = DurationRange::create(7, 14);
        $this->assertInstanceOf(DurationRange::class, $c);
        $json = $c->toJson();
        $this->assertSame('DurationRange', $json['type']);
    }

    public function testGetAdditionalFields(): void
    {
        $c = new DurationRange(1, 5);
        $this->assertNull($c->getAdditionalFields());
    }
}
