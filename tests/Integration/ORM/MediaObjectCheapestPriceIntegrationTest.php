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
     *
     * @param int $idMediaObject
     * @param int $rowId
     * @param DateTime $departure
     * @param float $priceTotal
     * @param int $optionOccupancy 2=double, 1=single
     * @param int $state 3=bookable, 1=request, 5=stop
     * @param string|null $idBookingPackage
     * @param string|null $idHousingPackage
     */
    private function insertCheapestPriceSpeedRow(
        int $idMediaObject,
        int $rowId,
        DateTime $departure,
        float $priceTotal = 999.99,
        int $optionOccupancy = 2,
        int $state = 3,
        ?string $idBookingPackage = null,
        ?string $idHousingPackage = null
    ): void {
        $arrival = (clone $departure)->modify('+7 days');
        $row = [
            'id' => $rowId,
            'id_media_object' => $idMediaObject,
            'price_total' => $priceTotal,
            'date_departure' => $departure->format('Y-m-d H:i:s'),
            'date_arrival' => $arrival->format('Y-m-d H:i:s'),
            'duration' => 7,
            'option_occupancy' => $optionOccupancy,
            'option_occupancy_min' => $optionOccupancy,
            'option_occupancy_max' => $optionOccupancy,
            'state' => $state,
            'transport_type' => 'bus',
            'transport_1_airport' => '',
            'transport_1_airport_name' => '',
        ];
        if ($idBookingPackage !== null) {
            $row['id_booking_package'] = $idBookingPackage;
        }
        if ($idHousingPackage !== null) {
            $row['id_housing_package'] = $idHousingPackage;
        }
        $this->db->insert('pmt2core_cheapest_price_speed', $row, false);
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

    public function testOccupancyFallbackWithRealDb(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(2, 'CP-OCC');
        $mo->create();
        $dep = $this->departureInFuture(25);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 2, self::TEST_ID_BASE + 40, $dep, 4899, 2, 3);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 2, self::TEST_ID_BASE + 41, $dep, 3399, 1, 3);

        $mo = new MediaObject(self::TEST_ID_BASE + 2, false);
        $filter = new \Pressmind\Search\CheapestPrice();
        $prices = $mo->getCheapestPrices($filter);
        $this->assertNotEmpty($prices);
        $first = $prices[0];
        $this->assertSame(2, (int) $first->option_occupancy, 'DZ should be preferred over EZ');
        $this->assertSame(4899.0, (float) $first->price_total);
    }

    public function testStateSortingWithRealDb(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(3, 'CP-STATE');
        $mo->create();
        $dep = $this->departureInFuture(22);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 3, self::TEST_ID_BASE + 50, $dep, 600, 2, 5);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 3, self::TEST_ID_BASE + 51, $dep, 800, 2, 1);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 3, self::TEST_ID_BASE + 52, $dep, 700, 2, 3);

        $mo = new MediaObject(self::TEST_ID_BASE + 3, false);
        $prices = $mo->getCheapestPrices(null);
        $this->assertNotEmpty($prices);
        $first = $prices[0];
        $this->assertSame(3, (int) $first->state, 'Bookable (3) should be first after state sorting');
        $this->assertSame(700.0, (float) $first->price_total);
    }

    public function testOccupancyPlusStateCombinationWithRealDb(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(4, 'CP-OCC-STATE');
        $mo->create();
        $dep = $this->departureInFuture(28);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 4, self::TEST_ID_BASE + 60, $dep, 3200, 2, 1);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 4, self::TEST_ID_BASE + 61, $dep, 3399, 1, 3);

        $mo = new MediaObject(self::TEST_ID_BASE + 4, false);
        $filter = new \Pressmind\Search\CheapestPrice();
        $one = $mo->getCheapestPrice($filter);
        $this->assertNotNull($one);
        $this->assertSame(2, (int) $one->option_occupancy, 'Occupancy (DZ) preferred over state (EZ bookable)');
        $this->assertSame(1, (int) $one->state);
        $this->assertSame(3200.0, (float) $one->price_total);
    }

    public function testHousingPackageWrapperWithRealDb(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(5, 'CP-HP');
        $mo->create();
        $dep = $this->departureInFuture(18);
        $this->insertCheapestPriceSpeedRow(self::TEST_ID_BASE + 5, self::TEST_ID_BASE + 70, $dep, 2100, 2, 3, null, 'hp-int-1');

        $pkg = new \Pressmind\ORM\Object\Touristic\Housing\Package();
        $pkg->setId('hp-int-1');
        $pkg->id_media_object = self::TEST_ID_BASE + 5;
        $result = $pkg->getCheapestPrice();
        $this->assertNotNull($result);
        $this->assertSame('hp-int-1', $result->id_housing_package);
        $this->assertSame(2100.0, (float) $result->price_total);
    }
}
