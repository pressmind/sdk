<?php

namespace Pressmind\Tests\Integration\Search;

use Pressmind\Registry;
use Pressmind\Search\Condition\MongoDB\{GeoBounds, GeoDistance, PriceRange, DateRange, Fulltext, AtlasLuceneFulltext};
use Pressmind\Search\Geo\Geometry;
use Pressmind\Search\MongoDB;
use Pressmind\Search\MongoDB\Indexer;
use Pressmind\Search\Paginator;
use Pressmind\Search\SearchType;
use Pressmind\Tests\Integration\AbstractIntegrationTestCase;

class GeoSearchIntegrationTest extends AbstractIntegrationTestCase
{
    private const ORIGIN = 990235;
    private $collection;

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->mongoDb === null) {
            $this->markTestSkipped('MongoDB required');
        }
        $config = $this->getIntegrationConfig();
        $config['data'] = [
            'search_mongodb' => [
                'database' => ['uri' => getenv('MONGODB_URI'), 'db' => getenv('MONGODB_DB')],
                'search' => ['build_for' => [], 'touristic' => ['duration_ranges' => [[1, 7]]]],
            ],
            'languages' => ['allowed' => ['de'], 'default' => 'de'],
            'search_opensearch' => ['enabled' => false, 'enabled_in_mongo_search' => false],
            'touristic' => [], 'media_types_fulltext_index_fields' => [], 'media_types_allowed_visibilities' => [],
        ];
        Registry::getInstance()->add('config', $config);
        MongoDB::clearConnectionCache();
        Indexer::resetIndexCheckCache();
        $name = MongoDB::getCollectionName('best_price_search_based_', 'de', self::ORIGIN);
        $this->collection = $this->mongoDb->selectCollection($name);
        $this->collection->drop();
        (new Indexer())->createCollectionIndex($name);
        $geometries = [
            1 => ['type' => 'LineString', 'coordinates' => [[13, 52.5], [14, 52.5]]], // crosses; endpoints outside
            2 => ['type' => 'Point', 'coordinates' => [13.5, 52.5]],
            3 => ['type' => 'Point', 'coordinates' => [10, 50]],
            4 => null,
            5 => ['type' => 'Point', 'coordinates' => [179, 0]],
            6 => ['type' => 'Point', 'coordinates' => [-179, 0]],
            7 => ['type' => 'Point', 'coordinates' => [13.5, 52.5]], // hidden
            8 => ['type' => 'Point', 'coordinates' => [13.5, 52.5]], // too expensive
        ];
        foreach ($geometries as $id => $geometry) {
            $price = ['price_total' => $id === 8 ? 5000 : 500, 'duration' => 7, 'occupancy' => 2, 'state' => 100,
                'date_departures' => [(new \DateTime('+30 days'))->format(DATE_RFC3339_EXTENDED)],
                'guaranteed_departures' => [], 'option_board_type' => 'HP', 'transport_type' => 'BUS',
                'earlybird_discount' => 0, 'earlybird_discount_f' => 0, 'price_mix' => 'date_housing'];
            $this->collection->insertOne([
                '_id' => $id, 'id_media_object' => $id, 'id_object_type' => 100,
                'geo' => Geometry::forSearch(['geometries' => $geometry ? [$geometry] : []], []),
                'fulltext' => 'radreise berlin', 'categories' => [], 'groups' => [], 'locations' => (object) [],
                'valid_from' => null, 'valid_to' => null, 'visibility' => $id === 7 ? 10 : 30,
                'prices' => [$price], 'best_price_meta' => $price, 'has_price' => true, 'sales_priority' => 'A000001',
                'departure_date_count' => 1, 'code' => [(string) $id], 'object_type_order' => 0,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->collection !== null) {
            $this->collection->drop();
        }
        MongoDB::clearConnectionCache();
        parent::tearDown();
    }

    private function search(array $conditions)
    {
        $search = new MongoDB($conditions, ['price_total' => 'asc'], 'de', self::ORIGIN);
        $search->setPaginator(Paginator::create(20, 1));
        return $search;
    }

    private function ids($result): array
    {
        $ids = array_map(static fn($row) => (int) $row->id_media_object, (array) $result->documents);
        sort($ids);
        return $ids;
    }

    public function testRadiusMatchesTrackInteriorWithPriceDateTextAndVisibilityFilters(): void
    {
        $search = $this->search([
            new GeoDistance(13.5, 52.5, 500), new PriceRange(0, 1000),
            new DateRange(new \DateTime('+1 day'), new \DateTime('+60 days')), new Fulltext('radreise'),
        ]);
        $this->assertArrayHasKey('$geoNear', $search->buildQuery()[0]);
        $result = $search->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);
        $this->assertSame([1, 2], $this->ids($result));
        $this->assertSame(2, $result->total);
        foreach ($result->documents as $document) {
            $this->assertLessThanOrEqual(500, $document->geo_distance_meters);
        }
    }

    public function testViewportMatchesCrossingTrackAndDateLine(): void
    {
        $result = $this->search([new GeoBounds(13.49, 52.49, 13.51, 52.51), new PriceRange(0, 1000)])
            ->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true]);
        $this->assertSame([1, 2], $this->ids($result));
        $ids = array_column($this->collection->find((new GeoBounds(170, -10, -170, 10))->getQuery())->toArray(), '_id');
        sort($ids);
        $this->assertSame([5, 6], $ids);
    }

    public function testNullGeometryClearsOldSearchMatchesAndDoesNotExcludeNormalSearch(): void
    {
        $this->collection->updateOne(['_id' => 1], ['$set' => ['geo' => null]]);
        $near = (new GeoDistance(13.5, 52.5, 500))->getQuery('geo_near');
        $rows = $this->collection->aggregate([$near])->toArray();
        $this->assertNotContains(1, array_column($rows, '_id'));
        $this->assertSame(8, $this->collection->countDocuments());
        $this->assertSame(7, $this->search([])->getResult(false, false, 0, null, null, [30], SearchType::DEFAULT, ['skip_search_hooks' => true])->total);
    }

    public function testAtlasUsesCorrelatedGeoLookupWithoutTruncatingCandidates(): void
    {
        $atlas = new AtlasLuceneFulltext('radreise');
        $pipeline = $this->search([$atlas, new GeoDistance(13.5, 52.5, 500)])->buildQuery();
        $this->assertArrayHasKey('$search', $pipeline[0]);
        $lookup = array_values(array_filter($pipeline, static fn($stage) => isset($stage['$lookup']['as']) && $stage['$lookup']['as'] === '_geo_match'))[0];
        // A local MongoDB has no Atlas search; exercise the actual correlated subpipeline on candidates.
        $rows = $this->collection->aggregate([
            ['$match' => ['_id' => ['$in' => [1, 3]]]], $lookup,
            ['$match' => ['_geo_match.0' => ['$exists' => true]]],
        ])->toArray();
        $this->assertSame([1], array_column($rows, '_id'), json_encode(array_map(static fn($row) => [$row->_id, $row->_geo_match], $rows)));
    }
    public function testWorldAndPolarViewportsAreValid(): void
    {
        $this->assertSame(7, $this->collection->countDocuments((new GeoBounds(-180, -90, 180, 90))->getQuery()));
        $this->assertSame(7, $this->collection->countDocuments((new GeoBounds(-180, -90, 180, 80))->getQuery()));
        $this->assertSame(7, $this->collection->countDocuments((new GeoBounds(-180, -80, 180, 90))->getQuery()));
    }

}
