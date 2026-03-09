<?php

namespace Pressmind\Tests\Integration\ORM;

use DateTime;
use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\ORM\Object\Brand;
use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Route;
use Pressmind\ORM\Object\Season;
use Pressmind\ORM\Object\Touristic\Booking\Package as BookingPackage;
use Pressmind\Registry;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;
use Pressmind\Tests\Integration\FixtureDateHelper;
use stdClass;

/**
 * Integration tests for MediaObject getCheapestPrices, getCheapestPrice,
 * getCheapestPricesOptions, and insertCheapestPrice (reduced).
 * Uses FixtureDateHelper for date-relative fixtures (departure in future).
 */
class MediaObjectCheapestPriceIntegrationTest extends AbstractIntegrationTestCase
{
    use FixtureDateHelper;

    private const TEST_ID_BASE = 900000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDateBase = new DateTime('today');
        if ($this->db === null) {
            return;
        }
        $this->ensureTables();
        $this->cleanTestData();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->cleanTestData();
        }
        parent::tearDown();
    }

    private function ensureTables(): void
    {
        $objects = [
            new Brand(),
            new Season(),
            new MediaObject(),
            new Route(),
            new CheapestPriceSpeed(),
            new BookingPackage(),
        ];
        foreach ($objects as $obj) {
            try {
                $scaffolder = new ScaffolderMysql($obj);
                $scaffolder->run(true);
            } catch (\Throwable $e) {
                // table may already exist
            }
        }
    }

    private function cleanTestData(): void
    {
        $this->db->delete('pmt2core_cheapest_price_speed', ['id_media_object >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_media_objects', ['id >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_seasons', ['id >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_brands', ['id >= ?', self::TEST_ID_BASE]);
    }

    private function insertBrandAndSeason(): void
    {
        $brand = new Brand();
        $brand->id = self::TEST_ID_BASE;
        $brand->name = 'CheapestPrice Test Brand';
        $brand->create();
        $season = new Season();
        $season->id = self::TEST_ID_BASE;
        $season->name = 'Test Season';
        $season->active = 1;
        $season->season_from = new DateTime('2020-01-01');
        $season->season_to = new DateTime('2030-12-31');
        $season->time_of_year = 'all';
        $season->create();
    }

    private function createMediaObject(int $idOffset = 0, string $code = 'CP-INT-MO'): MediaObject
    {
        $mo = new MediaObject(null, false);
        $mo->id = self::TEST_ID_BASE + $idOffset;
        $mo->id_pool = 1;
        $mo->id_object_type = 1;
        $mo->id_client = 1;
        $mo->id_brand = self::TEST_ID_BASE;
        $mo->id_season = self::TEST_ID_BASE;
        $mo->name = 'CheapestPrice Integration Product';
        $mo->code = $code;
        $mo->visibility = 30;
        $mo->state = 50;
        $mo->hidden = 0;
        return $mo;
    }

    /**
     * Insert a minimal CheapestPriceSpeed row with departure in the future (FixtureDateHelper).
     */
    private function insertCheapestPriceSpeedRow(int $idMediaObject, int $rowId, DateTime $departure, float $priceTotal = 999.99): void
    {
        $arrival = (clone $departure)->modify('+7 days');
        $this->db->insert('pmt2core_cheapest_price_speed', [
            'id' => $rowId,
            'id_media_object' => $idMediaObject,
            'price_total' => $priceTotal,
            'date_departure' => $departure->format('Y-m-d H:i:s'),
            'date_arrival' => $arrival->format('Y-m-d H:i:s'),
            'duration' => 7,
            'option_occupancy' => 2,
            'option_occupancy_min' => 2,
            'option_occupancy_max' => 2,
            'state' => 50,
            'transport_type' => 'bus',
            'transport_1_airport' => '',
            'transport_1_airport_name' => '',
        ], false);
    }

    public function testGetCheapestPricesReturnsEntriesWithFutureDeparture(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'CP-GET-1');
        $mo->create();
        $departure = $this->departureInFuture(30);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 1, self::TEST_ID_BASE + 1, $departure, 499.99);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 1, self::TEST_ID_BASE + 2, $this->departureInFuture(35), 599.99);

        $mo = new MediaObject(self::TEST_ID_BASE + 1, false);
        $prices = $mo->getCheapestPrices(null);
        $this->assertIsArray($prices, 'getCheapestPrices should return an array');
        $this->assertGreaterThanOrEqual(2, count($prices), 'At least two cheapest price entries expected');
        $first = $prices[0];
        $this->assertSame(499.99, (float) $first->price_total, 'First entry should be lowest price');
        $this->assertSame(self::TEST_ID_BASE + 1, (int) $first->id_media_object);
    }

    public function testGetCheapestPriceReturnsSingleEntry(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'CP-ONE');
        $mo->create();
        $departure = $this->departureInFuture(14);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 1, self::TEST_ID_BASE + 10, $departure, 299.99);

        $mo = new MediaObject(self::TEST_ID_BASE + 1, false);
        $one = $mo->getCheapestPrice(null);
        $this->assertNotNull($one, 'getCheapestPrice should return one entry');
        $this->assertSame(299.99, (float) $one->price_total);
    }

    public function testGetCheapestPriceReturnsNullWhenNoFuturePrices(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'CP-NONE');
        $mo->create();
        $mo = new MediaObject(self::TEST_ID_BASE + 1, false);
        $one = $mo->getCheapestPrice(null);
        $this->assertNull($one, 'getCheapestPrice should return null when no prices');
    }

    public function testGetCheapestPricesOptionsReturnsStructure(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'CP-OPT');
        $mo->create();
        $departure = $this->departureInFuture(20);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 1, self::TEST_ID_BASE + 20, $departure);

        $mo = new MediaObject(self::TEST_ID_BASE + 1, false);
        $options = $mo->getCheapestPricesOptions(null, null);
        $this->assertInstanceOf(stdClass::class, $options);
        $this->assertObjectHasProperty('count', $options);
        $this->assertObjectHasProperty('duration', $options);
        $this->assertObjectHasProperty('transport_type', $options);
        $this->assertGreaterThanOrEqual(0, $options->count);
    }

    public function testInsertCheapestPriceWithNoBookingPackagesRemovesExistingRows(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'CP-INSERT');
        $mo->create();
        $departure = $this->departureInFuture(40);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 1, self::TEST_ID_BASE + 30, $departure);

        $countBefore = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_cheapest_price_speed WHERE id_media_object = ?', [self::TEST_ID_BASE + 1]);
        $this->assertGreaterThan(0, (int) $countBefore, 'Fixture row should exist');

        $mo = new MediaObject(self::TEST_ID_BASE + 1, false);
        $mo->insertCheapestPrice();

        $countAfter = $this->db->fetchOne('SELECT COUNT(*) FROM pmt2core_cheapest_price_speed WHERE id_media_object = ?', [self::TEST_ID_BASE + 1]);
        $this->assertSame(0, (int) $countAfter, 'insertCheapestPrice with no booking_packages should remove existing rows');
    }
}
