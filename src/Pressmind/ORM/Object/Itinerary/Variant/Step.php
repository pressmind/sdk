<?php


namespace Pressmind\ORM\Object\Itinerary\Variant;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Itinerary\Variant\Step\Board;
use Pressmind\ORM\Object\Itinerary\Variant\Step\DocumentMediaObject;
use Pressmind\ORM\Object\Itinerary\Variant\Step\Geopoint;
use Pressmind\ORM\Object\Itinerary\Variant\Step\Section;

/**
 * Class Step
 * @package Pressmind\ORM\Object\Itinerary
 * @property integer $id
 * @property integer $id_variant
 * @property string $type
 * @property Section[] $sections
 * @property Board[] $board
 * @property Geopoint[] $geopoints
 * @property DocumentMediaObject[] $document_media_objects
 */
class Step extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Step',
            'namespace' => 'Pressmind\ORM\Object\Itinerary\Variant'
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
                'validators' => null,
                'filters' => null
            ],
            'id_variant' => [
                'title' => 'id_variant',
                'name' => 'id_variant',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
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
            ]
        ]
    ];
}
