<?php

namespace Pressmind\Search\Geo;

/** Provider-independent GeoJSON for search. Original display data is never modified. */
class Geometry
{
    public static function fromItinerary($result): ?array
    {
        $result = self::asArray($result);
        $geometries = [];
        foreach (($result['map']['features'] ?? []) as $feature) {
            if (in_array($feature['properties']['kind'] ?? '', ['point', 'track'], true)) {
                $geometries[] = $feature['geometry'] ?? [];
            }
        }
        $steps = $result['steps'] ?? [];
        foreach ($result['variants'] ?? [] as $variant) {
            $steps = array_merge($steps, $variant['steps'] ?? []);
        }
        foreach ($steps as $step) {
            foreach ($step['gpx_tracks']['features'] ?? [] as $feature) {
                $geometries[] = $feature['geometry'] ?? [];
            }
            foreach ($step['geopoints'] ?? [] as $point) {
                $geometries[] = ['type' => 'Point', 'coordinates' => [$point['lng'] ?? null, $point['lat'] ?? null]];
            }
            foreach ($step['ports'] ?? [] as $port) {
                $geometries[] = ['type' => 'Point', 'coordinates' => [$port['coordinates']['lng'] ?? null, $port['coordinates']['lat'] ?? null]];
            }
        }
        return self::collection($geometries);
    }

    /** Merge configured location fields (e.g. hotels) with the itinerary snapshot. */
    public static function forSearch($itineraryGeometry, $locations): ?array
    {
        $geometries = self::asArray($itineraryGeometry)['geometries'] ?? [];
        foreach (self::asArray($locations) as $location) {
            $geometries[] = $location;
        }
        return self::collection($geometries);
    }

    public static function position($coordinates): ?array
    {
        if (!is_array($coordinates) || count($coordinates) < 2
            || !is_numeric($coordinates[0]) || !is_numeric($coordinates[1])) {
            return null;
        }
        $lng = (float) $coordinates[0];
        $lat = (float) $coordinates[1];
        if (!is_finite($lng) || !is_finite($lat) || abs($lng) > 180 || abs($lat) > 90) {
            return null;
        }
        return [$lng, $lat];
    }

    private static function asArray($value): array
    {
        $value = is_object($value) ? get_object_vars($value) : $value;
        if (!is_array($value)) {
            return [];
        }
        foreach ($value as $key => $item) {
            if (is_array($item) || is_object($item)) {
                $value[$key] = self::asArray($item);
            }
        }
        return $value;
    }

    private static function collection(array $geometries): ?array
    {
        $normalized = [];
        $add = static function (array $geometry) use (&$normalized): void {
            $normalized[json_encode($geometry)] = $geometry;
        };
        foreach ($geometries as $geometry) {
            $type = $geometry['type'] ?? '';
            $coordinates = $geometry['coordinates'] ?? [];
            if ($type === 'Point' || $type === 'MultiPoint') {
                foreach ($type === 'Point' ? [$coordinates] : $coordinates as $point) {
                    if (($position = self::position($point)) !== null) {
                        $add(['type' => 'Point', 'coordinates' => $position]);
                    }
                }
            } elseif ($type === 'LineString' || $type === 'MultiLineString') {
                foreach ($type === 'LineString' ? [$coordinates] : $coordinates as $segment) {
                    $line = [];
                    foreach ($segment as $point) {
                        $position = self::position($point);
                        if ($position === null) {
                            // Never bridge an invalid coordinate with an invented segment.
                            $line = [];
                            break;
                        }
                        if (!$line || end($line) !== $position) {
                            $line[] = $position;
                        }
                    }
                    if (count($line) >= 2) {
                        $add(['type' => 'LineString', 'coordinates' => $line]);
                    } elseif (count($line) === 1) {
                        $add(['type' => 'Point', 'coordinates' => $line[0]]);
                    }
                }
            }
        }
        return $normalized ? ['type' => 'GeometryCollection', 'geometries' => array_values($normalized)] : null;
    }
}
