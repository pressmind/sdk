<?php


namespace Pressmind\ORM\Object\Touristic\Insurance;


use Pressmind\DB\Adapter\Pdo;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;

/**
 * Class InsuranceToPriceTable
 * @package Pressmind\ORM\Object\Touristic\Insurance
 * @property string $id_price_table
 * @property string $id_insurance
 */
class InsuranceToPriceTable extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_insurance_to_price_table',
            'primary_key' => [
                'id_price_table',
                'id_insurance'
            ],
        ],
        'properties' =>
            [
                'id_price_table' => [
                    'title' => 'id_price_table',
                    'name' => 'id_price_table',
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
                        'id_price_table' => 'index'
                    ]
                ],
                'id_insurance' => [
                    'title' => 'id_insurance',
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
                ]
            ]
    );

    public function delete($deleteRelations = false)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $db->execute('DELETE FROM ' . $this->getDbTableName() . ' WHERE id_insurance = ? AND id_price_table = ?', [$this->id_insurance, $this->id_price_table]);
        //parent::delete($deleteRelations); // TODO: Change the autogenerated stub
    }

}
