<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\Validity;

class ValidityTest extends TestCase
{
    public function testGetSqlAndValues(): void
    {
        $from = new \DateTime('2026-01-01 00:00:00');
        $to = new \DateTime('2026-12-31 23:59:59');
        $c = new Validity($from, $to);
        $sql = $c->getSQL();
        $this->assertStringContainsString('valid_from', $sql);
        $this->assertStringContainsString('valid_to', $sql);
        $values = $c->getValues();
        $this->assertArrayHasKey('valid_from', $values);
        $this->assertArrayHasKey('valid_to', $values);
        $this->assertSame(6, $c->getSort());
    }

    public function testGetJoinsNull(): void
    {
        $c = new Validity(new \DateTime(), new \DateTime());
        $this->assertNull($c->getJoins());
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new Validity(new \DateTime(), new \DateTime());
        $config = new \stdClass();
        $config->validFrom = '2026-01-01 00:00:00';
        $config->validTo = '2026-12-31 00:00:00';
        $c->setConfig($config);
        $cfg = $c->getConfig();
        $this->assertArrayHasKey('validFrom', $cfg);
        $this->assertArrayHasKey('validTo', $cfg);
    }

    public function testToJsonAndCreate(): void
    {
        $c = Validity::create(new \DateTime(), new \DateTime());
        $this->assertInstanceOf(Validity::class, $c);
        $json = $c->toJson();
        $this->assertSame('Validity', $json['type']);
    }
}
