<?php


namespace Pressmind\ORM\Object;

/**
 * Class AgencyToMediaObject
 * @package Pressmind\ORM\Object
 * @property integer $id
 * @property integer $id_agency
 * @property integer $id_media_object
 */
class AgencyToMediaObject extends AbstractObject
{

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_agency_to_media_object',
            'primary_key' => 'id',
        ],
        'properties' =>
            [
                'id' => [
                    'title' => 'ID',
                    'name' => 'id',
                    'type' => 'integer',
                    'required' => true,
                    'validators' => [
                        [
                            'name' => 'maxlength',
                            'params' => 22,
                        ],
                        [
                            'name' => 'unsigned',
                            'params' => null,
                        ]
                    ],
                    'filters' => NULL,
                ],
                'id_agency' => [
                    'title' => 'Agency ID',
                    'name' => 'id_agency',
                    'type' => 'integer',
                    'required' => true,
                    'validators' => [
                        [
                            'name' => 'maxlength',
                            'params' => 22,
                        ],
                        [
                            'name' => 'unsigned',
                            'params' => null,
                        ]
                    ],
                    'filters' => NULL,
                    'index' => [
                        'id_agency' => 'index'
                    ]
                ],
                'id_media_object' => [
                    'title' => 'Media Object ID',
                    'name' => 'id_media_object',
                    'type' => 'integer',
                    'required' => false,
                    'validators' => [
                        [
                            'name' => 'maxlength',
                            'params' => 22,
                        ],
                        [
                            'name' => 'unsigned',
                            'params' => null,
                        ]
                    ],
                    'filters' => NULL,
                    'index' => [
                        'id_media_object' => 'index'
                    ]
                ]
            ]
    );
}
