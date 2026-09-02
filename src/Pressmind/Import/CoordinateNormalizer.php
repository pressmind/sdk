<?php

namespace Pressmind\Import;

use InvalidArgumentException;
use stdClass;

final class CoordinateNormalizer
{
    /**
     * @param stdClass|array|null $coordinates
     * @return array{lat: float, lng: float}|null
     */
    public static function normalize($coordinates): ?array
    {
        if ($coordinates === null) {
            return null;
        }
        if ($coordinates instanceof stdClass) {
            $coordinates = (array) $coordinates;
        }
        if (!is_array($coordinates) || !array_key_exists('lat', $coordinates) || !array_key_exists('lng', $coordinates)) {
            throw new InvalidArgumentException('coordinates must contain lat and lng');
        }
        if (!is_numeric($coordinates['lat']) || !is_numeric($coordinates['lng'])) {
            throw new InvalidArgumentException('lat and lng must be numeric');
        }

        $lat = (float) $coordinates['lat'];
        $lng = (float) $coordinates['lng'];
        if (!is_finite($lat) || !is_finite($lng)) {
            throw new InvalidArgumentException('lat and lng must be finite');
        }
        if ($lat < -90 || $lat > 90) {
            throw new InvalidArgumentException('lat must be between -90 and 90');
        }
        if ($lng < -180 || $lng > 180) {
            throw new InvalidArgumentException('lng must be between -180 and 180');
        }

        return ['lat' => $lat, 'lng' => $lng];
    }
}
