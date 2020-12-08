<?php


namespace Pressmind\ORM\Object\Itinerary\Variant\Step\Section;

use Pressmind\ORM\Object\AbstractObject;

/**
 * Class Content
 * @package Pressmind\ORM\Object\Itinerary\Variant\Step\Section
 * @property $id
 * @property $id_section
 * @property string $headline
 * @property string $description
 */
class Content extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => 'Content',
            'namespace' => 'Pressmind\ORM\Object\Itinerary\Variant\Step\Section'
        ],
        'database' => [
            'table_name' => 'pmt2core_itinerary_step_section_contents',
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
            'id_section' => [
                'title' => 'id_section',
                'name' => 'id_section',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'headline' => [
                'title' => 'headline',
                'name' => 'headline',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'description' => [
                'title' => 'description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];
}
