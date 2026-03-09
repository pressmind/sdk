<?php

namespace Pressmind\Tests\Unit\Search;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;
use Pressmind\Search\Query;
use Pressmind\Search\Query\Filter;
use Pressmind\Tests\Unit\AbstractTestCase;

class QueryTest extends AbstractTestCase
{
    private function getMongoDbConfig(): array
    {
        return $this->createMockConfig([
            'data' => [
                'search_mongodb' => [
                    'database' => ['uri' => 'mongodb://localhost:27017', 'db' => 'test_db'],
                    'search' => [
                        'allow_invalid_offers' => false,
                        'order_by_primary_object_type_priority' => false,
                    ],
                ],
                'languages' => ['allowed' => ['de', 'en'], 'default' => 'de'],
                'search_opensearch' => ['enabled' => false, 'enabled_in_mongo_search' => false],
                'touristic' => ['generate_offer_for_each_startingpoint_option' => false],
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Registry::getInstance()->add('config', $this->getMongoDbConfig());
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
    public function testExtractDaterangeYyyyMmDdRange(): void
    {
        $result = Query::extractDaterange('20260601-20260630');
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(\DateTime::class, $result[0]);
        $this->assertInstanceOf(\DateTime::class, $result[1]);
        $this->assertSame('2026-06-01', $result[0]->format('Y-m-d'));
        $this->assertSame('2026-06-30', $result[1]->format('Y-m-d'));
    }

    public function testExtractDaterangeSingleDate(): void
    {
        $result = Query::extractDaterange('20260615');
        $this->assertIsArray($result);
        $this->assertInstanceOf(\DateTime::class, $result[0]);
        $this->assertSame('2026-06-15', $result[0]->format('Y-m-d'));
        $this->assertNull($result[1]);
    }

    public function testExtractDaterangeRelativeSingle(): void
    {
        $result = Query::extractDaterange('+90');
        $this->assertIsArray($result);
        $this->assertInstanceOf(\DateTime::class, $result[0]);
        $this->assertInstanceOf(\DateTime::class, $result[1]);
    }

    public function testExtractDaterangeRelativeRange(): void
    {
        $result = Query::extractDaterange('+90-+120');
        $this->assertIsArray($result);
        $this->assertInstanceOf(\DateTime::class, $result[0]);
        $this->assertInstanceOf(\DateTime::class, $result[1]);
    }

    public function testExtractDaterangeReturnsFalseForInvalid(): void
    {
        $this->assertFalse(Query::extractDaterange('invalid'));
        $this->assertFalse(Query::extractDaterange(''));
    }

    public function testExtractDurationRange(): void
    {
        $this->assertSame(['7', '14'], Query::extractDurationRange('7-14'));
        $this->assertSame(['7', '7'], Query::extractDurationRange('7'));
        $this->assertSame(7, Query::extractDurationRange('7-14', false, true));
        $this->assertFalse(Query::extractDurationRange('x', false));
    }

    public function testExtractPriceRange(): void
    {
        $this->assertSame(['100', '500'], Query::extractPriceRange('100-500'));
        $this->assertFalse(Query::extractPriceRange('invalid', false));
    }

    public function testExtractObjectType(): void
    {
        $this->assertSame([100], Query::extractObjectType('100'));
        $this->assertSame([100, 200], Query::extractObjectType('100,200'));
        $this->assertFalse(Query::extractObjectType(''));
    }

    public function testExtractTransportTypes(): void
    {
        $this->assertSame(['BUS', 'FLUG'], Query::extractTransportTypes('BUS,FLUG'));
        $this->assertSame([], Query::extractTransportTypes('', []));
    }

    public function testExtractBoardTypes(): void
    {
        $this->assertSame(['HP', 'VP'], Query::extractBoardTypes('HP,VP'));
        $this->assertSame(['HP'], Query::extractBoardTypes('  HP  '));
    }

    public function testExtractGroups(): void
    {
        $this->assertSame(['g1', 'g2'], Query::extractGroups('g1,g2'));
        $this->assertSame(['g1', 'g2'], Query::extractGroups(['g1', 'g2']));
    }

    public function testExtractIdStartingPointOptionCity(): void
    {
        $this->assertSame(['city1', 'city2'], Query::extractIdStartingPointOptionCity('city1,city2'));
        $this->assertSame([], Query::extractIdStartingPointOptionCity('invalid!', []));
        $this->assertSame('city1', Query::extractIdStartingPointOptionCity('city1,city2', [], true));
    }

    public function testExtractBoolean(): void
    {
        $this->assertTrue(Query::extractBoolean('1'));
        $this->assertFalse(Query::extractBoolean('0'));
        $this->assertFalse(Query::extractBoolean('2'));
    }

    public function testExtractSalesPriority(): void
    {
        $this->assertSame('A000001', Query::extractSalesPriority('A000001'));
        $this->assertNull(Query::extractSalesPriority('invalid'));
    }

    public function testExtractAirport3L(): void
    {
        $this->assertSame('DUS', Query::extractAirport3L('dus'));
        $this->assertNull(Query::extractAirport3L('xy'));
    }

    public function testExtractHousingPackageId(): void
    {
        $this->assertSame('pkg-1', Query::extractHousingPackageId('pkg-1'));
        $this->assertNull(Query::extractHousingPackageId('invalid!'));
    }

    public function testSanitizeStr(): void
    {
        $this->assertSame('safe', Query::sanitizeStr('  safe  '));
        $this->assertSame('München', Query::sanitizeStr('München'));
    }

    public function testGetCurrentPageAndPageSize(): void
    {
        // Query uses private static $page and $page_size; we only assert default getters
        $this->assertSame(1, Query::getCurrentPage());
        $this->assertSame(10, Query::getPageSize());
    }

    public function testGetCurrentQueryString(): void
    {
        $qs = Query::getCurrentQueryString();
        $this->assertIsString($qs);
        $custom = Query::getCurrentQueryString(2, 20);
        $this->assertStringContainsString('2,20', $custom);
        $withParams = Query::getCurrentQueryString(null, null, ['pm-ot' => '100']);
        $this->assertStringContainsString('pm-ot', $withParams);
    }

    public function testGetAvailableOrderOptions(): void
    {
        $options = Query::getAvailableOrderOptions();
        $this->assertIsArray($options);
        $this->assertArrayHasKey('price-asc', $options);
        $this->assertArrayHasKey('price-desc', $options);
        $this->assertArrayNotHasKey('rand', $options);
        $this->assertArrayNotHasKey('priority', $options);
        $customDisabled = Query::getAvailableOrderOptions(null, ['price-asc']);
        $this->assertArrayNotHasKey('price-asc', $customDisabled);
    }

    public function testFromRequestEmptyRequestReturnsMongoDB(): void
    {
        $search = Query::fromRequest([], 'pm', true, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
    }

    public function testFromRequestWithObjectType(): void
    {
        $search = Query::fromRequest(['pm-ot' => '100'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertStringContainsString('100', Query::getCurrentQueryString(null, null, [], 'pm'));
    }

    public function testFromRequestWithPaginator(): void
    {
        $search = Query::fromRequest(['pm-l' => '2,20'], 'pm', true, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertSame(2, Query::getCurrentPage());
        $this->assertSame(20, Query::getPageSize());
    }

    public function testRebuildRemovesParameter(): void
    {
        Query::fromRequest(['pm-ot' => '100', 'pm-du' => '7-14'], 'pm', false, 10);
        $qsBefore = Query::getCurrentQueryString();
        $this->assertStringContainsString('pm-ot', $qsBefore);
        $search = Query::rebuild(['pm-ot'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $qsAfter = Query::getCurrentQueryString();
        $this->assertStringNotContainsString('pm-ot', $qsAfter);
    }

    public function testRebuildRemovesNestedParameter(): void
    {
        Query::fromRequest(['pm-c' => ['destination' => '1,2']], 'pm', false, 10);
        $search = Query::rebuild(['pm-c[destination]'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
    }

    public function testExtractDurationRangeWithDefault(): void
    {
        $this->assertSame(['1', '99999'], Query::extractDurationRange('invalid', ['1', '99999']));
    }

    public function testExtractPriceRangeWithDefault(): void
    {
        $this->assertSame(['0', '9999999'], Query::extractPriceRange('x', ['0', '9999999']));
    }

    public function testExtractTransportTypesFirstKeyAsString(): void
    {
        $this->assertSame('BUS', Query::extractTransportTypes('BUS,FLUG', [], true));
    }

    public function testExtractAirport3LWithDefault(): void
    {
        $this->assertSame('FALLBACK', Query::extractAirport3L('xy', 'FALLBACK'));
    }

    // =========================================================================
    // fromRequest: all parameter branches
    // =========================================================================

    public function testFromRequestWithFulltext(): void
    {
        $search = Query::fromRequest(['pm-t' => 'mallorca'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('Fulltext'));
        $this->assertStringContainsString('pm-t', Query::getCurrentQueryString());
    }

    public function testFromRequestWithAtlasFulltext(): void
    {
        Query::$atlas_active = true;
        Query::$atlas_definition = false;
        $search = Query::fromRequest(['pm-t' => 'italien'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('AtlasLuceneFulltext'));
    }

    public function testFromRequestWithAtlasLocation(): void
    {
        Query::$atlas_active = true;
        $search = Query::fromRequest(['pm-loc' => ['geo_prop' => '50.123,12.456,25']], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('AtlasLuceneFulltext'));
    }

    public function testFromRequestWithCode(): void
    {
        $search = Query::fromRequest(['pm-co' => 'ABC-123,DEF'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('Code'));
        $this->assertStringContainsString('pm-co', Query::getCurrentQueryString());
    }

    public function testFromRequestWithPriceRange(): void
    {
        $search = Query::fromRequest(['pm-pr' => '100-500'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('PriceRange'));
        $this->assertStringContainsString('pm-pr=100-500', Query::getCurrentQueryString());
    }

    public function testFromRequestWithDurationRange(): void
    {
        $search = Query::fromRequest(['pm-du' => '3-14'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('DurationRange'));
        $this->assertStringContainsString('pm-du=3-14', Query::getCurrentQueryString());
    }

    public function testFromRequestWithDateRange(): void
    {
        $search = Query::fromRequest(['pm-dr' => '20260601-20260630'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('DateRange'));
        $this->assertStringContainsString('pm-dr', Query::getCurrentQueryString());
    }

    public function testFromRequestWithBoardType(): void
    {
        $search = Query::fromRequest(['pm-bt' => 'HP,VP'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('BoardType'));
        $this->assertStringContainsString('pm-bt', Query::getCurrentQueryString());
    }

    public function testFromRequestWithTransportType(): void
    {
        $search = Query::fromRequest(['pm-tr' => 'BUS,FLUG'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('TransportType'));
    }

    public function testFromRequestWithStartingPointCity(): void
    {
        $search = Query::fromRequest(['pm-sc' => 'city1,city2'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('StartingPointOptionCity'));
    }

    public function testFromRequestWithSoldOut(): void
    {
        $search = Query::fromRequest(['pm-so' => '0'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('SoldOut'));
        $this->assertStringContainsString('pm-so=0', Query::getCurrentQueryString());
    }

    public function testFromRequestWithIsRunning(): void
    {
        $search = Query::fromRequest(['pm-ir' => '1'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('Running'));
        $this->assertStringContainsString('pm-ir=1', Query::getCurrentQueryString());
    }

    public function testFromRequestWithGuaranteed(): void
    {
        $search = Query::fromRequest(['pm-gu' => '1'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('Guaranteed'));
    }

    public function testFromRequestWithSalesPriority(): void
    {
        $search = Query::fromRequest(['pm-sp' => 'A000001'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('SalesPriority'));
    }

    public function testFromRequestWithSalesPriorityInvalidIgnored(): void
    {
        $search = Query::fromRequest(['pm-sp' => 'invalid'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertFalse($search->hasCondition('SalesPriority'));
    }

    public function testFromRequestWithCategoryOr(): void
    {
        $search = Query::fromRequest(['pm-c' => ['land_default' => '1,2,3']], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('Category'));
        $this->assertStringContainsString('pm-c', Query::getCurrentQueryString());
    }

    public function testFromRequestWithCategoryAnd(): void
    {
        $search = Query::fromRequest(['pm-c' => ['region' => '1+2']], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('Category'));
    }

    public function testFromRequestWithOccupancy(): void
    {
        $search = Query::fromRequest(['pm-ho' => '2,3'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('Occupancy'));
    }

    public function testFromRequestWithOccupancyChild(): void
    {
        $search = Query::fromRequest(['pm-ho' => '2', 'pm-hoc' => '1'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('Occupancy'));
        $this->assertStringContainsString('pm-hoc', Query::getCurrentQueryString());
    }

    public function testFromRequestWithMediaObjectIds(): void
    {
        $search = Query::fromRequest(['pm-id' => '100,200,300'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('MediaObject'));
    }

    public function testFromRequestWithMediaObjectExclusion(): void
    {
        $search = Query::fromRequest(['pm-id' => '-100,200'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('MediaObject'));
    }

    public function testFromRequestWithPowerfilter(): void
    {
        $search = Query::fromRequest(['pm-pf' => '12345'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('Powerfilter'));
    }

    public function testFromRequestWithUrl(): void
    {
        $search = Query::fromRequest(['pm-url' => '/travel/italia/'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('Url'));
    }

    public function testFromRequestWithGroup(): void
    {
        $search = Query::fromRequest(['pm-gr' => 'g1,g2'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('Group'));
    }

    public function testFromRequestWithStaticGroupKeys(): void
    {
        Query::$group_keys = ['static_group'];
        $search = Query::fromRequest([], 'pm', false, 10);
        $this->assertTrue($search->hasCondition('Group'));
    }

    // =========================================================================
    // fromRequest: Sort orders
    // =========================================================================

    public function testFromRequestSortRand(): void
    {
        $search = Query::fromRequest(['pm-o' => 'rand'], 'pm', true, 10);
        $this->assertStringContainsString('pm-o=rand', Query::getCurrentQueryString());
    }

    public function testFromRequestSortPriceDesc(): void
    {
        $search = Query::fromRequest(['pm-o' => 'price-desc'], 'pm', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertStringContainsString('pm-o=price-desc', Query::getCurrentQueryString());
    }

    public function testFromRequestSortDateDepartureAsc(): void
    {
        $search = Query::fromRequest(['pm-o' => 'date_departure-asc'], 'pm', false, 10);
        $this->assertStringContainsString('pm-o=date_departure-asc', Query::getCurrentQueryString());
    }

    public function testFromRequestSortScoreDesc(): void
    {
        $search = Query::fromRequest(['pm-o' => 'score-desc', 'pm-t' => 'test'], 'pm', false, 10);
        $this->assertStringContainsString('pm-o=score-desc', Query::getCurrentQueryString());
    }

    public function testFromRequestSortRecommendationRate(): void
    {
        $search = Query::fromRequest(['pm-o' => 'recommendation_rate-desc'], 'pm', false, 10);
        $this->assertStringContainsString('pm-o=recommendation_rate-desc', Query::getCurrentQueryString());
    }

    public function testFromRequestSortPriority(): void
    {
        $search = Query::fromRequest(['pm-o' => 'priority'], 'pm', false, 10);
        $this->assertStringContainsString('pm-o=priority', Query::getCurrentQueryString());
    }

    public function testFromRequestSortList(): void
    {
        $search = Query::fromRequest(['pm-o' => 'list', 'pm-id' => '1,2,3'], 'pm', false, 10);
        $this->assertStringContainsString('pm-o=list', Query::getCurrentQueryString());
    }

    public function testFromRequestSortValidFrom(): void
    {
        $search = Query::fromRequest(['pm-o' => 'valid_from-desc'], 'pm', false, 10);
        $this->assertStringContainsString('pm-o=valid_from-desc', Query::getCurrentQueryString());
    }

    public function testFromRequestSortInvalidIgnored(): void
    {
        $search = Query::fromRequest(['pm-o' => 'invalid_sort'], 'pm', false, 10);
        $this->assertStringNotContainsString('pm-o', Query::getCurrentQueryString());
    }

    public function testFromRequestSortCustomOrder(): void
    {
        $config = $this->getMongoDbConfig();
        $config['data']['search_mongodb']['search']['custom_order'] = [
            100 => ['destination' => ['field' => 'categories.destination', 'direction' => 'asc']],
        ];
        Registry::getInstance()->add('config', $config);
        $search = Query::fromRequest(['pm-ot' => '100', 'pm-o' => 'co.destination-asc'], 'pm', false, 10);
        $this->assertStringContainsString('pm-o=co.destination-asc', Query::getCurrentQueryString());
    }

    public function testFromRequestSortCustomOrderInvalidFallback(): void
    {
        $search = Query::fromRequest(['pm-ot' => '100', 'pm-o' => 'co.nonexistent-asc'], 'pm', true, 10);
        $stages = $search->buildQuery();
        $facet = null;
        foreach ($stages as $stage) {
            if (isset($stage['$facet']['documents'])) {
                foreach ($stage['$facet']['documents'] as $step) {
                    if (isset($step['$sort'])) {
                        $facet = $step;
                    }
                }
            }
        }
        $this->assertNotNull($facet);
        $this->assertSame(1, $facet['$sort']['sales_priority']);
    }

    // =========================================================================
    // fromRequest: custom prefix
    // =========================================================================

    public function testFromRequestWithCustomPrefix(): void
    {
        $search = Query::fromRequest(['ts-ot' => '100'], 'ts', false, 10);
        $this->assertInstanceOf(MongoDB::class, $search);
        $this->assertTrue($search->hasCondition('ObjectType'));
    }

    // =========================================================================
    // fromRequest: custom conditions
    // =========================================================================

    public function testFromRequestWithCustomConditions(): void
    {
        $customCond = new \Pressmind\Search\Condition\MongoDB\MediaObject([999]);
        $search = Query::fromRequest([], 'pm', false, 10, [$customCond]);
        $this->assertTrue($search->hasCondition('MediaObject'));
    }

    // =========================================================================
    // fromRequest: validation (invalid inputs ignored)
    // =========================================================================

    public function testFromRequestInvalidPriceRangeIgnored(): void
    {
        $search = Query::fromRequest(['pm-pr' => 'not-a-range'], 'pm', false, 10);
        $this->assertFalse($search->hasCondition('PriceRange'));
    }

    public function testFromRequestInvalidCodeIgnored(): void
    {
        $search = Query::fromRequest(['pm-co' => 'invalid!@#'], 'pm', false, 10);
        $this->assertFalse($search->hasCondition('Code'));
    }

    public function testFromRequestInvalidOccupancyIgnored(): void
    {
        $search = Query::fromRequest(['pm-ho' => 'abc'], 'pm', false, 10);
        $this->assertFalse($search->hasCondition('Occupancy'));
    }

    public function testFromRequestInvalidMediaObjectIdIgnored(): void
    {
        $search = Query::fromRequest(['pm-id' => 'abc'], 'pm', false, 10);
        $this->assertFalse($search->hasCondition('MediaObject'));
    }

    public function testFromRequestInvalidPowerfilterIgnored(): void
    {
        $search = Query::fromRequest(['pm-pf' => 'abc!'], 'pm', false, 10);
        $this->assertFalse($search->hasCondition('Powerfilter'));
    }

    public function testFromRequestInvalidDateRangeIgnored(): void
    {
        $search = Query::fromRequest(['pm-dr' => 'invalid'], 'pm', false, 10);
        $this->assertFalse($search->hasCondition('DateRange'));
    }

    // =========================================================================
    // fromRequest: combined parameters
    // =========================================================================

    public function testFromRequestCombinedParameters(): void
    {
        $search = Query::fromRequest([
            'pm-ot' => '100',
            'pm-pr' => '200-800',
            'pm-du' => '5-10',
            'pm-bt' => 'HP',
            'pm-tr' => 'BUS',
            'pm-o' => 'price-asc',
            'pm-l' => '2,15',
        ], 'pm', true, 15);
        $this->assertTrue($search->hasCondition('ObjectType'));
        $this->assertTrue($search->hasCondition('PriceRange'));
        $this->assertTrue($search->hasCondition('DurationRange'));
        $this->assertTrue($search->hasCondition('BoardType'));
        $this->assertTrue($search->hasCondition('TransportType'));
        $this->assertSame(2, Query::getCurrentPage());
        $this->assertSame(15, Query::getPageSize());
    }
}
