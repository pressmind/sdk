<?php

namespace Pressmind\Tests\Integration\ORM;

use DateTime;
use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Pressmind\ORM\Object\Brand;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Season;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Route;
use Pressmind\ORM\Object\Touristic\Option;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;
use Pressmind\Tests\Integration\FixtureDateHelper;

/**
 * Integration tests for MediaObject getAllAvailableDates, getAllAvailableOptions,
 * getAllAvailableTransports. Uses FixtureDateHelper for dates with departure in the future.
 */
class MediaObjectAvailabilityIntegrationTest extends AbstractIntegrationTestCase
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
            new Date(),
            new Option(),
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
        $this->db->delete('pmt2core_touristic_dates', ['id_media_object >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_touristic_options', ['id_media_object >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_media_objects', ['id >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_seasons', ['id >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_brands', ['id >= ?', self::TEST_ID_BASE]);
    }

    private function insertBrandAndSeason(): void
    {
        $brand = new Brand();
        $brand->id = self::TEST_ID_BASE;
        $brand->name = 'Availability Test Brand';
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

    private function createMediaObject(int $idOffset = 0, string $code = 'AV-INT-MO'): MediaObject
    {
        $mo = new MediaObject(null, false);
        $mo->id = self::TEST_ID_BASE + $idOffset;
        $mo->id_pool = 1;
        $mo->id_object_type = 1;
        $mo->id_client = 1;
        $mo->id_brand = self::TEST_ID_BASE;
        $mo->id_season = self::TEST_ID_BASE;
        $mo->name = 'Availability Integration Product';
        $mo->code = $code;
        $mo->visibility = 30;
        $mo->state = 50;
        $mo->hidden = 0;
        return $mo;
    }

    private function insertDateFixture(int $idMediaObject, string $dateId, DateTime $departure): void
    {
        $arrival = (clone $departure)->modify('+7 days');
        $this->db->insert('pmt2core_touristic_dates', [
            'id' => $dateId,
            'id_media_object' => $idMediaObject,
            'id_booking_package' => 'avail-bp-1',
            'departure' => $departure->format('Y-m-d H:i:s'),
            'arrival' => $arrival->format('Y-m-d H:i:s'),
            'season' => 'all',
            'state' => 50,
        ], false);
    }

    private function insertOptionFixture(int $idMediaObject, string $optionId): void
    {
        $this->db->insert('pmt2core_touristic_options', [
            'id' => $optionId,
            'id_media_object' => $idMediaObject,
            'id_booking_package' => 'avail-bp-1',
            'type' => 'housing_option',
            'price' => 0.0,
            'price_pseudo' => 0.0,
            'price_child' => 0.0,
            'occupancy' => 2,
            'occupancy_child' => 0,
            'renewal_duration' => 0,
            'renewal_price' => 0.0,
            'order' => 0,
            'booking_type' => 0,
            'state' => 50,
            'min_pax' => 1,
            'max_pax' => 4,
            'age_from' => 0,
            'age_to' => 99,
        ], false);
    }

    public function testGetAllAvailableDatesReturnsFutureDates(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'AV-DATES');
        $mo->create();
        $departure = $this->departureInFuture(25);
        $this->insertDateFixture(self::TEST_ID_BASE + 1, 'avail-date-1', $departure);

        $mo = new MediaObject(self::TEST_ID_BASE + 1, false);
        $dates = $mo->getAllAvailableDates();
        $this->assertIsArray($dates, 'getAllAvailableDates should return an array');
        $this->assertCount(1, $dates, 'One future date expected');
        $this->assertSame('avail-date-1', $dates[0]->id);
    }

    public function testGetAllAvailableDatesReturnsEmptyWhenNoFutureDates(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'AV-NODATES');
        $mo->create();
        $mo = new MediaObject(self::TEST_ID_BASE + 1, false);
        $dates = $mo->getAllAvailableDates();
        $this->assertIsArray($dates);
        $this->assertCount(0, $dates);
    }

    public function testGetAllAvailableOptionsReturnsOptions(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'AV-OPT');
        $mo->create();
        $this->insertOptionFixture(self::TEST_ID_BASE + 1, 'avail-opt-1');

        $mo = new MediaObject(self::TEST_ID_BASE + 1, false);
        $options = $mo->getAllAvailableOptions();
        $this->assertIsArray($options);
        $this->assertCount(1, $options);
        $this->assertSame('avail-opt-1', $options[0]->id);
    }

    public function testGetAllAvailableTransportsReturnsArray(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL not available');
        }
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'AV-TRANS');
        $mo->create();
        $mo = new MediaObject(self::TEST_ID_BASE + 1, false);
        $transports = $mo->getAllAvailableTransports();
        if ($transports === null) {
            $transports = [];
        }
        $this->assertIsArray($transports, 'getAllAvailableTransports should return an array or null');
        $this->assertCount(0, $transports, 'No dates/transports fixture, so empty expected');
    }
}
