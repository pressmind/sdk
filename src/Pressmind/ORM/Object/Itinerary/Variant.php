<?php


namespace Pressmind\ORM\Object\Itinerary;


use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Itinerary\Step;

/**
 * Class Variant
 * @package Pressmind\ORM\Object\Itinerary
 * @property integer $id
 * @property integer $id_booking_package
 * @property string $booking_package_name
 * @property float $booking_package_duration
 * @property float $variant_duration
 * @property string $booking_package_variant_code
 * @property string $description
 * @property float $offset
 * @property string $type
 * @property string $code
 * @property Step[] $steps
 */
class Variant extends AbstractObject
{
    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_itinerary_variants',
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
            'id_booking_package' => [
                'title' => 'id_booking_package',
                'name' => 'id_booking_package',
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
                    'id_booking_package' => 'index'
                ],
                'filters' => null
            ],
            'booking_package_name' => [
                'title' => 'booking_package_name',
                'name' => 'booking_package_name',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'booking_package_duration' => [
                'title' => 'booking_package_duration',
                'name' => 'booking_package_duration',
                'type' => 'float',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'variant_duration' => [
                'title' => 'variant_duration',
                'name' => 'variant_duration',
                'type' => 'float',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'booking_package_variant_code' => [
                'title' => 'booking_package_variant_code',
                'name' => 'booking_package_variant_code',
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
            ],
            'offset' => [
                'title' => 'offset',
                'name' => 'offset',
                'type' => 'float',
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
            'code' => [
                'title' => 'code',
                'name' => 'code',
                'type' => 'string',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
            'steps' => [
                'title' => 'steps',
                'name' => 'steps',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_variant',
                    'class' => Step::class,
                    'filters' => null,
                    'on_save_related_properties' => ['id' => 'id_variant']
                ],
                'required' => false,
                'validators' => null,
                'filters' => null
            ]
        ]
    ];
}
