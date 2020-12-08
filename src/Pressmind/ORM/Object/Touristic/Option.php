<?php

namespace Pressmind\ORM\Object\Touristic;

use \DateTime;
use \Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\Housing\Package;

/**
 * Class Option
 * @property string $id
 * @property integer $id_media_object
 * @property string $id_booking_package
 * @property string $id_housing_package
 * @property string $type
 * @property string $season
 * @property string $code
 * @property string $name
 * @property string $board_type
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
 * @property integer $state
 * @property string $code_ibe
 * @property string $price_due
 * @property string $code_ibe_board_type
 * @property string $code_ibe_board_type_category
 * @property string $code_ibe_category
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
 * @property integer $use_earlybird
 * @property string $request_code
 * @property string $currency
 * @property integer $occupancy_min
 * @property integer $occupancy_max
 * @property integer $occupancy_max_age
 */
class Option extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'TouristicOption',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_options',
                'primary_key' => 'id',
            ),
        'properties' =>
            array(
                'id' =>
                    array(
                        'title' => 'Id',
                        'name' => 'id',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 32,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'id_media_object' =>
                    array(
                        'title' => 'Id_media_object',
                        'name' => 'id_media_object',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 22,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'id_booking_package' =>
                    array(
                        'title' => 'Id_booking_package',
                        'name' => 'id_booking_package',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 32,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'id_housing_package' =>
                    array(
                        'title' => 'Id_housing_package',
                        'name' => 'id_housing_package',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 32,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'type' =>
                    array(
                        'title' => 'Type',
                        'name' => 'type',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'inarray',
                                        'params' =>
                                            array(
                                                0 => 'housing_option',
                                                1 => 'extra',
                                                2 => 'sightseeing',
                                                3 => 'ticket'
                                            ),
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'season' =>
                    array(
                        'title' => 'Season',
                        'name' => 'season',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 100,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'code' =>
                    array(
                        'title' => 'Code',
                        'name' => 'code',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 45,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'name' =>
                    array(
                        'title' => 'Name',
                        'name' => 'name',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'board_type' =>
                    array(
                        'title' => 'Board_type',
                        'name' => 'board_type',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 45,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'price' =>
                    array(
                        'title' => 'Price',
                        'name' => 'price',
                        'type' => 'float',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'price_pseudo' =>
                    array(
                        'title' => 'Price_pseudo',
                        'name' => 'price_pseudo',
                        'type' => 'float',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'price_child' =>
                    array(
                        'title' => 'Price_child',
                        'name' => 'price_child',
                        'type' => 'float',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'occupancy' =>
                    array(
                        'title' => 'Occupancy',
                        'name' => 'occupancy',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'occupancy_child' =>
                    array(
                        'title' => 'Occupancy_child',
                        'name' => 'occupancy_child',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'quota' =>
                    array(
                        'title' => 'Quota',
                        'name' => 'quota',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'renewal_duration' =>
                    array(
                        'title' => 'Renewal_duration',
                        'name' => 'renewal_duration',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'renewal_price' =>
                    array(
                        'title' => 'Renewal_price',
                        'name' => 'renewal_price',
                        'type' => 'float',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'order' =>
                    array(
                        'title' => 'Order',
                        'name' => 'order',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'booking_type' =>
                    array(
                        'title' => 'Booking_type',
                        'name' => 'booking_type',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'event' =>
                    array(
                        'title' => 'Event',
                        'name' => 'event',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 45,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'state' =>
                    array(
                        'title' => 'State',
                        'name' => 'state',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'code_ibe' =>
                    array(
                        'title' => 'Code_ibe',
                        'name' => 'code_ibe',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'price_due' =>
                    array(
                        'title' => 'Price_due',
                        'name' => 'price_due',
                        'type' => 'string',
                        'required' => false,
                        'default_value' => 'person_stay',
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'inarray',
                                        'params' =>
                                            array(
                                                0 => 'once',
                                                1 => 'nightly',
                                                2 => 'daily',
                                                3 => 'weekly',
                                                4 => 'stay',
                                                5 => 'nights_person',
                                                6 => 'person_stay',
                                                7 => 'once_stay'
                                            ),
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'code_ibe_board_type' =>
                    array(
                        'title' => 'Code_ibe_board_type',
                        'name' => 'code_ibe_board_type',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'code_ibe_board_type_category' =>
                    array(
                        'title' => 'Code_ibe_board_type_category',
                        'name' => 'code_ibe_board_type_category',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'code_ibe_category' =>
                    array(
                        'title' => 'Code_ibe_category',
                        'name' => 'code_ibe_category',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'auto_book' =>
                    array(
                        'title' => 'Auto_book',
                        'name' => 'auto_book',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 4,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'required' =>
                    array(
                        'title' => 'Required',
                        'name' => 'required',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 4,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'required_group' =>
                    array(
                        'title' => 'Required_group',
                        'name' => 'required_group',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'description_long' =>
                    array(
                        'title' => 'Description_long',
                        'name' => 'description_long',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'min_pax' =>
                    array(
                        'title' => 'Min_pax',
                        'name' => 'min_pax',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'max_pax' =>
                    array(
                        'title' => 'Max_pax',
                        'name' => 'max_pax',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'reservation_date_from' =>
                    array(
                        'title' => 'Reservation_date_from',
                        'name' => 'reservation_date_from',
                        'type' => 'datetime',
                        'required' => false,
                        'validators' => array(
                            array(
                                'name' => 'datetime'
                            )
                        ),
                        'filters' => NULL,
                    ),
                'reservation_date_to' =>
                    array(
                        'title' => 'Reservation_date_to',
                        'name' => 'reservation_date_to',
                        'type' => 'datetime',
                        'required' => false,
                        'validators' => array(
                            array(
                                'name' => 'datetime'
                            )
                        ),
                        'filters' => NULL,
                    ),
                'age_from' =>
                    array(
                        'title' => 'Age_from',
                        'name' => 'age_from',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 6,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'age_to' =>
                    array(
                        'title' => 'Age_to',
                        'name' => 'age_to',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 6,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'selection_type' =>
                    array(
                        'title' => 'Selection_type',
                        'name' => 'selection_type',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'inarray',
                                        'params' =>
                                            array(
                                                0 => 'SINGLE',
                                                1 => 'OPTIONAL',
                                                2 => 'MULTIPLE',
                                            ),
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'use_earlybird' =>
                    array(
                        'title' => 'Use_earlybird',
                        'name' => 'use_earlybird',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 4,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'request_code' =>
                    array(
                        'title' => 'Request_code',
                        'name' => 'request_code',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'currency' =>
                    array(
                        'title' => 'Currency',
                        'name' => 'currency',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'occupancy_min' =>
                    array(
                        'title' => 'Occupancy_min',
                        'name' => 'occupancy_min',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'occupancy_max' =>
                    array(
                        'title' => 'Occupancy_max',
                        'name' => 'occupancy_max',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'occupancy_max_age' =>
                    array(
                        'title' => 'Occupancy_max_age',
                        'name' => 'occupancy_max_age',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
            ),
    );

    /**
     * @return Package
     * @throws \Exception
     */
    public function getHousingPackage() {
        $housing_packages = Package::listAll(['id' => $this->id_housing_package]);
        return $housing_packages[0];
    }
}
