<?php

namespace Pressmind\Tests\Integration\ORM;

use DateTime;
use Pressmind\DB\Scaffolder\Mysql as ScaffolderMysql;
use Custom\MediaType\Pauschalreise;
use Pressmind\ORM\Object\Brand;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Season;
use Pressmind\Registry;
use Pressmind\Search\CalendarFilter;
use Pressmind\Search\MongoDB;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;
use Pressmind\Tests\Integration\FixtureDateHelper;

/**
 * E2E tests for MediaObject: getCalendar with MongoDB, create/delete MongoDB Index and Calendar,
 * Pretty-URL roundtrip. Uses FixtureDateHelper for date-relative fixture data.
 * Requires MySQL and MongoDB from ENV.
 */
class MediaObjectE2ETest extends AbstractIntegrationTestCase
{
    use FixtureDateHelper;

    private const TEST_ID_BASE = 900000;
    private const CALENDAR_COLLECTION = 'calendar_origin_0';

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDateBase = new DateTime('today');
        if ($this->db === null || $this->mongoDb === null) {
            return;
        }
        $this->addE2EConfig();
        MongoDB::clearConnectionCache();
        $this->ensureTables();
        $this->cleanTestData();
    }

    protected function tearDown(): void
    {
        if ($this->mongoDb !== null) {
            try {
                $this->mongoDb->selectCollection(self::CALENDAR_COLLECTION)->drop();
            } catch (\Throwable $e) {
                // ignore
            }
            MongoDB::clearConnectionCache();
        }
        if ($this->db !== null) {
            $this->cleanTestData();
        }
        parent::tearDown();
    }

    private function addE2EConfig(): void
    {
        $mongoUri = getenv('MONGODB_URI');
        $mongoDbName = getenv('MONGODB_DB');
        if (empty($mongoUri) || empty($mongoDbName)) {
            return;
        }
        $config = Registry::getInstance()->get('config');
        $config['data'] = $config['data'] ?? [];
        $config['data']['search_mongodb'] = [
            'enabled' => true,
            'database' => ['uri' => $mongoUri, 'db' => $mongoDbName],
            'search' => [
                'build_for' => [
                    1 => [['origin' => 0, 'language' => 'de']],
                ],
                'touristic' => [
                    'occupancies' => [2],
                    'duration_ranges' => [[1, 21]],
                ],
            ],
        ];
        $config['data']['languages'] = ['default' => 'de', 'allowed' => ['de', 'en']];
        $config['data']['media_types'] = [1 => 'pauschalreise'];
        $config['data']['media_types_pretty_url'] = [
            1 => [
                'fields' => ['name'],
                'separator' => '-',
                'strategy' => 'unique',
                'prefix' => '/',
                'suffix' => '',
            ],
        ];
        $config['data']['touristic'] = $config['data']['touristic'] ?? [];
        $config['data']['media_types_allowed_visibilities'] = $config['data']['media_types_allowed_visibilities'] ?? [1 => [30]];
        $config['data']['media_types_fulltext_index_fields'] = $config['data']['media_types_fulltext_index_fields'] ?? [];
        $config['data']['primary_media_type_ids'] = $config['data']['primary_media_type_ids'] ?? [1];
        Registry::getInstance()->add('config', $config);
    }

    private function requireDbAndMongo(): void
    {
        if ($this->db === null) {
            $this->markTestSkipped('MySQL required');
        }
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
    }

    private function ensureTables(): void
    {
        $ns = 'Pressmind\\ORM\\Object\\';
        $models = [
            $ns . 'MediaObject',
            $ns . 'Route',
            $ns . 'Brand',
            $ns . 'Season',
            $ns . 'Agency',
            $ns . 'AgencyToMediaObject',
            $ns . 'FulltextSearch',
            $ns . 'CheapestPriceSpeed',
            $ns . 'Port',
            $ns . 'MediaObject\\MyContent',
            $ns . 'MediaObject\\ManualCheapestPrice',
            $ns . 'Touristic\\Base',
            $ns . 'Touristic\\Booking\\Package',
            $ns . 'Touristic\\Housing\\Package',
            $ns . 'Touristic\\Housing\\Package\\DescriptionLink',
            $ns . 'Touristic\\Date',
            $ns . 'Touristic\\Date\\Attribute',
            $ns . 'Touristic\\Transport',
            $ns . 'Touristic\\Option',
            $ns . 'Touristic\\Option\\Discount',
            $ns . 'Touristic\\Option\\Discount\\Scale',
            $ns . 'Touristic\\EarlyBirdDiscountGroup',
            $ns . 'Touristic\\EarlyBirdDiscountGroup\\Item',
            $ns . 'Touristic\\EarlyPaymentDiscountGroup',
            $ns . 'Touristic\\EarlyPaymentDiscountGroup\\Item',
            $ns . 'Touristic\\SeasonalPeriod',
            $ns . 'Touristic\\Startingpoint',
            $ns . 'Touristic\\Startingpoint\\Option',
            $ns . 'Touristic\\Startingpoint\\Option\\ZipRange',
            $ns . 'Touristic\\Pickupservice',
            $ns . 'Touristic\\Insurance\\Group',
            $ns . 'Itinerary\\Variant',
            $ns . 'Itinerary\\Step',
            $ns . 'Itinerary\\Step\\Port',
            $ns . 'Itinerary\\Step\\Board',
            $ns . 'Itinerary\\Step\\Geopoint',
            $ns . 'Itinerary\\Step\\Section',
            $ns . 'Itinerary\\Step\\Section\\Content',
            $ns . 'Itinerary\\Step\\TextMediaObject',
            $ns . 'Itinerary\\Step\\DocumentMediaObject',
        ];
        foreach ($models as $className) {
            try {
                $scaffolder = new ScaffolderMysql(new $className());
                $scaffolder->run(true);
            } catch (\Throwable $e) {
            }
        }
        try {
            $scaffolder = new ScaffolderMysql(new Pauschalreise());
            $scaffolder->run(true);
        } catch (\Throwable $e) {
        }
    }

    private function cleanTestData(): void
    {
        $this->db->delete('pmt2core_routes', ['id_media_object >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_media_objects', ['id >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_seasons', ['id >= ?', self::TEST_ID_BASE]);
        $this->db->delete('pmt2core_brands', ['id >= ?', self::TEST_ID_BASE]);
    }

    private function insertBrandAndSeason(): void
    {
        $brand = new Brand();
        $brand->id = self::TEST_ID_BASE;
        $brand->name = 'E2E Test Brand';
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

    private function createMediaObject(int $idOffset = 0, string $code = 'E2E-MO'): MediaObject
    {
        $mo = new MediaObject(null, false);
        $mo->id = self::TEST_ID_BASE + $idOffset;
        $mo->id_pool = 1;
        $mo->id_object_type = 1;
        $mo->id_client = 1;
        $mo->id_brand = self::TEST_ID_BASE;
        $mo->id_season = self::TEST_ID_BASE;
        $mo->name = 'E2E Test Product';
        $mo->code = $code;
        $mo->visibility = 30;
        $mo->state = 50;
        $mo->hidden = 0;
        return $mo;
    }

    public function testGetCalendarWithMongoDbData(): void
    {
        $this->requireDbAndMongo();
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'E2E-CAL');
        $mo->create();
        $collection = $this->mongoDb->selectCollection(self::CALENDAR_COLLECTION);
        $now = new \DateTime('+30 days');
        $dayOfMonth = (int) $now->format('j');
        $daysInMonth = (int) $now->format('t');
        $days = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayObj = (object) [
                'date' => $now->format('Y-m-') . str_pad($d, 2, '0', STR_PAD_LEFT),
            ];
            if ($d === $dayOfMonth) {
                $dayObj->cheapest_price = (object) [
                    'id' => 1,
                    'id_media_object' => self::TEST_ID_BASE + 1,
                    'id_booking_package' => 1,
                    'id_housing_package' => 1,
                    'id_date' => 1,
                    'option_occupancy' => 2,
                    'transport_type' => 'bus',
                    'price_total' => 499.00,
                    'date_departure' => $now->format(DATE_RFC3339_EXTENDED),
                    'date_arrival' => (new \DateTime('+37 days'))->format(DATE_RFC3339_EXTENDED),
                    'guaranteed' => false,
                    'saved' => false,
                    'id_option' => 1,
                    'earlybird_discount_date_to' => null,
                    'earlybird_discount' => 0,
                    'earlybird_discount_f' => '',
                    'earlybird_name' => '',
                    'price_regular_before_discount' => 499.00,
                    'price_option_pseudo' => 0,
                    'id_transport_1' => 0,
                    'id_transport_2' => 0,
                ];
            }
            $days[] = $dayObj;
        }
        $monthObj = (object) [
            'year' => $now->format('Y'),
            'month' => $now->format('n'),
            'days' => $days,
            'is_bookable' => true,
        ];
        $from = (clone $now)->modify('first day of this month');
        $to = (clone $now)->modify('first day of next month');
        $doc = [
            'id_media_object' => self::TEST_ID_BASE + 1,
            'transport_type' => 'bus',
            'occupancy' => 2,
            'booking_package' => (object) ['duration' => 7],
            'month' => [$monthObj],
            'from' => $from->format(DATE_RFC3339_EXTENDED),
            'to' => $to->format(DATE_RFC3339_EXTENDED),
            'bookable_date_count' => 1,
        ];
        $collection->insertOne($doc);

        $mo = new MediaObject(self::TEST_ID_BASE + 1, false);
        $filter = new CalendarFilter();
        $calendar = $mo->getCalendar($filter, 3, 0, null);
        $this->assertIsObject($calendar);
        $this->assertObjectHasProperty('filter', $calendar);
        $this->assertArrayHasKey('transport_types', $calendar->filter);
        $this->assertArrayHasKey('durations', $calendar->filter);
        $this->assertNotEmpty($calendar->filter['transport_types']);
        $this->assertArrayHasKey('bus', $calendar->filter['transport_types']);
        $this->assertNotNull($calendar->calendar);
        $this->assertNotEmpty($calendar->calendar->month);
    }

    public function testCreateAndDeleteMongoDBIndexAndCalendar(): void
    {
        $this->requireDbAndMongo();
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'E2E-IDX');
        $mo->create();
        $mo = new MediaObject(self::TEST_ID_BASE + 1, false);
        $mo->createMongoDBIndex();
        $mo->createMongoDBCalendar();
        $mo->deleteMongoDBIndex();
        $mo->deleteMongoDBCalendar();
        $this->addToAssertionCount(1);
    }

    public function testPrettyUrlRoundtrip(): void
    {
        $this->requireDbAndMongo();
        $this->insertBrandAndSeason();
        $mo = $this->createMediaObject(1, 'E2E-URL');
        $mo->create();
        $urls = $mo->buildPrettyUrls('de');
        $this->assertIsArray($urls);
        $this->assertCount(1, $urls);
        $route = $urls[0];
        $this->db->insert('pmt2core_routes', [
            'id' => self::TEST_ID_BASE,
            'id_media_object' => self::TEST_ID_BASE + 1,
            'id_object_type' => 1,
            'route' => $route,
            'language' => 'de',
        ], false);
        $result = MediaObject::getByPrettyUrl($route, null, 'de', null);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(self::TEST_ID_BASE + 1, $result[0]->id);
        $loaded = new MediaObject(self::TEST_ID_BASE + 1, false, true);
        $this->assertSame($route, $loaded->getPrettyUrl(null));
    }
}
