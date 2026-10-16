<?php

namespace Pressmind\Search\Geo;

use Pressmind\Search\Condition\MongoDB\{GeoBounds, GeoDistance};

/** Serializable, backend-independent parameters. Distances are always meters. */
class Filter
{
    public ?array $radius;
    public ?array $bounds;
    public ?array $location;

    public function __construct(?array $radius = null, ?array $bounds = null, ?array $location = null)
    {
        if ($radius !== null) { new GeoDistance(...$radius); }
        if ($bounds !== null) { new GeoBounds(...$bounds); }
        if ($location !== null) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $location[0])) {
                throw new \InvalidArgumentException('Invalid location field.');
            }
            new GeoDistance(...array_slice($location, 1));
        }
        $this->radius = $radius;
        $this->bounds = $bounds;
        $this->location = $location;
    }

    public function isEmpty(): bool
    {
        return $this->radius === null && $this->bounds === null && $this->location === null;
    }

    public function queries(): array
    {
        $filters = [];
        if ($this->bounds !== null) {
            [$w, $s, $e, $n] = $this->bounds;
            $boxes = $w > $e ? [[$w, $s, 180, $n], [-180, $s, $e, $n]] : [$this->bounds];
            $filters[] = $this->intersects('geo', $boxes);
        }
        if ($this->radius !== null) {
            $filters[] = $this->intersects('geo_radius_cover', Spatial::boxes(array_slice($this->radius, 0, 2), $this->radius[2]));
        }
        if ($this->location !== null) {
            [$field, $lon, $lat, $meters] = $this->location;
            // Candidate radius is padded; exact validation below uses the same sphere as tracks.
            $filters[] = ['geo_distance' => ['distance' => ($meters + 1) . 'm', 'location_points.' . $field => [$lon, $lat], 'ignore_unmapped' => true]];
        }
        return $filters;
    }

    private function intersects(string $field, array $boxes): array
    {
        $queries = [];
        foreach ($boxes as $box) {
            $queries[] = ['geo_shape' => [$field => ['shape' => Spatial::rectangle($box), 'relation' => 'intersects'], 'ignore_unmapped' => true]];
        }
        return ['bool' => ['should' => $queries, 'minimum_should_match' => 1]];
    }

    /** Bounds are checked by OpenSearch. Exact radii are checked before IDs reach MongoDB. */
    public function matches(array $source): bool
    {
        if ($this->radius !== null && Spatial::distance(array_slice($this->radius, 0, 2), $source['geo'] ?? null) > $this->radius[2] + 1e-6) {
            return false;
        }
        if ($this->location !== null) {
            [$field, $lon, $lat, $meters] = $this->location;
            $matched = false;
            foreach ($source['location_points'][$field] ?? [] as $point) {
                if (Spatial::distance([$lon, $lat], ['type' => 'Point', 'coordinates' => $point]) <= $meters + 1e-6) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) { return false; }
        }
        return true;
    }
}
