<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;
use Pressmind\Search\Query;
use Pressmind\Search\Query\Filter;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for Search\Query: extended extraction/validation edge cases,
 * fromRequest with combined filters, rebuild(), order handling, and
 * getResult with seeded data.
 *
 * Closes coverage gaps in: extractDaterange edge cases, extractDurationRange variants,
 * fromRequest with all condition types, rebuild(), getCurrentQueryString with params,
 * getAvailableOrderOptions with custom_order, getResult pipeline/caching.
 */
class QueryExtractorsIntegrationTest extends AbstractIntegrationTestCase
{
    private const TEST_COLLECTION = 'best_price_search_based_de_origin_0';
    private const DESC_COLLECTION = 'description_de_origin_0';

    protected function setUp(): void
    {
        parent::setUp();
        $this->addSearchConfigToRegistry();
        Query::$language_code = 'de';
        Query::$touristic_origin = 0;
        Query::$agency_id_price_index = null;
        Query::$group_keys = null;
        Query::$atlas_active = false;
        Query::$atlas_definition = false;
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
        $config = $this->getIntegrationConfig();
        $config['data'] = [
            'search_mongodb' => [
                'database' => [
                    'uri' => $mongoUri ?: 'mongodb://localhost:27017',
                    'db' => $mongoDb ?: 'pressmind_test',
                ],
                'search' => [
                    'allow_invalid_offers' => false,
                    'order_by_primary_object_type_priority' => false,
                    'touristic' => [
                        'duration_ranges' => [[1, 7], [8, 14], [15, 28]],
                    ],
                    'custom_order' => [
                        100 => [
                            'destination' => ['field' => 'region_name', 'label' => 'Reiseziel'],
                        ],
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
                '_id' => 2001,
                'id_media_object' => 2001,
                'id_object_type' => 100,
                'code' => ['QRY-001'],
                'url' => '/query/2001',
                'valid_from' => null,
                'valid_to' => null,
                'visibility' => 30,
                'recommendation_rate' => 75,
                'sales_priority' => 'A000010',
                'sold_out' => false,
                'is_running' => false,
                'has_price' => true,
                'has_guaranteed_departures' => false,
                'departure_date_count' => 2,
                'groups' => ['brand-x'],
                'categories' => [],
                'prices' => [
                    [
                        'price_total' => 399.0, 'duration' => 5, 'occupancy' => 2,
                        'transport_type' => 'BUS', 'option_board_type' => 'HP',
                        'state' => 100, 'date_departures' => [$futureDep],
                        'guaranteed_departures' => [], 'earlybird_discount' => 0,
                        'earlybird_discount_f' => 0, 'earlybird_name' => null,
                        'earlybird_discount_date_to' => null, 'option_name' => 'Standard',
                        'price_regular_before_discount' => 399.0, 'housing_package_name' => null,
                        'housing_package_id_name' => null, 'price_mix' => 'date_housing',
                        'quota_pax' => 30,
                    ],
                ],
                'best_price_meta' => ['price_total' => 399.0, 'duration' => 5],
                'last_modified_date' => $now->format(DATE_RFC3339_EXTENDED),
                'fulltext' => 'Busreise Toskana',
                'object_type_order' => 0,
                'custom_order' => ['destination' => 'Toskana'],
            ],
            [
                '_id' => 2002,
                'id_media_object' => 2002,
                'id_object_type' => 100,
                'code' => ['QRY-002'],
                'url' => '/query/2002',
                'valid_from' => null,
                'valid_to' => null,
                'visibility' => 30,
                'recommendation_rate' => 90,
                'sales_priority' => 'B000020',
                'sold_out' => true,
                'is_running' => true,
                'has_price' => true,
                'has_guaranteed_departures' => true,
                'departure_date_count' => 1,
                'groups' => ['brand-y'],
                'categories' => [
                    ['id_item' => 'cat_alps', 'name' => 'Alps', 'field_name' => 'region', 'level' => 0,
                     'id_tree' => 't1', 'id_parent' => null, 'path_str' => ['Alps'], 'path_ids' => ['cat_alps']],
                ],
                'prices' => [
                    [
                        'price_total' => 899.0, 'duration' => 10, 'occupancy' => 2,
                        'transport_type' => 'FLUG', 'option_board_type' => 'AI',
                        'state' => 100, 'date_departures' => [$farDep],
                        'guaranteed_departures' => [$farDep], 'earlybird_discount' => 0,
                        'earlybird_discount_f' => 0, 'earlybird_name' => null,
                        'earlybird_discount_date_to' => null, 'option_name' => 'Premium',
                        'price_regular_before_discount' => 899.0, 'housing_package_name' => null,
                        'housing_package_id_name' => null, 'price_mix' => 'date_housing',
                        'quota_pax' => 10,
                    ],
                ],
                'best_price_meta' => ['price_total' => 899.0, 'duration' => 10],
                'last_modified_date' => $now->format(DATE_RFC3339_EXTENDED),
                'fulltext' => 'Flugreise Alpen',
                'object_type_order' => 0,
                'custom_order' => ['destination' => 'Alpen'],
            ],
        ];

        $descDocs = [
            ['_id' => 2001, 'description' => ['title' => 'Toskana Tour', 'teaser' => 'Bus trip']],
            ['_id' => 2002, 'description' => ['title' => 'Alpen Deluxe', 'teaser' => 'Flight trip']],
        ];

        $coll = $this->mongoDb->selectCollection(self::TEST_COLLECTION);
        $coll->drop();
        $coll->insertMany($docs);

        $descColl = $this->mongoDb->selectCollection(self::DESC_COLLECTION);
        $descColl->drop();
        $descColl->insertMany($descDocs);
    }

    // --- Extended extraction edge cases ---

    public function testExtractDaterangeNegativeOffset(): void
    {
        $result = Query::extractDaterange('-5');
        $this->assertIsArray($result);
        $expected = (new \DateTime('now'))->modify('-5 day')->format('Y-m-d');
        $this->assertSame($expected, $result[1]->format('Y-m-d'));
    }

    public function testExtractDaterangeNegativeRange(): void
    {
        $result = Query::extractDaterange('-10-+10');
        $this->assertIsArray($result);
        $from = (new \DateTime('now'))->modify('-10 day')->format('Y-m-d');
        $to = (new \DateTime('now'))->modify('+10 day')->format('Y-m-d');
        $this->assertSame($from, $result[0]->format('Y-m-d'));
        $this->assertSame($to, $result[1]->format('Y-m-d'));
    }

    public function testExtractDurationRangeSingleNumber(): void
    {
        $this->assertSame(['5', '5'], Query::extractDurationRange('5'));
    }

    public function testExtractDurationRangeWithFirstKeyAsInt(): void
    {
        $this->assertSame(7, Query::extractDurationRange('7-14', false, true));
    }

    public function testExtractDurationRangeDefault(): void
    {
        $result = Query::extractDurationRange('invalid', 'fallback');
        $this->assertSame('fallback', $result);
    }

    public function testExtractTransportTypesFirstKeyAsString(): void
    {
        $result = Query::extractTransportTypes('FLUG,BUS,SCH', [], true);
        $this->assertSame('FLUG', $result);
    }

    public function testExtractTransportTypesInvalidChars(): void
    {
        $result = Query::extractTransportTypes('123!@#');
        $this->assertSame([], $result);
    }

    public function testExtractBoardTypesMultiple(): void
    {
        $result = Query::extractBoardTypes('HP,VP,AI,UE');
        $this->assertCount(4, $result);
        $this->assertSame(['HP', 'VP', 'AI', 'UE'], $result);
    }

    public function testExtractGroupsFromString(): void
    {
        $result = Query::extractGroups('alpha,beta,gamma');
        $this->assertSame(['alpha', 'beta', 'gamma'], $result);
    }

    public function testExtractGroupsFromArray(): void
    {
        $result = Query::extractGroups(['x', 'y']);
        $this->assertSame(['x', 'y'], $result);
    }

    public function testExtractBooleanEdgeCases(): void
    {
        $this->assertTrue(Query::extractBoolean('1'));
        $this->assertFalse(Query::extractBoolean('0'));
        $this->assertFalse(Query::extractBoolean('true'));
        $this->assertFalse(Query::extractBoolean('2'));
    }

    public function testExtractSalesPriorityEdgeCases(): void
    {
        $this->assertSame('A000000', Query::extractSalesPriority('A000000'));
        $this->assertSame('B123456', Query::extractSalesPriority('B123456'));
        $this->assertNull(Query::extractSalesPriority('X000000'));
        $this->assertNull(Query::extractSalesPriority(''));
    }

    public function testExtractAirport3LEdgeCases(): void
    {
        $this->assertSame('MUC', Query::extractAirport3L('muc'));
        $this->assertNull(Query::extractAirport3L(''));
        $this->assertNull(Query::extractAirport3L('AB'));
        $this->assertNull(Query::extractAirport3L('1AB'));
    }

    public function testExtractHousingPackageIdEdgeCases(): void
    {
        $this->assertSame('pkg-123-abc', Query::extractHousingPackageId('pkg-123-abc'));
        $this->assertSame('123', Query::extractHousingPackageId('123'));
        $this->assertNull(Query::extractHousingPackageId(''));
    }

    public function testExtractIdStartingPointOptionCityFirstKeyAsString(): void
    {
        $result = Query::extractIdStartingPointOptionCity('abc,def,ghi', [], true);
        $this->assertSame('abc', $result);
    }

    public function testExtractObjectTypeWithLeadingZeros(): void
    {
        $result = Query::extractObjectType('01,002');
        $this->assertSame([1, 2], $result);
    }

    public function testSanitizeStrRemovesSpecialChars(): void
    {
        $this->assertSame('Testscriptalert1script', Query::sanitizeStr('Test<script>alert(1)</script>'));
        $this->assertSame('ab-cd_ef.gh', Query::sanitizeStr('ab-cd_ef.gh'));
    }

    // --- fromRequest with combined conditions ---

    public function testFromRequestWithIsRunning(): void
    {
        $this->requireMongo();
        $search = Query::fromRequest(['pm-ir' => '1'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('Running'));
    }

    public function testFromRequestWithHousingPackage(): void
    {
        $this->requireMongo();
        $search = Query::fromRequest(['pm-ho' => '2', 'pm-hoc' => '1'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('Occupancy'));
    }

    public function testFromRequestWithSortPriceDesc(): void
    {
        $this->requireMongo();
        $search = Query::fromRequest(['pm-o' => 'price-desc'], 'pm', true, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
    }

    public function testFromRequestWithSortRandom(): void
    {
        $this->requireMongo();
        $search = Query::fromRequest(['pm-o' => 'rand'], 'pm', true, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
    }

    public function testFromRequestWithSortPriority(): void
    {
        $this->requireMongo();
        $search = Query::fromRequest(['pm-o' => 'priority'], 'pm', true, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
    }

    public function testFromRequestWithSortList(): void
    {
        $this->requireMongo();
        $search = Query::fromRequest(['pm-o' => 'list', 'pm-id' => '1001,1002'], 'pm', true, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
    }

    public function testFromRequestWithSortCustomOrder(): void
    {
        $this->requireMongo();
        $search = Query::fromRequest(
            ['pm-o' => 'co.destination-asc', 'pm-ot' => '100'],
            'pm', true, 10
        );
        $this->assertInstanceOf(MongoDB::class, $search);
    }

    public function testFromRequestWithInvalidSortFallsBack(): void
    {
        $this->requireMongo();
        $search = Query::fromRequest(['pm-o' => 'INVALID_ORDER'], 'pm', true, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
    }

    // --- rebuild() ---

    public function testRebuildRemovesSpecifiedCondition(): void
    {
        $this->requireMongo();
        Query::fromRequest(['pm-ot' => '100', 'pm-pr' => '100-500'], 'pm', true, 10);
        $rebuilt = Query::rebuild(['pm-pr'], 'pm', true, 10);
        $this->assertTrue($rebuilt->hasCondition('ObjectType'));
        $this->assertFalse($rebuilt->hasCondition('PriceRange'));
    }

    public function testRebuildRemovesCategoryCondition(): void
    {
        $this->requireMongo();
        Query::fromRequest(['pm-ot' => '100', 'pm-c' => ['region' => 'abc,def']], 'pm', true, 10);
        $rebuilt = Query::rebuild(['pm-c[region]'], 'pm', true, 10);
        $this->assertTrue($rebuilt->hasCondition('ObjectType'));
        $this->assertFalse($rebuilt->hasCondition('Category'));
    }

    // --- getCurrentQueryString ---

    public function testGetCurrentQueryStringWithCustomParams(): void
    {
        $this->requireMongo();
        Query::fromRequest(['pm-ot' => '100'], 'pm', true, 10);
        $qs = Query::getCurrentQueryString(2, 5, ['custom' => 'value']);
        $this->assertStringContainsString('pm-ot=100', $qs);
        $this->assertStringContainsString('pm-l=2,5', $qs);
        $this->assertStringContainsString('custom=value', $qs);
    }

    public function testGetCurrentQueryStringDefaultPagination(): void
    {
        $this->requireMongo();
        Query::fromRequest([], 'pm', true, 8);
        $qs = Query::getCurrentQueryString();
        $this->assertStringContainsString('pm-l=1,8', $qs);
    }

    // --- getAvailableOrderOptions ---

    public function testGetAvailableOrderOptionsIncludesCustomOrder(): void
    {
        $options = Query::getAvailableOrderOptions(100, []);
        $this->assertArrayHasKey('co.destination-asc', $options);
        $this->assertArrayHasKey('co.destination-desc', $options);
        $this->assertStringContainsString('Reiseziel', $options['co.destination-asc']);
    }

    public function testGetAvailableOrderOptionsNullObjectTypeLoadAll(): void
    {
        $options = Query::getAvailableOrderOptions(null, []);
        $this->assertArrayHasKey('co.destination-asc', $options);
    }

    public function testGetAvailableOrderOptionsCustomDisabled(): void
    {
        $options = Query::getAvailableOrderOptions(100, ['co.destination-asc', 'co.destination-desc']);
        $this->assertArrayNotHasKey('co.destination-asc', $options);
        $this->assertArrayNotHasKey('co.destination-desc', $options);
    }

    // --- getResult with seeded data ---

    public function testGetResultWithSeededDataReturnsItems(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $filter = new Filter();
        $filter->request = [];
        $filter->page_size = 10;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);
        $this->assertSame(2, $result['total_result']);
    }

    public function testGetResultWithObjectTypeFilterMatchesAll(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $filter = new Filter();
        $filter->request = ['pm-ot' => '100'];
        $filter->page_size = 10;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertSame(2, $result['total_result']);
    }

    public function testGetResultWithObjectTypeFilterNoMatch(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $filter = new Filter();
        $filter->request = ['pm-ot' => '999'];
        $filter->page_size = 10;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertEmpty($result['total_result']);
        $this->assertEmpty($result['items']);
    }

    public function testGetResultPaginationPage2(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $filter = new Filter();
        $filter->request = ['pm-l' => '2,1'];
        $filter->page_size = 1;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertSame(2, $result['total_result']);
        $this->assertSame(2, $result['current_page']);
        $this->assertCount(1, $result['items']);
    }

    public function testGetResultItemStructure(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $filter = new Filter();
        $filter->request = [];
        $filter->page_size = 10;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertNotEmpty($result['items']);
        $item = $result['items'][0];
        $this->assertArrayHasKey('id_media_object', $item);
        $this->assertArrayHasKey('id_object_type', $item);
        $this->assertArrayHasKey('url', $item);
        $this->assertArrayHasKey('cheapest_price', $item);
        $this->assertArrayHasKey('position', $item);
        $this->assertArrayHasKey('departure_date_count', $item);
        $this->assertArrayHasKey('last_modified_date', $item);
        $this->assertArrayHasKey('meta', $item);
    }

    public function testGetResultWithFiltersReturnsFilterData(): void
    {
        $this->requireMongo();
        $this->seedTestDocuments();

        $filter = new Filter();
        $filter->request = [];
        $filter->page_size = 10;
        $filter->getFilters = true;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('board_types', $result);
        $this->assertArrayHasKey('transport_types', $result);
        $this->assertArrayHasKey('duration_min', $result);
        $this->assertArrayHasKey('duration_max', $result);
        $this->assertArrayHasKey('price_min', $result);
        $this->assertArrayHasKey('price_max', $result);
    }
}
