<?php


namespace Pressmind\ORM\Object\Touristic\Insurance;


use Pressmind\ORM\Object\AbstractObject;

/**
 * Class InsuranceToInsurance
 * @package Pressmind\ORM\Object\Touristic\Insurance
 * @property string $id_insurance
 * @property string $id_sub_insurance
 */
class InsuranceToInsurance extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_insurance_to_insurance',
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
                'id_sub_insurance' => [
                    'title' => 'Sub Insurance ID',
                    'name' => 'id_sub_insurance',
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
                        'id_sub_insurance' => 'index'
                    ]
                ]
            ]
    );
}
