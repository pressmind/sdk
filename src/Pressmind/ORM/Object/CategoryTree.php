<?php

namespace Pressmind\ORM\Object;

use Exception;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\ORM\Object\CategoryTree\Item;
use Pressmind\Registry;

/**
 * Class CategoryTree
 * @property integer $id
 * @property string $name
 * @property Item[] $items
 */
class CategoryTree extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_category_trees',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
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
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255
                    ]
                ],
                'filters' => NULL,
            ],
            'items' => [
                'title' => 'items',
                'name' => 'items',
                'type' => 'relation',
                'required' => false,
                'filters' => null,
                'validators' => null,
                'relation' => [
                    'type' => 'hasMany',
                    'class' => '\Pressmind\ORM\Object\CategoryTree\Item',
                    'related_id' => 'id_tree',
                    'filters' => [
                        'id_parent' => 'IS NULL'
                    ],
                    'order_columns' => [
                        'sort' => 'ASC'
                    ]
                ],
            ]
        ]
    ];

    /**
     * @TODO somehow implement this function -> should return the flat array of items as a taxonomy tree
     * @return array
     */
    public function itemsToTaxonomy() {
        $array = [];
        foreach ($this->items as $item) {
            $array[] = $item->toStdClass();
        }
        return $array;
    }

    //public function getChildren

    private function _groupArray($arr, $group, $preserveGroupKey = false, $preserveSubArrays = false) {
        $temp = array();
        foreach($arr as $key => $value) {
            $groupValue = $value[$group];
            if(!$preserveGroupKey)
            {
                unset($arr[$key][$group]);
            }
            if(!array_key_exists($groupValue, $temp)) {
                $temp[$groupValue] = array();
            }

            if(!$preserveSubArrays){
                $data = count($arr[$key]) == 1? array_pop($arr[$key]) : $arr[$key];
            } else {
                $data = $arr[$key];
            }
            $temp[$groupValue][] = $data;
        }
        return $temp;
    }

    /**
     * @param string|int $media_object_type
     * @param $variable_name
     * @param string $language
     * @return CategoryTree
     * @throws Exception
     */
    public static function findForMediaObjectType($media_object_type, $variable_name, $language = 'de')
    {
        $media_object_type_id = null;
        if(is_int($media_object_type)) {
            $media_object_type_id = $media_object_type;
        } else if (is_string($media_object_type)) {
            $conf = Registry::getInstance()->get('config');
            $inverted_media_type_index = array_flip($conf['data']['media_types']);
            $media_object_type_id = isset($inverted_media_type_index[$media_object_type]) ? $inverted_media_type_index[$media_object_type] : null;
        } else {
            throw new Exception('parameter $media_object_type has to be of type integer or string');
        }
        if(is_null($media_object_type_id)) {
            throw new Exception('media type id could not be set');
        }
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $sql = "
        select DISTINCT ti.id_tree as tree_id from 
        pmt2core_media_object_tree_items ti 
        inner join pmt2core_media_objects mo 
        ON ti.id_media_object = mo.id 
        WHERE mo.id_object_type = {$media_object_type_id} 
        AND ti.var_name = '{$variable_name}'
        AND ti.language = {$language}";
        $result = $db->fetchRow($sql);
        if(is_null($result)) {
            throw new Exception('Category tree could not be found');
        }
        $tree = new self($result->tree_id);
        return $tree;
    }
}
