<?php

namespace Pressmind\ORM\Object\Touristic;

use \DateTime;
use Exception;
use \Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\Housing\Package;
use Pressmind\ORM\Object\Touristic\Option\Discount;

/**
 * Class Option
 * @property string $id
 * @property integer $id_media_object
 * @property string $id_booking_package
 * @property string $id_housing_package
 * @property string $id_transport
 * @property string $type
 * @property string $season
 * @property string $code
 * @property string $name
 * @property string $board_type
 * @property string $board_code
 * @property float $price
 * @property float $price_pseudo
 * @property float $price_child
 * @property integer $occupancy
 * @property integer $occupancy_child
 * @property integer $quota
 * @property integer $renewal_duration
 * @property float $renewal_price
 * @property integer $order
 * @property integer $booking_type
 * @property string $event
 * @property integer $state (0 = ausgebucht, 1 = anfrage, 2 = wenig, 3 = buchbar, 4 = buchungsstopp, 5 = ausblenden)
 * @property string $code_ibe
 * @property string $price_due
 * @property string $code_ibe_board_type
 * @property string $code_ibe_board_type_category
 * @property string $code_ibe_category // deprecated
 * @property integer $auto_book
 * @property integer $required
 * @property string $required_group
 * @property string $description_long
 * @property integer $min_pax
 * @property integer $max_pax
 * @property DateTime $reservation_date_from
 * @property DateTime $reservation_date_to
 * @property integer $age_from
 * @property integer $age_to
 * @property string $selection_type
 * @property boolean $use_earlybird
 * @property string $request_code
 * @property string $price_group
 * @property string $product_group
 * @property string $currency
 * @property integer $occupancy_min
 * @property integer $occupancy_max
 * @property integer $occupancy_max_age
 * @property integer $id_touristic_option_discount;
 * @property Discount $discount
 * @property string $ibe_clients
 * @property string $agencies
 * @property string $crs_meta_data
 * @property boolean $dont_use_for_offers
 */
class Option extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_options',
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
            'id_housing_package' => [
                'title' => 'Id_housing_package',
                'name' => 'id_housing_package',
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
                    'id_housing_package' => 'index'
                ]
            ],
            'id_transport' => [
                'title' => 'id_transport',
                'name' => 'id_transport',
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
                    'id_transport' => 'index'
                ]
            ],
            'id_touristic_option_discount' => [
                'title' => 'id_touristic_option_discount',
                'name' => 'id_touristic_option_discount',
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
                    'id_touristic_option_discount' => 'index'
                ]
            ],
            'type' => [
                'title' => 'Type',
                'name' => 'type',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => [
                            'housing_option',
                            'extra',
                            'sightseeing',
                            'ticket',
                            'transport_extra',
                            'dummy', // used as little joker... for example in price_mix date_transport context
                        ],
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'type' => 'index'
                ]
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
            'code' => [
                'title' => 'Code',
                'name' => 'code',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 45,
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
            'board_type' => [
                'title' => 'Board_type',
                'name' => 'board_type',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 45,
                    ],
                ],
                'filters' => NULL,
            ],
            'board_code' => [
                'title' => 'board_code',
                'name' => 'board_code',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 10,
                    ],
                ],
                'filters' => NULL,
            ],
            'price' => [
                'title' => 'Price',
                'name' => 'price',
                'type' => 'float',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'price_pseudo' => [
                'title' => 'Price_pseudo',
                'name' => 'price_pseudo',
                'type' => 'float',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'price_child' => [
                'title' => 'Price_child',
                'name' => 'price_child',
                'type' => 'float',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'occupancy' => [
                'title' => 'Occupancy',
                'name' => 'occupancy',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                ],
                'filters' => NULL,
            ],
            'occupancy_child' => [
                'title' => 'Occupancy_child',
                'name' => 'occupancy_child',
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
            'quota' => [
                'title' => 'Quota',
                'name' => 'quota',
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
            'renewal_duration' => [
                'title' => 'Renewal_duration',
                'name' => 'renewal_duration',
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
            'renewal_price' => [
                'title' => 'Renewal_price',
                'name' => 'renewal_price',
                'type' => 'float',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'order' => [
                'title' => 'Order',
                'name' => 'order',
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
            'booking_type' => [
                'title' => 'Booking_type',
                'name' => 'booking_type',
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
            'event' => [
                'title' => 'Event',
                'name' => 'event',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 45,
                    ],
                ],
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
            'price_due' => [
                'title' => 'Price_due',
                'name' => 'price_due',
                'type' => 'string',
                'required' => false,
                'default_value' => 'person_stay',
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => [
                            'once',         // extras: einmalig pro Person (Default)
                            'once_stay',    // extras: einmalig pro Aufenthalt
                            'nightly',      // extras: nächtlich
                            'daily',        // extras: täglich
                            'weekly',       // extras: wöchentlich
                            'person_stay',  // housing_option: pro Person pro Aufenthalt (Default)
                            'stay',         // housing_option: pro Aufenthalt
                            'nights_person',// housing_option: pro Nacht pro Person
                        ],
                    ],
                ],
                'filters' => NULL,
            ],
            'code_ibe_board_type' => [
                'title' => 'Code_ibe_board_type',
                'name' => 'code_ibe_board_type',
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
            'code_ibe_board_type_category' => [
                'title' => 'Code_ibe_board_type_category',
                'name' => 'code_ibe_board_type_category',
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
            'code_ibe_category' => [
                'title' => 'Code_ibe_category',
                'name' => 'code_ibe_category',
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
            'auto_book' => [
                'title' => 'Auto_book',
                'name' => 'auto_book',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'required' => [
                'title' => 'Required',
                'name' => 'required',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'required_group' => [
                'title' => 'Required_group',
                'name' => 'required_group',
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
            'description_long' => [
                'title' => 'Description_long',
                'name' => 'description_long',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'min_pax' => [
                'title' => 'Min_pax',
                'name' => 'min_pax',
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
            'max_pax' => [
                'title' => 'Max_pax',
                'name' => 'max_pax',
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
            'reservation_date_from' => [
                'title' => 'Reservation_date_from',
                'name' => 'reservation_date_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'datetime',
                    ],
                ],
                'filters' => NULL,
            ],
            'reservation_date_to' => [
                'title' => 'Reservation_date_to',
                'name' => 'reservation_date_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'datetime',
                    ],
                ],
                'filters' => NULL,
            ],
            'age_from' => [
                'title' => 'Age_from',
                'name' => 'age_from',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 6,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'age_to' => [
                'title' => 'Age_to',
                'name' => 'age_to',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 6,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'selection_type' => [
                'title' => 'Selection_type',
                'name' => 'selection_type',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => [
                            'MIN_ONE_OF_GROUP',
                            'MAX_ONE_OF_GROUP',
                            'EXACTLY_ONE_OF_GROUP',
                            'OPTIONAL',
                            'SINGLE' // legacy
                        ],
                    ],
                ],
                'filters' => NULL,
            ],
            'use_earlybird' => [
                'title' => 'Use_earlybird',
                'name' => 'use_earlybird',
                'type' => 'boolean',
                'required' => false,
                'filters' => NULL,
            ],
            'request_code' => [
                'title' => 'Request_code',
                'name' => 'request_code',
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
            'price_group' => [
                'title' => 'price_group',
                'name' => 'price_group',
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
            'product_group' => [
                'title' => 'product_group',
                'name' => 'product_group',
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
            'currency' => [
                'title' => 'Currency',
                'name' => 'currency',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ]
                ],
                'filters' => NULL,
            ],
            'occupancy_min' => [
                'title' => 'Occupancy_min',
                'name' => 'occupancy_min',
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
            'occupancy_max' => [
                'title' => 'Occupancy_max',
                'name' => 'occupancy_max',
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
            'occupancy_max_age' => [
                'title' => 'Occupancy_max_age',
                'name' => 'occupancy_max_age',
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
            'discount' => [
                'title' => 'Discount',
                'name' => 'discount',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_touristic_option_discount',
                    'class' => Discount::class,
                    'filters' => ['active' => 1]
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'ibe_clients' => [
                'title' => 'ibe_clients',
                'name' => 'ibe_clients',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'agencies' => [
                'title' => 'agencies',
                'name' => 'agencies',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'crs_meta_data' => [
                'title' => 'crs_meta_data',
                'name' => 'crs_meta_data',
                'type' => 'longtext',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'dont_use_for_offers' => [
                'title' => 'dont_use_for_offers',
                'name' => 'dont_use_for_offers',
                'type' => 'boolean',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
        ]
    );

    /**
     * @return Package
     * @throws Exception
     */
    public function getHousingPackage() {
        $housing_packages = Package::listAll(['id' => $this->id_housing_package]);
        return !empty($housing_packages[0]) ? $housing_packages[0] : null;
    }

    /**
     * Converts a periodic price_due to 'once', (person based price),
     * Use this to flatten the price model
     * @param float $duration_days
     * @param int $nights
     * @return float
     */
    public function calculatePrice($duration_days, $nights){
        $price = $this->price;
        if (in_array($this->type, ['ticket', 'sightseeing', 'extra']) && in_array($this->price_due, ['nightly', 'daily', 'weekly'])) {
            if (in_array($this->price_due, ['nightly', 'nights_person'])) {
                $price= $this->price * $nights;
            }
            if ($this->price_due == 'daily') {
                $price = $this->price * $duration_days;
            }
            if ($this->price_due == 'weekly') {
                $price =  $this->price * ceil($duration_days / 7);
            }
            $this->price_due = 'once';
            $this->price = $price;
        }
        return $price;
    }



}
