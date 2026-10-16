<?php

namespace Pressmind\Tests\Unit\Search\Geo;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Geo\Geometry;

class GeometryTest extends TestCase
{
    public function testIncludesUnselectedStopsPortsAndTracksButNotDecorativeConnections(): void
    {
        $line = ['type' => 'MultiLineString', 'coordinates' => [[[13, 52, 1.5], [14, 53, 100.25]]]];
        $source = [
            'map' => ['features' => [
                ['properties' => ['kind' => 'connection'], 'geometry' => ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]]],
                ['properties' => ['kind' => 'track'], 'geometry' => $line],
            ]],
            'variants' => [['steps' => [[
                'gpx_tracks' => ['features' => [['geometry' => $line]]],
                'geopoints' => [['lng' => '13.5', 'lat' => '52.5']],
                'ports' => [['coordinates' => ['lng' => 9, 'lat' => 53]]],
            ]]]],
        ];
        $geometry = Geometry::fromItinerary(json_decode(json_encode($source)));
        $this->assertCount(3, $geometry['geometries']);
        $this->assertSame([[13.0, 52.0], [14.0, 53.0]], $geometry['geometries'][0]['coordinates']);
        $this->assertSame(100.25, $source['map']['features'][1]['geometry']['coordinates'][0][1][2]);
    }

    public function testCombinesHotelLocationsAndRejectsInvalidCoordinatesWithoutInventingSegments(): void
    {
        $result = Geometry::forSearch(['geometries' => [
            ['type' => 'LineString', 'coordinates' => [[10, 20], [181, 20], [11, 21]]],
            ['type' => 'Point', 'coordinates' => [0, 0]],
        ]], (object) ['hotel' => ['type' => 'MultiPoint', 'coordinates' => [[13.4, 52.5], [null, 2], [1, 91], [NAN, 0]]]]);
        $this->assertCount(2, $result['geometries']);
        $this->assertSame([0.0, 0.0], $result['geometries'][0]['coordinates']);
        $this->assertSame([13.4, 52.5], $result['geometries'][1]['coordinates']);
        $this->assertNull(Geometry::forSearch(null, []));
    }
}
