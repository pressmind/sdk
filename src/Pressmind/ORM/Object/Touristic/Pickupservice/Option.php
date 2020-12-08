<?php

namespace Pressmind\ORM\Object\Touristic\Pickupservice;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class TouristicPickupserviceOption
 * @property string $id
 * @property string $id_pickupservice
 * @property integer $zip
 * @property string $code
 * @property string $name
 * @property float $price
 * @property float $distance
 * @property string $text
 */
class Option extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'TouristicPickupserviceOption',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_pickupservice_options',
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
                'id_pickupservice' =>
                    array(
                        'title' => 'Id_pickupservice',
                        'name' => 'id_pickupservice',
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
                        'validators' => NULL,
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
                'distance' =>
                    array(
                        'title' => 'Distance',
                        'name' => 'distance',
                        'type' => 'float',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'text' =>
                    array(
                        'title' => 'Text',
                        'name' => 'text',
                        'type' => 'string',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
            ),
    );
}
