<?php


namespace Pressmind\ORM\Object\Itinerary;

use Pressmind\HelperFunctions;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Itinerary\Step\Board;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject;
use Pressmind\ORM\Object\Itinerary\Step\Geopoint;
use Pressmind\ORM\Object\Itinerary\Step\Port;
use Pressmind\ORM\Object\Itinerary\Step\Section;
use Pressmind\ORM\Object\Itinerary\Step\TextMediaObject;
use Pressmind\Registry;

/**
 * Class Step
 * @package Pressmind\ORM\Object\Itinerary
 * @property integer $id
 * @property integer $id_variant
 * @property integer $id_media_object
 * @property string $type
 * @property Section[] $sections
 * @property Board[] $board
 * @property Geopoint[] $geopoints
 * @property Port[] $ports
 * @property DocumentMediaObject[] $document_media_objects
 * @property TextMediaObject[] $text_media_objects
 */
class Step extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_itinerary_steps',
            'primary_key' => 'id'
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
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
                'filters' => null
            ],
            'id_variant' => [
                'title' => 'id_variant',
                'name' => 'id_variant',
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
                'index' => [
                    'id_variant' => 'index'
                ],
                'filters' => null
            ],
            'id_media_object' => [
                'title' => 'id_media_object',
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
                'index' => [
                    'id_media_object' => 'index'
                ],
                'filters' => null
            ],
            'type' => [
                'title' => 'type',
                'name' => 'type',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'sections' => [
                'title' => 'sections',
                'name' => 'sections',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_step',
                    'class' => Section::class,
                    'filters' => null,
                    'on_save_related_properties' => ['id' => 'id_step']
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'board' => [
                'title' => 'board',
                'name' => 'board',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_step',
                    'class' => Board::class,
                    'filters' => null,
                    'on_save_related_properties' => ['id' => 'id_step']
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'geopoints' => [
                'title' => 'geopoints',
                'name' => 'geopoints',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_step',
                    'class' => Geopoint::class,
                    'filters' => null,
                    'on_save_related_properties' => ['id' => 'id_step']
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'ports' => [
                'title' => 'ports',
                'name' => 'ports',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_step',
                    'class' => Port::class,
                    'filters' => null,
                    'on_save_related_properties' => ['id' => 'id_step']
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'document_media_objects' => [
                'title' => 'document_media_objects',
                'name' => 'document_media_objects',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_step',
                    'class' => DocumentMediaObject::class,
                    'filters' => null,
                    'on_save_related_properties' => ['id' => 'id_step']
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'text_media_objects' => [
                'title' => 'text_media_objects',
                'name' => 'text_media_objects',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_step',
                    'class' => TextMediaObject::class,
                    'filters' => null,
                    'on_save_related_properties' => ['id' => 'id_step']
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
        ]
    ];

    /**
     * @param null $language
     * @return Section
     */
    public function getSectionForLanguage($language = null) {
        if(is_null($language)) {
            $config = Registry::getInstance()->get('config');
            $language = $config['data']['languages']['default'];
        }
        return HelperFunctions::findObjectInArray($this->sections, 'language', $language);
    }

    /**
     * @param null $language
     * @return Section\Content
     */
    public function getContentForlanguage($language = null) {
        if(is_null($language)) {
            $config = Registry::getInstance()->get('config');
            $language = $config['data']['languages']['default'];
        }
        return $this->getSectionForLanguage($language)->content;
    }
}
