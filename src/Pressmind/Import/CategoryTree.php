<?php


namespace Pressmind\Import;


use Exception;
use Gettext\Generator\MoGenerator;
use Gettext\Translation;
use Pressmind\HelperFunctions;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\CategoryTree\Item;
use Pressmind\Registry;
use Pressmind\REST\Client;
use Gettext\Translations;

class CategoryTree extends AbstractImport implements ImportInterface
{

    /**
     * @var array
     */
    private $_ids = [];

    private $_imported_items = [];

    private $_linked_media_objects = [];

    /**
     * @var array Config cached for performance during recursive iterations
     */
    private $_config = null;

    /**
     * CategoryTree constructor.
     * @param array $ids
     */
    public function __construct($ids)
    {
        parent::__construct();
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
        $this->_linked_media_objects = [];
        $this->_config = Registry::getInstance()->get('config');
        $client = new Client();
        $response = $client->sendRequest('Category', 'all', empty(array_filter($this->_ids)) ? [] : ['ids' => implode(',', $this->_ids)]);
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importCategoryTrees(): REST request done';
        $this->_checkApiResponse($response);
        if (is_a($response, 'stdClass') && isset($response->result) && is_array($response->result)) {
            foreach ($response->result as $tree_info) {
                if (is_a($tree_info, 'stdClass') && isset($tree_info->tree) && !empty($tree_info->tree)) {
                    $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importCategoryTrees(): Importing tree ID ' . $tree_info->id;
                    $tree = new \Pressmind\ORM\Object\CategoryTree();
                    $tree->id = $tree_info->id;
                    $tree->name = $tree_info->name;
                    try {
                        $tree->replace();
                    } catch (Exception $e) {
                        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importCategoryTrees(): Error importing tree ID ' . $tree->id . ': ' . $e->getMessage();
                        $this->_errors[] = 'Importer::_importCategoryTrees(): Error importing tree ID ' . $tree->id . ': ' . $e->getMessage();
                    }
                    $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importCategoryTrees(): Tree import done';
                    if (isset($tree_info->tree->item)) {
                        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importCategoryTrees(): Importing tree items ' . $tree_info->id;
                        $this->_iterateCategoryTreeItems($tree_info->id, $tree_info->tree->item);
                    }
                    $this->remove_orphans($tree_info->id);
                    $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importCategoryTrees(): Importing tree items done';
                }
            }
            $this->createGetTextFiles();
        }
        $filtered_linked_media_objects = $this->_filterExistingMediaObjects($this->_linked_media_objects);
        return [
            'linked_media_object_ids' => $filtered_linked_media_objects,
        ];
    }

    /**
     * @param $id_tree
     * @param $items
     * @param null $parent
     * @throws Exception
     */
    private function _iterateCategoryTreeItems($id_tree, $items, $parent = null) {
        $allowed_languages = $this->_config['data']['languages']['allowed'];
        $sort = 0;
        foreach ($items as $item) {
            $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_iterateCategoryTreeItems(): Importing tree item ID ' . $item->id;
            $sort++;
            $category_tree_item = new Item();
            $category_tree_item->id = $item->id;
            $category_tree_item->name = $item->name;
            $category_tree_item->id_parent = $parent;
            $category_tree_item->id_tree = $id_tree;
            $category_tree_item->code = empty($item->code) ? null : $item->code;
            $category_tree_item->sort = $sort;
            $category_tree_item->links = null;
            if (!empty($item->links)) {
                $category_tree_item->links = $item->links;
                $link_ids = array_map('intval', array_filter(explode(',', $item->links)));
                $this->_linked_media_objects = array_merge($this->_linked_media_objects, $link_ids);
            }
            if (!empty($allowed_languages) && is_array($allowed_languages)) {
                foreach ($allowed_languages as $language) {
                    if (isset($item->{$language})) {
                        $translation = new \Pressmind\ORM\Object\CategoryTree\Translation\Item();
                        $translation->id = $item->id;
                        $translation->id_tree = $id_tree;
                        $translation->name = empty($item->{$language}) ? $item->name : $item->{$language};
                        $translation->language = $language;
                        if (empty($translation->name) || empty($translation->language) || empty($translation->id)) {
                            continue;
                        }
                        $translation->replace();
                    }
                }
            }
            try {
                $category_tree_item->replace();
            } catch (Exception $e) {
                $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_iterateCategoryTreeItems(): Error importing tree item ID ' . $item->id . ': '. $e->getMessage();
                $this->_errors[] = 'Importer::_iterateCategoryTreeItems(): Error importing tree item ID ' . $item->id . ': '. $e->getMessage();
            }
            $this->_imported_items[] = $item->id;
            if (isset($item->item)) {
                $this->_iterateCategoryTreeItems($id_tree, $item->item, $item->id);
            }
        }
    }

    /**
     * Remove orphaned items that are no longer in the imported tree
     * Uses direct DELETE queries for better performance (Optimization #2)
     * @param int $id_tree
     */
    private function remove_orphans($id_tree)
    {
        $db = Registry::getInstance()->get('db');

        if (empty($this->_imported_items)) {
            $db->delete('pmt2core_category_tree_items', ['id_tree = ?', $id_tree]);
            $db->delete('pmt2core_category_tree_item_translation', ['id_tree = ?', $id_tree]);
            return;
        }

        // Build placeholders for the IN clause
        $placeholders = implode(',', array_fill(0, count($this->_imported_items), '?'));

        // Delete orphaned items directly with a single query
        $query = "DELETE FROM pmt2core_category_tree_items WHERE id_tree = ? AND id NOT IN ($placeholders)";
        $params = array_merge([$id_tree], $this->_imported_items);
        $db->execute($query, $params);

        // Delete orphaned translations directly with a single query
        $query = "DELETE FROM pmt2core_category_tree_item_translation WHERE id_tree = ? AND id NOT IN ($placeholders)";
        $db->execute($query, $params);
    }

    /**
     * Filter out MediaObject IDs that already exist in the database or are already imported in this run
     * Performs a single bulk query for all collected IDs (Optimization #1)
     * @param array $ids Array of MediaObject IDs to check
     * @return array Array of IDs that do NOT exist and are not already being imported
     */
    private function _filterExistingMediaObjects($ids)
    {
        if (empty($ids)) {
            return [];
        }

        // Remove duplicates first
        $ids = array_unique(array_map('intval', $ids));
        $total_collected = count($ids);

        global $_RUNTIME_IMPORTED_IDS;

        // Step 1: Filter out IDs already imported in this runtime
        $runtime_filtered = [];
        if (!empty($_RUNTIME_IMPORTED_IDS)) {
            $runtime_filtered = array_diff($ids, $_RUNTIME_IMPORTED_IDS);
            $skipped_runtime = $total_collected - count($runtime_filtered);
            if ($skipped_runtime > 0) {
                $this->_log[] = $this->_getElapsedTimeAndHeap() .
                    ' CategoryTree::_filterExistingMediaObjects(): Skipped ' .
                    $skipped_runtime . ' MediaObjects (already in runtime queue)';
            }
        } else {
            $runtime_filtered = $ids;
        }

        if (empty($runtime_filtered)) {
            return [];
        }

        // Step 2: Filter out IDs already in database (single bulk query)
        $db = Registry::getInstance()->get('db');
        $placeholders = implode(',', array_fill(0, count($runtime_filtered), '?'));
        $query = "SELECT id FROM pmt2core_media_objects WHERE id IN ($placeholders)";
        $existing = $db->fetchAll($query, array_values($runtime_filtered));

        $existing_ids = array_map('intval', array_column($existing, 'id'));
        $missing_ids = array_values(array_diff($runtime_filtered, $existing_ids));

        if (count($existing_ids) > 0) {
            $this->_log[] = $this->_getElapsedTimeAndHeap() .
                ' CategoryTree::_filterExistingMediaObjects(): Skipped ' .
                count($existing_ids) . ' MediaObjects (already in database)';
        }

        $this->_log[] = $this->_getElapsedTimeAndHeap() .
            ' CategoryTree::_filterExistingMediaObjects(): ' .
            count($missing_ids) . ' of ' . $total_collected . ' linked MediaObjects need import';

        return $missing_ids;
    }

    /**
     * Support for creating gettext machine object files (*.mo)
     * @return bool
     */
    public function createGetTextFiles()
    {
        $conf = Registry::getInstance()->get('config');
        $active = !empty($conf['data']['languages']['gettext']['active']);
        if($active === false){
            return true;
        }
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::createGetTextFiles(): Creating gettext (*.mo) files';
        $allowed_languages = $conf['data']['languages']['allowed'];
        $default_language = $conf['data']['languages']['default'];
        if(empty($conf['data']['languages']['gettext']['dir'])){
            $this->_errors[] = 'Importer::createGetTextFiles(): Error: No directory for gettext files defined in config, see config.data.languages.gettext.dir';
            return false;
        }
        $dir = rtrim($conf['data']['languages']['gettext']['dir'], '/');
        $dir = HelperFunctions::replaceConstantsFromConfig($dir);
        if(!is_dir($dir) && !@mkdir($dir, 0777, true)){
            $this->_errors[] = 'Importer::createGetTextFiles(): Could not create directory ' . $dir;
            return false;
        }
        try{
            foreach($allowed_languages as $language) {
                $Translations = Translations::create('categorytree');
                if($language === $default_language) {
                    $items = Item::listAll();
                    $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::createGetTextFiles(): Found ' . count($items) . ' items for default language ' . $default_language;
                }else{
                    $items = \Pressmind\ORM\Object\CategoryTree\Translation\Item::listAll('language = "'.$language.'"');
                    $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::createGetTextFiles(): Found ' . count($items) . ' items for language ' . $language;
                }
                if(count($items) == 0) {
                    $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::createGetTextFiles(): No items found for language ' . $language;
                    continue;
                }
                foreach ($items as $item) {
                    $Translation = Translation::create('', $item->id);
                    $Translation->translate($item->name);
                    $Translations->add($Translation);
                }
                $Generator = new MoGenerator();
                $file = $dir.'/categorytree-'.$language.'.mo';
                $Generator->generateFile($Translations, $file);
                $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::createGetTextFiles(): Wrote file ' . $file;
            }
        }catch(Exception $e){
            $this->_errors[] = 'Importer::createGetTextFiles(): Error: ' . $e->getMessage();
            return false;
        }
        return true;
    }
}
