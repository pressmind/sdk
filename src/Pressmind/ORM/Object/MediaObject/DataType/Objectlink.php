<?php

namespace Pressmind\ORM\Object\MediaObject\DataType;

use Exception;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject;

/**
 * Class Objectlink
 * @package Pressmind\ORM\Object\MediaObject\DataType
 * @property integer $id
 * @property integer $id_media_object
 * @property string $section_name
 * @property string $var_name
 * @property integer id_object_type
 * @property integer id_media_object_link
 * @property string $link_type
 */
class Objectlink extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Objectlink',
            'namespace' => '\Pressmind\ORM\MediaObject\DataType',
        ],
        'database' => [
            'table_name' => 'pmt2core_media_object_object_links',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'id_media_object' => [
                'title' => 'id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'section_name' => [
                'title' => 'section_name',
                'name' => 'section_name',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ],
            'language' => [
                'title' => 'language',
                'name' => 'language',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'var_name' => [
                'title' => 'var_name',
                'name' => 'var_name',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'id_media_object_link' => [
                'title' => 'id_media_object_link',
                'name' => 'id_media_object_link',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'id_object_type' => [
                'title' => 'id_object_type',
                'name' => 'id_object_type',
                'type' => 'integer',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
            'link_type' => [
                'title' => 'link_type',
                'name' => 'link_type',
                'type' => 'string',
                'required' => true,
                'filters' => null,
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => [
                            'image',
                            'objectlink',
                        ],
                    ]
                ],
            ],
        ]
    ];

    /**
     * @var MediaObject
     */
    private $_object = null;

    /**
     * @return MediaObject
     * @throws Exception
     */
    public function getObject()
    {
        if(is_null($this->_object)) {
            $this->_object = new MediaObject($this->id_media_object_link);
        }
        return $this->_object;
    }
}
