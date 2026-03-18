<?php

namespace Pressmind\ORM\Object\Touristic\Insurance;

use Pressmind\DB\Adapter\Pdo;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;

/**
 * Class InsuranceToAlternate
 * @package Pressmind\ORM\Object\Touristic\Insurance
 * @property string $id_insurance
 * @property string $id_alternate_insurance
 * @property integer $order
 */
class InsuranceToAlternate extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_insurance_to_alternate',
            'primary_key' => [
                'id_insurance',
                'id_alternate_insurance',
            ],
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
                ],
                'id_alternate_insurance' => [
                    'title' => 'Alternate Insurance ID',
                    'name' => 'id_alternate_insurance',
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
                        ],
                    ],
                    'filters' => NULL,
                ],
            ],
    );

    public function delete($deleteRelations = false)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $db->execute(
            'DELETE FROM ' . $this->getDbTableName() . ' WHERE id_insurance = ? AND id_alternate_insurance = ?',
            [$this->id_insurance, $this->id_alternate_insurance]
        );
    }
}
