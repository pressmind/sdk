<?php

namespace Pressmind\ORM\Object\Touristic;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class TouristicExtrasToMediaObject
 * @property integer $id
 * @property string $type
 * @property integer $id_type
 * @property integer $id_media_object
 * @property integer $from_id_media_object
 */
class ExtrasToMediaObject extends AbstractObject
{
    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'TouristicExtrasToMediaObject',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_extras_to_media_objects',
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
                'type' =>
                    array(
                        'title' => 'Type',
                        'name' => 'type',
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
                'id_type' =>
                    array(
                        'title' => 'Id_type',
                        'name' => 'id_type',
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
                'from_id_media_object' =>
                    array(
                        'title' => 'From_id_media_object',
                        'name' => 'from_id_media_object',
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
