<?php


namespace Pressmind\ORM\Object;

/**
 * Class Search
 * @property integer $id
 * @property integer $id_media_object
 * @property string $var_name
 * @property string $language
 * @property string $fulltext_values
 */
class FulltextSearch extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_fulltext_search',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
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
            'id_media_object' => [
                'title' => 'Id_media_object',
                'name' => 'id_media_object',
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
                    'id_media_object' => 'index'
                ]
            ],
            'var_name' => [
                'title' => 'Variable Name',
                'name' => 'var_name',
                'type' => 'varchar',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'var_name' => 'index'
                ]
            ],
            'language' => [
                'title' => 'Variable Name',
                'name' => 'language',
                'type' => 'varchar',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'language' => 'index'
                ]
            ],
            'fulltext_values' => [
                'title' => 'Variable Name',
                'name' => 'fulltext_values',
                'type' => 'longtext',
                'required' => false,
                'filters' => NULL,
                'index' => [
                    'fulltext_values' => 'fulltext'
                ]
            ],
        ]
    ];
}
