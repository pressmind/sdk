<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\Transport;

class TransportTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $c = new Transport(['BUS', 'FLUG']);
        $sql = $c->getSQL();
        $this->assertStringContainsString('transport_type', $sql);
        $this->assertStringContainsString('OR', $sql);
        $values = $c->getValues();
        $this->assertCount(2, $values);
        $this->assertSame(7, $c->getSort());
    }

    public function testGetJoins(): void
    {
        $c = new Transport(['BUS']);
        $this->assertStringContainsString('pmt2core_cheapest_price_speed', $c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new Transport(['BUS']);
        $config = new \stdClass();
        $config->transport_types = ['FLUG', 'ZUG'];
        $c->setConfig($config);
        $this->assertSame(['transport_types' => ['FLUG', 'ZUG']], $c->getConfig());
    }

    public function testToJsonAndCreate(): void
    {
        $c = Transport::create(['BUS']);
        $this->assertInstanceOf(Transport::class, $c);
        $json = $c->toJson();
        $this->assertArrayHasKey('type', $json);
        $this->assertArrayHasKey('config', $json);
    }

    public function testGetAdditionalFields(): void
    {
        $c = new Transport(['BUS']);
        $this->assertNull($c->getAdditionalFields());
    }
}
