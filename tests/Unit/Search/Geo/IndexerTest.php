<?php
namespace Pressmind\Tests\Unit\Search\Geo;

use Pressmind\Registry;
use Pressmind\Search\OpenSearch\Indexer;
use Pressmind\Tests\Unit\AbstractTestCase;

class IndexerTest extends AbstractTestCase
{
    public function testGeoOnlyObjectHasStableIdAndRemovedGeometryIsCleared(): void
    {
        $config = $this->createMockConfig(['data' => [
            'languages' => ['allowed' => ['de'], 'default' => 'de'],
            'search_opensearch' => ['index' => []], 'media_types_fulltext_index_fields' => [],
            'search_mongodb' => ['search' => ['locations' => []]],
            'media_types' => [],
        ]]);
        Registry::getInstance()->add('config', $config);
        $geometry = ['type' => 'GeometryCollection', 'geometries' => [['type' => 'Point', 'coordinates' => [13.0, 52.0]]]];
        $db = $this->createMock(\Pressmind\DB\Adapter\AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturnCallback(static function ($sql) use (&$geometry) {
            if (strpos($sql, 'pmt2core_itinerary_maps') !== false) {
                return (object) ['id' => 100, 'search_geometry' => $geometry === null ? null : json_encode($geometry)];
            }
            return (object) ['id' => 100, 'id_object_type' => 100, 'name' => 'Geo trip'];
        });
        Registry::getInstance()->add('db', $db);
        $indexer = $this->getMockBuilder(Indexer::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $property = new \ReflectionProperty(Indexer::class, '_config');
        $property->setValue($indexer, $config['data']['search_opensearch']);
        $document = $indexer->createIndex(100, 'de');
        $this->assertSame(100, $document->id);
        $this->assertSame('', $document->fulltext);
        $this->assertSame($geometry, $document->geo);
        $this->assertNotNull($document->geo_radius_cover);
        $geometry = null;
        $document = $indexer->createIndex(100, 'de');
        $this->assertNull($document->geo);
        $this->assertNull($document->geo_radius_cover);
    }
}
