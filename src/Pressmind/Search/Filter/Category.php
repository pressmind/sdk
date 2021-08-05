<?php


namespace Pressmind\Search\Filter;


use Exception;
use Pressmind\DB\Adapter\Pdo;
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

    public function __construct($tree_id = null, $search = null, $var_name = null)
    {
        $this->_search = $search;
        $this->_tree_id = $tree_id;
        $this->_var_name = $var_name;
    }

    public function getSearch()
    {
        return $this->_search;
    }

    /**
     * @return Item[]
     * @throws Exception
     */
    public function getResult() {
        $results = $this->_search->getResults(false, true);
        $ids = [];
        foreach ($results as $result) {
            $ids[] = $result->id;
        }
        /** @var Categorytree[] $all_items */
        $all_items = $this->getAllItemsForIds($ids);
        $list = [];
        foreach ($all_items as $item) {
            if($item->item->id_parent == null) {
                $list[$item->item->id] = $item->item;
            }
        }
        usort($list, "self::sortItems");
        return $list;
    }

    /**
     * @param Item $a
     * @param Item $b
     * @return mixed
     */
    private static function sortItems($a, $b) {
        return $a->sort - $b->sort;
    }

    private function getAllItemsForIds($ids) {
        //return Categorytree::listAll(['id_tree' => $this->_tree_id, 'id_media_object' => ['IN', implode(",", $ids)]]);
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $query = "SELECT * FROM pmt2core_media_object_tree_items where id_tree = " . $this->_tree_id;
        if(!is_null($this->_var_name)) {
            $query .= " AND var_name = '" . $this->_var_name . "'";
        }
        $query .= " AND (id_media_object in (" . implode(",", $ids) . ") OR id_media_object in (SELECT id_media_object_link from pmt2core_media_object_object_links WHERE id_media_object in (" . implode(",", $ids) . ")))";
        return $db->fetchAll($query, null, Categorytree::class);
    }

    public static function create($tree_id, $search, $var_name = null) {
        return new self($tree_id, $search, $var_name);
    }

    public function setSearch($search) {
        $this->_search = $search;
    }

    public function setConfig($config) {
        $this->_tree_id = $config->tree_id;
    }
}
