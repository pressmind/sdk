<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\Pool;

class PoolTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $c = new Pool([1, 2]);
        $sql = $c->getSQL();
        $this->assertStringContainsString('id_pool', $sql);
        $this->assertStringContainsString('OR', $sql);
        $values = $c->getValues();
        $this->assertCount(2, $values);
        $this->assertSame(6, $c->getSort());
    }

    public function testGetJoinsNull(): void
    {
        $c = new Pool([1]);
        $this->assertNull($c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new Pool([1]);
        $config = new \stdClass();
        $config->pools = [10, 20];
        $c->setConfig($config);
        $this->assertSame(['pools' => [10, 20]], $c->getConfig());
    }

    public function testToJsonAndCreate(): void
    {
        $c = Pool::create([1]);
        $this->assertInstanceOf(Pool::class, $c);
        $json = $c->toJson();
        $this->assertSame('Pool', $json['type']);
    }
}
