<?php


namespace Pressmind\Import;


use Exception;
use Gettext\Generator\MoGenerator;
use Gettext\Translation;
use Pressmind\HelperFunctions;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\CategoryTree\Item;
use Pressmind\ORM\Object\ImportHash;
use Pressmind\Registry;
use Gettext\Translations;

class CategoryTree extends AbstractImport implements ImportInterface
{

    /**
     * @var array
     */
    private $_ids = [];

    /**
     * When true, hash is ignored and every tree is fully imported (e.g. fullimport mode).
     * @var bool
     */
    private $_force = false;

    private $_imported_items = [];

    private $_linked_media_objects = [];

    /**
     * @var array Config cached for performance during recursive iterations
     */
    private $_config = null;

    /**
     * CategoryTree constructor.
     * @param array $ids Category tree IDs to import
     * @param bool $force If true, skip hash check and always import (e.g. fullimport)
     */
    public function __construct($ids, $force = false)
    {
        parent::__construct();
        $this->_ids = $ids;
        $this->_force = (bool) $force;
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
        $client = $this->getClient();
        $response = $client->sendRequest('Category', 'all', empty(array_filter($this->_ids)) ? [] : ['ids' => implode(',', $this->_ids)]);
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importCategoryTrees(): REST request done';
        $this->_checkApiResponse($response);
        $any_tree_imported = false;
        if (is_a($response, 'stdClass') && isset($response->result) && is_array($response->result)) {
            foreach ($response->result as $tree_info) {
                if (!is_a($tree_info, 'stdClass') || !isset($tree_info->tree) || empty($tree_info->tree)) {
                    continue;
                }
                $tree_hash = hash('sha256', json_encode($tree_info));
                if (!$this->_force && !ImportHash::hasChanged((string) $tree_info->id, 'category_tree', $tree_hash)) {
                    $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importCategoryTrees(): Tree ID ' . $tree_info->id . ' hash unchanged, skipping';
                    $this->_linked_media_objects = array_merge($this->_linked_media_objects, $this->_extractLinkIdsFromTreeItems($tree_info->tree->item ?? []));
                    continue;
                }
                $any_tree_imported = true;
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
                    $this->_imported_items = [];
                    $items_rows = [];
                    $translations_rows = [];
                    $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importCategoryTrees(): Importing tree items ' . $tree_info->id;
                    $this->_iterateCategoryTreeItems($tree_info->id, $tree_info->tree->item, $items_rows, $translations_rows);
                    $this->_batchInsertTreeItems($items_rows, $translations_rows);
                }
                $this->remove_orphans($tree_info->id);
                ImportHash::store((string) $tree_info->id, 'category_tree', $tree_hash);
                $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_importCategoryTrees(): Importing tree items done';
            }
            if ($any_tree_imported) {
                $this->createGetTextFiles();
            }
        }
        if (!empty($this->_config['data']['disable_category_tree_linked_import'])) {
            $this->_linked_media_objects = [];
        }
        $filtered_linked_media_objects = $this->_filterExistingMediaObjects($this->_linked_media_objects);
        return [
            'linked_media_object_ids' => $filtered_linked_media_objects,
        ];
    }

    /**
     * Recursively extract linked media object IDs from tree item structure (for hash-skip path).
     *
     * @param array $items Tree items (each may have ->links and ->item)
     * @return array List of linked media object IDs
     */
    private function _extractLinkIdsFromTreeItems($items)
    {
        $ids = [];
        if (!is_array($items)) {
            return $ids;
        }
        foreach ($items as $item) {
            if (!empty($item->links)) {
                $link_ids = array_map('intval', array_filter(explode(',', $item->links)));
                $ids = array_merge($ids, $link_ids);
            }
            if (isset($item->item) && is_array($item->item)) {
                $ids = array_merge($ids, $this->_extractLinkIdsFromTreeItems($item->item));
            }
        }
        return $ids;
    }

    /**
     * Collect tree items and translations into batch arrays (no DB writes).
     *
     * @param int $id_tree
     * @param array $items
     * @param array $items_rows Output rows for pmt2core_category_tree_items
     * @param array $translations_rows Output rows for pmt2core_category_tree_item_translation
     * @param int|null $parent
     * @param int $sort Current sort index
     * @return int Next sort index
     */
    private function _iterateCategoryTreeItems($id_tree, $items, &$items_rows, &$translations_rows, $parent = null, $sort = 0)
    {
        $allowed_languages = $this->_config['data']['languages']['allowed'];
        foreach ($items as $item) {
            $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_iterateCategoryTreeItems(): Collecting tree item ID ' . $item->id;
            $sort++;
            $links = !empty($item->links) ? $item->links : null;
            if (!empty($item->links)) {
                $link_ids = array_map('intval', array_filter(explode(',', $item->links)));
                $this->_linked_media_objects = array_merge($this->_linked_media_objects, $link_ids);
            }
            $items_rows[] = [
                $item->id,
                $parent,
                $id_tree,
                $item->name ?? '',
                empty($item->code) ? null : $item->code,
                null,
                null,
                $links,
                $sort,
            ];
            if (!empty($allowed_languages) && is_array($allowed_languages)) {
                foreach ($allowed_languages as $language) {
                    if (isset($item->{$language})) {
                        $name = empty($item->{$language}) ? ($item->name ?? '') : $item->{$language};
                        if ($name !== '' && $language !== '' && $item->id !== '') {
                            $translations_rows[] = [$item->id, $id_tree, $name, $language];
                        }
                    }
                }
            }
            $this->_imported_items[] = $item->id;
            if (isset($item->item) && is_array($item->item)) {
                $sort = $this->_iterateCategoryTreeItems($id_tree, $item->item, $items_rows, $translations_rows, $item->id, $sort);
            }
        }
        return $sort;
    }

    /**
     * Batch insert items and translations (REPLACE INTO) in chunks.
     *
     * @param array $items_rows
     * @param array $translations_rows
     * @param int $chunk_size
     */
    private function _batchInsertTreeItems(array $items_rows, array $translations_rows, $chunk_size = 500)
    {
        $db = Registry::getInstance()->get('db');
        $items_columns = ['id', 'id_parent', 'id_tree', 'name', 'code', 'id_media_object', 'dynamic_values', 'links', 'sort'];
        $trans_columns = ['id', 'id_tree', 'name', 'language'];
        foreach (array_chunk($items_rows, $chunk_size) as $chunk) {
            $db->batchInsert('pmt2core_category_tree_items', $items_columns, $chunk, true);
        }
        foreach (array_chunk($translations_rows, $chunk_size) as $chunk) {
            $db->batchInsert('pmt2core_category_tree_item_translation', $trans_columns, $chunk, true);
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
     * Filter out MediaObject IDs that are already in the current import run (runtime queue).
     * IDs that exist in DB are still returned so importMediaObject() can hash-check and skip if unchanged.
     *
     * @param array $ids Array of MediaObject IDs (from tree links)
     * @return array IDs to pass to importer (runtime duplicates removed)
     */
    private function _filterExistingMediaObjects($ids)
    {
        if (empty($ids)) {
            return [];
        }

        $ids = array_unique(array_map('intval', $ids));
        $total_collected = count($ids);

        global $_RUNTIME_IMPORTED_IDS;

        $runtime_filtered = $ids;
        if (!empty($_RUNTIME_IMPORTED_IDS)) {
            $runtime_filtered = array_values(array_diff($ids, $_RUNTIME_IMPORTED_IDS));
            $skipped_runtime = $total_collected - count($runtime_filtered);
            if ($skipped_runtime > 0) {
                $this->_log[] = $this->_getElapsedTimeAndHeap() .
                    ' CategoryTree::_filterExistingMediaObjects(): Skipped ' .
                    $skipped_runtime . ' MediaObjects (already in runtime queue)';
            }
        }

        $this->_log[] = $this->_getElapsedTimeAndHeap() .
            ' CategoryTree::_filterExistingMediaObjects(): ' .
            count($runtime_filtered) . ' of ' . $total_collected . ' linked MediaObjects to check (hash-based import)';

        return $runtime_filtered;
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
