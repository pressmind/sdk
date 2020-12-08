<?php

namespace Pressmind\ORM\Object;

/**
 * Class Airport
 * @property integer $id
 * @property string $name
 * @property string $city
 * @property string $country
 * @property string $iata
 * @property string $icao
 * @property float $latitude
 * @property float $longitude
 * @property float $altitude_feet
 * @property string $timezone
 * @property string $dst
 * @property string $source
 */
class Airport extends AbstractObject
{
    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'Airport',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_airports',
                'primary_key' => 'id',
            ),
        'properties' =>
            array(
                'id' =>
                    array(
                        'title' => 'Id',
                        'name' => 'id',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 22,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'name' =>
                    array(
                        'title' => 'Name',
                        'name' => 'name',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'city' =>
                    array(
                        'title' => 'City',
                        'name' => 'city',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'country' =>
                    array(
                        'title' => 'Country',
                        'name' => 'country',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'iata' =>
                    array(
                        'title' => 'Iata',
                        'name' => 'iata',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 3,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'icao' =>
                    array(
                        'title' => 'Icao',
                        'name' => 'icao',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 4,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'latitude' =>
                    array(
                        'title' => 'Latitude',
                        'name' => 'latitude',
                        'type' => 'float',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'longitude' =>
                    array(
                        'title' => 'Longitude',
                        'name' => 'longitude',
                        'type' => 'float',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'altitude_feet' =>
                    array(
                        'title' => 'Altitude_feet',
                        'name' => 'altitude_feet',
                        'type' => 'float',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'timezone' =>
                    array(
                        'title' => 'Timezone',
                        'name' => 'timezone',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'dst' =>
                    array(
                        'title' => 'Dst',
                        'name' => 'dst',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 1,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'source' =>
                    array(
                        'title' => 'Source',
                        'name' => 'source',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
            ),
    );
}
