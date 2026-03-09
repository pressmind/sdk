<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\HousingOption;

class HousingOptionTest extends TestCase
{
    public function testGetSqlWithOccupancies(): void
    {
        $c = new HousingOption([2, 3]);
        $sql = $c->getSQL();
        $this->assertStringContainsString('option_occupancy', $sql);
        $values = $c->getValues();
        $this->assertNotEmpty($values);
        $this->assertSame(2, $c->getSort());
    }

    public function testGetSqlWithStatus(): void
    {
        $c = new HousingOption(null, [1, 2]);
        $sql = $c->getSQL();
        $this->assertStringContainsString('state IN', $sql);
    }

    public function testGetJoins(): void
    {
        $c = new HousingOption(2);
        $this->assertStringContainsString('cheapest_price_speed', $c->getJoins());
        $this->assertSame('SUBSELECT', $c->getJoinType());
        $this->assertSame('pmt2core_cheapest_price_speed', $c->getSubselectJoinTable());
    }

    public function testConstructorScalarOccupancy(): void
    {
        $c = new HousingOption(2);
        $this->assertSame([2], $c->occupancies);
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new HousingOption([2]);
        $config = new \stdClass();
        $config->occupancies = [2, 3];
        $config->status = [1];
        $c->setConfig($config);
        $this->assertSame(['occupancies' => [2, 3], 'status' => [1]], $c->getConfig());
    }

    public function testToJsonAndCreate(): void
    {
        $c = HousingOption::create(2);
        $this->assertInstanceOf(HousingOption::class, $c);
        $json = $c->toJson();
        $this->assertSame('HousingOption', $json['type']);
    }

    public function testGetAdditionalFields(): void
    {
        $c = new HousingOption(2);
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfigWithScalarStatus(): void
    {
        $c = new HousingOption([2]);
        $config = new \stdClass();
        $config->occupancies = [2];
        $config->status = 1;
        $c->setConfig($config);
        $this->assertSame([1], $c->status);
    }

    public function testConstructorWithScalarStatus(): void
    {
        $c = new HousingOption([2], 1);
        $this->assertSame([1], $c->status);
    }

    public function testSetConfigWithMissingOptionalFields(): void
    {
        $c = new HousingOption([2]);
        $config = new \stdClass();
        $c->setConfig($config);
        $this->assertNull($c->occupancies);
        $this->assertSame([0], $c->status);
    }
}
