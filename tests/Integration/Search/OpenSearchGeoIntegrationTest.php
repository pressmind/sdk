<?php
namespace Pressmind\Tests\Integration\Search;

use OpenSearch\SymfonyClientFactory;
use Pressmind\Registry;
use Pressmind\Search\Geo\{Filter, Spatial};
use Pressmind\Search\OpenSearch;
use Pressmind\Search\OpenSearch\Indexer;
use Pressmind\Search\Condition\MongoDB\{GeoDistance, GeoBounds, PriceRange, AtlasLuceneFulltext, MediaObject};
use Pressmind\Search\Paginator;

/** Runs the common geo/offer fixtures through real OpenSearch and MongoDB. */
class OpenSearchGeoIntegrationTest extends GeoSearchIntegrationTest
{
    private $client;
    private ?string $index = null;

    protected function setUp(): void
    {
        parent::setUp();
        $uri = getenv('OPENSEARCH_URI');
        if (!$uri) { $this->markTestSkipped('OPENSEARCH_URI required'); }
        $this->client = (new SymfonyClientFactory())->create(['base_uri' => $uri]);
        $config = Registry::getInstance()->get('config');
        $config['data']['languages']['allowed'] = ['de', 'en'];
        $config['data']['search_mongodb']['search']['locations'] = [100 => ['hotel' => []]];
        $config['data']['search_opensearch'] = [
            'uri' => $uri, 'enabled' => true, 'enabled_in_mongo_search' => true,
            'index_prefix' => 'codex_geo_test_' . bin2hex(random_bytes(4)),
            'index' => ['fulltext' => ['type' => 'text', 'boost' => 1]],
            'query' => ['geo' => ['enabled' => true]],
            'vector' => ['space_type' => 'cosinesimil'],
        ];
        Registry::getInstance()->add('config', $config);
        $indexer = new Indexer();
        $this->index = $indexer->getIndexTemplateName('de');
        // Start with an old index to exercise additive mapping migration.
        $this->client->indices()->create(['index' => $this->index, 'body' => [
            'settings' => ['number_of_replicas' => 0, 'index.knn' => true],
            'mappings' => ['properties' => ['fulltext' => ['type' => 'text'], 'content_vector' => ['type' => 'knn_vector', 'dimension' => 2]]],
        ]]);
        $indexer->ensureGeoMappings();
        foreach ($this->collection->find() as $document) {
            $geometry = json_decode(json_encode($document['geo']), true);
            $this->put($document['_id'], $geometry, $document['_id'] === 2 ? [[13.5, 52.5]] : [[10, 50]]);
        }
        $this->client->indices()->refresh(['index' => $this->index]);
    }

    protected function tearDown(): void
    {
        if ($this->index !== null) { $this->client->indices()->delete(['index' => $this->index]); }
        parent::tearDown();
    }

    private function put(int $id, ?array $geometry, array $hotel = []): void
    {
        $this->client->index(['index' => $this->index, 'id' => $id, 'body' => [
            'id' => $id, 'fulltext' => 'radreise berlin', 'geo' => Spatial::forOpenSearch($geometry),
            'geo_radius_cover' => Spatial::cover($geometry), 'location_points' => ['hotel' => $hotel],
            'content_vector' => [1, 0],
        ]]);
    }

    private function openSearch(Filter $filter, string $term = '', int $size = 2): OpenSearch
    {
        $search = new OpenSearch($term, 'de', $size);
        $search->setGeoFilter($filter);
        return $search;
    }

    public function testPureGeoPaginationFiltersAndRepeatedSearch(): void
    {
        $search = $this->search([new GeoDistance(13.5, 52.5, 500), new GeoBounds(13.49, 52.49, 13.51, 52.51), new PriceRange(0, 1000)]);
        $search->setPaginator(Paginator::create(1, 1));
        $first = $search->getResult(true);
        $this->assertSame(2, $first->total);
        $this->assertCount(1, $first->documents);
        $this->assertNotFalse($search->getConditionByType('GeoDistance'));
        $search->setPaginator(Paginator::create(1, 2));
        $second = $search->getResult(true);
        $this->assertSame(2, $second->total);
        $this->assertNotSame($this->ids($first), $this->ids($second));
        $restricted = $this->search([new GeoDistance(13.5, 52.5, 500), new MediaObject([1, 2, -1])])->getResult();
        $this->assertSame([2], $this->ids($restricted));
    }

    public function testAtlasLocationSurvivesMigrationAndDoesNotMatchTrack(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data']['search_mongodb']['term_resolver']['enabled'] = true;
        Registry::getInstance()->add('config', $config);
        $result = $this->search([new AtlasLuceneFulltext(null, [], 'hotel', 13.5, 52.5, 1)])->getResult();
        $this->assertSame([2], $this->ids($result));
    }

    public function testDatelineAndPolarRadiusAndViewport(): void
    {
        $this->put(9, ['type' => 'LineString', 'coordinates' => [[179, 0], [-179, 0]]]);
        $this->put(10, ['type' => 'LineString', 'coordinates' => [[0, 80], [180, 80]]]);
        $this->client->indices()->refresh(['index' => $this->index]);
        $this->assertContains('9', $this->openSearch(new Filter([180, 0, 10]))->getResult());
        $this->assertContains('10', $this->openSearch(new Filter([0, 90, 10]))->getResult());
        $this->assertContains('9', $this->openSearch(new Filter(null, [179.5, -1, -179.5, 1]))->getResult());
        $this->assertNotContains('9', $this->openSearch(new Filter(null, [-1, -1, 1, 1]))->getResult());
        $this->assertCount(9, $this->openSearch(new Filter(null, [-180, -90, 180, 90]))->getResult());
        $this->assertContains('10', $this->openSearch(new Filter(null, [-180, 79, 180, 90]))->getResult());
    }

    public function testVectorHybridAndAdditiveIdsRespectExactRadius(): void
    {
        // The wide candidate envelope overlaps the circle, but the actual track is outside.
        $this->put(11, ['type' => 'LineString', 'coordinates' => [[13, 52.51], [14, 52.51]]]);
        $this->client->indices()->refresh(['index' => $this->index]);
        $filter = new Filter([13.5, 52.5, 500]);
        $search = $this->openSearch($filter, 'radreise');
        $expected = ['1', '2', '7', '8'];
        $ids = $search->getVectorSearchResultIds([1, 0], 20); sort($ids);
        $this->assertEquals($expected, $ids);
        $ids = $search->getHybridSearchResultIds('radreise', [1, 0], 20); sort($ids);
        $this->assertEquals($expected, $ids);
        $this->assertEquals(['1', '2'], $search->filterGeoIds([1, 2, 3, 4]));
    }

    public function testReplacedGeometryDisappearsImmediately(): void
    {
        $this->put(1, null);
        $this->client->indices()->refresh(['index' => $this->index]);
        $this->assertNotContains('1', $this->openSearch(new Filter([13.5, 52.5, 500]))->getResult());
    }

    public function testMoreThanTenThousandCandidatesAreNotTruncated(): void
    {
        $geometry = ['type' => 'Point', 'coordinates' => [0, 0]];
        foreach (array_chunk(range(100, 10110), 1000) as $chunk) {
            $body = [];
            foreach ($chunk as $id) {
                $body[] = ['index' => ['_index' => $this->index, '_id' => $id]];
                $body[] = ['id' => $id, 'geo' => $geometry, 'geo_radius_cover' => Spatial::cover($geometry)];
            }
            $response = $this->client->bulk(['body' => $body]);
            $this->assertFalse($response['errors']);
        }
        $this->client->indices()->refresh(['index' => $this->index]);
        $this->assertCount(10011, $this->openSearch(new Filter([0, 0, 0]), '', 10000)->getResult());
    }
    public function testTermResolverCannotBypassGeoEvenWhenLexicalSearchIsEmpty(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['data']['search_mongodb']['term_resolver']['enabled'] = true;
        Registry::getInstance()->add('config', $config);
        $dictionary = $this->mongoDb->selectCollection('term_resolver_de_origin_990235');
        try {
            $dictionary->insertOne(['_id' => 'specialcategory', 'field' => 'region', 'id_item' => '42', 'name' => 'Special', 'count' => 2]);
            \Pressmind\Search\TermResolver::clearCache();
            $this->collection->updateMany(['_id' => ['$in' => [1, 3]]], ['$set' => ['categories' => [['field_name' => 'region', 'id_item' => '42']]]]);
            $result = $this->search([new \Pressmind\Search\Condition\MongoDB\Fulltext('specialcategory'), new GeoDistance(13.5, 52.5, 500)])->getResult();
            $this->assertSame([1], $this->ids($result));
        } finally {
            $dictionary->drop();
            \Pressmind\Search\TermResolver::clearCache();
        }
    }

    public function testCacheRefreshRetainsGeoLanguageAndQueryMode(): void
    {
        if (!getenv('GEO_REDIS_PORT')) { $this->markTestSkipped('GEO_REDIS_PORT required'); }
        $config = Registry::getInstance()->get('config');
        $prefix = 'codex_geo_' . bin2hex(random_bytes(4));
        $config['cache'] = ['enabled' => true, 'types' => ['OPENSEARCH', 'MONGODB'], 'key_prefix' => $prefix,
            'max_idle_time' => 60, 'adapter' => ['name' => 'Redis', 'config' => ['host' => '127.0.0.1', 'port' => (int) getenv('GEO_REDIS_PORT')]]];
        $config['logging']['mode'] = 'ERROR';
        Registry::getInstance()->add('config', $config);
        $cache = new \Pressmind\Cache\Adapter\Redis();
        $search = $this->openSearch(new Filter([13.5, 52.5, 500]));
        try {
            $this->assertContains('1', $search->getResult(false, 60));
            $this->assertContains('1', $search->getResult(false, 60));
            $this->assertNotSame($search->generateCacheKey(), $search->generateCacheKey(true));
            $this->assertNotSame($search->generateCacheKey(), $this->openSearch(new Filter([10, 50, 500]))->generateCacheKey());
            $this->put(1, null);
            $this->client->indices()->refresh(['index' => $this->index]);
            $search->updateCache($search->generateCacheKey());
            $this->assertNotContains('1', $search->getResult(false, 60));
            $this->assertNotContains('3', $search->getResult(false, 60));
            // MongoDB aggregates must not retain an old candidate-ID list after reimport.
            $mongo = $this->search([new GeoDistance(13.5, 52.5, 500), new PriceRange(0, 1000)]);
            $this->assertSame([2], $this->ids($mongo->getResult(false, false, 60)));
        } finally {
            $redis = new \Redis();
            $redis->connect('127.0.0.1', (int) getenv('GEO_REDIS_PORT'));
            foreach ($redis->keys('pm:' . $prefix . ':*') as $key) { $redis->del($key); }
        }
    }

    public function testMappingConflictFailsWithoutDeletingIndex(): void
    {
        $this->client->indices()->delete(['index' => $this->index]);
        $this->client->indices()->create(['index' => $this->index, 'body' => ['mappings' => ['properties' => ['geo' => ['type' => 'keyword']]]]]);
        try {
            (new Indexer())->ensureGeoMappings();
            $this->fail('Incompatible geo mapping must fail');
        } catch (\OpenSearch\Exception\BadRequestHttpException $exception) {
            $this->assertTrue($this->client->indices()->exists(['index' => $this->index]));
        }
    }

}
