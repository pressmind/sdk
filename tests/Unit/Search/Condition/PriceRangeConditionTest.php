<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\PriceRange;

class PriceRangeConditionTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $c = new PriceRange(100, 500);
        $sql = $c->getSQL();
        $this->assertStringContainsString('price_total BETWEEN', $sql);
        $this->assertStringContainsString(':now', $sql);
        $values = $c->getValues();
        $this->assertSame(100, $values[':price_from']);
        $this->assertSame(500, $values[':price_to']);
        $this->assertArrayHasKey(':now', $values);
        $this->assertSame(2, $c->getSort());
    }

    public function testGetJoins(): void
    {
        $c = new PriceRange(1, 1000);
        $this->assertStringContainsString('cheapest_price_speed', $c->getJoins());
        $this->assertSame('SUBSELECT', $c->getJoinType());
        $this->assertSame('pmt2core_cheapest_price_speed', $c->getSubselectJoinTable());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new PriceRange(1, 100);
        $config = new \stdClass();
        $config->priceFrom = 50;
        $config->priceTo = 200;
        $c->setConfig($config);
        $this->assertSame(['priceFrom' => 50, 'priceTo' => 200], $c->getConfig());
    }

    public function testToJsonAndCreate(): void
    {
        $c = PriceRange::create(100, 500);
        $this->assertInstanceOf(PriceRange::class, $c);
        $json = $c->toJson();
        $this->assertSame('PriceRange', $json['type']);
    }

    public function testGetAdditionalFields(): void
    {
        $c = new PriceRange(100, 500);
        $this->assertNull($c->getAdditionalFields());
    }
}
