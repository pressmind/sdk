<?php

namespace Pressmind\ORM\Object;

/**
 * Class Airline
 * @property integer $id
 * @property string $name
 * @property string $alias
 * @property string $iata
 * @property string $icao
 * @property string $callsign
 * @property string $country
 * @property string $active
 */
class Airline extends AbstractObject
{
    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'Airline',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_airlines',
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
                'alias' =>
                    array(
                        'title' => 'Alias',
                        'name' => 'alias',
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
                'iata' =>
                    array(
                        'title' => 'Iata',
                        'name' => 'iata',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 2,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'icao' =>
                    array(
                        'title' => 'Icao',
                        'name' => 'icao',
                        'type' => 'string',
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
                'callsign' =>
                    array(
                        'title' => 'Callsign',
                        'name' => 'callsign',
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
                'country' =>
                    array(
                        'title' => 'Country',
                        'name' => 'country',
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
                'active' =>
                    array(
                        'title' => 'Active',
                        'name' => 'active',
                        'type' => 'boolean',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
            ),
    );
}
