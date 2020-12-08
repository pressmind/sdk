<?php
namespace Pressmind\ORM\Object\Touristic\Booking;

use Pressmind\ORM\Object\AbstractObject;
/**
 * Class TouristicBookingEarlybird
 * @property integer $id
 * @property integer $id_booking_package
 * @property integer $id_media_object
 * @property \DateTime $travel_date_from
 * @property \DateTime $travel_date_to
 * @property \DateTime $booking_date_from
 * @property \DateTime $booking_date_to
 * @property float $discount_value
 * @property string $type
 * @property integer $round
 * @property integer $early_payer
 */
class Earlybird extends AbstractObject
{
    protected $_definitions = array (
  'class' =>
  array (
    'name' => 'TouristicBookingEarlybird',
  ),
  'database' =>
  array (
    'table_name' => 'pmt2core_touristic_booking_earlybirds',
    'primary_key' => 'id',
  ),
  'properties' =>
  array (
    'id' =>
    array (
      'title' => 'Id',
      'name' => 'id',
      'type' => 'integer',
      'required' => true,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 22,
        ),
      ),
      'filters' => NULL,
    ),
    'id_booking_package' =>
    array (
      'title' => 'Id_booking_package',
      'name' => 'id_booking_package',
      'type' => 'integer',
      'required' => true,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 22,
        ),
      ),
      'filters' => NULL,
    ),
    'id_media_object' =>
    array (
      'title' => 'Id_media_object',
      'name' => 'id_media_object',
      'type' => 'integer',
      'required' => true,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 22,
        ),
      ),
      'filters' => NULL,
    ),
    'travel_date_from' =>
    array (
      'title' => 'Travel_date_from',
      'name' => 'travel_date_from',
      'type' => 'datetime',
      'required' => true,
      'validators' => NULL,
      'filters' => NULL,
    ),
    'travel_date_to' =>
    array (
      'title' => 'Travel_date_to',
      'name' => 'travel_date_to',
      'type' => 'datetime',
      'required' => true,
      'validators' => NULL,
      'filters' => NULL,
    ),
    'booking_date_from' =>
    array (
      'title' => 'Booking_date_from',
      'name' => 'booking_date_from',
      'type' => 'datetime',
      'required' => true,
      'validators' => NULL,
      'filters' => NULL,
    ),
    'booking_date_to' =>
    array (
      'title' => 'Booking_date_to',
      'name' => 'booking_date_to',
      'type' => 'datetime',
      'required' => true,
      'validators' => NULL,
      'filters' => NULL,
    ),
    'discount_value' =>
    array (
      'title' => 'Discount_value',
      'name' => 'discount_value',
      'type' => 'float',
      'required' => false,
      'validators' => NULL,
      'filters' => NULL,
    ),
    'type' =>
    array (
      'title' => 'Type',
      'name' => 'type',
      'type' => 'string',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 12,
        ),
      ),
      'filters' => NULL,
    ),
    'round' =>
    array (
      'title' => 'Round',
      'name' => 'round',
      'type' => 'integer',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 1,
        ),
      ),
      'filters' => NULL,
    ),
    'early_payer' =>
    array (
      'title' => 'Early_payer',
      'name' => 'early_payer',
      'type' => 'integer',
      'required' => false,
      'validators' =>
      array (
        0 =>
        array (
          'name' => 'maxlength',
          'params' => 1,
        ),
      ),
      'filters' => NULL,
    ),
  ),
);}
