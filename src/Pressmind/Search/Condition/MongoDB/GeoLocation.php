<?php
namespace Pressmind\Search\Condition\MongoDB;

/** Legacy pm-loc: only points of the selected location field, distance in meters. */
class GeoLocation
{
    private array $parameters;

    public function __construct(string $field, float $longitude, float $latitude, float $radiusMeters)
    {
        new \Pressmind\Search\Geo\Filter(null, null, [$field, $longitude, $latitude, $radiusMeters]);
        $this->parameters = [$field, $longitude, $latitude, $radiusMeters];
    }

    public function getType(): string { return 'GeoLocation'; }
    public function getParameters(): array { return $this->parameters; }

    public function getQuery($type = 'first_match')
    {
        if ($type !== 'first_match') { return null; }
        [$field, $lon, $lat, $radius] = $this->parameters;
        return ['locations.' . $field . '.coordinates' => ['$geoWithin' => ['$centerSphere' => [[$lon, $lat], $radius / \Pressmind\Search\Geo\Spatial::EARTH_RADIUS]]]];
    }
}
