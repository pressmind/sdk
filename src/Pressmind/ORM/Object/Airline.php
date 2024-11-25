<?php

namespace Pressmind\ORM\Object;

/**
 * Class Airline
 * @property integer $id
 * @property string $name
 * @property string $alias
 * @property string $iata
 * @property string $icao
 * @property string $callsign
 * @property string $country
 * @property string $active
 */
class Airline extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_airlines',
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
                'filters' => null,
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
                'filters' => null,
            ],
            'alias' => [
                'title' => 'Alias',
                'name' => 'alias',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => null,
            ],
            'iata' => [
                'title' => 'Iata',
                'name' => 'iata',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 2,
                    ],
                ],
                'filters' => null,
            ],
            'icao' => [
                'title' => 'Icao',
                'name' => 'icao',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 4,
                    ],
                ],
                'filters' => null,
            ],
            'callsign' => [
                'title' => 'Callsign',
                'name' => 'callsign',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => null,
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
                'filters' => null,
            ],
            'active' => [
                'title' => 'Active',
                'name' => 'active',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => null,
            ],
        ],
    ];

    /**
     * Human friendly validation
     * @return array
     * @throws Exception
     */
    public static function validate($prefix = ''){
        $result = [];
        $Airline = new Airline();
        $r = $Airline->getTableRowCount();
        if($r == 0){
            $result[] = $prefix . ' ❌  No airlines found, pls run the import script';
        }
        return $result;
    }
}
