<?php

namespace Pressmind\ORM\Object\Touristic\Booking;

use DateInterval;
use DateTime;
use Exception;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\ORM\Object\Itinerary\Variant;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\Insurance;
use Pressmind\ORM\Object\Touristic\Option;
use Pressmind\ORM\Object\Touristic\Pickupservice;
use Pressmind\ORM\Object\Touristic\SeasonalPeriod;

/**
 * Class TouristicBookingPackage
 * @property string $id
 * @property integer $id_media_object
 * @property string $name
 * @property float $duration
 * @property integer $order
 * @property string $url
 * @property string $text
 * @property string $price_mix
 * @property integer $id_pickupservice
 * @property integer $id_insurance_group
 * @property integer $ibe_type
 * @property string $product_type_ibe
 * @property integer $id_origin
 * @property string $code
 * @property string $type_of_travel
 * @property string $variant_code
 * @property string $request_code
 * @property string $destination_airport
 * @property Pickupservice $pickupservice
 * @property Insurance\Group $insurance_group
 * @property Date[] $dates
 * @property SeasonalPeriod[] $seasonal_periods
 * @property Variant[] $itinerary_variants
 * @property \Pressmind\ORM\Object\Touristic\Housing\Package[] $housing_packages
 * @property Option[] $sightseeings
 * @property Option[] $tickets
 * @property Option[] $extras
 */
class Package extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_booking_packages',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' =>
                array(
                    'title' => 'Id',
                    'name' => 'id',
                    'type' => 'string',
                    'required' => true,
                    'validators' => [
                        [
                            'name' => 'maxlength',
                            'params' => 32,
                        ]
                    ],
                    'filters' => null
                ),
            'id_media_object' =>
                array(
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
                    'filters' => null,
                    'index' => [
                        'id_media_object' => 'index'
                    ]
                ),
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'duration' => [
                'title' => 'Duration',
                'name' => 'duration',
                'type' => 'float',
                'required' => true,
                'validators' => null,
                'filters' => null
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
                'filters' => null
            ],
            'url' => [
                'title' => 'Url',
                'name' => 'url',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'text' => [
                'title' => 'Text',
                'name' => 'text',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'price_mix' => [
                'title' => 'Price_mix',
                'name' => 'price_mix',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => null
            ],
            'id_pickupservice' => array(
                'title' => 'Id_pickupservice',
                'name' => 'id_pickupservice',
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
                'filters' => null,
                'index' => [
                    'id_pickupservice' => 'index'
                ]
            ),
            'id_insurance_group' => [
                'title' => 'Id_insurance_group',
                'name' => 'id_insurance_group',
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
                'filters' => null,
                'index' => [
                    'id_insurance_group' => 'index'
                ]
            ],
            'ibe_type' => [
                'title' => 'Ibe_type',
                'name' => 'ibe_type',
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
                'filters' => null
            ],
            'product_type_ibe' => [
                'title' => 'Product_type_ibe',
                'name' => 'product_type_ibe',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => null
            ],
            'id_origin' => [
                'title' => 'Id_origin',
                'name' => 'id_origin',
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
                'filters' => null,
                'index' => [
                    'id_origin' => 'index'
                ]
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
                'filters' => null
            ],
            'type_of_travel' => [
                'title' => 'type_of_travel',
                'name' => 'type_of_travel',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => null
            ],
            'variant_code' => [
                'title' => 'variant_code',
                'name' => 'variant_code',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => null
            ],
            'request_code' => [
                'title' => 'request_code',
                'name' => 'request_code',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => null
            ],
            'destination_airport' => [
                'title' => 'destination_airport',
                'name' => 'destination_airport',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => null
            ],
            'pickupservice' => [
                'title' => 'Pickupservice',
                'name' => 'pickupservice',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_pickupservice',
                    'class' => Pickupservice::class
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'insurance_group' => [
                'title' => 'Insurance Group',
                'name' => 'insurance_group',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_insurance_group',
                    'class' => Insurance\Group::class
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'dates' => [
                'title' => 'Dates',
                'name' => 'dates',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_booking_package',
                    'class' => Date::class,
                    'order_columns' => [
                        'departure' => 'ASC'
                    ]
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'seasonal_periods' => [
                'title' => 'seasonal_periods',
                'name' => 'seasonal_periods',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_booking_package',
                    'class' => SeasonalPeriod::class
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'itinerary_variants' => [
                'title' => 'itinerary_variants',
                'name' => 'itinerary_variants',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_booking_package',
                    'class' => Variant::class
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'housing_packages' => [
                'title' => 'Housing Packages',
                'name' => 'housing_packages',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_booking_package',
                    'class' => \Pressmind\ORM\Object\Touristic\Housing\Package::class
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'sightseeings' => [
                'title' => 'sightseeings',
                'name' => 'sightseeings',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_booking_package',
                    'class' => Option::class,
                    'filters' => [
                        'type' => 'sightseeing'
                    ]
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'tickets' => [
                'title' => 'tickets',
                'name' => 'tickets',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_booking_package',
                    'class' => Option::class,
                    'filters' => [
                        'type' => 'ticket'
                    ]
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'extras' => [
                'title' => 'extras',
                'name' => 'extras',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_booking_package',
                    'class' => Option::class,
                    'filters' => [
                        'type' => 'extra'
                    ]
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];

    /**
     * @return mixed
     * @throws Exception
     */
    public function getCheapestPrice()
    {
        $now = new DateTime();
        $where = "id_booking_package = '" . $this->getId() . "' AND price_total > 0 AND date_departure > '" . $now->format('Y-m-d H:i:s') . "'";
        $cheapest_price = CheapestPriceSpeed::listAll($where . ' AND option_occupancy = 2', ['price_total' => 'ASC']);
        if(empty($cheapest_price)) {
            $cheapest_price = CheapestPriceSpeed::listAll($where . ' AND option_occupancy = 1', ['price_total' => 'ASC']);
        }
        if(empty($cheapest_price)) {
            $cheapest_price = CheapestPriceSpeed::listAll($where, ['price_total' => 'ASC']);
        }
        return $cheapest_price[0];
    }

    /**
     * @param null|DateTime $dateFrom
     * @param int $offsetDays
     * @return Date[]
     * @throws Exception
     */
    public function getValidDates($dateFrom = null, $offsetDays = 0) {
        $dates = [];
        if(is_null($dateFrom)) {
            $dateFrom = new DateTime();
            $dateFrom->add(new DateInterval('P' . $offsetDays . 'D'));
        }
        foreach ($this->dates as $date) {
            if($date->departure > $dateFrom && (count($date->getHousingOptions()) > 0 || $this->duration == 1)) {
                $dates[] = $date;
            }
        }
        return $dates;
    }

    /**
     * @param null $dateFrom
     * @param int $offsetDays
     * @return bool
     * @throws Exception
     */
    public function hasValidDates($dateFrom = null, $offsetDays = 0)
    {
        return count($this->getValidDates($dateFrom, $offsetDays)) > 0;
    }
}
