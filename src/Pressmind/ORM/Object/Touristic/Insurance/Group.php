<?php

namespace Pressmind\ORM\Object\Touristic\Insurance;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\Insurance;

/**
 * Class TouristicInsuranceGroup
 * @property string $id
 * @property string $name
 * @property string $description
 * @property integer $active
 * @property Insurance[] $insurances
 */
class Group extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'TouristicInsuranceGroup',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_insurance_groups',
                'primary_key' => 'id',
            ),
        'properties' =>
            array(
                'id' =>
                    array(
                        'title' => 'Id',
                        'name' => 'id',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 32,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'name' =>
                    array(
                        'title' => 'Name',
                        'name' => 'name',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'description' =>
                    array(
                        'title' => 'Description',
                        'name' => 'description',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'active' =>
                    array(
                        'title' => 'Active',
                        'name' => 'active',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 1,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'insurances' => array (
                    'name' => 'insurances',
                    'title' => 'insurances',
                    'type' => 'relation',
                    'required' => false,
                    'validators' => NULL,
                    'filters' => NULL,
                    'relation' =>
                        array (
                            'type' => 'ManyToMany',
                            'class' => Insurance::class,
                            'relation_table' => 'pmt2core_touristic_insurance_to_group',
                            'related_id' => 'id_insurance_group',
                            'target_id' => 'id_insurance'
                        ),
                )
            ),
    );
}
