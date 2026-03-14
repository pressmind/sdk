<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use DateTime;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;
use Pressmind\Search\CheapestPrice;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

/**
 * Unit tests for MediaObject cheapest price API: getCheapestPrice, getCheapestPrices,
 * getCheapestPricesOptions, insertCheapestPrice (minimal with empty booking_packages).
 */
class MediaObjectCheapestPriceTest extends AbstractTestCase
{
    private function createMediaObject(?int $id = null): MediaObject
    {
        return new MediaObject($id, false);
    }

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

    private function createCheapestPriceSpeedRow(int $id = 1, float $priceTotal = 999.99, string $dateDeparture = '2026-06-01 00:00:00', int $optionOccupancy = 2, int $state = 3): stdClass
    {
        $row = new stdClass();
        $row->id = $id;
        $row->id_media_object = 100;
        $row->price_total = $priceTotal;
        $row->date_departure = $dateDeparture;
        $row->duration = 7;
        $row->option_occupancy = $optionOccupancy;
        $row->state = $state;
        $row->transport_type = 'bus';
        $row->transport_1_airport = null;
        $row->transport_1_airport_name = null;
        $row->date_departure_from = $dateDeparture;
        $row->date_departure_to = $dateDeparture;
        return $row;
    }

    public function testGetCheapestPricesReturnsEmptyArrayWhenNoResults(): void
    {
        Registry::getInstance()->add('db', $this->createMockDbForCheapestPriceSpeed([]));

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getCheapestPrices();
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetCheapestPricesReturnsRowsFromMock(): void
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $row1 = $this->createCheapestPriceSpeedRow(1, 899.99, $futureDate);
        $row2 = $this->createCheapestPriceSpeedRow(2, 1099.99, $futureDate);

        Registry::getInstance()->add('db', $this->createMockDbForCheapestPriceSpeed([$row1, $row2]));

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getCheapestPrices();
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame(899.99, $result[0]->price_total);
        $this->assertSame(1099.99, $result[1]->price_total);
    }

    public function testGetCheapestPricesRespectsLimit(): void
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $rows = [
            $this->createCheapestPriceSpeedRow(1, 100, $futureDate),
            $this->createCheapestPriceSpeedRow(2, 200, $futureDate),
        ];
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($rows) {
            if (strpos($query, 'cheapest_price_speed') !== false) {
                if (strpos($query, 'LIMIT 0, 2') !== false) {
                    return $rows;
                }
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
        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getCheapestPrices(null, ['price_total' => 'ASC'], [0, 2]);
        $this->assertCount(2, $result);
    }

    public function testGetCheapestPriceReturnsFirstElementOrNull(): void
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $row = $this->createCheapestPriceSpeedRow(1, 599.99, $futureDate);
        Registry::getInstance()->add('db', $this->createMockDbForCheapestPriceSpeed([$row]));

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getCheapestPrice();
        $this->assertNotNull($result);
        $this->assertSame(599.99, $result->price_total);
    }

    public function testGetCheapestPriceReturnsNullWhenNoResults(): void
    {
        Registry::getInstance()->add('db', $this->createMockDbForCheapestPriceSpeed([]));

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $this->assertNull($mo->getCheapestPrice());
    }

    public function testGetCheapestPricesWithFiltersCallsDbWithWhereConditions(): void
    {
        $futureDate = (new DateTime('+60 days'))->format('Y-m-d 00:00:00');
        $row = $this->createCheapestPriceSpeedRow(1, 799.99, $futureDate);
        $db = $this->createMockDbForCheapestPriceSpeed([$row]);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($row) {
            if (strpos($query, 'cheapest_price_speed') !== false) {
                $this->assertStringContainsString('duration BETWEEN', $query);
                $this->assertStringContainsString('date_departure BETWEEN', $query);
                return [$row];
            }
            return [];
        });
        Registry::getInstance()->add('db', $db);

        $filters = new CheapestPrice();
        $filters->duration_from = 5;
        $filters->duration_to = 14;
        $filters->date_from = new DateTime('+30 days');
        $filters->date_to = new DateTime('+90 days');

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $result = $mo->getCheapestPrices($filters);
        $this->assertCount(1, $result);
    }

    public function testGetCheapestPricesOptionsReturnsStdClassWithExpectedKeys(): void
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $row = new stdClass();
        $row->duration = 7;
        $row->transport_type = 'bus';
        $row->transport_1_airport_name = null;
        $row->transport_1_airport = null;
        $row->option_occupancy = 2;
        $row->date_departure_from = $futureDate;
        $row->date_departure_to = $futureDate;
        $row->count = 5;

        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($row) {
            if (strpos($query, 'cheapest_price_speed') !== false && strpos($query, 'GROUP BY') !== false) {
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
        $result = $mo->getCheapestPricesOptions(null, null);
        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame(5, $result->count);
        $this->assertIsArray($result->duration);
        $this->assertIsArray($result->transport_type);
    }

    public function testGetCheapestPricesOptionsWithOriginAndAgency(): void
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $row = new stdClass();
        $row->duration = 7;
        $row->transport_type = 'flight';
        $row->transport_1_airport_name = 'FRA';
        $row->transport_1_airport = 'FRA';
        $row->option_occupancy = 2;
        $row->date_departure_from = $futureDate;
        $row->date_departure_to = $futureDate;
        $row->count = 3;

        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($row) {
            if (strpos($query, 'origin = 1') !== false && strpos($query, 'agency = "AGY"') !== false) {
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
        $result = $mo->getCheapestPricesOptions(1, 'AGY');
        $this->assertSame(3, $result->count);
    }

    /**
     * insertCheapestPrice with empty booking_packages: should not throw (logs "No booking packages found").
     */
    public function testInsertCheapestPriceWithEmptyBookingPackagesDoesNotThrow(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['touristic'] = [
            'max_offers_per_product' => 5000,
            'date_filter' => ['active' => false],
            'housing_option_filter' => ['active' => false],
            'transport_filter' => ['active' => false],
            'agency_based_option_and_prices' => ['enabled' => false],
        ];
        Registry::getInstance()->add('config', $config);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $mo->booking_packages = [];
        $mo->insertCheapestPrice();
        $this->addToAssertionCount(1);
    }

    // ------------------------------------------------------------------
    // Occupancy fallback (DZ -> EZ -> all) and state fallback
    // ------------------------------------------------------------------

    public function testOccupancyFallbackDoubleRoomFirst(): void
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $rowDz = $this->createCheapestPriceSpeedRow(1, 4899, $futureDate, 2, 3);
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($rowDz) {
            if (strpos($query, 'cheapest_price_speed') !== false && strpos($query, 'option_occupancy = 2') !== false) {
                return [$rowDz];
            }
            return [];
        });
        $this->attachDefaultDbMethods($db);
        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $filter = new CheapestPrice();
        $result = $mo->getCheapestPrices($filter);
        $this->assertCount(1, $result);
        $this->assertSame(4899.0, (float)$result[0]->price_total);
        $this->assertSame(2, (int)$result[0]->option_occupancy);
    }

    public function testOccupancyFallbackToSingleWhenNoDouble(): void
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $rowEz = $this->createCheapestPriceSpeedRow(2, 3399, $futureDate, 1, 3);
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($rowEz) {
            if (strpos($query, 'cheapest_price_speed') === false) {
                return [];
            }
            if (strpos($query, 'option_occupancy = 2') !== false) {
                return [];
            }
            if (strpos($query, 'option_occupancy = 1') !== false) {
                return [$rowEz];
            }
            return [];
        });
        $this->attachDefaultDbMethods($db);
        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $filter = new CheapestPrice();
        $result = $mo->getCheapestPrices($filter);
        $this->assertCount(1, $result);
        $this->assertSame(3399.0, (float)$result[0]->price_total);
        $this->assertSame(1, (int)$result[0]->option_occupancy);
    }

    public function testOccupancyFallbackToAllWhenNoDoubleOrSingle(): void
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $rowAll = $this->createCheapestPriceSpeedRow(3, 2999, $futureDate, 3, 3);
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $callCount = 0;
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($rowAll, &$callCount) {
            if (strpos($query, 'cheapest_price_speed') === false) {
                return [];
            }
            $callCount++;
            if (strpos($query, 'option_occupancy = 2') !== false || strpos($query, 'option_occupancy = 1') !== false) {
                return [];
            }
            return [$rowAll];
        });
        $this->attachDefaultDbMethods($db);
        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $filter = new CheapestPrice();
        $result = $mo->getCheapestPrices($filter);
        $this->assertCount(1, $result);
        $this->assertSame(2999.0, (float)$result[0]->price_total);
        $this->assertSame(3, (int)$result[0]->option_occupancy);
    }

    public function testStateFallbackBookableFirst(): void
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $row = $this->createCheapestPriceSpeedRow(1, 4899, $futureDate, 2, 3);
        Registry::getInstance()->add('db', $this->createMockDbForCheapestPriceSpeed([$row]));

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $filter = new CheapestPrice();
        $filter->state = 3;
        $filter->state_fallback_order = [3, 1, 5];
        $result = $mo->getCheapestPrices($filter);
        $this->assertCount(1, $result);
        $this->assertSame(3, (int)$result[0]->state);
    }

    public function testStateFallbackToRequestWhenNoBookable(): void
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $rowRequest = $this->createCheapestPriceSpeedRow(1, 3200, $futureDate, 2, 1);
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($rowRequest) {
            if (strpos($query, 'cheapest_price_speed') === false) {
                return [];
            }
            if (strpos($query, 'state = 3') !== false) {
                return [];
            }
            if (strpos($query, 'state = 1') !== false) {
                return [$rowRequest];
            }
            return [];
        });
        $this->attachDefaultDbMethods($db);
        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $filter = new CheapestPrice();
        $filter->state = 3;
        $filter->state_fallback_order = [3, 1, 5];
        $result = $mo->getCheapestPrices($filter);
        $this->assertCount(1, $result);
        $this->assertSame(1, (int)$result[0]->state);
    }

    public function testStateFallbackReturnsEmptyWhenAllEmpty(): void
    {
        $db = $this->createMockDbForCheapestPriceSpeed([]);
        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $filter = new CheapestPrice();
        $filter->state = 3;
        $filter->state_fallback_order = [3, 1, 5];
        $result = $mo->getCheapestPrices($filter);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testOccupancyDisableFallback(): void
    {
        $futureDate = (new DateTime('+30 days'))->format('Y-m-d 00:00:00');
        $row = $this->createCheapestPriceSpeedRow(1, 4899, $futureDate, 2, 3);
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function ($query) use ($row) {
            if (strpos($query, 'cheapest_price_speed') !== false) {
                $this->assertStringNotContainsString('option_occupancy = 2', $query, 'With fallback disabled, no occupancy filter');
                return [$row];
            }
            return [];
        });
        $this->attachDefaultDbMethods($db);
        Registry::getInstance()->add('db', $db);

        $mo = $this->createMediaObject();
        $mo->setId(100);
        $filter = new CheapestPrice();
        $filter->occupancies_disable_fallback = true;
        $result = $mo->getCheapestPrices($filter);
        $this->assertCount(1, $result);
    }

    private function attachDefaultDbMethods($db): void
    {
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
    }
}
