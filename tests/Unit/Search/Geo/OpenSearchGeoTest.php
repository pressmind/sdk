<?php

namespace Pressmind\Tests\Unit\Search\Geo;

use Pressmind\Search\Geo\Spatial;
use Pressmind\Search\Geo\Filter;
use Pressmind\Tests\Unit\AbstractTestCase;

class OpenSearchGeoTest extends AbstractTestCase
{
    public function testRadiusMeasuresSegmentsNotVertices(): void
    {
        $line = ['type' => 'LineString', 'coordinates' => [[-1, 0], [1, 0]]];
        $this->assertEqualsWithDelta(0, Spatial::distance([0, 0], $line), 0.001);
        $this->assertEqualsWithDelta(111195, Spatial::distance([0, 1], $line), 1);
        $this->assertGreaterThan(111000, Spatial::distance([2, 0], $line));
        $this->assertTrue((new Filter([0, 0, 10]))->matches(['geo' => $line]));
        $this->assertFalse((new Filter([0, 1, 100000]))->matches(['geo' => $line]));
    }

    public function testDatelinePolesAndDegenerateSegments(): void
    {
        $this->assertEqualsWithDelta(0, Spatial::distance([180, 0], [
            'type' => 'LineString', 'coordinates' => [[179, 0], [-179, 0]],
        ]), 0.001);
        $this->assertEqualsWithDelta(0, Spatial::distance([0, 90], [
            'type' => 'LineString', 'coordinates' => [[0, 80], [180, 80]],
        ]), 0.001);
        $this->assertEqualsWithDelta(0, Spatial::distance([0, 0], [
            'type' => 'LineString', 'coordinates' => [[0, 0], [0, 0]],
        ]), 0.001);
        $this->assertFalse((new Filter([0, 0, 1]))->matches(['geo' => null]));
    }

    public function testFieldSpecificLocationDoesNotMatchTrack(): void
    {
        $filter = new Filter(null, null, ['hotel', 0, 0, 10]);
        $this->assertFalse($filter->matches(['geo' => ['type' => 'Point', 'coordinates' => [0, 0]], 'location_points' => ['hotel' => [[1, 1]]]]));
        $this->assertTrue($filter->matches(['location_points' => ['hotel' => [[0, 0]]]]));
    }
    public function testDatelineEndpointDoesNotCreateInvalidZeroLengthLine(): void
    {
        $geometry = Spatial::forOpenSearch(['type' => 'LineString', 'coordinates' => [[180, 0], [-179, 0]]]);
        $this->assertEqualsWithDelta(0, Spatial::distance([-179.5, 0], $geometry), 0.001);
        foreach ($geometry['geometries'] as $part) {
            if ($part['type'] === 'LineString') { $this->assertNotEquals($part['coordinates'][0], $part['coordinates'][1]); }
        }
    }

    public function testAmbiguousAntipodalSegmentIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Spatial::forOpenSearch(['type' => 'LineString', 'coordinates' => [[0, 0], [180, 0]]]);
    }

}
