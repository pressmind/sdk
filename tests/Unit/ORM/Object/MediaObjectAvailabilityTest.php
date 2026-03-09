<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

/**
 * Unit tests for MediaObject getAllAvailableDates, getAllAvailableOptions, getAllAvailableTransports (caching).
 */
class MediaObjectAvailabilityTest extends AbstractTestCase
{
    private function createMediaObject(?int $id = null): MediaObject
    {
        return new MediaObject($id, false);
    }

    public function testGetAllAvailableDatesReturnsEmptyArrayWhenNoResults(): void
    {
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
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
        $db->method('inTransaction')->willReturn(false);

        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getAllAvailableDates();
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetAllAvailableDatesCachesResult(): void
    {
        $row = new stdClass();
        $row->id = 'date-1';
        $row->id_media_object = 100;
        $row->departure = (new \DateTime('+30 days'))->format('Y-m-d H:i:s');

        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($row) {
            if (strpos($query, 'pmt2core_dates') !== false || strpos($query, 'dates') !== false) {
                return [$row];
            }
            return [];
        });
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
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
        $db->method('inTransaction')->willReturn(false);

        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $first = $mo->getAllAvailableDates();
        $second = $mo->getAllAvailableDates();
        $this->assertSame($first, $second, 'getAllAvailableDates should return cached array on second call');
    }

    public function testGetAllAvailableOptionsReturnsEmptyArrayWhenNoResults(): void
    {
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
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
        $db->method('inTransaction')->willReturn(false);

        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getAllAvailableOptions();
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetAllAvailableOptionsCachesResult(): void
    {
        $row = new stdClass();
        $row->id = 'opt-1';
        $row->id_media_object = 100;

        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($row) {
            if (strpos($query, 'option') !== false || strpos($query, 'pmt2core_touristic_option') !== false) {
                return [$row];
            }
            return [];
        });
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
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
        $db->method('inTransaction')->willReturn(false);

        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $first = $mo->getAllAvailableOptions();
        $second = $mo->getAllAvailableOptions();
        $this->assertSame($first, $second, 'getAllAvailableOptions should return cached array on second call');
    }

    /**
     * getAllAvailableTransports returns array (from dates' transports) or null when no dates loaded.
     */
    public function testGetAllAvailableTransportsReturnsArrayOrNull(): void
    {
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
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
        $db->method('inTransaction')->willReturn(false);

        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getAllAvailableTransports();
        $this->assertTrue($result === null || is_array($result), 'getAllAvailableTransports returns array or null when no dates');
        if (is_array($result)) {
            $this->assertCount(0, $result);
        }
    }
}
