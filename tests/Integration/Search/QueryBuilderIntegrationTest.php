<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;
use Pressmind\Search\Query;
use Pressmind\Search\Query\Filter;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for Search\Query: static extraction/validation helpers,
 * fromRequest() query building, pagination, and getResult() structure.
 *
 * Tests that do NOT need MongoDB test the static parsing logic.
 * Tests that require MongoDB are skipped when not available.
 */
class QueryBuilderIntegrationTest extends AbstractIntegrationTestCase
{
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

    // --- Static extraction / validation helpers (no DB needed) ---

    public function testExtractObjectTypeSingleId(): void
    {
        $result = Query::extractObjectType('100');
        $this->assertSame([100], $result);
    }

    public function testExtractObjectTypeMultipleIds(): void
    {
        $result = Query::extractObjectType('100,200,300');
        $this->assertSame([100, 200, 300], $result);
    }

    public function testExtractObjectTypeInvalidReturnsFalse(): void
    {
        $this->assertFalse(Query::extractObjectType('abc'));
        $this->assertFalse(Query::extractObjectType(''));
        $this->assertFalse(Query::extractObjectType('100;200'));
    }

    public function testExtractDaterangeAbsolute(): void
    {
        $result = Query::extractDaterange('20260101-20260331');
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('2026-01-01', $result[0]->format('Y-m-d'));
        $this->assertSame('2026-03-31', $result[1]->format('Y-m-d'));
    }

    public function testExtractDaterangeExactDay(): void
    {
        $result = Query::extractDaterange('20260615');
        $this->assertIsArray($result);
        $this->assertSame('2026-06-15', $result[0]->format('Y-m-d'));
        $this->assertNull($result[1]);
    }

    public function testExtractDaterangeRelativeOffset(): void
    {
        $result = Query::extractDaterange('+90');
        $this->assertIsArray($result);
        $expected = (new \DateTime('now'))->modify('+90 day')->format('Y-m-d');
        $this->assertSame($expected, $result[1]->format('Y-m-d'));
    }

    public function testExtractDaterangeRelativeRange(): void
    {
        $result = Query::extractDaterange('+30-+60');
        $this->assertIsArray($result);
        $from = (new \DateTime('now'))->modify('+30 day')->format('Y-m-d');
        $to = (new \DateTime('now'))->modify('+60 day')->format('Y-m-d');
        $this->assertSame($from, $result[0]->format('Y-m-d'));
        $this->assertSame($to, $result[1]->format('Y-m-d'));
    }

    public function testExtractDaterangeInvalid(): void
    {
        $this->assertFalse(Query::extractDaterange('invalid'));
        $this->assertFalse(Query::extractDaterange(''));
    }

    public function testExtractDurationRange(): void
    {
        $this->assertSame(['3', '14'], Query::extractDurationRange('3-14'));
        $this->assertSame(['7', '7'], Query::extractDurationRange('7'));
        $this->assertSame(3, Query::extractDurationRange('3-14', false, true));
        $this->assertFalse(Query::extractDurationRange('abc'));
        $this->assertSame('default', Query::extractDurationRange('', 'default'));
    }

    public function testExtractPriceRange(): void
    {
        $this->assertSame(['100', '999'], Query::extractPriceRange('100-999'));
        $this->assertFalse(Query::extractPriceRange('invalid'));
        $this->assertSame('fallback', Query::extractPriceRange('', 'fallback'));
    }

    public function testExtractTransportTypes(): void
    {
        $this->assertSame(['BUS', 'FLUG'], Query::extractTransportTypes('BUS,FLUG'));
        $this->assertSame('BUS', Query::extractTransportTypes('BUS,FLUG', [], true));
        $this->assertSame([], Query::extractTransportTypes('123'));
    }

    public function testExtractBoardTypes(): void
    {
        $result = Query::extractBoardTypes('HP,VP,AI');
        $this->assertCount(3, $result);
        $this->assertSame('HP', $result[0]);
    }

    public function testExtractGroups(): void
    {
        $result = Query::extractGroups('group1,group2');
        $this->assertSame(['group1', 'group2'], $result);

        $result = Query::extractGroups(['already', 'array']);
        $this->assertSame(['already', 'array'], $result);
    }

    public function testExtractBoolean(): void
    {
        $this->assertTrue(Query::extractBoolean('1'));
        $this->assertFalse(Query::extractBoolean('0'));
        $this->assertFalse(Query::extractBoolean('yes'));
    }

    public function testExtractSalesPriority(): void
    {
        $this->assertSame('A000001', Query::extractSalesPriority('A000001'));
        $this->assertSame('C999999', Query::extractSalesPriority('C999999'));
        $this->assertNull(Query::extractSalesPriority('D000000'));
        $this->assertNull(Query::extractSalesPriority('A00001'));
        $this->assertNull(Query::extractSalesPriority('invalid'));
    }

    public function testExtractAirport3L(): void
    {
        $this->assertSame('DUS', Query::extractAirport3L('dus'));
        $this->assertSame('FRA', Query::extractAirport3L('FRA'));
        $this->assertNull(Query::extractAirport3L('XXXX'));
        $this->assertNull(Query::extractAirport3L('12'));
    }

    public function testExtractHousingPackageId(): void
    {
        $this->assertSame('abc-123', Query::extractHousingPackageId('abc-123'));
        $this->assertNull(Query::extractHousingPackageId(''));
    }

    public function testExtractIdStartingPointOptionCity(): void
    {
        $this->assertSame(['abc', '123'], Query::extractIdStartingPointOptionCity('abc,123'));
        $this->assertSame('abc', Query::extractIdStartingPointOptionCity('abc,123', [], true));
        $this->assertSame([], Query::extractIdStartingPointOptionCity('INVALID!'));
    }

    public function testSanitizeStr(): void
    {
        $this->assertSame('Hello-World', Query::sanitizeStr('Hello-World'));
        $this->assertSame('Testscript', Query::sanitizeStr('Test<script>'));
        $this->assertSame('ÄÖÜ', Query::sanitizeStr('ÄÖÜ'));
    }

    // --- fromRequest() query building ---

    public function testFromRequestMinimalReturnsMongoDBSearch(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest([], 'pm', true, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
    }

    public function testFromRequestWithObjectType(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-ot' => '100'], 'pm', true, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('ObjectType'));
    }

    public function testFromRequestWithPriceRange(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-pr' => '100-500'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('PriceRange'));
    }

    public function testFromRequestWithDurationRange(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-du' => '3-14'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('DurationRange'));
    }

    public function testFromRequestWithDateRange(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-dr' => '20260101-20260331'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('DateRange'));
    }

    public function testFromRequestWithCategory(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-c' => ['land_default' => 'abc,def']], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('Category'));
    }

    public function testFromRequestWithCategoryAndOperator(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-c' => ['region' => 'abc%2Bdef']], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('Category'));
    }

    public function testFromRequestWithTransportType(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-tr' => 'BUS,FLUG'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('TransportType'));
    }

    public function testFromRequestWithBoardType(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-bt' => 'HP,VP'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('BoardType'));
    }

    public function testFromRequestWithSoldOut(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-so' => '1'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('SoldOut'));
    }

    public function testFromRequestWithGuaranteed(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-gu' => '1'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('Guaranteed'));
    }

    public function testFromRequestWithMediaObjectIds(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-id' => '1001,1002,1003'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('MediaObject'));
    }

    public function testFromRequestWithCode(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-co' => 'ABC,DEF'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('Code'));
    }

    public function testFromRequestWithUrl(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-url' => '/travel/italy/'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('Url'));
    }

    public function testFromRequestWithGroup(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-gr' => 'brand-a,brand-b'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('Group'));
    }

    public function testFromRequestWithOccupancy(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-ho' => '2,3'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('Occupancy'));
    }

    public function testFromRequestWithStartingPointCity(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-sc' => 'abc123'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('StartingPointOptionCity'));
    }

    public function testFromRequestWithSalesPriority(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-sp' => 'A000001'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('SalesPriority'));
    }

    public function testFromRequestWithPowerfilter(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest(['pm-pf' => '12345'], 'pm', true, 10);
        $this->assertTrue($search->hasCondition('Powerfilter'));
    }

    public function testFromRequestWithMultipleConditions(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $search = Query::fromRequest([
            'pm-ot' => '100',
            'pm-pr' => '200-800',
            'pm-du' => '3-7',
            'pm-ho' => '2',
            'pm-tr' => 'BUS',
        ], 'pm', true, 10);

        $conditions = $search->listConditions();
        $this->assertContains('ObjectType', $conditions);
        $this->assertContains('PriceRange', $conditions);
        $this->assertContains('DurationRange', $conditions);
        $this->assertContains('Occupancy', $conditions);
        $this->assertContains('TransportType', $conditions);
    }

    // --- Pagination ---

    public function testFromRequestPagination(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        Query::fromRequest(['pm-l' => '3,20'], 'pm', true, 20);
        $this->assertSame(3, Query::getCurrentPage());
        $this->assertSame(20, Query::getPageSize());
    }

    public function testFromRequestPaginationDefaults(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        Query::fromRequest([], 'pm', true, 12);
        $this->assertSame(1, Query::getCurrentPage());
        $this->assertSame(12, Query::getPageSize());
    }

    // --- getCurrentQueryString ---

    public function testGetCurrentQueryString(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        Query::fromRequest(['pm-ot' => '100', 'pm-l' => '1,10'], 'pm', true, 10);
        $qs = Query::getCurrentQueryString();
        $this->assertStringContainsString('pm-ot=100', $qs);
        $this->assertStringContainsString('pm-l=', $qs);
    }

    // --- getResult() structure ---

    public function testGetResultReturnsExpectedKeys(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }

        $filter = new Filter();
        $filter->request = [];
        $filter->page_size = 5;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertIsArray($result);
        $expectedKeys = ['total_result', 'current_page', 'pages', 'page_size', 'items', 'cache', 'mongodb'];
        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
        $this->assertSame(5, $result['page_size']);
        $this->assertIsArray($result['items']);
    }

    public function testGetResultWithFilters(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }

        $filter = new Filter();
        $filter->request = ['pm-ot' => '100'];
        $filter->page_size = 10;
        $filter->getFilters = true;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('board_types', $result);
        $this->assertArrayHasKey('transport_types', $result);
        $this->assertArrayHasKey('startingpoint_options', $result);
        $this->assertArrayHasKey('duration_min', $result);
        $this->assertArrayHasKey('duration_max', $result);
        $this->assertArrayHasKey('price_min', $result);
        $this->assertArrayHasKey('price_max', $result);
    }

    public function testGetResultReturnFiltersOnly(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }

        $filter = new Filter();
        $filter->request = [];
        $filter->page_size = 10;
        $filter->getFilters = true;
        $filter->returnFiltersOnly = true;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertEmpty($result['items'], 'returnFiltersOnly should yield no items');
    }

    public function testGetResultWithDurationFilter(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }

        $filter = new Filter();
        $filter->request = ['pm-du' => '3-7', 'pm-l' => '1,5'];
        $filter->page_size = 5;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_result', $result);
    }

    public function testGetResultCachesIdenticalCalls(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }

        $filter = new Filter();
        $filter->request = ['pm-ot' => '999'];
        $filter->page_size = 5;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result1 = Query::getResult($filter);
        $result2 = Query::getResult($filter);

        $stripVolatile = function (array $r): array {
            $volatileKeys = ['duration_search_ms', 'duration_total_ms', 'duration_filter_ms',
                             'aggregation_pipeline_search', 'aggregation_pipeline_filter'];
            $r = array_diff_key($r, array_flip($volatileKeys));
            if (isset($r['mongodb']) && is_array($r['mongodb'])) {
                $r['mongodb'] = array_diff_key($r['mongodb'], array_flip($volatileKeys));
            }
            return $r;
        };
        $this->assertSame($stripVolatile($result1), $stripVolatile($result2), 'Identical filter should return cached result');
    }

    // --- Order options ---

    public function testGetAvailableOrderOptions(): void
    {
        $options = Query::getAvailableOrderOptions();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('price-asc', $options);
        $this->assertArrayHasKey('price-desc', $options);
        $this->assertArrayHasKey('date_departure-asc', $options);
        $this->assertArrayNotHasKey('rand', $options, 'rand should be disabled by default');
    }

    public function testGetAvailableOrderOptionsNoDisabled(): void
    {
        $options = Query::getAvailableOrderOptions(null, []);
        $this->assertArrayHasKey('rand', $options);
        $this->assertArrayHasKey('priority', $options);
    }
}
