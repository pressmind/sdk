<?php

namespace Pressmind\Tests\Unit\Search\Condition;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\Agency;

class AgencyTest extends TestCase
{
    public function testGetSqlWithAgencies(): void
    {
        $c = new Agency([100, 200]);
        $sql = $c->getSQL();
        $this->assertStringContainsString('pmt2core_agency_to_media_object', $sql);
        $this->assertStringContainsString('OR', $sql);
        $values = $c->getValues();
        $this->assertCount(2, $values);
        $this->assertSame(6, $c->getSort());
    }

    public function testGetSqlWithMappedAgencyCountZero(): void
    {
        $c = new Agency([], 0);
        $sql = $c->getSQL();
        $this->assertStringContainsString('NOT IN', $sql);
        $this->assertNull($c->getJoins());
    }

    public function testGetSqlWithMappedAgencyCountPositive(): void
    {
        $c = new Agency([], 2);
        $sql = $c->getSQL();
        $this->assertStringContainsString('>= 2', $sql);
    }

    public function testGetJoinsWhenAgenciesSet(): void
    {
        $c = new Agency([1]);
        $this->assertStringContainsString('INNER JOIN pmt2core_agency_to_media_object', $c->getJoins());
    }

    public function testGetJoinTypeAndSubselectAndAdditionalFields(): void
    {
        $c = new Agency([1]);
        $this->assertNull($c->getJoinType());
        $this->assertNull($c->getSubselectJoinTable());
        $this->assertNull($c->getAdditionalFields());
    }

    public function testSetConfigAndGetConfig(): void
    {
        $c = new Agency([1]);
        $config = new \stdClass();
        $config->agencies = [10, 20];
        $config->mappedAgencyCount = null;
        $c->setConfig($config);
        $this->assertSame(['agencies' => [10, 20], 'mappedAgencyCount' => null], $c->getConfig());
    }

    public function testCreate(): void
    {
        $c = Agency::create([1, 2], null);
        $this->assertInstanceOf(Agency::class, $c);
    }
}
