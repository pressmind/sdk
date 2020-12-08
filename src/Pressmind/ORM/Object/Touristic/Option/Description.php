<?php

namespace Pressmind\ORM\Object\Touristic\Option;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class TouristicOptionDescription
 * @property string $id_booking_package
 * @property integer $id_media_object
 * @property string $type
 * @property string $name
 * @property string $text
 * @property integer $necessary
 */
class Description extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'Touristic\Option\Description',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_option_descriptions',
                'primary_key' => 'id_booking_package',
            ),
        'properties' =>
            array(
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
                                                0 => 'extra',
                                                1 => 'sightseeing',
                                                2 => 'ticket',
                                            ),
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
                'text' =>
                    array(
                        'title' => 'Text',
                        'name' => 'text',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'necessary' =>
                    array(
                        'title' => 'Necessary',
                        'name' => 'necessary',
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
            ),
    );
}
