<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

class MediaObjectStaticQueryTest extends AbstractTestCase
{
    private function createMediaObject(?int $id = null): MediaObject
    {
        return new MediaObject($id, false);
    }

    private function createDbMockWithFetchAll(array $rows): AdapterInterface
    {
        $db = $this->createMockDb();
        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn($rows);
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
        return $db;
    }

    // --- getByIbeCodeDate ---

    public function testGetByIbeCodeDateReturnsEmptyArrayWhenNoMatch(): void
    {
        $result = MediaObject::getByIbeCodeDate('NONEXISTENT');
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    // --- getByIbeCodeHousingPackage ---

    public function testGetByIbeCodeHousingPackageReturnsEmptyArrayWhenNoMatch(): void
    {
        $result = MediaObject::getByIbeCodeHousingPackage('NONEXISTENT');
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    // --- deleteTouristic ---

    public function testDeleteTouristicCallsDeleteOnAllTables(): void
    {
        $deletedTables = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('inTransaction')->willReturn(false);
        $db->method('execute')->willReturn(null);
        $db->method('insert')->willReturn(null);
        $db->method('replace')->willReturn(null);
        $db->method('update')->willReturn(null);
        $db->expects($this->exactly(9))
            ->method('delete')
            ->willReturnCallback(function ($table) use (&$deletedTables) {
                $deletedTables[] = $table;
            });
        $db->method('truncate')->willReturn(null);
        $db->method('batchInsert')->willReturn(1);
        $db->method('beginTransaction')->willReturn(null);
        $db->method('commit')->willReturn(null);
        $db->method('rollback')->willReturn(null);

        Registry::getInstance()->add('db', $db);

        MediaObject::deleteTouristic(42);

        $expectedTables = [
            'pmt2core_cheapest_price_speed',
            'pmt2core_touristic_date_attributes',
            'pmt2core_touristic_transports',
            'pmt2core_touristic_dates',
            'pmt2core_touristic_options',
            'pmt2core_touristic_housing_package_description_links',
            'pmt2core_touristic_housing_packages',
            'pmt2core_touristic_seasonal_periods',
            'pmt2core_touristic_booking_packages',
        ];
        $this->assertSame($expectedTables, $deletedTables);
    }

    // --- getCheapestPriceCount ---

    public function testGetCheapestPriceCountReturnsEmptyArrayWhenNoResults(): void
    {
        $result = MediaObject::getCheapestPriceCount(999);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetCheapestPriceCountAcceptsSingleId(): void
    {
        $row = new stdClass();
        $row->id_media_object = 42;
        $row->count = 5;

        $db = $this->createDbMockWithFetchAll([$row]);
        Registry::getInstance()->add('db', $db);

        $result = MediaObject::getCheapestPriceCount(42);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]->id);
        $this->assertSame(5, $result[0]->count);
    }

    public function testGetCheapestPriceCountAcceptsArrayOfIds(): void
    {
        $row1 = new stdClass();
        $row1->id_media_object = 1;
        $row1->count = 3;
        $row2 = new stdClass();
        $row2->id_media_object = 2;
        $row2->count = 7;

        $db = $this->createDbMockWithFetchAll([$row1, $row2]);
        Registry::getInstance()->add('db', $db);

        $result = MediaObject::getCheapestPriceCount([1, 2]);
        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]->id);
        $this->assertSame(2, $result[1]->id);
    }

    // --- getJoinedMediaObjectsByCategory ---

    public function testGetJoinedMediaObjectsByCategoryReturnsEmptyWhenNoMatch(): void
    {
        $result = MediaObject::getJoinedMediaObjectsByCategory('item-1', 123);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetJoinedMediaObjectsByCategoryAcceptsFieldName(): void
    {
        $result = MediaObject::getJoinedMediaObjectsByCategory('item-1', 123, 'zielgebiet_default');
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    // --- getMediaObjectByCRSID ---

    public function testGetMediaObjectByCRSIDWithCodePropertyReturnsEmptyWhenNoMatch(): void
    {
        $result = MediaObject::getMediaObjectByCRSID('CRS-999');
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetMediaObjectByCRSIDWithCodePropertyReturnsResults(): void
    {
        $row = new stdClass();
        $row->id_media_object = 42;

        $db = $this->createDbMockWithFetchAll([$row]);
        Registry::getInstance()->add('db', $db);

        $result = MediaObject::getMediaObjectByCRSID('CRS-42');
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]->id);
    }
}
