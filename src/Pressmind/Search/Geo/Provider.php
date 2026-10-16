<?php

namespace Pressmind\Search\Geo;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Itinerary\Map;
use Pressmind\Registry;

/** Shared persisted geometry and configured location fields; no search-server dependency. */
class Provider
{
    public static function locations(MediaObject $object, $language): \stdClass
    {
        $locations = new \stdClass();
        $config = Registry::getInstance()->get('config');
        $fields = $config['data']['search_mongodb']['search']['locations'][$object->id_object_type] ?? [];
        if (empty($fields)) { return $locations; }
        $data = $object->getDataForLanguage($language);
        if (empty($data)) { return $locations; }
        $data = $data->toStdClass();
        foreach (array_keys($fields) as $field) {
            $points = [];
            foreach ($data->{$field} ?? [] as $location) {
                $point = Geometry::position([$location->lng ?? null, $location->lat ?? null]);
                if ($point !== null) { $points[] = $point; }
            }
            if ($points !== []) {
                $locations->{$field} = (object) ['type' => 'MultiPoint', 'coordinates' => $points];
            }
        }
        return $locations;
    }

    public static function geometry(int $id, $locations): ?array
    {
        $map = new Map($id);
        return Geometry::forSearch($map->search_geometry, $locations);
    }
}
