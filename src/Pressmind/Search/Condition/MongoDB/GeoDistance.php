<?php

namespace Pressmind\Search\Condition\MongoDB;

use InvalidArgumentException;
use Pressmind\Search\Geo\Geometry;

/** Radius in meters, measured to the closest location or point along a GPX track. */
class GeoDistance
{
    private array $position;
    private float $radiusMeters;

    public function __construct(float $longitude, float $latitude, float $radiusMeters)
    {
        $position = Geometry::position([$longitude, $latitude]);
        if ($position === null || !is_finite($radiusMeters) || $radiusMeters < 0) {
            throw new InvalidArgumentException('GeoDistance requires valid longitude/latitude and a non-negative radius in meters.');
        }
        $this->position = $position;
        $this->radiusMeters = $radiusMeters;
    }

    public function getParameters(): array
    {
        return [$this->position[0], $this->position[1], $this->radiusMeters];
    }

    public function getType(): string
    {
        return 'GeoDistance';
    }

    public function getQuery($type = 'first_match')
    {
        if ($type !== 'geo_near') {
            return null;
        }
        return ['$geoNear' => [
            'key' => 'geo',
            'near' => ['type' => 'Point', 'coordinates' => $this->position],
            'spherical' => true,
            'maxDistance' => $this->radiusMeters,
            'distanceField' => 'geo_distance_meters',
        ]];
    }
}
