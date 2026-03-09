<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\Visibility;

class VisibilityTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $c = new Visibility([30, 40]);
        $sql = $c->getSQL();
        $this->assertStringContainsString('visibility', $sql);
        $this->assertStringContainsString('OR', $sql);
        $values = $c->getValues();
        $this->assertCount(2, $values);
        $this->assertSame(6, $c->getSort());
    }

    public function testGetJoinsNull(): void
    {
        $c = new Visibility([30]);
        $this->assertNull($c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new Visibility([30]);
        $config = new \stdClass();
        $config->visibilities = [30, 40];
        $c->setConfig($config);
        $this->assertSame(['visibilities' => [30, 40]], $c->getConfig());
    }

    public function testToJsonAndCreate(): void
    {
        $c = Visibility::create([30]);
        $this->assertInstanceOf(Visibility::class, $c);
        $json = $c->toJson();
        $this->assertSame('Visibility', $json['type']);
    }
}
