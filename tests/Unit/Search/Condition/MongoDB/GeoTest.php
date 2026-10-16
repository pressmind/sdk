<?php

namespace Pressmind\Tests\Unit\Search\Condition\MongoDB;

use PHPUnit\Framework\TestCase;
use Pressmind\Search\Condition\MongoDB\GeoBounds;
use Pressmind\Search\Condition\MongoDB\GeoDistance;

class GeoTest extends TestCase
{
    public function testDistanceUsesMetersAndWholeIndexedGeometry(): void
    {
        $condition = new GeoDistance(13.4, 52.5, 2500);
        $near = $condition->getQuery('geo_near')['$geoNear'];
        $this->assertSame('geo', $near['key']);
        $this->assertSame(2500.0, $near['maxDistance']);
        $this->assertSame([13.4, 52.5], $near['near']['coordinates']);
        $this->assertNull($condition->getQuery('prices_filter'));
    }

    public function testBoundsWrapAtDateLineAndUseIntersections(): void
    {
        $query = (new GeoBounds(170, -20, -170, 20))->getQuery();
        $this->assertCount(2, $query['$or']);
        foreach ($query['$or'] as $part) {
            $this->assertArrayHasKey('$geoIntersects', $part['geo']);
        }
        $this->assertNull((new GeoBounds(13, 52, 14, 53))->getQuery('prices_filter'));
    }

    public function testRejectsInvalidRadius(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GeoDistance(13, 52, -1);
    }

    public function testRejectsInvertedLatitude(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GeoBounds(13, 53, 14, 52);
    }

    public function testRejectsNonFiniteCoordinates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new GeoDistance(NAN, 52, 10);
    }
}
