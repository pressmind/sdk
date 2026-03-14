<?php

namespace Pressmind\Tests\Unit\ORM\Object\Touristic;

use DateTime;
use Pressmind\ORM\Object\Touristic\Booking\Package as BookingPackage;
use Pressmind\ORM\Object\Touristic\Housing\Package as HousingPackage;
use Pressmind\Registry;
use Pressmind\Search\CheapestPrice;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

/**
 * Unit tests for Housing\Package::getCheapestPrice() and Booking\Package::getCheapestPrice()
 * delegating to MediaObject::getCheapestPrice() with correct filter (id_housing_package / id_booking_package).
 */
class HousingBookingPackageWrapperTest extends AbstractTestCase
{
    private function createMockDbForCheapestPriceSpeed(array $rows): \Pressmind\DB\Adapter\AdapterInterface
    {
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query, $params = null, $class_name = null) use ($rows) {
            if (strpos($query, 'cheapest_price_speed') !== false) {
                return $rows;
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
        return $db;
    }

    private function createCheapestPriceRow(int $id, float $priceTotal, string $idHousingPackage = null, string $idBookingPackage = null): stdClass
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $row = new stdClass();
        $row->id = $id;
        $row->id_media_object = 100;
        $row->price_total = $priceTotal;
        $row->date_departure = $futureDate;
        $row->duration = 7;
        $row->option_occupancy = 2;
        $row->state = 3;
        $row->id_housing_package = $idHousingPackage;
        $row->id_booking_package = $idBookingPackage;
        $row->transport_type = 'bus';
        $row->transport_1_airport = null;
        $row->transport_1_airport_name = null;
        $row->date_departure_from = $futureDate;
        $row->date_departure_to = $futureDate;
        return $row;
    }

    public function testHousingPackageGetCheapestPriceDelegatesToMediaObject(): void
    {
        $row = $this->createCheapestPriceRow(1, 4899.99, 'hp-123', null);
        $db = $this->createMockDbForCheapestPriceSpeed([$row]);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($row) {
            if (strpos($query, 'cheapest_price_speed') !== false && strpos($query, 'id_housing_package') !== false && strpos($query, 'hp-123') !== false) {
                return [$row];
            }
            return [];
        });
        Registry::getInstance()->add('db', $db);

        $pkg = new HousingPackage();
        $pkg->setId('hp-123');
        $pkg->id_media_object = 100;

        $result = $pkg->getCheapestPrice();

        $this->assertNotNull($result);
        $this->assertSame(4899.99, (float)$result->price_total);
        $this->assertSame('hp-123', $result->id_housing_package);
    }

    public function testBookingPackageGetCheapestPriceDelegatesToMediaObject(): void
    {
        $row = $this->createCheapestPriceRow(1, 3200.00, null, 'bp-456');
        $db = $this->createMockDbForCheapestPriceSpeed([$row]);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($row) {
            if (strpos($query, 'cheapest_price_speed') !== false && strpos($query, 'id_booking_package') !== false && strpos($query, 'bp-456') !== false) {
                return [$row];
            }
            return [];
        });
        Registry::getInstance()->add('db', $db);

        $pkg = new BookingPackage();
        $pkg->setId('bp-456');
        $pkg->id_media_object = 100;

        $result = $pkg->getCheapestPrice();

        $this->assertNotNull($result);
        $this->assertSame(3200.00, (float)$result->price_total);
        $this->assertSame('bp-456', $result->id_booking_package);
    }

    public function testHousingPackageGetCheapestPriceReturnsNullWhenNoPrices(): void
    {
        Registry::getInstance()->add('db', $this->createMockDbForCheapestPriceSpeed([]));

        $pkg = new HousingPackage();
        $pkg->setId('hp-empty');
        $pkg->id_media_object = 100;

        $result = $pkg->getCheapestPrice();

        $this->assertNull($result);
    }

    public function testWrapperInheritsStateFallback(): void
    {
        $rowRequest = $this->createCheapestPriceRow(1, 3200, 'hp-1', null);
        $rowRequest->state = 1;
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($rowRequest) {
            if (strpos($query, 'cheapest_price_speed') === false) {
                return [];
            }
            if (strpos($query, 'state = 3') !== false) {
                return [];
            }
            if (strpos($query, 'state = 1') !== false && strpos($query, 'id_housing_package') !== false) {
                return [$rowRequest];
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

        $pkg = new HousingPackage();
        $pkg->setId('hp-1');
        $pkg->id_media_object = 100;

        $result = $pkg->getCheapestPrice();

        $this->assertNotNull($result, 'State fallback (3→1) should yield on-request price');
        $this->assertSame(1, (int)$result->state);
    }
}
