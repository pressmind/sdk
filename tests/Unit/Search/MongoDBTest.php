<?php

namespace Pressmind\Tests\Unit\Search;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;
use Pressmind\Search\Paginator;
use Pressmind\Search\Condition\MongoDB\MediaObject;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Search\MongoDB: query building, options, getCollectionName.
 * No real MongoDB connection; Registry/config and conditions are mocked.
 */
class MongoDBTest extends AbstractTestCase
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
        MongoDB::clearConnectionCache();
    }

    protected function tearDown(): void
    {
        MongoDB::clearConnectionCache();
        parent::tearDown();
    }

    // --- getCollectionName (static) ---

    public function testGetCollectionNameDefaultPrefix(): void
    {
        $name = MongoDB::getCollectionName('best_price_search_based_', null, 0, null);
        $this->assertSame('best_price_search_based_origin_0', $name);
    }

    public function testGetCollectionNameWithLanguage(): void
    {
        $name = MongoDB::getCollectionName('best_price_search_based_', 'de', 0, null);
        $this->assertSame('best_price_search_based_de_origin_0', $name);
    }

    public function testGetCollectionNameWithAgency(): void
    {
        $name = MongoDB::getCollectionName('best_price_search_based_', null, 1, 'AG1');
        $this->assertSame('best_price_search_based_origin_1_agency_AG1', $name);
    }

    public function testGetCollectionNameWithLanguageAndAgency(): void
    {
        $name = MongoDB::getCollectionName('description_', 'en', 2, 'X');
        $this->assertSame('description_en_origin_2_agency_X', $name);
    }

    // --- clearConnectionCache ---

    public function testClearConnectionCache(): void
    {
        MongoDB::clearConnectionCache();
        $this->assertTrue(true, 'No exception');
    }

    // --- Constructor + getters (no DB access in constructor beyond config) ---

    public function testConstructorSetsCollectionNameAndAgency(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $this->assertSame('best_price_search_based_de_origin_0', $search->getCurrentCollectionName());
        $this->assertNull($search->getAgency());
    }

    public function testConstructorWithAgency(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'en', 1, 'MY_AGENCY');
        $this->assertSame('best_price_search_based_en_origin_1_agency_MY_AGENCY', $search->getCurrentCollectionName());
        $this->assertSame('MY_AGENCY', $search->getAgency());
    }

    // --- Conditions ---

    public function testAddConditionGetConditionByTypeHasConditionListConditions(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $cond = new MediaObject([1, 2, 3]);
        $search->addCondition('MediaObject', $cond);

        $this->assertTrue($search->hasCondition('MediaObject'));
        $this->assertFalse($search->hasCondition('DateRange'));
        $this->assertSame($cond, $search->getConditionByType('MediaObject'));
        $this->assertContains('MediaObject', $search->listConditions());
    }

    public function testRemoveCondition(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->addCondition('MediaObject', new MediaObject([1]));
        $search->removeCondition('MediaObject');
        $this->assertFalse($search->hasCondition('MediaObject'));
        $this->assertFalse($search->getConditionByType('MediaObject'));
    }

    // --- prepareQuery ---

    public function testPrepareQueryReturnsArray(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->addCondition('MediaObject', new MediaObject([10, 20]));
        $prepared = $search->prepareQuery();
        $this->assertIsArray($prepared);
        $this->assertContains('MediaObject', $search->listConditions());
    }

    // --- buildQuery (no DB; uses conditions + config) ---

    public function testBuildQueryReturnsArrayOfStages(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->addCondition('MediaObject', new MediaObject([1, 2, 3]));
        $stages = $search->buildQuery();
        $this->assertIsArray($stages);
        $this->assertNotEmpty($stages);
        foreach ($stages as $stage) {
            $this->assertIsArray($stage);
        }
    }

    public function testBuildQueryWithPaginator(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->addCondition('MediaObject', new MediaObject([1]));
        $paginator = new Paginator(2, 10);
        $search->setPaginator($paginator);
        $stages = $search->buildQuery();
        $this->assertNotEmpty($stages);
    }

    public function testBuildQueryAsJson(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->setGetFilters(true);
        $json = $search->buildQueryAsJson(true, null, null, [30]);
        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
    }

    // --- generateCacheKey ---

    public function testGenerateCacheKeyDeterministic(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->addCondition('MediaObject', new MediaObject([1]));
        $key1 = $search->generateCacheKey('', null, null, [30]);
        $key2 = $search->generateCacheKey('', null, null, [30]);
        $this->assertStringStartsWith('MONGODB:', $key1);
        $this->assertSame($key1, $key2);
    }

    public function testGenerateCacheKeyWithAdd(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $key = $search->generateCacheKey('suffix', null, null, [30]);
        $this->assertStringContainsString('suffix', $key);
    }

    // --- getLog ---

    public function testGetLogWhenLoggingDisabled(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $log = $search->getLog();
        $this->assertIsArray($log);
        $this->assertStringContainsString('Logging is disabled', $log[0] ?? '');
    }

    // --- setGetFilters / setReturnFiltersOnly ---

    public function testSetGetFiltersAndSetReturnFiltersOnly(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->setGetFilters(true);
        $search->setReturnFiltersOnly(true);
        $stages = $search->buildQuery(null, null, [30]);
        $this->assertNotEmpty($stages);
    }

    public function testGetLogWhenLoggingEnabled(): void
    {
        $config = $this->getMongoDbConfig();
        $config['logging'] = ['enable_advanced_object_log' => true];
        Registry::getInstance()->add('config', $config);
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $log = $search->getLog();
        $this->assertIsArray($log);
        $this->assertNotEmpty($log);
        $this->assertStringContainsString('__construct', $log[0]);
    }

    // =========================================================================
    // buildQuery: Sort Branches
    // =========================================================================

    private function createSearchWithSort(array $sort): MongoDB
    {
        $search = new MongoDB([], $sort, 'de', 0, null);
        $search->addCondition('MediaObject', new MediaObject([1]));
        $search->setPaginator(new Paginator(10, 1));
        return $search;
    }

    private function findFacetStage(array $stages): ?array
    {
        foreach ($stages as $stage) {
            if (isset($stage['$facet'])) {
                return $stage;
            }
        }
        return null;
    }

    private function findSortInFacet(array $stages): ?array
    {
        $facet = $this->findFacetStage($stages);
        if ($facet && isset($facet['$facet']['documents'])) {
            foreach ($facet['$facet']['documents'] as $step) {
                if (isset($step['$sort']) || isset($step['$sample'])) {
                    return $step;
                }
            }
        }
        return null;
    }

    public function testBuildQuerySortPriceAsc(): void
    {
        $stages = $this->createSearchWithSort(['price_total' => 'asc'])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertArrayHasKey('$sort', $sort);
        $this->assertSame(1, $sort['$sort']['prices.price_total']);
        $this->assertSame(-1, $sort['$sort']['prices.duration']);
    }

    public function testBuildQuerySortPriceDesc(): void
    {
        $stages = $this->createSearchWithSort(['price_total' => 'desc'])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertSame(-1, $sort['$sort']['prices.price_total']);
        $this->assertSame(1, $sort['$sort']['prices.duration']);
    }

    public function testBuildQuerySortRand(): void
    {
        $stages = $this->createSearchWithSort(['rand' => ''])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertArrayHasKey('$sample', $sort);
        $this->assertSame(10, $sort['$sample']['size']);
    }

    public function testBuildQuerySortScore(): void
    {
        $stages = $this->createSearchWithSort(['score' => 'desc'])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertSame(-1, $sort['$sort']['score']);
    }

    public function testBuildQuerySortDateDeparture(): void
    {
        $stages = $this->createSearchWithSort(['date_departure' => 'asc'])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $hasAddFields = false;
        foreach ($stages as $stage) {
            if (isset($stage['$addFields']['fst_date_departure'])) {
                $hasAddFields = true;
                break;
            }
        }
        $this->assertTrue($hasAddFields, 'fst_date_departure $addFields stage expected');
    }

    public function testBuildQuerySortDateDepartureDesc(): void
    {
        $stages = $this->createSearchWithSort(['date_departure' => 'desc'])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertSame(-1, $sort['$sort']['fst_date_departure']);
    }

    public function testBuildQuerySortRecommendationRate(): void
    {
        $stages = $this->createSearchWithSort(['recommendation_rate' => 'desc'])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertSame(-1, $sort['$sort']['recommendation_rate']);
    }

    public function testBuildQuerySortPriority(): void
    {
        $stages = $this->createSearchWithSort(['priority' => ''])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertSame(1, $sort['$sort']['sales_priority']);
        $this->assertArrayNotHasKey('prices.price_total', $sort['$sort']);
    }

    public function testBuildQuerySortList(): void
    {
        $search = new MongoDB([], ['list' => ''], 'de', 0, null);
        $search->addCondition('MediaObject', new MediaObject([10, 20, 30]));
        $search->setPaginator(new Paginator(10, 1));
        $stages = $search->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertSame(1, $sort['$sort']['sort']);
        $hasIndexOfArray = false;
        foreach ($stages as $stage) {
            if (isset($stage['$addFields']['sort']['$indexOfArray'])) {
                $hasIndexOfArray = true;
                break;
            }
        }
        $this->assertTrue($hasIndexOfArray);
    }

    public function testBuildQuerySortListByCode(): void
    {
        $search = new MongoDB([], ['list' => ''], 'de', 0, null);
        $search->addCondition('Code', new \Pressmind\Search\Condition\MongoDB\Code(['ABC', 'DEF']));
        $search->setPaginator(new Paginator(10, 1));
        $stages = $search->buildQuery();
        $hasIndexOfArray = false;
        foreach ($stages as $stage) {
            if (isset($stage['$addFields']['sort']['$indexOfArray'])) {
                $this->assertSame(['ABC', 'DEF'], $stage['$addFields']['sort']['$indexOfArray'][0]);
                $hasIndexOfArray = true;
                break;
            }
        }
        $this->assertTrue($hasIndexOfArray);
    }

    public function testBuildQuerySortValidFrom(): void
    {
        $stages = $this->createSearchWithSort(['valid_from' => 'asc'])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertSame(1, $sort['$sort']['valid_from']);
    }

    public function testBuildQuerySortValidFromDesc(): void
    {
        $stages = $this->createSearchWithSort(['valid_from' => 'desc'])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertSame(-1, $sort['$sort']['valid_from']);
    }

    public function testBuildQuerySortCustomOrder(): void
    {
        $stages = $this->createSearchWithSort(['custom_order.destination' => 'asc'])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertSame(1, $sort['$sort']['custom_order.destination']);
    }

    public function testBuildQuerySortDefaultFallback(): void
    {
        $stages = $this->createSearchWithSort(['unknown_sort' => 'asc'])->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertSame(1, $sort['$sort']['sales_priority']);
    }

    // =========================================================================
    // buildQuery: Config Variants
    // =========================================================================

    public function testBuildQueryAllowInvalidOffers(): void
    {
        $config = $this->getMongoDbConfig();
        $config['data']['search_mongodb']['search']['allow_invalid_offers'] = true;
        Registry::getInstance()->add('config', $config);
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $stages = $search->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertSame(-1, $sort['$sort']['has_price']);
    }

    public function testBuildQueryOrderByObjectTypePriority(): void
    {
        $config = $this->getMongoDbConfig();
        $config['data']['search_mongodb']['search']['order_by_primary_object_type_priority'] = true;
        Registry::getInstance()->add('config', $config);
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $stages = $search->buildQuery();
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $keys = array_keys($sort['$sort']);
        $this->assertSame('object_type_order', $keys[0]);
    }

    public function testBuildQueryStartingPointIndex(): void
    {
        $config = $this->getMongoDbConfig();
        $config['data']['touristic']['generate_offer_for_each_startingpoint_option'] = true;
        Registry::getInstance()->add('config', $config);
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $stages = $search->buildQuery();
        $hasGroup = false;
        $hasReplaceRoot = false;
        foreach ($stages as $stage) {
            if (isset($stage['$group']['startingpoint_options'])) {
                $hasGroup = true;
            }
            if (isset($stage['$replaceRoot'])) {
                $hasReplaceRoot = true;
            }
        }
        $this->assertTrue($hasGroup, 'StartingPoint $group stage expected');
        $this->assertTrue($hasReplaceRoot, '$replaceRoot stage expected');
    }

    public function testBuildQueryOpenSearchUnsetFulltext(): void
    {
        $config = $this->getMongoDbConfig();
        $config['data']['search_opensearch']['enabled'] = true;
        $config['data']['search_opensearch']['enabled_in_mongo_search'] = true;
        Registry::getInstance()->add('config', $config);
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $stages = $search->buildQuery();
        $hasUnsetFulltext = false;
        foreach ($stages as $stage) {
            if (isset($stage['$unset']) && in_array('fulltext', (array)$stage['$unset'])) {
                $hasUnsetFulltext = true;
            }
        }
        $this->assertFalse($hasUnsetFulltext, 'fulltext should NOT be unset when OpenSearch is active');
    }

    public function testBuildQueryNoOpenSearchUnsetsFulltext(): void
    {
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $stages = $search->buildQuery();
        $hasUnsetFulltext = false;
        foreach ($stages as $stage) {
            if (isset($stage['$unset']) && in_array('fulltext', (array)$stage['$unset'])) {
                $hasUnsetFulltext = true;
            }
        }
        $this->assertTrue($hasUnsetFulltext, 'fulltext should be unset when OpenSearch is disabled');
    }

    // =========================================================================
    // buildQuery: date_list output mode
    // =========================================================================

    public function testBuildQueryDateListOutputMode(): void
    {
        $search = $this->createSearchWithSort(['date_departure' => 'asc']);
        $stages = $search->buildQuery('date_list');
        $hasUnwindPrices = false;
        $hasGroupDateList = false;
        foreach ($stages as $stage) {
            if (isset($stage['$unwind']['path']) && $stage['$unwind']['path'] === '$prices') {
                $hasUnwindPrices = true;
            }
            if (isset($stage['$group']['prices']['$push'])) {
                $hasGroupDateList = true;
            }
        }
        $this->assertTrue($hasUnwindPrices, 'date_list requires $unwind $prices');
        $this->assertTrue($hasGroupDateList, 'date_list requires $group with $push prices');
    }

    public function testBuildQueryDateListSortContainsDepartureDate(): void
    {
        $search = $this->createSearchWithSort(['date_departure' => 'asc']);
        $stages = $search->buildQuery('date_list');
        $sort = $this->findSortInFacet($stages);
        $this->assertNotNull($sort);
        $this->assertArrayHasKey('prices.date_departures', $sort['$sort']);
    }

    // =========================================================================
    // buildQuery: Validity and Visibility stages
    // =========================================================================

    public function testBuildQueryValidityStageUsesCurrentDate(): void
    {
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $now = new \DateTime();
        $stages = $search->buildQuery(null, null, [30]);
        $found = false;
        foreach ($stages as $stage) {
            if (isset($stage['$match']['$and'])) {
                foreach ($stage['$match']['$and'] as $cond) {
                    if (isset($cond['$or'][0]['valid_from']['$lte'])) {
                        $found = true;
                        $stageDate = new \DateTime($cond['$or'][0]['valid_from']['$lte']);
                        $this->assertEqualsWithDelta($now->getTimestamp(), $stageDate->getTimestamp(), 5);
                    }
                }
            }
        }
        $this->assertTrue($found, 'Validity stage with valid_from expected');
    }

    public function testBuildQueryPreviewDateOverridesCurrentDate(): void
    {
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $previewDate = new \DateTime('2025-01-15');
        $stages = $search->buildQuery(null, $previewDate, [30]);
        $found = false;
        foreach ($stages as $stage) {
            if (isset($stage['$match']['$and'])) {
                foreach ($stage['$match']['$and'] as $cond) {
                    if (isset($cond['$or'][0]['valid_from']['$lte'])) {
                        $this->assertStringContainsString('2025-01-15', $cond['$or'][0]['valid_from']['$lte']);
                        $found = true;
                    }
                }
            }
        }
        $this->assertTrue($found, 'preview_date should be used for validity check');
    }

    public function testBuildQueryVisibilityStagePresent(): void
    {
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $stages = $search->buildQuery(null, null, [30, 40]);
        $found = false;
        foreach ($stages as $stage) {
            if (isset($stage['$match']['visibility']['$in'])) {
                $this->assertSame([30, 40], $stage['$match']['visibility']['$in']);
                $found = true;
            }
        }
        $this->assertTrue($found, 'Visibility stage expected');
    }

    public function testBuildQueryVisibilitySkippedWithPreviewDate(): void
    {
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $stages = $search->buildQuery(null, new \DateTime('2025-01-01'), [30]);
        $found = false;
        foreach ($stages as $stage) {
            if (isset($stage['$match']['visibility'])) {
                $found = true;
            }
        }
        $this->assertFalse($found, 'Visibility stage should be skipped with preview_date');
    }

    // =========================================================================
    // buildQuery: Condition Pipeline Stages
    // =========================================================================

    public function testBuildQueryPowerfilterStages(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->addCondition('Powerfilter', new \Pressmind\Search\Condition\MongoDB\Powerfilter(42));
        $search->setPaginator(new Paginator(10, 1));
        $stages = $search->buildQuery();
        $hasLookup = false;
        $hasMatch = false;
        foreach ($stages as $stage) {
            if (isset($stage['$lookup']['from']) && $stage['$lookup']['from'] === 'powerfilter') {
                $hasLookup = true;
            }
            if (isset($stage['$match']['matchedPowerfilter._id'])) {
                $this->assertSame(42, $stage['$match']['matchedPowerfilter._id']);
                $hasMatch = true;
            }
        }
        $this->assertTrue($hasLookup, 'Powerfilter $lookup stage expected');
        $this->assertTrue($hasMatch, 'Powerfilter $match stage expected');
    }

    public function testBuildQueryFulltextProjectStage(): void
    {
        $search = new MongoDB([], ['score' => 'desc'], 'de', 0, null);
        $search->addCondition('Fulltext', new \Pressmind\Search\Condition\MongoDB\Fulltext('test search'));
        $search->setPaginator(new Paginator(10, 1));
        $stages = $search->buildQuery();
        $hasFulltextProject = false;
        foreach ($stages as $stage) {
            if (isset($stage['$project']['visibility']) && $stage['$project']['visibility'] === 1 && !isset($stage['$project']['score'])) {
                $hasFulltextProject = true;
            }
        }
        $this->assertTrue($hasFulltextProject, 'Fulltext $project stage expected');
    }

    public function testBuildQueryPricesMerging(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->addCondition('PriceRange', new \Pressmind\Search\Condition\MongoDB\PriceRange(100, 500));
        $search->addCondition('DurationRange', new \Pressmind\Search\Condition\MongoDB\DurationRange(3, 14));
        $search->setPaginator(new Paginator(10, 1));
        $stages = $search->buildQuery();
        $matchStage = null;
        foreach ($stages as $stage) {
            if (isset($stage['$match']['$and'])) {
                $matchStage = $stage;
                break;
            }
        }
        $this->assertNotNull($matchStage, '$match with $and expected');
        $mergedPrices = null;
        foreach ($matchStage['$match']['$and'] as $cond) {
            if (isset($cond['prices']['$elemMatch']['price_total']) && isset($cond['prices']['$elemMatch']['duration'])) {
                $mergedPrices = $cond;
            }
        }
        $this->assertNotNull($mergedPrices, 'PriceRange + DurationRange should merge into single prices.$elemMatch');
    }

    public function testBuildQueryDateRangeDepartureFilter(): void
    {
        $from = new \DateTime('2026-06-01');
        $to = new \DateTime('2026-08-31');
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->addCondition('DateRange', new \Pressmind\Search\Condition\MongoDB\DateRange($from, $to));
        $search->setPaginator(new Paginator(10, 1));
        $stages = $search->buildQuery();
        $hasDepartureFilter = false;
        foreach ($stages as $stage) {
            $json = json_encode($stage);
            if (strpos($json, '2026-06-01') !== false && strpos($json, 'date_departures') !== false && isset($stage['$addFields'])) {
                $hasDepartureFilter = true;
            }
        }
        $this->assertTrue($hasDepartureFilter, 'DateRange departure_filter $addFields stage expected');
    }

    public function testBuildQueryStageAfterMatch(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->addCondition('Occupancy', new \Pressmind\Search\Condition\MongoDB\Occupancy([2]));
        $search->setPaginator(new Paginator(10, 1));
        $stages = $search->buildQuery();
        $hasPricesFilter = false;
        foreach ($stages as $stage) {
            if (isset($stage['$addFields']['prices']['$filter']['cond']['$and'])) {
                $conds = $stage['$addFields']['prices']['$filter']['cond']['$and'];
                foreach ($conds as $c) {
                    if (isset($c['$in']) && is_array($c['$in']) && count($c['$in']) === 2 && $c['$in'][0] === '$$this.occupancy') {
                        $hasPricesFilter = true;
                    }
                }
            }
        }
        $this->assertTrue($hasPricesFilter, 'Occupancy prices_filter should produce $in condition');
    }

    // =========================================================================
    // buildQuery: Filter/Facet stages
    // =========================================================================

    public function testBuildQueryFiltersEnabledHasFacetFields(): void
    {
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $search->setGetFilters(true);
        $stages = $search->buildQuery();
        $facet = $this->findFacetStage($stages);
        $this->assertNotNull($facet);
        $this->assertArrayHasKey('categoriesGrouped', $facet['$facet']);
        $this->assertArrayHasKey('boardTypesGrouped', $facet['$facet']);
        $this->assertArrayHasKey('transportTypesGrouped', $facet['$facet']);
        $this->assertArrayHasKey('startingPointsGrouped', $facet['$facet']);
        $this->assertArrayHasKey('sold_out', $facet['$facet']);
        $this->assertArrayHasKey('is_running', $facet['$facet']);
    }

    public function testBuildQueryFiltersDisabledNoFacetFields(): void
    {
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $search->setGetFilters(false);
        $search->setReturnFiltersOnly(false);
        $stages = $search->buildQuery();
        $facet = $this->findFacetStage($stages);
        $this->assertNotNull($facet);
        $this->assertArrayNotHasKey('categoriesGrouped', $facet['$facet']);
        $this->assertArrayNotHasKey('boardTypesGrouped', $facet['$facet']);
    }

    public function testBuildQueryReturnFiltersOnlyOmitsDocumentsInProject(): void
    {
        $search = $this->createSearchWithSort(['price_total' => 'asc']);
        $search->setReturnFiltersOnly(true);
        $stages = $search->buildQuery();
        $projectStage = end($stages);
        $this->assertArrayHasKey('$project', $projectStage);
        $facet = $this->findFacetStage($stages);
        $this->assertNotNull($facet);
        $this->assertArrayHasKey('categoriesGrouped', $facet['$facet']);
    }

    public function testBuildQueryPaginatorSlice(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->addCondition('MediaObject', new MediaObject([1]));
        $search->setPaginator(new Paginator(10, 3));
        $stages = $search->buildQuery();
        $lastStage = end($stages);
        $this->assertArrayHasKey('$project', $lastStage);
        if (isset($lastStage['$project']['documents']['$slice'])) {
            $slice = $lastStage['$project']['documents']['$slice'];
            $this->assertSame(20, $slice[1]);
            $this->assertSame(10, $slice[2]);
        }
        $this->assertArrayHasKey('pages', $lastStage['$project']);
        $this->assertArrayHasKey('currentPage', $lastStage['$project']);
    }

    // =========================================================================
    // Condition-aware case sensitivity
    // =========================================================================

    public function testConditionCaseInsensitiveLookup(): void
    {
        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search->addCondition('mediaobject', new MediaObject([1]));
        $this->assertTrue($search->hasCondition('MediaObject'));
        $this->assertNotFalse($search->getConditionByType('MEDIAOBJECT'));
    }

    public function testConstructorWithConditions(): void
    {
        $cond = new MediaObject([1, 2]);
        $search = new MongoDB([$cond], ['price_total' => 'asc'], 'de', 0, null);
        $this->assertTrue($search->hasCondition('MediaObject'));
    }

    public function testGenerateCacheKeyDifferentConditions(): void
    {
        $search1 = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search1->addCondition('MediaObject', new MediaObject([1]));
        $search2 = new MongoDB([], ['price_total' => 'asc'], 'de', 0, null);
        $search2->addCondition('MediaObject', new MediaObject([2]));
        $this->assertNotSame(
            $search1->generateCacheKey('', null, null, [30]),
            $search2->generateCacheKey('', null, null, [30])
        );
    }
}
