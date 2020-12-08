<?php

namespace Pressmind\ORM\Object\Touristic\Startingpoint;

use Pressmind\ORM\Object\AbstractObject;
use \DateTime;
/**
 * Class TouristicStartingpointOption
 * @property string $id
 * @property string $id_startingpoint
 * @property string $zip
 * @property string $code
 * @property string $name
 * @property float $price
 * @property string $text
 * @property DateTime $start_time
 * @property integer $with_start_time
 * @property string $city
 * @property string $street
 * @property string $code_ibe
 * @property float $lat
 * @property float $lon
 * @property integer $entry
 * @property integer $exit
 * @property DateTime $exit_time
 * @property integer $exit_time_offset
 * @property integer $start_time_offset
 * @property integer $with_end_time
 * @property integer $with_exit_time
 * @property string $ibe_clients
 * @property boolean $is_pickup_service
 */
class Option extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'Option',
                'namespace' => '\Pressmind\ORM\Object\Touristic\Startingpoint'
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_startingpoint_options',
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
                'id_startingpoint' =>
                    array(
                        'title' => 'Id_startingpoint',
                        'name' => 'id_startingpoint',
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
                'zip' =>
                    array(
                        'title' => 'Zip',
                        'name' => 'zip',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 5,
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
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'price' =>
                    array(
                        'title' => 'Price',
                        'name' => 'price',
                        'type' => 'float',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'text' =>
                    array(
                        'title' => 'Text',
                        'name' => 'text',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'start_time' =>
                    array(
                        'title' => 'Start_time',
                        'name' => 'start_time',
                        'type' => 'time',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'with_start_time' =>
                    array(
                        'title' => 'With_start_time',
                        'name' => 'with_start_time',
                        'type' => 'integer',
                        'required' => false,
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
                'city' =>
                    array(
                        'title' => 'City',
                        'name' => 'city',
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
                'street' =>
                    array(
                        'title' => 'Street',
                        'name' => 'street',
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
                'lat' =>
                    array(
                        'title' => 'Lat',
                        'name' => 'lat',
                        'type' => 'float',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'lon' =>
                    array(
                        'title' => 'Lon',
                        'name' => 'lon',
                        'type' => 'float',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'entry' =>
                    array(
                        'title' => 'Entry',
                        'name' => 'entry',
                        'type' => 'integer',
                        'required' => false,
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
                'exit' =>
                    array(
                        'title' => 'Exit',
                        'name' => 'exit',
                        'type' => 'integer',
                        'required' => false,
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
                'exit_time' =>
                    array(
                        'title' => 'Exit_time',
                        'name' => 'exit_time',
                        'type' => 'datetime',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'exit_time_offset' =>
                    array(
                        'title' => 'Exit_time_offset',
                        'name' => 'exit_time_offset',
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
                'start_time_offset' =>
                    array(
                        'title' => 'Start_time_offset',
                        'name' => 'start_time_offset',
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
                'with_end_time' =>
                    array(
                        'title' => 'With_end_time',
                        'name' => 'with_end_time',
                        'type' => 'integer',
                        'required' => false,
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
                'with_exit_time' =>
                    array(
                        'title' => 'With_exit_time',
                        'name' => 'with_exit_time',
                        'type' => 'integer',
                        'required' => false,
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
                'ibe_clients' =>
                    array(
                        'title' => 'Ibe_clients',
                        'name' => 'ibe_clients',
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
                'is_pickup_service' => [
                    'title' => 'is_pickup_service',
                    'name' => 'is_pickup_service',
                    'type' => 'boolean',
                    'required' => false,
                    'validators' => null,
                    'filters' => null,
                ]
            ),
    );
}
