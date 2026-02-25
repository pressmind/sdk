<?php


namespace Pressmind\ORM\Object\Touristic\Insurance;


use Pressmind\DB\Adapter\Pdo;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;

/**
 * Class InsuranceToInsurance
 * @package Pressmind\ORM\Object\Touristic\Insurance
 * @property string $id_insurance
 * @property string $id_additional_insurance
 * @property integer $order
 */
class InsuranceToInsurance extends AbstractObject
{
    //protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_insurance_to_insurance',
            'primary_key' => 'id',
        ],
        'properties' =>
            [
                'id' => [
                    'title' => 'ID',
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
                    'filters' => NULL,
                ],
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
                'id_additional_insurance' => [
                    'title' => 'Additional Insurance ID',
                    'name' => 'id_additional_insurance',
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
                        'id_additional_insurance' => 'index'
                    ]
                ],
                'order' => [
                    'title' => 'Order',
                    'name' => 'order',
                    'type' => 'integer',
                    'required' => false,
                    'validators' => [
                        [
                            'name' => 'maxlength',
                            'params' => 11,
                        ],
                        [
                            'name' => 'unsigned',
                            'params' => null,
                        ]
                    ],
                    'filters' => NULL,
                ]
            ]
    );

    public function delete($deleteRelations = false)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $db->execute('DELETE FROM ' . $this->getDbTableName() . ' WHERE id_insurance = ? AND id_additional_insurance = ?', [$this->id_insurance, $this->id_additional_insurance]);
    }
}
