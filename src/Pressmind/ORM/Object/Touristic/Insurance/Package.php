<?php

namespace Pressmind\ORM\Object\Touristic\Insurance;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\Insurance;

/**
 * Class Package
 * @property string $id
 * @property string $name
 * @property string $code_ibe
 * @property Insurance[] $insurances
 */
class Package extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_insurance_packages',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
            ],
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'filters' => NULL,
            ],
            'code_ibe' => [
                'title' => 'code_ibe',
                'name' => 'code_ibe',
                'type' => 'string',
                'required' => false,
                'filters' => NULL,
            ],
            'insurances' => [
                'name' => 'insurances',
                'title' => 'insurances',
                'type' => 'relation',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
                'relation' => [
                    'type' => 'ManyToMany',
                    'class' => Insurance::class,
                    'relation_table' => 'pmt2core_touristic_insurance_to_package',
                    'relation_class' => InsuranceToPackage::class,
                    'related_id' => 'id_insurance_package',
                    'target_id' => 'id_insurance',
                ],
            ],
        ]
    );
}
