<?php

namespace Pressmind\ORM\Object\Touristic;

use Pressmind\ORM\Object\AbstractObject;
use \DateTime;

/**
 * Class SeasonalPeriod
 * @property string $id
 * @property integer $id_media_object
 * @property string $id_booking_package
 * @property DateTime $season_begin
 * @property DateTime $season_end
 * @property string $info
 * @property string $season
 * @property integer $monday
 * @property integer $tuesday
 * @property integer $wednesday
 * @property integer $thursday
 * @property integer $friday
 * @property integer $saturday
 * @property integer $sunday
 * @property integer $created_by
 * @property DateTime $created_date
 * @property integer $modified_by
 * @property DateTime $modified_date
 * @property integer $offset
 * @property integer $status
 * @property string $link
 * @property integer $pax_max
 * @property integer $pax_min
 * @property integer $pax
 * @property string $code_ibe
 * @property string $id_touristic_early_birds
 * @property string $link_pib
 * @property string $code
 * @property string $id_starting_point
 * @property integer $guaranteed
 * @property integer $saved
 * @property string $touroperator
 * @property Startingpoint $startingpoint
 */
class SeasonalPeriod extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_seasonal_periods',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
            ],
            'id_media_object' => [
                'title' => 'Id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'id_media_object' => 'index'
                ]
            ],
            'id_booking_package' => [
                'title' => 'Id_booking_package',
                'name' => 'id_booking_package',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'id_booking_package' => 'index'
                ]
            ],
            'season_begin' => [
                'title' => 'Season_begin',
                'name' => 'season_begin',
                'type' => 'date',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'season_end' => [
                'title' => 'Season_end',
                'name' => 'season_end',
                'type' => 'date',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'info' => [
                'title' => 'Info',
                'name' => 'info',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'season' => [
                'title' => 'Season',
                'name' => 'season',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 100,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'season' => 'index'
                ]
            ],
            'monday' => [
                'title' => 'Monday',
                'name' => 'monday',
                'type' => 'boolean',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 1,
                    ],
                ],
                'filters' => NULL,
            ],
            'tuesday' => [
                'title' => 'Tuesday',
                'name' => 'tuesday',
                'type' => 'boolean',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 1,
                    ],
                ],
                'filters' => NULL,
            ],
            'wednesday' => [
                'title' => 'Wednesday',
                'name' => 'wednesday',
                'type' => 'boolean',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 1,
                    ],
                ],
                'filters' => NULL,
            ],
            'thursday' => [
                'title' => 'Thursday',
                'name' => 'thursday',
                'type' => 'boolean',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 1,
                    ],
                ],
                'filters' => NULL,
            ],
            'friday' => [
                'title' => 'Friday',
                'name' => 'friday',
                'type' => 'boolean',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 1,
                    ],
                ],
                'filters' => NULL,
            ],
            'saturday' => [
                'title' => 'Saturday',
                'name' => 'saturday',
                'type' => 'boolean',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 1,
                    ],
                ],
                'filters' => NULL,
            ],
            'sunday' => [
                'title' => 'Sunday',
                'name' => 'sunday',
                'type' => 'boolean',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 1,
                    ],
                ],
                'filters' => NULL,
            ],
            'offset' => [
                'title' => 'Offset',
                'name' => 'offset',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'state' => [
                'title' => 'Status',
                'name' => 'state',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 1,
                    ],
                ],
                'filters' => NULL,
            ],
            'url' => [
                'title' => 'Url',
                'name' => 'url',
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
            'pax_max' => [
                'title' => 'Pax_max',
                'name' => 'pax_max',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'pax_min' => [
                'title' => 'Pax_min',
                'name' => 'pax_min',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'pax' => [
                'title' => 'Pax',
                'name' => 'pax',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'code_ibe' => [
                'title' => 'Code_ibe',
                'name' => 'code_ibe',
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
            'id_touristic_early_bird' => [
                'title' => 'Id_touristic_early_bird',
                'name' => 'id_touristic_early_bird',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
            ],
            'link_pib' => [
                'title' => 'Link_pib',
                'name' => 'link_pib',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'code' => [
                'title' => 'Code',
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
            'id_starting_point' => [
                'title' => 'Id_starting_point',
                'name' => 'id_starting_point',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
            ],
            'guaranteed' => [
                'title' => 'Guaranteed',
                'name' => 'guaranteed',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 1,
                    ],
                ],
                'filters' => NULL,
            ],
            'saved' => [
                'title' => 'Saved',
                'name' => 'saved',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 1,
                    ],
                ],
                'filters' => NULL,
            ],
            'touroperator' => [
                'title' => 'Touroperator',
                'name' => 'touroperator',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
            ],
            'startingpoint' => [
                'title' => 'Startingpoint',
                'name' => 'startingpoint',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_starting_point',
                    'class' => Startingpoint::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
        ]
    );
}
