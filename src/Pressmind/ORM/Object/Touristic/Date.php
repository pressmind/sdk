<?php

namespace Pressmind\ORM\Object\Touristic;

use Exception;
use Pressmind\ORM\Object\AbstractObject;
use DateTime;

/**
 * Class Date
 * @property string $id
 * @property integer $id_media_object
 * @property string $id_booking_package
 * @property string $id_starting_point
 * @property string $code
 * @property DateTime $departure
 * @property DateTime $arrival
 * @property string $text
 * @property integer $pax_min
 * @property integer $pax_max
 * @property string $season
 * @property string $url
 * @property integer $state
 * @property string $code_ibe
 * @property integer $id_earlybird_discount
 * @property string $link_pib
 * @property integer $guaranteed
 * @property integer $saved
 * @property string $touroperator
 * @property Startingpoint $startingpoint
 * @property Transport[] $transports
 */
class Date extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_dates',
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
                'index' => [
                    'id_starting_point' => 'index'
                ]
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
            'departure' => [
                'title' => 'Departure',
                'name' => 'departure',
                'type' => 'date',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'arrival' => [
                'title' => 'Arrival',
                'name' => 'arrival',
                'type' => 'date',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'text' => [
                'title' => 'Text',
                'name' => 'text',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
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
            'season' => [
                'title' => 'Season',
                'name' => 'season',
                'type' => 'string',
                'required' => false,
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
            'url' => [
                'title' => 'Url',
                'name' => 'url',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'state' => [
                'title' => 'State',
                'name' => 'state',
                'type' => 'integer',
                'required' => true,
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
            'id_earlybird_discount' => [
                'title' => 'Id_earlybird_discount',
                'name' => 'id_earlybird_discount',
                'type' => 'integer',
                'required' => false,
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
            ],
            'link_pib' => [
                'title' => 'Link_pib',
                'name' => 'link_pib',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'guaranteed' => [
                'title' => 'Guaranteed',
                'name' => 'guaranteed',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'saved' => [
                'title' => 'Saved',
                'name' => 'saved',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
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
                        'params' => 255,
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
            'transports' => [
                'title' => 'transports',
                'name' => 'transports',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_date',
                    'class' => Transport::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
        ]
    );

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getHousingOptions()
    {
        $housing_options = [];
        $housingOptions = Option::listAll("id_booking_package = '" . $this->id_booking_package . "' AND type = 'housing_option' AND season = '" . $this->season . "'");
        foreach ($housingOptions as $housing_option) {
            $housing_options[] = $housing_option;
        }
        return $housing_options;
    }

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getSightseeings()
    {
        $sightseeings = [];
        $sightseeings_list = Option::listAll("id_booking_package = '" . $this->id_booking_package . "' AND type = 'sightseeing' AND season = '" . $this->season . "'");
        foreach ($sightseeings_list as $sightseeing) {
            $sightseeings[] = $sightseeing;
        }
        return $sightseeings;
    }

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getExtras()
    {
        $extras = [];
        $extras_list = Option::listAll("id_booking_package = '" . $this->id_booking_package . "' AND type = 'extra' AND season = '" . $this->season . "'");
        foreach ($extras_list as $extra) {
            $extras[] = $extra;
        }
        return $extras;
    }

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getTickets()
    {
        $tickets = [];
        $tickets_list = Option::listAll("id_booking_package = '" . $this->id_booking_package . "' AND type = 'ticket' AND season = '" . $this->season . "'");
        foreach ($tickets_list as $ticket) {
            $tickets[] = $ticket;
        }
        return $tickets;
    }
}
