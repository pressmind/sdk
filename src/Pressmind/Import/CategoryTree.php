<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\CategoryTree\Item;
use Pressmind\REST\Client;

class CategoryTree extends AbstractImport implements ImportInterface
{

    /**
     * @var array
     */
    private $_ids = [];

    private $_imported_items = [];

    /**
     * CategoryTree constructor.
     * @param array $ids
     */
    public function __construct($ids)
    {
        $this->_ids = $ids;
    }

    /**
     * @param array $ids
     * @throws Exception
     */
    public function import()
    {
        global $_RUNTIME_IMPORTED_CATEGORY_IDS;
        if(!is_array($_RUNTIME_IMPORTED_CATEGORY_IDS)){
            $_RUNTIME_IMPORTED_CATEGORY_IDS = [];
        }
        $ids = $this->_ids;
        if(empty($this->_ids) && !is_array($this->_ids)){
            $ids = [null];
        }
        $request = [];
        foreach($ids as $category_id){
            if(!in_array($category_id, $_RUNTIME_IMPORTED_CATEGORY_IDS)){
                $request[] = $category_id;
            }
        }
        if(empty($request)){
           return true;
        }
        $_RUNTIME_IMPORTED_CATEGORY_IDS = array_merge($_RUNTIME_IMPORTED_CATEGORY_IDS, $request);
        $client = new Client();
        $response = $client->sendRequest('Category', 'all', empty(array_filter($this->_ids)) ? [] : ['ids' => implode(',', $this->_ids)]);
        $this->_log[] = ' Importer::_importCategoryTrees(): REST request done';
        $this->_checkApiResponse($response);
        if (is_a($response, 'stdClass') && isset($response->result) && is_array($response->result)) {
            foreach ($response->result as $tree_info) {
                $_imported_items = [];
                if (is_a($tree_info, 'stdClass') && isset($tree_info->tree) && !empty($tree_info->tree)) {
                    $this->_log[] = 'Importer::_importCategoryTrees(): Importing tree ID ' . $tree_info->id;
                    $tree = new \Pressmind\ORM\Object\CategoryTree();
                    $tree->id = $tree_info->id;
                    $tree->name = $tree_info->name;
                    try {
                        $tree->replace();
                    } catch (Exception $e) {
                        $this->_log[] = 'Importer::_importCategoryTrees(): Error importing tree ID ' . $tree->id . ': ' . $e->getMessage();
                        $this->_errors[] = 'Importer::_importCategoryTrees(): Error importing tree ID ' . $tree->id . ': ' . $e->getMessage();
                    }
                    $this->_log[] = 'Importer::_importCategoryTrees(): Tree import done';
                    if (isset($tree_info->tree->item)) {
                        $this->_log[] = ' Importer::_importCategoryTrees(): Importing tree items ' . $tree_info->id;
                        $this->_iterateCategoryTreeItems($tree_info->id, $tree_info->tree->item);
                    }
                    $this->remove_orphans($tree_info->id);
                    $this->_log[] = 'Importer::_importCategoryTrees(): Importing tree items done';
                }
            }
        }
    }

    /**
     * @param $id_tree
     * @param $items
     * @param null $parent
     * @throws Exception
     */
    private function _iterateCategoryTreeItems($id_tree, $items, $parent = null) {
        $sort = 0;
        foreach ($items as $item) {
            $this->_log[] = ' Importer::_iterateCategoryTreeItems(): Importing tree item ID ' . $item->id;
            $sort++;
            $category_tree_item = new Item();
            $category_tree_item->id = $item->id;
            $category_tree_item->name = $item->name;
            $category_tree_item->id_parent = $parent;
            $category_tree_item->id_tree = $id_tree;
            $category_tree_item->code = empty($item->code) ? null : $item->code;
            $category_tree_item->sort = $sort;
            try {
                $category_tree_item->replace();
            } catch (Exception $e) {
                $this->_log[] = ' Importer::_iterateCategoryTreeItems(): Error importing tree item ID ' . $item->id . ': '. $e->getMessage();
                $this->_errors[] = 'Importer::_iterateCategoryTreeItems(): Error importing tree item ID ' . $item->id . ': '. $e->getMessage();
            }
            $this->_imported_items [] = $item->id;
            if (isset($item->item)) {
                $this->_iterateCategoryTreeItems($id_tree, $item->item, $item->id);
            }
        }
    }

    private function remove_orphans($id_tree){
        $Orphans = Item::listAll('id_tree = '.$id_tree.' AND id NOT IN ("'.implode('","', $this->_imported_items).'")');
        foreach($Orphans as $Orphan){
            $Orphan->delete();
        }
    }
}
