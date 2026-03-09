<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\State;

class StateTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $c = new State([1, 2]);
        $sql = $c->getSQL();
        $this->assertStringContainsString('state =', $sql);
        $this->assertStringContainsString('OR', $sql);
        $values = $c->getValues();
        $this->assertCount(2, $values);
        $this->assertSame(6, $c->getSort());
    }

    public function testGetJoinsNull(): void
    {
        $c = new State([1]);
        $this->assertNull($c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new State([1]);
        $config = new \stdClass();
        $config->states = [10, 20];
        $c->setConfig($config);
        $this->assertSame(['states' => [10, 20]], $c->getConfig());
    }

    public function testToJsonAndCreate(): void
    {
        $c = State::create([1]);
        $this->assertInstanceOf(State::class, $c);
        $json = $c->toJson();
        $this->assertSame('State', $json['type']);
    }
}
