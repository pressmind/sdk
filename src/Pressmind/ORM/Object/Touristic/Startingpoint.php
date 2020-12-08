<?php

namespace Pressmind\ORM\Object\Touristic;

use Pressmind\ORM\Object\AbstractObject;
use \Pressmind\ORM\Object\Touristic\Startingpoint\Option;

/**
 * Class TouristicStartingpoint
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $text
 * @property integer $logic
 * @property Option[] $options
 */
class Startingpoint extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_check_variables_for_existence = false;

    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'TouristicStartingpoint',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_startingpoints',
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
                'text' =>
                    array(
                        'title' => 'Text',
                        'name' => 'text',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'logic' =>
                    array(
                        'title' => 'Logic',
                        'name' => 'logic',
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
                'options' => array(
                    'title' => 'Options',
                    'name' => 'options',
                    'type' => 'relation',
                    'relation' => array(
                        'type' => 'hasMany',
                        'related_id' => 'id_startingpoint',
                        'class' => '\\Pressmind\\ORM\\Object\\Touristic\\Startingpoint\\Option'
                    ),
                    'required' => false,
                    'validators' => null,
                    'filters' => null
                ),
            ),
    );
}
