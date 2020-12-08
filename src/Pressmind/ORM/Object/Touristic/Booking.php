<?php

namespace Pressmind\ORM\Object\Touristic;

use Pressmind\ORM\Object\AbstractObject;
/**
 * Class Booking
 * @property integer $id
 * @property \DateTime $booking_date
 * @property string $code
 * @property string $travel_name
 * @property integer $id_media_object
 * @property string $email
 * @property string $city
 * @property string $zip
 * @property string $country
 * @property string $phone
 * @property string $name
 * @property string $surname
 * @property string $street
 * @property string $street_no
 * @property string $gender
 * @property string $data
 * @property string $msg
 * @property float $value
 * @property  $booked
 * @property \DateTime $created_date
 * @property \DateTime $modified_date
 * @property float $booking_data
 * @property string $booking_type
 * @property string $partner_code
 * @property string $ibe_code
 * @property string $ibe_agency
 * @property string $attachments
 * @property integer $id_wp_user
 * @property string $tt_reference
 */
class Booking extends AbstractObject
{
    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'Booking',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_bookings',
                'primary_key' => 'id',
            ),
        'properties' =>
            array(
                'id' =>
                    array(
                        'title' => 'Id',
                        'name' => 'id',
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
                'booking_date' =>
                    array(
                        'title' => 'Booking_date',
                        'name' => 'booking_date',
                        'type' => 'datetime',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'code' =>
                    array(
                        'title' => 'Code',
                        'name' => 'code',
                        'type' => 'string',
                        'required' => true,
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
                'travel_name' =>
                    array(
                        'title' => 'Travel_name',
                        'name' => 'travel_name',
                        'type' => 'string',
                        'required' => true,
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
                'email' =>
                    array(
                        'title' => 'Email',
                        'name' => 'email',
                        'type' => 'string',
                        'required' => true,
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
                'city' =>
                    array(
                        'title' => 'City',
                        'name' => 'city',
                        'type' => 'string',
                        'required' => true,
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
                'zip' =>
                    array(
                        'title' => 'Zip',
                        'name' => 'zip',
                        'type' => 'string',
                        'required' => true,
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
                'country' =>
                    array(
                        'title' => 'Country',
                        'name' => 'country',
                        'type' => 'string',
                        'required' => true,
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
                'phone' =>
                    array(
                        'title' => 'Phone',
                        'name' => 'phone',
                        'type' => 'string',
                        'required' => true,
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
                'name' =>
                    array(
                        'title' => 'Name',
                        'name' => 'name',
                        'type' => 'string',
                        'required' => true,
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
                'surname' =>
                    array(
                        'title' => 'Surname',
                        'name' => 'surname',
                        'type' => 'string',
                        'required' => true,
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
                'street' =>
                    array(
                        'title' => 'Street',
                        'name' => 'street',
                        'type' => 'string',
                        'required' => true,
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
                'street_no' =>
                    array(
                        'title' => 'Street_no',
                        'name' => 'street_no',
                        'type' => 'string',
                        'required' => true,
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
                'gender' =>
                    array(
                        'title' => 'Gender',
                        'name' => 'gender',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'inarray',
                                        'params' =>
                                            array(
                                                0 => 'M',
                                                1 => 'F',
                                            ),
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'data' =>
                    array(
                        'title' => 'Data',
                        'name' => 'data',
                        'type' => 'string',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'msg' =>
                    array(
                        'title' => 'Msg',
                        'name' => 'msg',
                        'type' => 'string',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'value' =>
                    array(
                        'title' => 'Value',
                        'name' => 'value',
                        'type' => 'float',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'booked' =>
                    array(
                        'title' => 'Booked',
                        'name' => 'booked',
                        'type' => 'boolean',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 1,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'created_date' =>
                    array(
                        'title' => 'Created_date',
                        'name' => 'created_date',
                        'type' => 'datetime',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'modified_date' =>
                    array(
                        'title' => 'Modified_date',
                        'name' => 'modified_date',
                        'type' => 'datetime',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'booking_data' =>
                    array(
                        'title' => 'Booking_data',
                        'name' => 'booking_data',
                        'type' => 'float',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'booking_type' =>
                    array(
                        'title' => 'Booking_type',
                        'name' => 'booking_type',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'inarray',
                                        'params' =>
                                            array(
                                                0 => 'booking',
                                                1 => 'booking-ibe',
                                                2 => 'request',
                                                3 => 'coach',
                                                4 => 'coupon-ibe',
                                                5 => 'catalog',
                                            ),
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'partner_code' =>
                    array(
                        'title' => 'Partner_code',
                        'name' => 'partner_code',
                        'type' => 'string',
                        'required' => true,
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
                'ibe_code' =>
                    array(
                        'title' => 'Ibe_code',
                        'name' => 'ibe_code',
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
                'ibe_agency' =>
                    array(
                        'title' => 'Ibe_agency',
                        'name' => 'ibe_agency',
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
                'attachments' =>
                    array(
                        'title' => 'Attachments',
                        'name' => 'attachments',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'id_wp_user' =>
                    array(
                        'title' => 'Id_wp_user',
                        'name' => 'id_wp_user',
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
                'tt_reference' =>
                    array(
                        'title' => 'Tt_reference',
                        'name' => 'tt_reference',
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
            ),
    );
}
