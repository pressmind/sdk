<?php

namespace Pressmind\ORM\Object;

/**
 * Class Airport
 * @property integer $id
 * @property string $name
 * @property string $city
 * @property string $country
 * @property string $iata
 * @property float $latitude
 * @property float $longitude
 */
class Airport extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_airports',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                ],
                'filters' => NULL,
            ],
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'city' => [
                'title' => 'City',
                'name' => 'city',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
            'filters' => NULL,
            ],
            'country' => [
                'title' => 'Country',
                'name' => 'country',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'iata' => [
                'title' => 'Iata',
                'name' => 'iata',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 3,
                    ],
                ],
                'filters' => NULL,
            ],
            'latitude' => [
                'title' => 'Latitude',
                'name' => 'latitude',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'longitude' => [
                'title' => 'Longitude',
                'name' => 'longitude',
                'type' => 'float',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ]
        ],
    ];

    public static $run_time_cache = [];

    /**
     * @param $code
     * @return Airport|null
     * @throws \Exception
     */
    public static function getByIata($code){
        if(empty($code)){
            $airport = new Airport();
            $airport->name = 'Unbekannt';
            return $airport;
        }
        $code = strtoupper($code);
        if(isset(self::$run_time_cache[$code])){
            return self::$run_time_cache[$code];
        }
        $airport = self::listOne('iata = "'.$code.'"');
        self::$run_time_cache[$code] = $airport;
        return $airport;

    }

    /**
     * Human friendly validation
     * @return array
     * @throws Exception
     */
    public static function validate($prefix = ''){
        $result = [];
        $Airport = new Airport();
        $r = $Airport->getTableRowCount();
        if($r == 0){
            $result[] = $prefix . ' ❌  No airports found, pls run the import script';
        }
        return $result;
    }
}
