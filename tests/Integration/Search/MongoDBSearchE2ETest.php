<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Registry;
use Pressmind\Search\Condition\MongoDB\BoardType;
use Pressmind\Search\Condition\MongoDB\Code;
use Pressmind\Search\Condition\MongoDB\DateRange;
use Pressmind\Search\Condition\MongoDB\DurationRange;
use Pressmind\Search\Condition\MongoDB\Fulltext;
use Pressmind\Search\Condition\MongoDB\ObjectType;
use Pressmind\Search\Condition\MongoDB\Occupancy;
use Pressmind\Search\Condition\MongoDB\PriceRange;
use Pressmind\Search\Condition\MongoDB\TransportType;
use Pressmind\Search\MongoDB;
use Pressmind\Search\Paginator;
use Pressmind\Search\SearchType;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;
use Pressmind\Tests\Integration\FixtureLoader;

/**
 * E2E integration tests for MongoDB Search: real aggregation pipeline execution
 * against fixture data. Verifies that conditions, sort, pagination, and filters
 * produce correct results when querying products in the database.
 */
class MongoDBSearchE2ETest extends AbstractIntegrationTestCase
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
            try {
                $this->mongoDb->selectCollection(self::TEST_COLLECTION)->drop();
            } catch (\Throwable $e) {
                // ignore if already dropped
            }
            try {
                $this->mongoDb->selectCollection(self::DESC_COLLECTION)->drop();
            } catch (\Throwable $e) {
                // ignore
            }
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

    /**
     * Load E2E fixtures, resolve dynamic dates, ensure validity/visibility fields.
     */
    private function seedE2EFixtures(): void
    {
        $files = [
            'search_document_standard.json',
            'search_document_earlybird.json',
            'search_document_hotel.json',
            'search_document_sold_out.json',
            'search_document_high_rating.json',
            'search_document_code_search.json',
        ];
        $docs = [];
        $descDocs = [];
        foreach ($files as $file) {
            $data = FixtureLoader::loadJsonFixture($file, 'mongodb');
            $data = FixtureLoader::resolveDynamicDates($data);
            if (!isset($data['valid_from'])) {
                $data['valid_from'] = null;
            }
            if (!isset($data['valid_to'])) {
                $data['valid_to'] = null;
            }
            if (!isset($data['visibility'])) {
                $data['visibility'] = 30;
            }
            $docs[] = $data;
            $id = $data['_id'] ?? $data['id_media_object'];
            $descDocs[] = [
                '_id' => $id,
                'description' => $data['description'] ?? new \stdClass(),
            ];
        }
        $coll = $this->mongoDb->selectCollection(self::TEST_COLLECTION);
        $coll->drop();
        $coll->insertMany($docs);
        $descColl = $this->mongoDb->selectCollection(self::DESC_COLLECTION);
        $descColl->drop();
        $descColl->insertMany($descDocs);
    }

    private function getResultIds($result): array
    {
        $documents = is_array($result->documents) ? $result->documents : iterator_to_array($result->documents);
        return array_map(function ($doc) {
            $arr = json_decode(json_encode($doc), true);
            return $arr['id_media_object'] ?? $arr['_id'] ?? null;
        }, $documents);
    }

    public function testSearchByPriceRange(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new PriceRange(800, 1500)], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total);
        $ids = $this->getResultIds($result);
        $this->assertContains(100001, $ids, 'Standard 899 should match');
        $this->assertContains(100002, $ids, 'Earlybird 759 should match');
    }

    public function testSearchByPriceRangeNoMatch(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new PriceRange(10, 50)], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(0, $result->total);
    }

    public function testSearchByDateRange(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $from = new \DateTime('+20 days');
        $to = new \DateTime('+70 days');
        $search = new MongoDB([new DateRange($from, $to)], ['date_departure' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total);
    }

    public function testSearchByTransportType(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new TransportType(['FLUG'])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total);
        $ids = $this->getResultIds($result);
        $this->assertContains(100001, $ids);
        $this->assertContains(100006, $ids);
    }

    public function testSearchByOccupancy(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new Occupancy([2])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total);
    }

    public function testSearchByBoardType(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new BoardType(['AI'])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total);
        $ids = $this->getResultIds($result);
        $this->assertContains(100001, $ids);
    }

    public function testSearchByCodeExact(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new Code(['TEST-001'])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(1, $result->total);
        $ids = $this->getResultIds($result);
        $this->assertContains(100001, $ids);
    }

    public function testSearchByCodeIn(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new Code(['MULTI-01', 'MULTI-02'])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(1, $result->total);
        $ids = $this->getResultIds($result);
        $this->assertContains(100007, $ids);
    }

    public function testSearchByFulltext(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new Fulltext('special')], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total);
        $ids = $this->getResultIds($result);
        $this->assertContains(100007, $ids, 'code_search fixture has fulltext "multi code search product special offer"');
    }

    public function testSearchCombinedConditions(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([
            new PriceRange(400, 2000),
            new TransportType(['BUS']),
            new Occupancy([2]),
        ], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total);
    }

    public function testSortByPriceAsc(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(2, $result->total);
        $documents = is_array($result->documents) ? $result->documents : iterator_to_array($result->documents);
        $first = json_decode(json_encode($documents[0]), true);
        $second = json_decode(json_encode($documents[1]), true);
        $p1 = $first['prices']['price_total'] ?? $first['best_price_meta']['price_total'] ?? 0;
        $p2 = $second['prices']['price_total'] ?? $second['best_price_meta']['price_total'] ?? 0;
        $this->assertLessThanOrEqual($p2, $p1, 'First document price should be <= second when sorting by price asc');
    }

    public function testSortByPriceDesc(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([], ['price_total' => 'desc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(2, $result->total);
        $documents = is_array($result->documents) ? $result->documents : iterator_to_array($result->documents);
        $first = json_decode(json_encode($documents[0]), true);
        $second = json_decode(json_encode($documents[1]), true);
        $p1 = $first['prices']['price_total'] ?? $first['best_price_meta']['price_total'] ?? 0;
        $p2 = $second['prices']['price_total'] ?? $second['best_price_meta']['price_total'] ?? 0;
        $this->assertGreaterThanOrEqual($p2, $p1, 'First document price should be >= second when sorting by price desc');
    }

    public function testSortByRecommendationRateDesc(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([], ['recommendation_rate' => 'desc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total);
        $documents = is_array($result->documents) ? $result->documents : iterator_to_array($result->documents);
        $first = json_decode(json_encode($documents[0]), true);
        $this->assertArrayHasKey('recommendation_rate', $first);
        $this->assertGreaterThanOrEqual(88, $first['recommendation_rate'], 'High rating fixture has 95, hotel has 88');
    }

    public function testSortBySalesPriority(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([], ['sales_priority' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total);
    }

    public function testPaginationPage1AndPage2(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(2, 1));
        $result1 = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $search2 = new MongoDB([], ['price_total' => 'asc'], 'de', 0);
        $search2->setPaginator(Paginator::create(2, 2));
        $result2 = $search2->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame($result1->total, $result2->total);
        $doc1 = is_array($result1->documents) ? $result1->documents : iterator_to_array($result1->documents);
        $doc2 = is_array($result2->documents) ? $result2->documents : iterator_to_array($result2->documents);
        $this->assertCount(2, $doc1);
        $this->assertLessThanOrEqual(2, count($doc2));
        $ids1 = $this->getResultIds($result1);
        $ids2 = $this->getResultIds($result2);
        $this->assertEmpty(array_intersect($ids1, $ids2), 'Page 1 and page 2 should not return same documents');
    }

    public function testGetResultWithFiltersReturnsFilterData(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new ObjectType([2000, 3000])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $search->setGetFilters(true);
        $result = $search->getResult(true, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total);
        $this->assertObjectHasProperty('documents', $result);
        $this->assertObjectHasProperty('total', $result);
    }

    public function testEmptyResultWhenNoMatch(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new Code(['NONEXISTENT-CODE-999'])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(0, $result->total);
        $documents = is_array($result->documents) ? $result->documents : iterator_to_array($result->documents);
        $this->assertCount(0, $documents);
    }

    public function testFilterByObjectType(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new ObjectType([3000])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertSame(1, $result->total);
        $ids = $this->getResultIds($result);
        $this->assertContains(100004, $ids, 'Hotel fixture is id_object_type 3000');
    }

    public function testReturnFiltersOnlyOmitsDocuments(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new ObjectType([2000])], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(10, 1));
        $search->setGetFilters(true);
        $search->setReturnFiltersOnly(true);
        $result = $search->getResult(true, true, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertObjectHasProperty('total', $result);
        $documents = is_array($result->documents) ? $result->documents : iterator_to_array($result->documents);
        $this->assertCount(0, $documents, 'returnFiltersOnly should return no documents');
    }

    public function testSearchByDurationRange(): void
    {
        $this->requireMongo();
        $this->seedE2EFixtures();

        $search = new MongoDB([new DurationRange(3, 5)], ['price_total' => 'asc'], 'de', 0);
        $search->setPaginator(Paginator::create(20, 1));
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertGreaterThanOrEqual(1, $result->total);
    }
}
