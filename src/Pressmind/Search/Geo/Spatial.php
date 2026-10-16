<?php

namespace Pressmind\Search\Geo;

/** Spherical distances to minor great-circle arcs, in meters (mean Earth radius). */
class Spatial
{
    public const EARTH_RADIUS = 6371008.8;

    public static function distance(array $position, ?array $geometry): float
    {
        if (empty($geometry)) {
            return INF;
        }
        $type = $geometry['type'];
        if ($type === 'GeometryCollection') {
            $distances = array_map(static fn($g) => self::distance($position, $g), $geometry['geometries']);
            return empty($distances) ? INF : min($distances);
        }
        $p = self::vector($position);
        if ($type === 'Point') {
            return self::angle($p, self::vector($geometry['coordinates'])) * self::EARTH_RADIUS;
        }
        if ($type === 'LineString') {
            $distance = INF;
            $previous = null;
            foreach ($geometry['coordinates'] as $coordinate) {
                $v = self::vector($coordinate);
                $distance = min($distance, self::angle($p, $v));
                if ($previous !== null) {
                    $normal = self::cross($previous, $v);
                    $norm = sqrt(self::dot($normal, $normal));
                    if ($norm > 1e-14) {
                        $normal = array_map(static fn($x) => $x / $norm, $normal);
                        $dot = self::dot($p, $normal);
                        $foot = [$p[0] - $dot * $normal[0], $p[1] - $dot * $normal[1], $p[2] - $dot * $normal[2]];
                        $footNorm = sqrt(self::dot($foot, $foot));
                        if ($footNorm > 1e-14) {
                            $foot = array_map(static fn($x) => $x / $footNorm, $foot);
                            if (self::angle($previous, $foot) + self::angle($foot, $v) <= self::angle($previous, $v) + 1e-12) {
                                $distance = min($distance, self::angle($p, $foot));
                            }
                        }
                    }
                }
                $previous = $v;
            }
            return $distance * self::EARTH_RADIUS;
        }
        return INF;
    }

    /** Conservative circle bounding boxes; longitude splits at the antimeridian. */
    public static function boxes(array $position, float $meters): array
    {
        [$lon, $lat] = $position;
        // Pad the candidate envelope only, accounting for BKD coordinate quantization.
        $angle = min(M_PI, $meters / self::EARTH_RADIUS + 1e-7);
        $south = max(-90, $lat - rad2deg($angle));
        $north = min(90, $lat + rad2deg($angle));
        if ($south <= -90 || $north >= 90) {
            return [[-180, $south, 180, $north]];
        }
        $delta = rad2deg(asin(min(1, sin($angle) / cos(deg2rad($lat)))));
        $west = $lon - $delta;
        $east = $lon + $delta;
        if ($west < -180) {
            return [[-180, $south, $east, $north], [$west + 360, $south, 180, $north]];
        }
        if ($east > 180) {
            return [[$west, $south, 180, $north], [-180, $south, $east - 360, $north]];
        }
        return [[$west, $south, $east, $north]];
    }

    /** One conservative envelope per geometry, including spherical segment interiors. */
    public static function cover(?array $geometry): ?array
    {
        if ($geometry === null) {
            return null;
        }
        if ($geometry['type'] === 'GeometryCollection') {
            $parts = [];
            foreach ($geometry['geometries'] as $part) {
                $parts[] = self::cover($part);
            }
            return ['type' => 'GeometryCollection', 'geometries' => $parts];
        }
        $coordinates = $geometry['type'] === 'Point' ? [$geometry['coordinates']] : $geometry['coordinates'];
        $bounds = [180, 90, -180, -90];
        foreach ($coordinates as $i => $point) {
            // The entire minor arc lies inside the ball about either endpoint with radius arc length.
            $radius = isset($coordinates[$i + 1])
                ? self::angle(self::vector($point), self::vector($coordinates[$i + 1])) * self::EARTH_RADIUS : 0;
            foreach (self::boxes($point, $radius) as [$w, $s, $e, $n]) {
                $bounds = [min($bounds[0], $w), min($bounds[1], $s), max($bounds[2], $e), max($bounds[3], $n)];
            }
        }
        return self::rectangle($bounds);
    }

    public static function rectangle(array $bounds): array
    {
        [$w, $s, $e, $n] = $bounds;
        return ['type' => 'envelope', 'coordinates' => [[$w, $n], [$e, $s]]];
    }

    /** Split dateline-crossing lines; inserted vertices lie on the original spherical arc. */
    public static function forOpenSearch(?array $geometry): ?array
    {
        if ($geometry === null) {
            return null;
        }
        if ($geometry['type'] === 'GeometryCollection') {
            return ['type' => 'GeometryCollection', 'geometries' => array_map([self::class, 'forOpenSearch'], $geometry['geometries'])];
        }
        if ($geometry['type'] !== 'LineString') {
            return $geometry;
        }
        $lines = [];
        $line = [];
        $previous = null;
        foreach ($geometry['coordinates'] as $point) {
            if ($previous !== null && self::angle(self::vector($previous), self::vector($point)) > M_PI - 1e-12) {
                throw new \InvalidArgumentException('Antipodal GPX segment has no unique minor arc; provide an intermediate waypoint.');
            }
            if ($previous !== null && abs($point[0] - $previous[0]) > 180) {
                $normal = self::cross(self::vector($previous), self::vector($point));
                $lat = rad2deg(atan2($normal[0], $normal[2]));
                if ($lat > 90) { $lat -= 180; }
                if ($lat < -90) { $lat += 180; }
                $edge = $previous[0] >= 0 ? 180 : -180;
                if (abs($previous[0]) !== 180.0 && abs($previous[0]) !== 180) { $line[] = [$edge, $lat]; }
                $lines[] = count($line) === 1 ? ['type' => 'Point', 'coordinates' => $line[0]] : ['type' => 'LineString', 'coordinates' => $line];
                $line = [[-$edge, $lat]];
            }
            if (end($line) != $point) { $line[] = $point; }
            $previous = $point;
        }
        $lines[] = count($line) === 1 ? ['type' => 'Point', 'coordinates' => $line[0]] : ['type' => 'LineString', 'coordinates' => $line];
        return count($lines) === 1 ? $lines[0] : ['type' => 'GeometryCollection', 'geometries' => $lines];
    }

    private static function vector(array $point): array
    {
        [$lon, $lat] = array_map('deg2rad', $point);
        return [cos($lat) * cos($lon), cos($lat) * sin($lon), sin($lat)];
    }

    private static function dot(array $a, array $b): float
    {
        return $a[0] * $b[0] + $a[1] * $b[1] + $a[2] * $b[2];
    }

    private static function cross(array $a, array $b): array
    {
        return [$a[1] * $b[2] - $a[2] * $b[1], $a[2] * $b[0] - $a[0] * $b[2], $a[0] * $b[1] - $a[1] * $b[0]];
    }

    private static function angle(array $a, array $b): float
    {
        $cross = self::cross($a, $b);
        return atan2(sqrt(self::dot($cross, $cross)), self::dot($a, $b));
    }
}
