<?php

namespace Pressmind\ORM\Object\Touristic\Startingpoint\Option;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class ValidityArea
 * @TODO unused???
 * @property string $id_startingpoint_option
 * @property string $zip
 * @property integer $id_startingpoint
 */
class ValidityArea extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_startingpoint_options_validity_areas',
            'primary_key' => NULL,
        ],
        'properties' =>
            array(
                'id_startingpoint_option' =>
                    array(
                        'title' => 'Id_startingpoint_option',
                        'name' => 'id_startingpoint_option',
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
                        'required' => true,
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
                'id_startingpoint' =>
                    array(
                        'title' => 'Id_startingpoint',
                        'name' => 'id_startingpoint',
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
            ),
    );
}
