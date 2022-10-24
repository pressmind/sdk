<?php

namespace Pressmind\ORM\Object\Touristic\Insurance;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\Insurance;
use Pressmind\Registry;

/**
 * Class Package
 * @property string $id
 * @property string $name
 * @property string $code_ibe
 * @property PriceTable[] $price_tables
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
            'table_name' => 'pmt2core_touristic_insurance_price_table_packages',
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
            'price_tables' => [
                'name' => 'price_tables',
                'title' => 'price_tables',
                'type' => 'relation',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
                'relation' => [
                    'type' => 'ManyToMany',
                    'class' => PriceTable::class,
                    'relation_table' => 'pmt2core_touristic_insurance_price_table_to_package',
                    'relation_class' => PriceTableToPackage::class,
                    'related_id' => 'id_price_table_package',
                    'target_id' => 'id_price_table',
                ],
            ],
        ]
    );

    /**
     * @param $id_insurance_group
     * @return ValidPriceTablePackage[]
     */
    public function getValidPackagesByInsuranceGroup($id_insurance_group)
    {
        $db = Registry::getInstance()->get('db');
        $query = 'select id_price_table_package as id, items, code_ibe, name from (
                    select p.id_price_table_package,
                           group_concat(p.id_price_table order by p.id_price_table ASC) as items,
                           count(p.id_price_table) as count,
                           tp.code_ibe, tp.name
                    from pmt2core_touristic_insurance_price_table_to_package p
                             left join pmt2core_touristic_insurance_price_table_packages tp on (p.id_price_table_package = tp.id)
                    where p.id_price_table in (
                        select t.id
                        from pmt2core_touristic_insurance_to_group g
                                left join pmt2core_touristic_insurance_to_price_table tp on tp.id_insurance = g.id_insurance
                                left join pmt2core_touristic_insurances_price_tables t on tp.id_insurance = g.id_insurance
                        where g.id_insurance_group = "'.$id_insurance_group.'"
                    )
                    group by id_price_table_package
                    ) q2 where count > 1';

        $output = [];
        foreach($db->fetchAll($query) as $row){
            $row->items = explode(',', $row->items);
            $output[] = $row;
        }
        return $output;
    }
}
