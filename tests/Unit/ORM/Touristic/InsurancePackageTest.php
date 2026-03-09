<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\Touristic\Insurance\Package;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class InsurancePackageTest extends AbstractTestCase
{
    public function testGetValidPackagesByInsuranceGroupReturnsEmptyWhenNoResults(): void
    {
        $package = new Package(null, false);
        $result = $package->getValidPackagesByInsuranceGroup('group-1');
        $this->assertSame([], $result);
    }

    public function testGetValidPackagesByInsuranceGroupExplodesItems(): void
    {
        $row = new \stdClass();
        $row->id = 'pkg-1';
        $row->items = 'item-a,item-b,item-c';
        $row->code_ibe = 'IBE-001';
        $row->name = 'Test Package';

        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn([$row]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('inTransaction')->willReturn(false);
        $db->method('execute')->willReturn(null);
        $db->method('insert')->willReturn(null);
        $db->method('replace')->willReturn(null);
        $db->method('update')->willReturn(null);
        $db->method('delete')->willReturn(null);
        $db->method('truncate')->willReturn(null);
        $db->method('batchInsert')->willReturn(1);
        $db->method('beginTransaction')->willReturn(null);
        $db->method('commit')->willReturn(null);
        $db->method('rollback')->willReturn(null);

        Registry::getInstance()->add('db', $db, true);

        $package = new Package(null, false);
        $result = $package->getValidPackagesByInsuranceGroup('group-1');

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]->items);
        $this->assertSame(['item-a', 'item-b', 'item-c'], $result[0]->items);
        $this->assertSame('pkg-1', $result[0]->id);
        $this->assertSame('IBE-001', $result[0]->code_ibe);
        $this->assertSame('Test Package', $result[0]->name);
    }
}
