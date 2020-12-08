<?php


namespace Pressmind\Search\Filter;


use Exception;
use Pressmind\ORM\Object\CategoryTree\Item;
use Pressmind\ORM\Object\MediaObject\DataType\Categorytree;
use Pressmind\Search;

class Category implements FilterInterface
{

    /**
     * @var Search
     */
    private $_search;

    private $_tree_id;

    public function __construct($tree_id = null, $search = null)
    {
        $this->_search = $search;
        $this->_tree_id = $tree_id;
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
        $results = $this->_search->getResults();
        $ids = [];
        foreach ($results as $result) {
            $ids[] = $result->getId();
        }
        /** @var Categorytree[] $all_items */
        $all_items = Categorytree::listAll(['id_tree' => $this->_tree_id, 'id_media_object' => ['IN', implode(",", $ids)]]);
        $list = [];
        foreach ($all_items as $item) {
            if($item->item->id_parent == null) {
                $list[$item->item->id] = $item->item;
            }
        }
        return $list;
    }

    public static function create($tree_id, $search) {
        return new self($tree_id, $search);
    }

    public function setSearch($search) {
        $this->_search = $search;
    }

    public function setConfig($config) {
        $this->_tree_id = $config->tree_id;
    }
}
