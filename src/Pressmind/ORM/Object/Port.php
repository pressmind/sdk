<?php

namespace Pressmind\ORM\Object;

/**
 * Class Port
 * @property integer $id
 * @property string $name
 * @property string $code
 * @property boolean $active
 * @property string $description
 * @property float|null $lat
 * @property float|null $lng
 */
class Port extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;
    protected $_replace_into_on_create = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_ports',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
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
            'active' => [
                'title' => 'active',
                'name' => 'active',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => null,
            ],
            'code' => [
                'title' => 'code',
                'name' => 'code',
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
            'description' => [
                'title' => 'description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'lat' => [
                'title' => 'lat',
                'name' => 'lat',
                'type' => 'float',
                'required' => false,
                'validators' => [
                    ['name' => 'precision', 'params' => [8, 6]],
                ],
                'filters' => null,
            ],
            'lng' => [
                'title' => 'lng',
                'name' => 'lng',
                'type' => 'float',
                'required' => false,
                'validators' => [
                    ['name' => 'precision', 'params' => [9, 6]],
                ],
                'filters' => null,
            ],
        ],
    ];

    public static $run_time_cache = [];

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function getCoordinates(): ?array
    {
        if ($this->lat === null || $this->lng === null) {
            return null;
        }
        return ['lat' => $this->lat, 'lng' => $this->lng];
    }

    protected function parsePropertyValue($name, $value, $direction = 'input')
    {
        if ($direction === 'output' && ($name === 'lat' || $name === 'lng') && $value === null) {
            return null;
        }
        return parent::parsePropertyValue($name, $value, $direction);
    }

}
