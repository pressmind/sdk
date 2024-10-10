<?php

namespace Pressmind\ORM\Object\Touristic;
use Pressmind\ORM\Filter\Input\TimeFilter;
use Pressmind\ORM\Object\AbstractObject;
use DateTime;
use Pressmind\ORM\Object\Touristic\Option\Discount;
use Pressmind\System\I18n;

/**
 * Class Transport
 * @property string $id
 * @property string $id_date
 * @property integer $id_media_object
 * @property string $id_booking_package
 * @property integer $id_early_bird_discount_group
 * @property string $code
 * @property string $description
 * @property string $type
 * @property integer $way
 * @property float $price
 * @property integer $order
 * @property integer $state
 * @property integer $quota
 * @property string $code_ibe
 * @property integer $auto_book
 * @property integer $required
 * @property string $required_group
 * @property string $transport_group
 * @property string $description_long
 * @property string $id_starting_point
 * @property DateTime $transport_date_from
 * @property DateTime $transport_date_to
 * @property integer $age_from
 * @property integer $age_to
 * @property EarlyBirdDiscountGroup $early_bird_discount_group
 * @property boolean $seatplan_required
 * @property string $airline
 * @property string $flight
 * @property boolean $dont_use_for_offers
 * @property boolean $use_earlybird
 * @property string $time_departure
 * @property string $time_arrival
 * @property Discount $discount
 * @property string $agencies
 */
class Transport extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_transports',
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
            'id_date' => [
                'title' => 'Id_date',
                'name' => 'id_date',
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
                    'id_date' => 'index'
                ]
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
            'id_early_bird_discount_group' => [
                'title' => 'id_early_bird_discount_group',
                'name' => 'id_early_bird_discount_group',
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
                    'id_early_bird_discount_group' => 'index'
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
            'airline' => [
                'title' => 'airline',
                'name' => 'airline',
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
                'title' => 'Description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'type' => [
                'title' => 'Type',
                'name' => 'type',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 5,
                    ],
                ],
                'filters' => NULL,
            ],
            'way' => [
                'title' => 'Way',
                'name' => 'way',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 4,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
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
            'order' => [
                'title' => 'Order',
                'name' => 'order',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                ],
                'filters' => NULL,
            ],
            'state' => [
                'title' => 'State',
                'name' => 'state',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
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
            'auto_book' => [
                'title' => 'Auto_book',
                'name' => 'auto_book',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 4,
                    ],
                ],
                'filters' => NULL,
            ],
            'required' => [
                'title' => 'Required',
                'name' => 'required',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                ],
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
            'transport_group' => [
                'title' => 'Transport_group',
                'name' => 'transport_group',
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
            'transport_date_from' => [
                'title' => 'Transport_date_from',
                'name' => 'transport_date_from',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'transport_date_to' => [
                'title' => 'Transport_date_to',
                'name' => 'transport_date_to',
                'type' => 'datetime',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'age_from' => [
                'title' => 'Age from',
                'name' => 'age_from',
                'type' => 'integer',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'age_to' => [
                'title' => 'Age to',
                'name' => 'age_to',
                'type' => 'integer',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'early_bird_discount_group' => [
                'title' => 'Early Bird Discount',
                'name' => 'early_bird_discount_group',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_early_bird_discount_group',
                    'class' => EarlyBirdDiscountGroup::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'starting_points' => [
                'title' => 'Starting Points',
                'name' => 'starting_points',
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
            'seatplan_required' => [
                'title' => 'seatplan_required',
                'name' => 'seatplan_required',
                'type' => 'boolean',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'flight' => [
                'title' => 'flight',
                'name' => 'flight',
                'type' => 'string',
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
            'use_earlybird' => [
                'title' => 'use_earlybird',
                'name' => 'use_earlybird',
                'type' => 'boolean',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'time_departure' => [
                'title' => 'time_departure',
                'name' => 'time_departure',
                'type' => 'time',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'time_arrival' => [
                'title' => 'time_arrival',
                'name' => 'time_arrival',
                'type' => 'time',
                'required' => false,
                'validators' => NULL,
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
            'agencies' => [
                'title' => 'agencies',
                'name' => 'agencies',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
        ]
    );

    public function mapTypeToString() {
        $mapping = [
            'BUS' => 'Busreise',
            'PKW' => 'Eigenanreise',
            'FLUG' => 'Flugreise',
            'SCHIFF' => 'Schiffsreise',
            'BAH' => 'Bahnreise'
        ];
        return I18n::translate($mapping[$this->type]);
    }

    /**
     * @return string[]
     */
    public function getValidTypes(){
        return ['BUS', 'PKW', 'FLUG', 'SCHIFF', 'BAH'];
    }

    /**
     * Human friendly validation
     * @param string $prefix
     * @return array
     */
    public function validate($prefix){
        $result = [];
        if(in_array($this->type, $this->getValidTypes()) === false){
            $result[] = $prefix.' ❌. Transport type is not valid Transport ID: ' . $this->id. ' has not a valid type ('.$this->type.') , allowed ('.implode(', ',  $this->getValidTypes() ).')';
        }
        if($this->type === 'FLUG' && strlen((string)$this->code) < 6){
            $result[] = $prefix.' ❌. Flight is not valid Transport ID: ' . $this->id. ' has not a valid IATA code (2x 3 Letters)';
        }
        if($this->dont_use_for_offers === true){
            $result[] = $prefix.' ❌. Transport ID: ' . $this->id. ' is probably not available for booking (dont_use_for_offers = true)';
        }
        if(empty($result)){
            $result[] = $prefix.' ✅. Transport ID: ' . $this->id. ' is valid';
        }
        return $result;
    }
}
