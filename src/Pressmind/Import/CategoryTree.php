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
            $this->createGetTextFiles();
        }
    }

    /**
     * @param $id_tree
     * @param $items
     * @param null $parent
     * @throws Exception
     */
    private function _iterateCategoryTreeItems($id_tree, $items, $parent = null) {
        $conf = Registry::getInstance()->get('config');
        $allowed_languages = $conf['data']['languages']['allowed'];
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
            if(!empty($allowed_languages) && is_array($allowed_languages)){
                foreach($allowed_languages as $language){
                    if(isset($item->{$language})){
                        $translation = new \Pressmind\ORM\Object\CategoryTree\Translation\Item();
                        $translation->id = $item->id;
                        $translation->name = empty($item->{$language}) ? $item->name : $item->{$language};
                        $translation->language = $language;
                        $translation->replace();
                    }
                }
            }
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
        $conf =  Registry::getInstance()->get('config');
        $db = Registry::getInstance()->get('db');
        $allowed_languages = $conf['data']['languages']['allowed'];
        $Orphans = Item::listAll('id_tree = '.$id_tree.' AND id NOT IN ("'.implode('","', $this->_imported_items).'")');
        foreach($Orphans as $Orphan){
            $Orphan->delete();
        }
        $Orphans = \Pressmind\ORM\Object\CategoryTree\Translation\Item::listAll('id NOT IN ("'.implode('","', $this->_imported_items).'")');
        foreach($Orphans as $Orphan){
            $db->delete('pmt2core_category_tree_item_translation', ["id = ?", $Orphan->id]);
        }
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
        $this->_log[] = 'Importer::createGetTextFiles(): Creating gettext (*.mo) files';
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
                    $this->_log[] = 'Importer::createGetTextFiles(): Found ' . count($items) . ' items for default language ' . $default_language;
                }else{
                    $items = \Pressmind\ORM\Object\CategoryTree\Translation\Item::listAll('language = "'.$language.'"');
                    $this->_log[] = 'Importer::createGetTextFiles(): Found ' . count($items) . ' items for language ' . $language;
                }
                if(count($items) == 0) {
                    $this->_log[] = 'Importer::createGetTextFiles(): No items found for language ' . $language;
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
                $this->_log[] = 'Importer::createGetTextFiles(): Wrote file ' . $file;
            }
        }catch(Exception $e){
            $this->_errors[] = 'Importer::createGetTextFiles(): Error: ' . $e->getMessage();
            return false;
        }
        return true;
    }
}
