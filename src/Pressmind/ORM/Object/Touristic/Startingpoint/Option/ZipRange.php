<?php

namespace Pressmind\ORM\Object\Touristic\Startingpoint\Option;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class TouristicStartingpointOptionsZipRange
 * @property string $id_zip_ranges
 * @property string $id_option
 * @property string $from
 * @property string $to
 */
class ZipRange extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'TouristicStartingpointOptionsZipRange',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_startingpoint_options_zip_ranges',
                'primary_key' => 'id_zip_ranges',
            ),
        'properties' =>
            array(
                'id_zip_ranges' =>
                    array(
                        'title' => 'Id_zip_ranges',
                        'name' => 'id_zip_ranges',
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
                'id_option' =>
                    array(
                        'title' => 'Id_option',
                        'name' => 'id_option',
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
                'from' =>
                    array(
                        'title' => 'From',
                        'name' => 'from',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 42,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'to' =>
                    array(
                        'title' => 'To',
                        'name' => 'to',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 42,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
            ),
    );
}
