<?php

namespace Pressmind\Search\Condition\MongoDB;

use InvalidArgumentException;
use Pressmind\Search\Geo\Geometry;

/** Matches locations and GPX segments intersecting a map viewport (west, south, east, north). */
class GeoBounds
{
    private array $bounds;

    public function __construct(float $west, float $south, float $east, float $north)
    {
        if (Geometry::position([$west, $south]) === null || Geometry::position([$east, $north]) === null
            || $south >= $north || $west === $east || ($west === 180.0 && $east === -180.0)) {
            throw new InvalidArgumentException('GeoBounds requires valid coordinates, south < north and distinct west/east.');
        }
        $this->bounds = [$west, $south, $east, $north];
    }

    public function getParameters(): array
    {
        return $this->bounds;
    }

    public function getType(): string
    {
        return 'GeoBounds';
    }

    public function getQuery($type = 'first_match')
    {
        if ($type !== 'first_match') {
            return null;
        }
        [$west, $south, $east, $north] = $this->bounds;
        if ($west === -180.0 && $east === 180.0 && $south === -90.0 && $north === 90.0) {
            return ['geo' => ['$ne' => null]];
        }
        $ranges = $west > $east ? [[$west, 180.0], [-180.0, $east]] : [[$west, $east]];
        $parts = [];
        foreach ($ranges as [$left, $right]) {
            // Split wide/antimeridian viewports into polygons smaller than a hemisphere.
            while ($left < $right) {
                $next = min($right, $left + 90.0);
                $midLat = ($south + $north) / 2;
                $midLng = ($left + $next) / 2;
                // At a pole, all longitudes describe one vertex. Avoid duplicate vertices.
                $ring = $south === -90.0 ? [[$midLng, $south]] : [[$left, $south], [$next, $south]];
                $ring[] = [$next, $midLat];
                if ($north === 90.0) {
                    $ring[] = [$midLng, $north];
                } else {
                    $ring[] = [$next, $north];
                    $ring[] = [$left, $north];
                }
                $ring[] = [$left, $midLat];
                $ring[] = $ring[0];
                $parts[] = ['geo' => ['$geoIntersects' => ['$geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [$ring],
                ]]]];
                $left = $next;
            }
        }
        return count($parts) === 1 ? $parts[0] : ['$or' => $parts];
    }
}
