<?php


namespace Pressmind\ORM\Object\Touristic\Insurance;


use Pressmind\ORM\Object\AbstractObject;

class InsuranceToGroup extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'InsuranceToGroup',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_insurance_to_group',
                'primary_key' => 'id_insurance',
            ),
        'properties' =>
            array(
                'id_insurance' =>
                    array(
                        'title' => 'Insurance ID',
                        'name' => 'id_insurance',
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
                'id_insurance_group' =>
                    array(
                        'title' => 'Group ID',
                        'name' => 'id_insurance_group',
                        'type' => 'string',
                        'required' => false,
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
            )
    );
}
