<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Registry;
use Pressmind\Search\Condition\MongoDB\BoardType;
use Pressmind\Search\Condition\MongoDB\Category;
use Pressmind\Search\Condition\MongoDB\Code;
use Pressmind\Search\Condition\MongoDB\DateRange;
use Pressmind\Search\Condition\MongoDB\DurationRange;
use Pressmind\Search\Condition\MongoDB\Guaranteed;
use Pressmind\Search\Condition\MongoDB\Group;
use Pressmind\Search\Condition\MongoDB\ObjectType;
use Pressmind\Search\Condition\MongoDB\Occupancy;
use Pressmind\Search\Condition\MongoDB\PriceRange;
use Pressmind\Search\Condition\MongoDB\Running;
use Pressmind\Search\Condition\MongoDB\SoldOut;
use Pressmind\Search\Condition\MongoDB\TransportType;
use Pressmind\Search\MongoDB;
use Pressmind\Search\Paginator;
use Pressmind\Search\SearchType;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;
use Pressmind\Tests\Integration\FixtureLoader;

/**
 * Integration tests for Search\MongoDB: condition management, query construction,
 * aggregation pipeline building, sort handling, pagination, and result processing.
 *
 * Tests against a real MongoDB instance with seeded test data.
 */
class MongoDBQueryBuilderTest extends AbstractIntegrationTestCase
{
    private const TEST_COLLECTION = 'best_price_search_based_de_origin_0';
    private const DESC_COLLECTION = 'description_de_origin_0';

    protected function setUp(): void
    {
        parent::setUp();
        $this->addSearchConfigToRegistry();
        MongoDB::clearConnectionCache();
    }

    protected function tearDown(): void
    {
        if ($this->mongoDb !== null) {
            $this->mongoDb->selectCollection(self::TEST_COLLECTION)->drop();
            $this->mongoDb->selectCollection(self::DESC_COLLECTION)->drop();
        }
        MongoDB::clearConnectionCache();
        parent::tearDown();
    }

    private function addSearchConfigToRegistry(): void
    {
        $mongoUri = getenv('MONGODB_URI');
        $mongoDb = getenv('MONGODB_DB');
        if (empty($mongoUri) || empty($mongoDb)) {
            return;
        }
        $config = $this->getIntegrationConfig();
        $config['data'] = [
            'search_mongodb' => [
                'database' => ['uri' => $mongoUri, 'db' => $mongoDb],
                'search' => [
                    'allow_invalid_offers' => false,
                    'order_by_primary_object_type_priority' => false,
                    'touristic' => [
                        'duration_ranges' => [[1, 7], [8, 14]],
                    ],
                ],
            ],
            'languages' => ['allowed' => ['de', 'en'], 'default' => 'de'],
            'search_opensearch' => ['enabled' => false, 'enabled_in_mongo_search' => false],
            'touristic' => ['generate_offer_for_each_startingpoint_option' => false],
            'media_types_allowed_visibilities' => [],
            'media_types_fulltext_index_fields' => [],
        ];
        Registry::getInstance()->add('config', $config);
    }

    private function requireMongo(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
    }

    private function seedTestDocuments(): void
    {
        $now = new \DateTime();
        $futureDep = (clone $now)->modify('+30 days')->format(DATE_RFC3339_EXTENDED);
        $farDep = (clone $now)->modify('+90 days')->format(DATE_RFC3339_EXTENDED);

        $docs = [
            [
                '_id' => 1001,
                'id_media_object' => 1001,
                'id_object_type' => 100,
                'code' => ['TEST-001'],
                'url' => '/test/1001',
                'valid_from' => null,
                'valid_to' => null,
                'visibility' => 30,
                'recommendation_rate' => 85,
                'sales_priority' => 'A000001',
                'sold_out' => false,
                'is_running' => false,
                'has_price' => true,
                'has_guaranteed_departures' => false,
                'departure_date_count' => 2,
                'groups' => ['brand-a'],
                'categories' => [
                    ['id_item' => 'cat1', 'name' => 'Europe', 'field_name' => 'region', 'level' => 0, 'id_tree' => 't1', 'id_parent' => null, 'path_str' => ['Europe'], 'path_ids' => ['cat1']],
                ],
                'prices' => [
                    [
                        'price_total' => 599.0,
                        'duration' => 7,
                        'occupancy' => 2,
                        'transport_type' => 'BUS',
                        'option_board_type' => 'HP',
                        'state' => 100,
                        'date_departures' => [$futureDep, $farDep],
                        'guaranteed_departures' => [],
                        'earlybird_discount' => 0,
                        'earlybird_discount_f' => 0,
                        'earlybird_name' => null,
                        'earlybird_discount_date_to' => null,
                        'option_name' => 'Standard',
                        'price_regular_before_discount' => 599.0,
                        'housing_package_name' => null,
                        'housing_package_id_name' => null,
                        'price_mix' => 'date_housing',
                        'quota_pax' => 20,
                    ],
                ],
                'best_price_meta' => ['price_total' => 599.0, 'duration' => 7],
                'last_modified_date' => $now->format(DATE_RFC3339_EXTENDED),
                'fulltext' => 'Reise nach Europa Busreise',
                'object_type_order' => 0,
            ],
            [
                '_id' => 1002,
                'id_media_object' => 1002,
                'id_object_type' => 100,
                'code' => ['TEST-002'],
                'url' => '/test/1002',
                'valid_from' => null,
                'valid_to' => null,
                'visibility' => 30,
                'recommendation_rate' => 92,
                'sales_priority' => 'B000002',
                'sold_out' => false,
                'is_running' => true,
                'has_price' => true,
                'has_guaranteed_departures' => true,
                'departure_date_count' => 1,
                'groups' => ['brand-b'],
                'categories' => [
                    ['id_item' => 'cat2', 'name' => 'Asia', 'field_name' => 'region', 'level' => 0, 'id_tree' => 't1', 'id_parent' => null, 'path_str' => ['Asia'], 'path_ids' => ['cat2']],
                ],
                'prices' => [
                    [
                        'price_total' => 1299.0,
                        'duration' => 14,
                        'occupancy' => 2,
                        'transport_type' => 'FLUG',
                        'option_board_type' => 'AI',
                        'state' => 100,
                        'date_departures' => [$farDep],
                        'guaranteed_departures' => [$farDep],
                        'earlybird_discount' => 50,
                        'earlybird_discount_f' => 0.05,
                        'earlybird_name' => 'Early Bird 5%',
                        'earlybird_discount_date_to' => $farDep,
                        'option_name' => 'Deluxe',
                        'price_regular_before_discount' => 1349.0,
                        'housing_package_name' => null,
                        'housing_package_id_name' => null,
                        'price_mix' => 'date_housing',
                        'quota_pax' => 5,
                    ],
                ],
                'best_price_meta' => ['price_total' => 1299.0, 'duration' => 14],
                'last_modified_date' => $now->format(DATE_RFC3339_EXTENDED),
                'fulltext' => 'Fernreise Asien Flugreise',
                'object_type_order' => 0,
            ],
        ];

        $descDocs = [
            ['_id' => 1001, 'description' => ['title' => 'Europa Tour', 'teaser' => 'Short bus trip']],
            ['_id' => 1002, 'description' => ['title' => 'Asien Deluxe', 'teaser' => 'Premium flight tour']],
        ];

        $coll = $this->mongoDb->selectCollection(self::TEST_COLLECTION);
        $coll->drop();
        $coll->insertMany($docs);

        $descColl = $this->mongoDb->selectCollection(self::DESC_COLLECTION);
        $descColl->drop();
        $descColl->insertMany($descDocs);
    }

    /**
     * One media object: cheapest occupancy-2 price only in December,
     * a more expensive occupancy-2 price in June. Filter min/max must span both.
     */
    private function seedCheapestPriceVsAllDeparturesDocument(): void
    {
        $now = new \DateTime();
        $june = '2026-06-15T00:00:00.000+00:00';
        $december = '2026-12-02T00:00:00.000+00:00';
        $basePrice = [
            'duration' => 8,
            'occupancy' => 2,
            'transport_type' => 'BUS',
            'option_board_type' => 'HP',
            'state' => 100,
            'guaranteed_departures' => [],
            'earlybird_discount' => 0,
            'earlybird_discount_f' => 0,
            'earlybird_name' => null,
            'earlybird_discount_date_to' => null,
            'option_name' => 'Standard',
            'housing_package_name' => null,
            'housing_package_id_name' => null,
            'price_mix' => 'date_housing',
            'quota_pax' => 10,
        ];

        $docs = [
            [
                '_id' => 2001,
                'id_media_object' => 2001,
                'id_object_type' => 100,
                'code' => ['TEST-CHEAPEST-DATES'],
                'url' => '/test/2001',
                'valid_from' => null,
                'valid_to' => null,
                'visibility' => 30,
                'recommendation_rate' => 80,
                'sales_priority' => 'A000001',
                'sold_out' => false,
                'is_running' => false,
                'has_price' => true,
                'has_guaranteed_departures' => false,
                'departure_date_count' => 2,
                'groups' => ['grimm_reisen'],
                'categories' => [],
                'prices' => [
                    array_merge($basePrice, [
                        'price_total' => 400.0,
                        'price_regular_before_discount' => 400.0,
                        'date_departures' => [$december],
                    ]),
                    array_merge($basePrice, [
                        'price_total' => 900.0,
                        'price_regular_before_discount' => 900.0,
                        'date_departures' => [$june],
                    ]),
                ],
                'best_price_meta' => ['price_total' => 400.0, 'duration' => 8],
                'last_modified_date' => $now->format(DATE_RFC3339_EXTENDED),
                'fulltext' => 'Hotel Flora Kurreise',
                'object_type_order' => 0,
            ],
        ];

        $coll = $this->mongoDb->selectCollection(self::TEST_COLLECTION);
        $coll->drop();
        $coll->insertMany($docs);

        $descColl = $this->mongoDb->selectCollection(self::DESC_COLLECTION);
        $descColl->drop();
        $descColl->insertMany([
            ['_id' => 2001, 'description' => ['title' => 'Hotel Flora', 'teaser' => 'Marienbad']],
        ]);
    }

    private function toDateString($value): string
    {
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return $value->toDateTime()->format('Y-m-d');
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_array($value) && isset($value['$date'])) {
            return (new \DateTime($value['$date']))->format('Y-m-d');
        }
        if (is_object($value) && isset($value->{'$date'})) {
            return (new \DateTime($value->{'$date'}))->format('Y-m-d');
        }
        if (is_string($value)) {
            return (new \DateTime($value))->format('Y-m-d');
        }
        $this->fail('Unexpected date type: ' . get_debug_type($value));
    }

    // --- Condition management ---

    public function testAddAndListConditions(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);

        $search->addCondition('ot', new ObjectType([100]));
        $search->addCondition('pr', new PriceRange(100, 500));

        $list = $search->listConditions();
        $this->assertContains('ObjectType', $list);
        $this->assertContains('PriceRange', $list);
    }

    public function testHasConditionReturnsTrueForAdded(): void
    {
        $this->requireMongo();
        $search = new MongoDB([new ObjectType([100])], ['price_total' => 'asc'], 'de', 0);
        $this->assertTrue($search->hasCondition('ObjectType'));
        $this->assertFalse($search->hasCondition('PriceRange'));
    }

    public function testGetConditionByType(): void
    {
        $this->requireMongo();
        $ot = new ObjectType([100, 200]);
        $search = new MongoDB([$ot], ['price_total' => 'asc'], 'de', 0);

        $found = $search->getConditionByType('ObjectType');
        $this->assertSame([100, 200], $found->getObjectTypes());
    }

    public function testGetConditionByTypeMissing(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $this->assertFalse($search->getConditionByType('NonExistent'));
    }

    public function testRemoveCondition(): void
    {
        $this->requireMongo();
        $search = new MongoDB([
            new ObjectType([100]),
            new PriceRange(100, 500),
        ], ['price_total' => 'asc'], 'de', 0);

        $this->assertTrue($search->hasCondition('ObjectType'));
        $search->removeCondition('ObjectType');
        $this->assertFalse($search->hasCondition('ObjectType'));
        $this->assertTrue($search->hasCondition('PriceRange'));
    }

    // --- Collection name ---

    public function testGetCurrentCollectionName(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $this->assertSame(self::TEST_COLLECTION, $search->getCurrentCollectionName());
    }

    public function testGetCurrentCollectionNameWithAgency(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'en', 1, 'test_ag');
        $this->assertSame('best_price_search_based_en_origin_1_agency_test_ag', $search->getCurrentCollectionName());
    }

    // --- buildQuery pipeline structure ---

    public function testBuildQueryReturnsPipelineArray(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $pipeline = $search->buildQuery();

        $this->assertIsArray($pipeline);
        $this->assertNotEmpty($pipeline);

        $stageTypes = array_map(function ($stage) {
            return array_key_first($stage);
        }, $pipeline);
        $this->assertContains('$match', $stageTypes, 'Pipeline should contain $match');
        $this->assertContains('$facet', $stageTypes, 'Pipeline should contain $facet');
        $this->assertContains('$project', $stageTypes, 'Pipeline should contain $project');
    }

    public function testBuildQueryAsJsonReturnsValidJson(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $json = $search->buildQueryAsJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
    }

    public function testBuildQueryWithConditions(): void
    {
        $this->requireMongo();
        $search = new MongoDB([
            new ObjectType([100]),
            new PriceRange(200, 800),
            new DurationRange(3, 14),
        ], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));

        $pipeline = $search->buildQuery();
        $json = json_encode($pipeline);

        $this->assertStringContainsString('id_object_type', $json);
        $this->assertStringContainsString('price_total', $json);
    }

    // --- Sort variants ---

    public function testBuildQuerySortByPriceAsc(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $json = json_encode($search->buildQuery());
        $this->assertStringContainsString('"prices.price_total":1', str_replace(' ', '', $json));
    }

    public function testBuildQuerySortByPriceDesc(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'desc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $json = json_encode($search->buildQuery());
        $this->assertStringContainsString('"prices.price_total":-1', str_replace(' ', '', $json));
    }

    public function testBuildQuerySortByDateDeparture(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['date_departure' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $json = json_encode($search->buildQuery());
        $this->assertStringContainsString('fst_date_departure', $json);
    }

    public function testBuildQuerySortByRecommendationRate(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['recommendation_rate' => 'desc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $json = json_encode($search->buildQuery());
        $this->assertStringContainsString('recommendation_rate', $json);
    }

    public function testBuildQuerySortByPriority(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['priority' => ''], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $json = json_encode($search->buildQuery());
        $this->assertStringContainsString('sales_priority', $json);
    }

    public function testBuildQuerySortByValidFrom(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['valid_from' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $json = json_encode($search->buildQuery());
        $this->assertStringContainsString('valid_from', $json);
    }

    public function testBuildQuerySortByCustomOrder(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['custom_order.destination' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $json = json_encode($search->buildQuery());
        $this->assertStringContainsString('custom_order.destination', $json);
    }

    // --- Pagination in pipeline ---

    public function testBuildQueryWithPaginationIncludesSlice(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(5, 2));
        $json = json_encode($search->buildQuery());

        $this->assertStringContainsString('$slice', $json);
        $this->assertStringContainsString('currentPage', $json);
        $this->assertStringContainsString('pages', $json);
    }

    public function testBuildQueryFilterDepartureBoundsUseAllPricesNotCheapestReduce(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $search->setGetFilters(true);
        $pipeline = $search->buildQuery();
        $json = json_encode($pipeline);

        $this->assertStringContainsString('filter_min_departure', $json);
        $this->assertStringContainsString('filter_max_departure', $json);
        $this->assertStringContainsString('departureBounds', $json);
        $this->assertStringContainsString('$departureBounds.minDeparture', $json);
        $this->assertStringNotContainsString('$prices.prices.date_departures', $json);

        $projectWithBounds = null;
        $facet = null;
        $addFields = null;
        foreach ($pipeline as $stage) {
            $key = array_key_first($stage);
            if ($key === '$project' && isset($stage['$project']['filter_min_departure'])) {
                $projectWithBounds = $stage['$project'];
            }
            if ($key === '$facet') {
                $facet = $stage['$facet'];
            }
            if ($key === '$addFields' && isset($stage['$addFields']['minDeparture'])) {
                $addFields = $stage['$addFields'];
            }
        }

        $this->assertNotNull($projectWithBounds, 'Filter $project must keep departure bounds before cheapest-price $reduce');
        $this->assertArrayHasKey('prices', $projectWithBounds);
        $this->assertArrayHasKey('$reduce', $projectWithBounds['prices']);
        $this->assertArrayHasKey('filter_min_departure', $projectWithBounds);
        $this->assertArrayHasKey('filter_max_departure', $projectWithBounds);

        $this->assertIsArray($facet);
        $this->assertArrayHasKey('departureBounds', $facet);
        $this->assertSame('$filter_min_departure', $facet['departureBounds'][0]['$group']['minDeparture']['$min']);
        $this->assertSame('$filter_max_departure', $facet['departureBounds'][0]['$group']['maxDeparture']['$max']);

        $this->assertIsArray($addFields);
        $this->assertSame('$departureBounds.minDeparture', $addFields['minDeparture']['$arrayElemAt'][0]);
        $this->assertSame('$departureBounds.maxDeparture', $addFields['maxDeparture']['$arrayElemAt'][0]);
    }

    public function testBuildQueryDateListKeepsDepartureMinMaxFromUnwoundPrices(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $search->setGetFilters(true);
        $pipeline = $search->buildQuery('date_list');
        $json = json_encode($pipeline);

        $this->assertStringNotContainsString('filter_min_departure', $json);
        $this->assertStringNotContainsString('departureBounds', $json);
        $this->assertStringContainsString('$prices.prices.date_departures', $json);
    }

    // --- getResult() with seeded data ---

    public function testGetResultEmptyCollection(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $options = ['skip_search_hooks' => true];
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, $options);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('documents', $result);
        $this->assertObjectHasProperty('total', $result);
        $this->assertSame(0, $result->total);
    }

    public function testGetResultWithSeededData(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $options = ['skip_search_hooks' => true];
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, $options);

        $this->assertSame(2, $result->total);
        $this->assertCount(2, $result->documents);
    }

    public function testGetResultSortedByPriceAsc(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $options = ['skip_search_hooks' => true];
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, $options);

        $this->assertSame(2, $result->total);
        $firstDoc = json_decode(json_encode($result->documents[0]), true);
        $secondDoc = json_decode(json_encode($result->documents[1]), true);
        $this->assertLessThanOrEqual(
            $secondDoc['prices']['price_total'],
            $firstDoc['prices']['price_total']
        );
    }

    public function testGetResultFilterByObjectType(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB(
            [new ObjectType([100])],
            ['price_total' => 'asc'],
            'de', 0
        );
        $search->setPaginator(Paginator::create(10, 1));
        $options = ['skip_search_hooks' => true];
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, $options);

        $this->assertSame(2, $result->total);
    }

    public function testGetResultFilterByObjectTypeNoMatch(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB(
            [new ObjectType([999])],
            ['price_total' => 'asc'],
            'de', 0
        );
        $search->setPaginator(Paginator::create(10, 1));
        $options = ['skip_search_hooks' => true];
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, $options);

        $this->assertSame(0, $result->total);
    }

    public function testGetResultFilterByPriceRange(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB(
            [new PriceRange(100, 700)],
            ['price_total' => 'asc'],
            'de', 0
        );
        $search->setPaginator(Paginator::create(10, 1));
        $options = ['skip_search_hooks' => true];
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, $options);

        $this->assertSame(1, $result->total);
        $doc = json_decode(json_encode($result->documents[0]), true);
        $this->assertSame(1001, $doc['id_media_object']);
    }

    public function testGetResultWithFiltersEnabled(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $options = ['skip_search_hooks' => true];
        $result = $search->getResult(true, false, 0, null, null, [30], SearchType::DEFAULT, $options);

        $this->assertObjectHasProperty('categoriesGrouped', $result);
        $this->assertObjectHasProperty('boardTypesGrouped', $result);
        $this->assertObjectHasProperty('transportTypesGrouped', $result);
        $this->assertObjectHasProperty('minPrice', $result);
        $this->assertObjectHasProperty('maxPrice', $result);
        $this->assertObjectHasProperty('minDuration', $result);
        $this->assertObjectHasProperty('maxDuration', $result);
        $this->assertObjectHasProperty('minDeparture', $result);
        $this->assertObjectHasProperty('maxDeparture', $result);
    }

    public function testFilterMinDepartureUsesAllPricesNotOnlyCheapest(): void
    {
        $this->requireMongo();
        $this->seedCheapestPriceVsAllDeparturesDocument();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $options = ['skip_search_hooks' => true];
        $result = $search->getResult(true, true, 0, null, null, [30], SearchType::DEFAULT, $options);

        $this->assertSame('2026-06-15', $this->toDateString($result->minDeparture));
        $this->assertSame('2026-12-02', $this->toDateString($result->maxDeparture));

        $searchItems = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $searchItems->setPaginator(Paginator::create(10, 1));
        $items = $searchItems->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, $options);
        $doc = json_decode(json_encode($items->documents[0]), true);
        $this->assertSame(400.0, $doc['prices']['price_total']);
        $this->assertSame('2026-12-02', $this->toDateString($doc['prices']['date_departures'][0]));
    }

    public function testGetResultPagination(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(1, 1));
        $options = ['skip_search_hooks' => true];
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, $options);

        $this->assertSame(2, $result->total);
        $this->assertCount(1, $result->documents);
        $this->assertSame(1, $result->currentPage);
        $this->assertSame(2.0, $result->pages);
    }

    public function testGetResultPaginationPage2(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(1, 2));
        $options = ['skip_search_hooks' => true];
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, $options);

        $this->assertSame(2, $result->total);
        $this->assertCount(1, $result->documents);
        $this->assertSame(2, $result->currentPage);
    }

    public function testGetResultDescriptionJoin(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $options = ['skip_search_hooks' => true];
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, $options);

        foreach ($result->documents as $doc) {
            $docArray = json_decode(json_encode($doc), true);
            $this->assertArrayHasKey('description', $docArray);
            $this->assertNotEmpty($docArray['description']);
        }
    }

    // --- Logging ---

    public function testGetLogWhenDisabled(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $log = $search->getLog();
        $this->assertIsArray($log);
        $this->assertStringContainsString('disabled', $log[0]);
    }

    public function testGetLogWhenEnabled(): void
    {
        $this->requireMongo();
        $config = Registry::getInstance()->get('config');
        $config['logging']['enable_advanced_object_log'] = true;
        Registry::getInstance()->add('config', $config);

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $log = $search->getLog();
        $this->assertIsArray($log);
        $this->assertNotEmpty($log);
        $this->assertStringContainsString('__construct', $log[0]);
    }

    // --- Static helper ---

    public function testGetCollectionNameStatic(): void
    {
        $this->assertSame(
            'best_price_search_based_de_origin_0',
            MongoDB::getCollectionName('best_price_search_based_', 'de', 0, null)
        );
    }

    public function testClearConnectionCache(): void
    {
        MongoDB::clearConnectionCache();
        $this->assertTrue(true, 'clearConnectionCache should not throw');
    }

    // --- prepareQuery ---

    public function testPrepareQueryCallsPrepareOnConditions(): void
    {
        $this->requireMongo();
        $search = new MongoDB(
            [new ObjectType([100])],
            ['price_total' => 'asc'],
            'de', 0
        );
        $prepared = $search->prepareQuery();
        $this->assertIsArray($prepared);
    }
}
