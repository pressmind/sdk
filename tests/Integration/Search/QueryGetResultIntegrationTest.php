<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;
use Pressmind\Search\Query;
use Pressmind\Search\Query\Filter;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

/**
 * Integration tests for Query::getResult() and Search\MongoDB->getResult() with real MongoDB connection.
 *
 * - Run in Docker: `docker compose run --rm integration` (after `make build` so this file is in the image).
 * - Run locally with MongoDB: set MONGODB_URI and MONGODB_DB (e.g. mongodb://localhost:17017, pressmind_test).
 * - Tests are skipped when MongoDB is not available.
 */
class QueryGetResultIntegrationTest extends AbstractIntegrationTestCase
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
        if (empty($mongoUri) || empty($mongoDb)) {
            return;
        }
        $config = $this->getIntegrationConfig();
        $config['data'] = [
            'search_mongodb' => [
                'database' => [
                    'uri' => $mongoUri,
                    'db' => $mongoDb,
                ],
                'search' => [
                    'allow_invalid_offers' => false,
                    'order_by_primary_object_type_priority' => false,
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

    public function testQueryGetResultReturnsStructureWithRealMongoDB(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required for Query::getResult integration test');
        }

        $filter = new Filter();
        $filter->request = [];
        $filter->page_size = 10;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_result', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('page_size', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cache', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsInt($result['total_result'] ?? null);
    }

    public function testQueryGetResultWithObjectTypeFilter(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }

        $filter = new Filter();
        $filter->request = ['pm-ot' => '100', 'pm-l' => '1,10'];
        $filter->page_size = 10;
        $filter->getFilters = false;
        $filter->returnFiltersOnly = false;
        $filter->skip_search_hooks = true;

        $result = Query::getResult($filter);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_result', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertSame(1, $result['current_page']);
        $this->assertSame(10, $result['page_size']);
    }

    public function testSearchMongoDBGetResultWithEmptyCollection(): void
    {
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }

        $conditions = [];
        $search = new MongoDB($conditions, ['price_total' => 'asc'], 'de', 0, null);
        $result = $search->getResult(false, false, 0, null, null, [30], \Pressmind\Search\SearchType::DEFAULT, ['skip_search_hooks' => true]);

        $this->assertIsObject($result);
        $this->assertObjectHasProperty('documents', $result);
        $this->assertObjectHasProperty('total', $result);
        $this->assertIsArray($result->documents);
    }
}
