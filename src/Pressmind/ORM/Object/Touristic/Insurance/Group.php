<?php

namespace Pressmind\ORM\Object\Touristic\Insurance;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\Insurance;

/**
 * Class Group
 * @property string $id
 * @property string $name
 * @property string $description
 * @property boolean $active
 * @property string $mode
 * @property Insurance[] $insurances
 */
class Group extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_insurance_groups',
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
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'description' => [
                'title' => 'Description',
                'name' => 'description',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'active' => [
                'title' => 'Active',
                'name' => 'active',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'mode' => [
                'title' => 'Mode',
                'name' => 'mode',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'inarray',
                        'params' => ['single_selection', 'multi_selection', null, ''],
                    ],
                ],
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
                    'relation_table' => 'pmt2core_touristic_insurance_to_group',
                    'relation_class' => InsuranceToGroup::class,
                    'related_id' => 'id_insurance_group',
                    'target_id' => 'id_insurance',
                ],
            ],
        ]
    );
}
