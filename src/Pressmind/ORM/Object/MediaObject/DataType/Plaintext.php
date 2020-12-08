<?php

namespace Pressmind\ORM\Object\MediaObject\DataType;
/**
 * Class Plaintext
 * @package Pressmind\ORM\Object\MediaObject\DataType
 * @property string $value
 */
class Plaintext extends AbstractDataType
{
    protected $_definition = [
        'class' => [
            'name' => 'Plaintext',
            'namespace' => '\Pressmind\ORM\MediaObject\DataType',
        ],
        'properties' => [
            'value' => [
                'title' => 'value',
                'name' => 'value',
                'type' => 'string',
                'required' => false,
                'filters' => null,
                'validators' => null,
            ]
        ]

    ];
}
