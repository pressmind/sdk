<?php


namespace Pressmind\ORM\Object;

use DateTime;

/**
 * Class Season
 * @package Pressmind\ORM\Object
 * @property integer $id
 * @property boolean $active
 * @property string $name
 * @property DateTime $season_from
 * @property DateTime $season_to
 */
class Season extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => [
            'name' => 'Season',
            'namespace' => '\Pressmind\ORM\Object',
        ],
        'database' => [
            'table_name' => 'pmt2core_seasons',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                ],
            ],
            'active' => [
                'title' => 'active',
                'name' => 'active',
                'type' => 'boolean',
                'required' => true,
                'filters' => null,
                'validators' => null
            ],
            'name' => [
                'title' => 'name',
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
            ],
            'season_from' => [
                'title' => 'season_from',
                'name' => 'season_from',
                'type' => 'datetime',
                'required' => true,
                'filters' => null,
                'validators' => null
            ],
            'season_to' => [
                'title' => 'season_to',
                'name' => 'season_to',
                'type' => 'datetime',
                'required' => true,
                'filters' => null,
                'validators' => null
            ],
        ]
    ];
}
