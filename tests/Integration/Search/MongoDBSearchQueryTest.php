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
use Pressmind\Search\Condition\MongoDB\MediaObject;
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

/**
 * Integration tests for Search\MongoDB: extended query construction,
 * condition interaction, aggregation pipeline variants, result filtering,
 * and edge cases not covered by MongoDBQueryBuilderTest.
 *
 * Closes coverage gaps in: getAgency, buildQueryAsJson with filters,
 * multi-condition interactions, sort combinations with data,
 * getResult with conditions filtering actual results, connection caching.
 */
class MongoDBSearchQueryTest extends AbstractIntegrationTestCase
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
                        'duration_ranges' => [[1, 7], [8, 14], [15, 28]],
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
        $dep30 = (clone $now)->modify('+30 days')->format(DATE_RFC3339_EXTENDED);
        $dep60 = (clone $now)->modify('+60 days')->format(DATE_RFC3339_EXTENDED);
        $dep120 = (clone $now)->modify('+120 days')->format(DATE_RFC3339_EXTENDED);

        $docs = [
            [
                '_id' => 3001,
                'id_media_object' => 3001,
                'id_object_type' => 100,
                'code' => ['SQ-001'],
                'url' => '/sq/3001',
                'valid_from' => null,
                'valid_to' => null,
                'visibility' => 30,
                'recommendation_rate' => 80,
                'sales_priority' => 'A000001',
                'sold_out' => false,
                'is_running' => false,
                'has_price' => true,
                'has_guaranteed_departures' => false,
                'departure_date_count' => 3,
                'groups' => ['group-alpha'],
                'categories' => [
                    ['id_item' => 'coast', 'name' => 'Coast', 'field_name' => 'region', 'level' => 0,
                     'id_tree' => 't1', 'id_parent' => null, 'path_str' => ['Coast'], 'path_ids' => ['coast']],
                ],
                'prices' => [
                    [
                        'price_total' => 450.0, 'duration' => 5, 'occupancy' => 2,
                        'transport_type' => 'BUS', 'option_board_type' => 'HP',
                        'state' => 100, 'date_departures' => [$dep30, $dep60],
                        'guaranteed_departures' => [], 'earlybird_discount' => 0,
                        'earlybird_discount_f' => 0, 'earlybird_name' => null,
                        'earlybird_discount_date_to' => null, 'option_name' => 'Standard',
                        'price_regular_before_discount' => 450.0, 'housing_package_name' => null,
                        'housing_package_id_name' => null, 'price_mix' => 'date_housing',
                        'quota_pax' => 25,
                    ],
                    [
                        'price_total' => 650.0, 'duration' => 7, 'occupancy' => 2,
                        'transport_type' => 'FLUG', 'option_board_type' => 'VP',
                        'state' => 100, 'date_departures' => [$dep120],
                        'guaranteed_departures' => [], 'earlybird_discount' => 0,
                        'earlybird_discount_f' => 0, 'earlybird_name' => null,
                        'earlybird_discount_date_to' => null, 'option_name' => 'Premium',
                        'price_regular_before_discount' => 650.0, 'housing_package_name' => null,
                        'housing_package_id_name' => null, 'price_mix' => 'date_housing',
                        'quota_pax' => 10,
                    ],
                ],
                'best_price_meta' => ['price_total' => 450.0, 'duration' => 5],
                'last_modified_date' => $now->format(DATE_RFC3339_EXTENDED),
                'fulltext' => 'Busreise Kueste',
                'object_type_order' => 0,
            ],
            [
                '_id' => 3002,
                'id_media_object' => 3002,
                'id_object_type' => 200,
                'code' => ['SQ-002'],
                'url' => '/sq/3002',
                'valid_from' => null,
                'valid_to' => null,
                'visibility' => 30,
                'recommendation_rate' => 95,
                'sales_priority' => 'A000002',
                'sold_out' => false,
                'is_running' => true,
                'has_price' => true,
                'has_guaranteed_departures' => true,
                'departure_date_count' => 1,
                'groups' => ['group-beta'],
                'categories' => [
                    ['id_item' => 'mountain', 'name' => 'Mountain', 'field_name' => 'region', 'level' => 0,
                     'id_tree' => 't1', 'id_parent' => null, 'path_str' => ['Mountain'], 'path_ids' => ['mountain']],
                ],
                'prices' => [
                    [
                        'price_total' => 1500.0, 'duration' => 14, 'occupancy' => 2,
                        'transport_type' => 'FLUG', 'option_board_type' => 'AI',
                        'state' => 100, 'date_departures' => [$dep60],
                        'guaranteed_departures' => [$dep60], 'earlybird_discount' => 100,
                        'earlybird_discount_f' => 0.05, 'earlybird_name' => 'Early 5%',
                        'earlybird_discount_date_to' => $dep60, 'option_name' => 'All Inclusive',
                        'price_regular_before_discount' => 1600.0, 'housing_package_name' => null,
                        'housing_package_id_name' => null, 'price_mix' => 'date_housing',
                        'quota_pax' => 3,
                    ],
                ],
                'best_price_meta' => ['price_total' => 1500.0, 'duration' => 14],
                'last_modified_date' => $now->format(DATE_RFC3339_EXTENDED),
                'fulltext' => 'Bergurlaub premium',
                'object_type_order' => 1,
            ],
            [
                '_id' => 3003,
                'id_media_object' => 3003,
                'id_object_type' => 100,
                'code' => ['SQ-003'],
                'url' => '/sq/3003',
                'valid_from' => null,
                'valid_to' => null,
                'visibility' => 30,
                'recommendation_rate' => 60,
                'sales_priority' => 'C000099',
                'sold_out' => true,
                'is_running' => false,
                'has_price' => true,
                'has_guaranteed_departures' => false,
                'departure_date_count' => 1,
                'groups' => ['group-alpha'],
                'categories' => [],
                'prices' => [
                    [
                        'price_total' => 200.0, 'duration' => 3, 'occupancy' => 1,
                        'transport_type' => 'PKW', 'option_board_type' => 'UE',
                        'state' => 200, 'date_departures' => [$dep30],
                        'guaranteed_departures' => [], 'earlybird_discount' => 0,
                        'earlybird_discount_f' => 0, 'earlybird_name' => null,
                        'earlybird_discount_date_to' => null, 'option_name' => 'Budget',
                        'price_regular_before_discount' => 200.0, 'housing_package_name' => null,
                        'housing_package_id_name' => null, 'price_mix' => 'date_housing',
                        'quota_pax' => 50,
                    ],
                ],
                'best_price_meta' => ['price_total' => 200.0, 'duration' => 3],
                'last_modified_date' => $now->format(DATE_RFC3339_EXTENDED),
                'fulltext' => 'Kurztrip Budget',
                'object_type_order' => 0,
            ],
        ];

        $descDocs = [
            ['_id' => 3001, 'description' => ['title' => 'Coast Trip', 'teaser' => 'Bus & Flight']],
            ['_id' => 3002, 'description' => ['title' => 'Mountain Premium', 'teaser' => 'All inclusive flight']],
            ['_id' => 3003, 'description' => ['title' => 'Budget Short', 'teaser' => 'PKW short trip']],
        ];

        $coll = $this->mongoDb->selectCollection(self::TEST_COLLECTION);
        $coll->drop();
        $coll->insertMany($docs);

        $descColl = $this->mongoDb->selectCollection(self::DESC_COLLECTION);
        $descColl->drop();
        $descColl->insertMany($descDocs);
    }

    // --- Constructor & accessors ---

    public function testGetAgencyReturnsNull(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $this->assertNull($search->getAgency());
    }

    public function testGetAgencyReturnsValue(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, 'my_agency');
        $this->assertSame('my_agency', $search->getAgency());
    }

    public function testGetCollectionNameStaticWithAllParams(): void
    {
        $this->assertSame(
            'best_price_search_based_de_origin_1_agency_ag1',
            MongoDB::getCollectionName('best_price_search_based_', 'de', 1, 'ag1')
        );
    }

    public function testGetCollectionNameStaticWithoutOptional(): void
    {
        $this->assertSame(
            'best_price_search_based_origin_0',
            MongoDB::getCollectionName('best_price_search_based_', null, 0, null)
        );
    }

    public function testGetCurrentCollectionNameDefault(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $this->assertSame(self::TEST_COLLECTION, $search->getCurrentCollectionName());
    }

    // --- Condition management ---

    public function testAddConditionWithStringKey(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->addCondition('myObjectType', new ObjectType([100]));
        $this->assertTrue($search->hasCondition('ObjectType'));
    }

    public function testRemoveConditionCaseInsensitive(): void
    {
        $this->requireMongo();
        $search = new MongoDB([new ObjectType([100])], ['price_total' => 'asc'], 'de', 0);
        $this->assertTrue($search->hasCondition('ObjectType'));
        $search->removeCondition('objecttype');
        $this->assertFalse($search->hasCondition('ObjectType'));
    }

    public function testHasConditionCaseInsensitive(): void
    {
        $this->requireMongo();
        $search = new MongoDB([new PriceRange(100, 999)], ['price_total' => 'asc'], 'de', 0);
        $this->assertTrue($search->hasCondition('PriceRange'));
        $this->assertTrue($search->hasCondition('pricerange'));
    }

    public function testListConditionsMultiple(): void
    {
        $this->requireMongo();
        $search = new MongoDB([
            new ObjectType([100]),
            new PriceRange(100, 999),
            new DurationRange(3, 14),
            new TransportType(['BUS']),
        ], ['price_total' => 'asc'], 'de', 0);

        $list = $search->listConditions();
        $this->assertContains('ObjectType', $list);
        $this->assertContains('PriceRange', $list);
        $this->assertContains('DurationRange', $list);
        $this->assertContains('TransportType', $list);
        $this->assertCount(4, $list);
    }

    // --- Connection caching ---

    public function testClearConnectionCacheDoesNotThrow(): void
    {
        MongoDB::clearConnectionCache();
        $this->assertTrue(true);
    }

    public function testGetClientReturnsSameInstance(): void
    {
        $this->requireMongo();
        $uri = getenv('MONGODB_URI');
        $client1 = MongoDB::getClient($uri);
        $client2 = MongoDB::getClient($uri);
        $this->assertSame($client1, $client2, 'Same URI should return cached client');
    }

    public function testGetDatabaseReturnsSameInstance(): void
    {
        $this->requireMongo();
        $uri = getenv('MONGODB_URI');
        $dbName = getenv('MONGODB_DB');
        $db1 = MongoDB::getDatabase($uri, $dbName);
        $db2 = MongoDB::getDatabase($uri, $dbName);
        $this->assertSame($db1, $db2, 'Same URI+DB should return cached database');
    }

    // --- buildQuery pipeline structure ---

    public function testBuildQueryWithMultipleConditionsProducesComplexPipeline(): void
    {
        $this->requireMongo();
        $search = new MongoDB([
            new ObjectType([100]),
            new PriceRange(200, 1000),
            new DurationRange(3, 14),
            new Occupancy([2]),
            new TransportType(['BUS', 'FLUG']),
            new BoardType(['HP', 'VP']),
        ], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));

        $pipeline = $search->buildQuery();
        $this->assertIsArray($pipeline);
        $this->assertNotEmpty($pipeline);

        $json = json_encode($pipeline);
        $this->assertStringContainsString('id_object_type', $json);
        $this->assertStringContainsString('price_total', $json);
        $this->assertStringContainsString('$facet', $json);
    }

    public function testBuildQueryWithDateRangeCondition(): void
    {
        $this->requireMongo();
        $from = new \DateTime('+10 days');
        $to = new \DateTime('+90 days');
        $search = new MongoDB([
            new DateRange($from, $to),
        ], ['date_departure' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));

        $pipeline = $search->buildQuery();
        $json = json_encode($pipeline);
        $this->assertStringContainsString('date_departures', $json);
    }

    public function testBuildQueryWithGroupCondition(): void
    {
        $this->requireMongo();
        $search = new MongoDB([
            new Group(['group-alpha']),
        ], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));

        $pipeline = $search->buildQuery();
        $json = json_encode($pipeline);
        $this->assertStringContainsString('groups', $json);
    }

    public function testBuildQueryWithCodeCondition(): void
    {
        $this->requireMongo();
        $search = new MongoDB([
            new Code(['SQ-001', 'SQ-002']),
        ], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));

        $pipeline = $search->buildQuery();
        $json = json_encode($pipeline);
        $this->assertStringContainsString('code', $json);
    }

    public function testBuildQueryWithMediaObjectCondition(): void
    {
        $this->requireMongo();
        $search = new MongoDB([
            new MediaObject([3001, 3002]),
        ], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));

        $pipeline = $search->buildQuery();
        $json = json_encode($pipeline);
        $this->assertStringContainsString('3001', $json);
        $this->assertStringContainsString('3002', $json);
    }

    public function testBuildQueryAsJsonWithFilters(): void
    {
        $this->requireMongo();
        $search = new MongoDB([
            new ObjectType([100]),
        ], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));

        $json = $search->buildQueryAsJson(true);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertNotEmpty($decoded);
    }

    // --- getResult with conditions against seeded data ---

    public function testGetResultFilterByObjectType100(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([new ObjectType([100])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(2, $result->total);
        $documents = is_array($result->documents) ? $result->documents : iterator_to_array($result->documents);
        $ids = array_map(function ($doc) {
            return json_decode(json_encode($doc), true)['id_media_object'];
        }, $documents);
        $this->assertContains(3001, $ids);
        $this->assertContains(3003, $ids);
    }

    public function testGetResultFilterByPriceRangeNarrow(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([new PriceRange(400, 700)], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(1, $result->total);
        $doc = json_decode(json_encode($result->documents[0]), true);
        $this->assertSame(3001, $doc['id_media_object']);
    }

    public function testGetResultFilterByDurationRange(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([new DurationRange(10, 20)], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(1, $result->total);
        $doc = json_decode(json_encode($result->documents[0]), true);
        $this->assertSame(3002, $doc['id_media_object']);
    }

    public function testGetResultFilterByTransportType(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([new TransportType(['PKW'])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(1, $result->total);
        $doc = json_decode(json_encode($result->documents[0]), true);
        $this->assertSame(3003, $doc['id_media_object']);
    }

    public function testGetResultFilterByBoardType(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([new BoardType(['AI'])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(1, $result->total);
        $doc = json_decode(json_encode($result->documents[0]), true);
        $this->assertSame(3002, $doc['id_media_object']);
    }

    public function testGetResultFilterByGroup(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([new Group(['group-alpha'])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(2, $result->total);
    }

    public function testGetResultFilterByCode(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([new Code(['SQ-002'])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(1, $result->total);
        $doc = json_decode(json_encode($result->documents[0]), true);
        $this->assertSame(3002, $doc['id_media_object']);
    }

    public function testGetResultFilterByMediaObjectIds(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([new MediaObject([3001, 3003])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(2, $result->total);
    }

    public function testGetResultSortByPriceDesc(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['price_total' => 'desc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(3, $result->total);
        $firstDoc = json_decode(json_encode($result->documents[0]), true);
        $lastDoc = json_decode(json_encode($result->documents[2]), true);
        $this->assertGreaterThanOrEqual(
            $lastDoc['prices']['price_total'],
            $firstDoc['prices']['price_total']
        );
    }

    public function testGetResultSortByRecommendationRateDesc(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['recommendation_rate' => 'desc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(3, $result->total);
        $firstDoc = json_decode(json_encode($result->documents[0]), true);
        $this->assertSame(95, $firstDoc['recommendation_rate']);
    }

    public function testGetResultWithFiltersEnabledReturnsFilterData(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(true, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertObjectHasProperty('categoriesGrouped', $result);
        $this->assertObjectHasProperty('boardTypesGrouped', $result);
        $this->assertObjectHasProperty('transportTypesGrouped', $result);
        $this->assertObjectHasProperty('minPrice', $result);
        $this->assertObjectHasProperty('maxPrice', $result);
        $this->assertObjectHasProperty('minDuration', $result);
        $this->assertObjectHasProperty('maxDuration', $result);
    }

    public function testGetResultPaginationPage1(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(2, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(3, $result->total);
        $this->assertCount(2, $result->documents);
        $this->assertSame(1, $result->currentPage);
    }

    public function testGetResultPaginationPage2(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(2, 2));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(3, $result->total);
        $this->assertCount(1, $result->documents);
        $this->assertSame(2, $result->currentPage);
    }

    public function testGetResultCombinedConditions(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([
            new ObjectType([100]),
            new PriceRange(100, 500),
        ], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(2, $result->total);
    }

    public function testGetResultDescriptionJoin(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        foreach ($result->documents as $doc) {
            $arr = json_decode(json_encode($doc), true);
            $this->assertArrayHasKey('description', $arr);
            $this->assertNotEmpty($arr['description']);
        }
    }

    // --- prepareQuery ---

    public function testPrepareQueryReturnsArrayWithConditions(): void
    {
        $this->requireMongo();
        $search = new MongoDB([
            new ObjectType([100]),
            new PriceRange(200, 800),
        ], ['price_total' => 'asc'], 'de', 0);

        $prepared = $search->prepareQuery();
        $this->assertIsArray($prepared);
    }

    // --- Logging ---

    public function testGetLogDisabledReturnsInfoMessage(): void
    {
        $this->requireMongo();
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $log = $search->getLog();
        $this->assertIsArray($log);
        $this->assertCount(1, $log);
        $this->assertStringContainsString('disabled', $log[0]);
    }

    public function testGetLogEnabledContainsConstructorEntry(): void
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
}
