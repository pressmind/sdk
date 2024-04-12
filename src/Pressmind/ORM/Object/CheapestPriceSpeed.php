<?php


namespace Pressmind\ORM\Object;

use DateTime;

/**
 * Class CheapestPriceSpeed
 * @package Pressmind\ORM\Object
 * @property integer $id
 * @property integer $id_media_object
 * @property string $id_booking_package
 * @property string $id_housing_package
 * @property string $id_date
 * @property string $id_option
 * @property string $id_transport_1
 * @property string $id_transport_2
 * @property float $duration
 * @property DateTime $date_departure
 * @property DateTime $date_arrival
 * @property string $option_name
 * @property string $option_description_long
 * @property string $option_code
 * @property string $option_board_type
 * @property integer $option_occupancy
 * @property integer $option_occupancy_min
 * @property integer $option_occupancy_max
 * @property string $option_price_due
 * @property float $price_transport_total
 * @property float $price_transport_1
 * @property float $price_transport_2
 * @property string $price_mix
 * @property float $price_option
 * @property float $price_option_pseudo
 * @property float $price_regular_before_discount
 * @property float $price_total
 * @property string $transport_code
 * @property string $transport_type
 * @property integer $transport_1_way
 * @property integer $transport_2_way
 * @property string $transport_1_description
 * @property string $transport_2_description
 * @property string $transport_1_airport
 * @property string $transport_1_airport_name
 * @property string $transport_2_airport
 * @property string $transport_2_airport_name
 * @property string $transport_1_airline
 * @property string $transport_2_airline
 * @property string $transport_1_flight
 * @property string $transport_2_flight
 * @property string $transport_1_date_from
 * @property string $transport_2_date_from
 * @property string $transport_1_date_to
 * @property string $transport_2_date_to
 * @property integer $state
 * @property string $infotext
 * @property float $earlybird_discount
 * @property float $earlybird_discount_f
 * @property DateTime $earlybird_discount_date_to
 * @property string $earlybird_name
 * @property integer $id_option_auto_book
 * @property integer $id_option_required_group
 * @property string $id_startingpoint_option
 * @property integer $id_origin
 * @property string $id_startingpoint
 * @property string $date_code_ibe
 * @property string $housing_package_code_ibe
 * @property string $housing_package_name
 * @property string $housing_package_code
 * @property string $option_code_ibe
 * @property string $option_code_ibe_board_type
 * @property string $option_code_ibe_board_type_category
 * @property string $option_code_ibe_category
 * @property string $option_request_code
 * @property string $transport_1_code_ibe
 * @property string $transport_2_code_ibe
 * @property string $startingpoint_code_ibe
 * @property string $booking_package_ibe_type
 * @property string $booking_package_product_type_ibe
 * @property string $booking_package_type_of_travel
 * @property string $booking_package_variant_code
 * @property string $booking_package_request_code
 * @property string $booking_package_name
 * @property string $diff_to_single_room
 * @property string $included_options_price
 * @property string $included_options_description
 * @property float $id_included_options
 * @property float $code_ibe_included_options
 * @property boolean $is_virtual_created_price (true if price is created from MediaObject->CheapestPrice during Import (! belongs to Pressmind > MOE -> Booking -> Prices)
 * @property boolean $guaranteed
 * @property boolean $saved
 * @property string $startingpoint_name
 * @property string $agency
 */
class CheapestPriceSpeed extends AbstractObject
{

    protected $_definitions = [
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_cheapest_price_speed',
            'primary_key' => 'id',
            'storage_engine' => 'myisam',
            'indexes' => [
                'search_filter_index' => [
                    'type' => 'index',
                    'columns' => [
                        'id_media_object',
                        'price_total',
                        'date_departure',
                        'date_arrival',
                        'option_occupancy',
                        'option_occupancy_min',
                        'option_occupancy_max',
                        'duration'
                    ]
                ],
                'cheapest_price_index' => [
                    'type' => 'index',
                    'columns' => [
                        'id_media_object',
                        'price_total',
                        'date_departure',
                    ]
                ],
                'id_id_media_object_price_total_index' => [
                    'type' => 'index',
                    'columns' => [
                        'id_media_object',
                        'price_total'
                    ]
                ]
            ]
        ],
        'properties' => [
            'id' => [
                'name' => 'id',
                'title' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null
            ],
            'id_media_object' => [
                'name' => 'id_media_object',
                'title' => 'id_media_object',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'index' => [
                    'id_media_object' => 'index'
                ]
            ],
            'id_booking_package' => [
                'name' => 'id_booking_package',
                'title' => 'id_booking_package',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'index' => [
                    'id_booking_package' => 'index'
                ]
            ],
            'id_housing_package' => [
                'name' => 'id_housing_package',
                'title' => 'id_housing_package',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'index' => [
                    'id_housing_package' => 'index'
                ]
            ],
            'id_date' => [
                'name' => 'id_date',
                'title' => 'id_date',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'index' => [
                    'id_date' => 'index'
                ]
            ],
            'id_option' => [
                'name' => 'id_option',
                'title' => 'id_option',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'index' => [
                    'id_option' => 'index'
                ]
            ],
            'id_transport_1' => [
                'name' => 'id_transport_1',
                'title' => 'id_transport_1',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'index' => [
                    'id_transport_1' => 'index'
                ]
            ],
            'id_transport_2' => [
                'name' => 'id_transport_2',
                'title' => 'id_transport_2',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'index' => [
                    'id_transport_2' => 'index'
                ]
            ],
            'duration' => [
                'name' => 'duration',
                'title' => 'duration',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'date_departure' => [
                'name' => 'date_departure',
                'title' => 'date_departure',
                'type' => 'datetime',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'index' => [
                    'date_departure' => 'index'
                ]
            ],
            'date_arrival' => [
                'name' => 'date_arrival',
                'title' => 'date_arrival',
                'type' => 'datetime',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'index' => [
                    'date_arrival' => 'index'
                ]
            ],
            'option_name' => [
                'name' => 'option_name',
                'title' => 'option_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'option_description_long' => [
                'name' => 'option_description_long',
                'title' => 'option_description_long',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'option_code' => [
                'name' => 'option_code',
                'title' => 'option_code',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'option_board_type' => [
                'name' => 'option_board_type',
                'title' => 'option_board_type',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'option_occupancy' => [
                'name' => 'option_occupancy',
                'title' => 'option_occupancy',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'index' => [
                    'option_occupancy' => 'index'
                ]
            ],
            'option_occupancy_min' => [
                'name' => 'option_occupancy_min',
                'title' => 'option_occupancy_min',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'index' => [
                    'option_occupancy_min' => 'index'
                ]
            ],
            'option_occupancy_max' => [
                'name' => 'option_occupancy_max',
                'title' => 'option_occupancy_max',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'index' => [
                    'option_occupancy_max' => 'index'
                ]
            ],
            'price_transport_total' => [
                'name' => 'price_transport_total',
                'title' => 'price_transport_total',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'price_transport_1' => [
                'name' => 'price_transport_1',
                'title' => 'price_transport_1',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'price_transport_2' => [
                'name' => 'price_transport_2',
                'title' => 'price_transport_2',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'price_mix' => [
                'name' => 'price_mix',
                'title' => 'price_mix',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'price_option' => [
                'name' => 'price_option',
                'title' => 'price_option',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'price_option_pseudo' => [
                'name' => 'price_option_pseudo',
                'title' => 'price_option_pseudo',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'option_price_due' => [
                'name' => 'option_price_due',
                'title' => 'option_price_due',
                'type' => 'string',
                'required' => false,
                'default_value' => 'person_stay',
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => [
                            'once',
                            'nightly',
                            'daily',
                            'weekly',
                            'stay',
                            'nights_person',
                            'person_stay',
                            'once_stay'
                        ]
                    ]
                ],
                'filters' => NULL,
            ],
            'price_regular_before_discount' => [
                'name' => 'price_regular_before_discount',
                'title' => 'price_regular_before_discount',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'price_total' => [
                'name' => 'price_total',
                'title' => 'price_total',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'index' => [
                    'price_total' => 'index'
                ]
            ],
            'transport_code' => [
                'name' => 'transport_code',
                'title' => 'transport_code',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_type' => [
                'name' => 'transport_type',
                'title' => 'transport_type',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_1_way' => [
                'name' => 'transport_1_way',
                'title' => 'transport_1_way',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_2_way' => [
                'name' => 'transport_2_way',
                'title' => 'transport_2_way',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_1_description' => [
                'name' => 'transport_1_description',
                'title' => 'transport_1_description',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_2_description' => [
                'name' => 'transport_2_description',
                'title' => 'transport_2_description',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_1_airport' => [
                'name' => 'transport_1_airport',
                'title' => 'transport_1_airport',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 3,
                    ]
                ],
                'index' => [
                    'id_date' => 'index'
                ]
            ],
            'transport_1_airport_name' => [
                'name' => 'transport_1_airport_name',
                'title' => 'transport_1_airport_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_2_airport' => [
                'name' => 'transport_2_airport',
                'title' => 'transport_2_airport',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 3,
                    ]
                ],
                'index' => [
                    'id_date' => 'index'
                ]
            ],
            'transport_2_airport_name' => [
                'name' => 'transport_2_airport_name',
                'title' => 'transport_2_airport_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_1_airline' => [
                'name' => 'transport_1_airline',
                'title' => 'transport_1_airline',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_2_airline' => [
                'name' => 'transport_2_airline',
                'title' => 'transport_2_airline',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_1_flight' => [
                'name' => 'transport_1_flight',
                'title' => 'transport_1_flight',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_2_flight' => [
                'name' => 'transport_2_flight',
                'title' => 'transport_2_flight',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],

            'transport_1_date_from' => [
                'name' => 'transport_1_date_from',
                'title' => 'transport_1_date_from',
                'type' => 'DateTime',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_1_date_to' => [
                'name' => 'transport_1_date_to',
                'title' => 'transport_1_date_to',
                'type' => 'DateTime',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_2_date_from' => [
                'name' => 'transport_2_date_from',
                'title' => 'transport_2_date_from',
                'type' => 'DateTime',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_2_date_to' => [
                'name' => 'transport_2_date_to',
                'title' => 'transport_2_date_to',
                'type' => 'DateTime',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'state' => [
                'name' => 'state',
                'title' => 'state',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'infotext' => [
                'name' => 'infotext',
                'title' => 'infotext',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'earlybird_discount' => [
                'name' => 'earlybird_discount',
                'title' => 'earlybird_discount',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'earlybird_discount_f' => [
                'name' => 'earlybird_discount_f',
                'title' => 'earlybird_discount_f',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'earlybird_discount_date_to' => [
                'name' => 'earlybird_discount_date_to',
                'title' => 'earlybird_discount_date_to',
                'type' => 'DateTime',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'earlybird_name' => [
                'name' => 'earlybird_name',
                'title' => 'earlybird_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'id_option_auto_book' => [
                'name' => 'id_option_auto_book',
                'title' => 'id_option_auto_book',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'id_option_required_group' => [
                'name' => 'id_option_required_group',
                'title' => 'id_option_required_group',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'id_startingpoint_option' => [
                'name' => 'id_startingpoint_option',
                'title' => 'id_startingpoint_option',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ]
            ],
            'id_origin' => [
                'name' => 'id_origin',
                'title' => 'id_origin',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'id_startingpoint' => [
                'name' => 'id_startingpoint',
                'title' => 'id_startingpoint',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ]
            ],
            'date_code_ibe' => [
                'name' => 'date_code_ibe',
                'title' => 'date_code_ibe',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'housing_package_code_ibe' => [
                'name' => 'housing_package_code_ibe',
                'title' => 'housing_package_code_ibe',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'option_code_ibe' => [
                'name' => 'option_code_ibe',
                'title' => 'option_code_ibe',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'option_code_ibe_board_type' => [
                'name' => 'option_code_ibe_board_type',
                'title' => 'option_code_ibe_board_type',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'option_code_ibe_board_type_category' => [
                'name' => 'option_code_ibe_board_type_category',
                'title' => 'option_code_ibe_board_type_category',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'option_code_ibe_category' => [
                'name' => 'option_code_ibe_category',
                'title' => 'option_code_ibe_category',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'option_request_code' => [
                'name' => 'option_request_code',
                'title' => 'option_request_code',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_1_code_ibe' => [
                'name' => 'transport_1_code_ibe',
                'title' => 'transport_1_code_ibe',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'transport_2_code_ibe' => [
                'name' => 'transport_2_code_ibe',
                'title' => 'transport_2_code_ibe',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'startingpoint_code_ibe' => [
                'name' => 'startingpoint_code_ibe',
                'title' => 'startingpoint_code_ibe',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'booking_package_ibe_type' => [
                'name' => 'booking_package_ibe_type',
                'title' => 'booking_package_ibe_type',
                'type' => 'integer',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'booking_package_product_type_ibe' => [
                'name' => 'booking_package_product_type_ibe',
                'title' => 'booking_package_product_type_ibe',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'booking_package_type_of_travel' => [
                'name' => 'booking_package_type_of_travel',
                'title' => 'booking_package_type_of_travel',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'booking_package_variant_code' => [
                'name' => 'booking_package_variant_code',
                'title' => 'booking_package_variant_code',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'booking_package_request_code' => [
                'name' => 'booking_package_request_code',
                'title' => 'booking_package_request_code',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'booking_package_name' => [
                'name' => 'booking_package_name',
                'title' => 'booking_package_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'housing_package_code' => [
                'name' => 'housing_package_code',
                'title' => 'housing_package_code',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'housing_package_name' => [
                'name' => 'housing_package_name',
                'title' => 'housing_package_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'diff_to_single_room' => [
                'name' => 'diff_to_single_room',
                'title' => 'diff_to_single_room',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'included_options_price' => [
                'name' => 'included_options_price',
                'title' => 'included_options_price',
                'type' => 'float',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'included_options_description' => [
                'name' => 'included_options_description',
                'title' => 'included_options_description',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'id_included_options' => [
                'name' => 'id_included_options',
                'title' => 'id_included_options',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'code_ibe_included_options' => [
                'name' => 'code_ibe_included_options',
                'title' => 'code_ibe_included_options',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'is_virtual_created_price' => [
                'title' => 'is_virtual_created_price',
                'name' => 'is_virtual_created_price',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'guaranteed' => [
                'title' => 'guaranteed',
                'name' => 'guaranteed',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'saved' => [
                'title' => 'saved',
                'name' => 'saved',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'startingpoint_name' => [
                'name' => 'startingpoint_name',
                'title' => 'startingpoint_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null
            ],
            'agency' => [
                'name' => 'agency',
                'title' => 'agency',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ]
            ],
        ]
    ];

    /**
     * get the lowest price of all available media objects
     * @return float|null
     */
    public function getLowestPrice()
    {
       $min_result = $this->_db->fetchRow("SELECT MIN(price_total) as min_price FROM pmt2core_cheapest_price_speed INNER JOIN pmt2core_media_objects ON pmt2core_cheapest_price_speed.id_media_object = pmt2core_media_objects.id WHERE pmt2core_media_objects.visibility = 30");
       return !is_null($min_result) ? $min_result->min_price : null;
    }

    /**
     * @return float|null
     */
    public function getHighestPrice()
    {
        $max_result = $this->_db->fetchRow("SELECT MAX(price_total) as max_price FROM pmt2core_cheapest_price_speed INNER JOIN pmt2core_media_objects ON pmt2core_cheapest_price_speed.id_media_object = pmt2core_media_objects.id WHERE pmt2core_media_objects.visibility = 30");
        return !is_null($max_result) ? $max_result->max_price : null;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public static function getMinMaxPrices() {
        $object = new self();
        return array($object->getLowestPrice(), $object->getHighestPrice());
    }

    /**
     * generates the single room price index, on an existing price index
     * @param $id_media_object
     */
    public function generateSingleRoomIndex($id_media_object){
        $items = $this->_db->fetchAll("select * from (
                                                    select round((select price_total from pmt2core_cheapest_price_speed
                                                        where
                                                            date_departure = s1.date_departure and
                                                            duration = s1.duration and
                                                            option_board_type <=> s1.option_board_type  and
                                                            transport_type <=> s1.transport_type and
                                                            id_housing_package = s1.id_housing_package and
                                                            id_booking_package = s1.id_booking_package and
                                                            id_media_object = s1.id_media_object and
                                                            option_occupancy = 1
                                                            order by price_total asc
                                                        limit 1
                                                        ) - s1.price_total ) as diff_to_single_room, id
                                                    from pmt2core_cheapest_price_speed s1
                                                    where s1.option_occupancy = 2 and id_media_object = ".$id_media_object.")
                                                         as t where diff_to_single_room is not null");
        foreach($items as $item){
            $this->_db->update('pmt2core_cheapest_price_speed', ['diff_to_single_room' => $item->diff_to_single_room],['id = ?', $item->id]);
        }
    }

    /**
     * @param $id
     * @return mixed
     */
    public function deleteByMediaObjectId($id){
        return $this->_db->delete($this->getDbTableName(), ['id_media_object = ?', $id]);
    }

}
