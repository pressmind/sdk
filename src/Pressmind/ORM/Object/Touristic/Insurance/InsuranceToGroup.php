<?php


namespace Pressmind\ORM\Object\Touristic\Insurance;


use Pressmind\ORM\Object\AbstractObject;

/**
 * Class InsuranceToGroup
 * @package Pressmind\ORM\Object\Touristic\Insurance
 * @property string $id_insurance
 * @property string $id_insurance_group
 */
class InsuranceToGroup extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_insurance_to_group',
            'primary_key' => 'id_insurance',
        ],
        'properties' =>
            [
                'id_insurance' => [
                    'title' => 'Insurance ID',
                    'name' => 'id_insurance',
                    'type' => 'string',
                    'required' => true,
                    'validators' => [
                        [
                            'name' => 'maxlength',
                            'params' => 32,
                        ],
                    ],
                    'filters' => NULL,
                    'index' => [
                        'id_insurance' => 'index'
                    ]
                ],
                'id_insurance_group' => [
                    'title' => 'Group ID',
                    'name' => 'id_insurance_group',
                    'type' => 'string',
                    'required' => false,
                    'validators' => [
                       [
                           'name' => 'maxlength',
                           'params' => 32,
                       ],
                    ],
                    'filters' => NULL,
                    'index' => [
                        'id_insurance_group' => 'index'
                    ]
                ]
            ]
    );
}
