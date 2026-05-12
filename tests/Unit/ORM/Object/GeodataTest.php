<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\Geodata;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class GeodataTest extends AbstractTestCase
{
    public function testGetByZipReturnsFalseForNonNumericZip(): void
    {
        $geodata = new Geodata(null, false);
        $this->assertFalse($geodata->getByZip('abc'));
    }

    public function testGetByZipExecutesQueryForLeadingZeroPostalCode(): void
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->expects($this->once())
            ->method('fetchAll')
            ->with(
                $this->callback(function ($query) {
                    return is_string($query)
                        && str_contains($query, 'postleitzahl')
                        && str_contains($query, '?');
                }),
                $this->equalTo(['01099'])
            )
            ->willReturn([]);

        Registry::getInstance()->add('db', $db);

        $geodata = new Geodata(null, false);
        $this->assertFalse($geodata->getByZip('01099'));
    }

    public function testGetByZipReturnsFalseWhenNoResults(): void
    {
        $geodata = new Geodata(null, false);
        $result = $geodata->getByZip(12345);
        $this->assertFalse($result);
    }

    public function testGetZipsAroundZipReturnsEmptyWhenZipNotFound(): void
    {
        $geodata = new Geodata(null, false);
        $result = $geodata->getZipsAroundZip('99999');
        $this->assertSame([], $result);
    }

    public function testGetZipsAroundCoordsReturnsEmptyWhenNoResults(): void
    {
        $geodata = new Geodata(null, false);
        $result = $geodata->getZipsAroundCoords(52.52, 13.405, 10);
        $this->assertSame([], $result);
    }

    public function testValidateReturnsErrorWhenNoGeodata(): void
    {
        $result = Geodata::validate('TEST');
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('No geodata', $result[0]);
    }

    public function testValidateReturnsEmptyWhenGeodataExists(): void
    {
        $row = new \stdClass();
        $row->count = 1000;

        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('inTransaction')->willReturn(false);
        $db->method('execute')->willReturn([$row]);
        $db->method('insert')->willReturn(null);
        $db->method('replace')->willReturn(null);
        $db->method('update')->willReturn(null);
        $db->method('delete')->willReturn(null);
        $db->method('truncate')->willReturn(null);
        $db->method('batchInsert')->willReturn(1);
        $db->method('beginTransaction')->willReturn(null);
        $db->method('commit')->willReturn(null);
        $db->method('rollback')->willReturn(null);

        Registry::getInstance()->add('db', $db);

        $result = Geodata::validate('TEST');
        $this->assertSame([], $result);
    }
}
