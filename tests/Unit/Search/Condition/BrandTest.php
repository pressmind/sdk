<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\Brand;

class BrandTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $c = new Brand([1, 2]);
        $sql = $c->getSQL();
        $this->assertStringContainsString('id_brand', $sql);
        $this->assertStringContainsString('OR', $sql);
        $values = $c->getValues();
        $this->assertCount(2, $values);
        $this->assertSame(6, $c->getSort());
    }

    public function testGetJoinsAndNulls(): void
    {
        $c = new Brand([1]);
        $this->assertNull($c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new Brand([1]);
        $config = new \stdClass();
        $config->brands = [10, 20];
        $c->setConfig($config);
        $this->assertSame(['brands' => [10, 20]], $c->getConfig());
    }

    public function testToJson(): void
    {
        $c = new Brand([1]);
        $json = $c->toJson();
        $this->assertSame('Brand', $json['type']);
        $this->assertArrayHasKey('config', $json);
    }

    public function testCreate(): void
    {
        $c = Brand::create([1]);
        $this->assertInstanceOf(Brand::class, $c);
    }
}
