<?php


namespace Pressmind\Search\Filter;


use Exception;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\HelperFunctions;
use Pressmind\ORM\Object\CategoryTree\Item;
use Pressmind\ORM\Object\MediaObject\DataType\Categorytree;
use Pressmind\Registry;
use Pressmind\Search;

class Category implements FilterInterface
{

    /**
     * @var Search
     */
    private $_search;

    private $_tree_id;

    private $_var_name;

    private $_linked_object_search;

    private $_sql;

    private $_values;

    private $_cache_enabled;

    public function __construct($tree_id = null, $search = null, $var_name = null, $linked_object_search = false)
    {
        $config = Registry::getInstance()->get('config');
        $this->_search = $search;
        $this->_tree_id = $tree_id;
        $this->_var_name = $var_name;
        $this->_linked_object_search = $linked_object_search;
        $this->_cache_enabled = $config['cache']['enabled'] && in_array('SEARCH_FILTER', $config['cache']['types']);
    }

    public function getSearch()
    {
        return $this->_search;
    }

    /**
     * Possible values for $order_by: sort, name, code
     * @param string $order_by
     * @return Item[]
     * @throws Exception
     */
    public function getResult($order_by = 'sort') {
        $results = $this->_search->getResults(false, true);
        $ids = [];
        foreach ($results as $result) {
            $ids[] = $result->id;
        }
        return $this->buildTree($this->getAllItemsForIds($ids, $order_by));
    }

    private function buildTree(array $elements, $parentId = null) {
        $branch = array();

        foreach ($elements as $element) {
            if ($element->id_parent == $parentId) {
                $children = $this->buildTree($elements, $element->id);
                if ($children) {
                    $element->children = $children;
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }

    /**
     * @param Item $a
     * @param Item $b
     * @return mixed
     */
    private static function sortItemsSort($a, $b) {
        return $a->sort - $b->sort;
    }

    /**
     * @param Item $a
     * @param Item $b
     * @return mixed
     */
    private static function sortItemsName($a, $b) {
        return strcmp(HelperFunctions::replaceLatinSpecialChars($a->name), HelperFunctions::replaceLatinSpecialChars($b->name));
    }

    /**
     * @param Item $a
     * @param Item $b
     * @return mixed
     */
    private static function sortItemsCode($a, $b) {
        return strcmp(HelperFunctions::replaceLatinSpecialChars($a->code), HelperFunctions::replaceLatinSpecialChars($b->code));
    }

    /**
     * @param array $ids
     * @return array|void
     * @throws Exception
     */
    private function getAllItemsForIds($ids, $order_by) {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $this->_values = [
            'id_tree' => $this->_tree_id,
        ];
        $this->_sql = "SELECT pcti.* FROM pmt2core_media_object_tree_items pmoti INNER JOIN pmt2core_category_tree_items pcti ON pcti.id = pmoti.id_item where pmoti.id_tree = :id_tree";
        if(!is_null($this->_var_name)) {
            $this->_sql .= " AND pmoti.var_name = :var_name";
            $this->_values['var_name'] = $this->_var_name;
        }
        if($this->_linked_object_search == false) {
            $this->_sql .= " AND pmoti.id_media_object in (" . implode(',', $ids) . ")";
        } else {
            $this->_sql .= " AND pmoti.id_media_object in (SELECT id_media_object_link from pmt2core_media_object_object_links pmool WHERE pmool.id_media_object in (" . implode(',', $ids) . "))";
        }
        $this->_sql .= ' GROUP BY pcti.id ORDER BY pcti.' . $order_by;
        if($this->_cache_enabled) {
            $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            $key = 'SEARCH_FILTER_CATEGORY_' . md5($this->_sql . implode('', $this->_values));
            if($cache_adapter->exists($key)) {
                return json_decode($cache_adapter->get($key));
            } else {
                $info = [
                    'type' => 'SEARCH_FILTER',
                    'method' => 'updateCache',
                    'classname' => self::class,
                    'parameters' => [
                        'sql' => $this->_sql,
                        'values' => $this->_values
                    ]
                ];
                $data = $db->fetchAll($this->_sql, $this->_values);
                $cache_adapter->add($key, json_encode($data), $info);
                return $data;
            }
        } else {
            return $db->fetchAll($this->_sql, $this->_values);
        }
    }

    public static function create($tree_id, $search, $var_name = null, $linked_object_search = false) {
        return new self($tree_id, $search, $var_name, $linked_object_search);
    }

    public function setSearch($search) {
        $this->_search = $search;
    }

    public function setConfig($config) {
        $this->_tree_id = $config->tree_id;
    }
}
