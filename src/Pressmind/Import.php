<?php

namespace Pressmind;

use Pressmind\DB\Adapter\Pdo;
use Pressmind\Import\Brand;
use Pressmind\Import\CategoryTree;
use Pressmind\Import\ImportInterface;
use Pressmind\Import\Itinerary;
use Pressmind\Import\MediaObjectData;
use Pressmind\Import\MediaObjectType;
use Pressmind\Import\MyContent;
use Pressmind\Import\Season;
use Pressmind\Import\StartingPointOptions;
use Pressmind\Import\TouristicData;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\REST\Client;
use \DirectoryIterator;
use \Exception;

/**
 * Class Importer
 * @package Pressmind
 */
class Import
{

    /**
     * @var Client
     */
    private $_client;

    /**
     * @var string
     */
    private $_tmp_import_folder = 'import_ids';

    /**
     * @var array
     */
    private $_log = [];

    /**
     * @var array
     */
    private $_errors = [];

    private $_import_type = null;

    /**
     * @var float
     */
    private $_start_time;

    /**
     * @var float
     */
    private $_overall_start_time;

    /**
     * @var array
     */
    private $_imported_ids = [];

    /**
     * Importer constructor.
     * @param string $importType
     * @throws Exception
     */
    public function __construct($importType = 'fullimport')
    {
        $this->_start_time = microtime(true);
        $this->_overall_start_time = microtime(true);
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::__construct()', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        $this->_client = new Client();
        $this->_import_type = $importType;
    }

    /**
     * @param integer|null $id_pool
     * @throws Exception
     */
    public function import($id_pool = null, $allowed_object_types = null)
    {
        $conf = Registry::getInstance()->get('config');
        if(is_null($allowed_object_types)) {
            $allowed_object_types = array_keys($conf['data']['media_types']);
            if(isset($conf['data']['primary_media_type_ids']) && !empty($conf['data']['primary_media_type_ids'])) {
                $allowed_object_types = $conf['data']['primary_media_type_ids'];
            }
        }
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::import()', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        foreach ($allowed_object_types as $allowed_object_type) {
            $allowed_visibilities = $conf['data']['media_types_allowed_visibilities'][$allowed_object_type];
            if(is_array($allowed_visibilities)) {
                foreach ($allowed_visibilities as $allowed_visibility) {
                    $params = [
                        'id_media_object_type' => $allowed_object_type,
                        'visibility' => $allowed_visibility
                    ];
                    if (!is_null($id_pool)) {
                        $params['id_pool'] = intval($id_pool);
                    }
                    $this->_importIds(0, $params);
                }
            }
        }
        $this->_importMediaObjectsFromFolder();
        $this->removeOrphans();
    }

    /**
     * @param int $startIndex
     * @param array $params
     * @param int $numItems
     * @throws Exception
     */
    private function _importIds($startIndex, $params, $numItems = 50)
    {
        $config = Registry::getInstance()->get('config');
        $log_params = 'id_media_object_type=' . $params['id_media_object_type']. ', visibility=' . $params['visibility'];
        if(isset( $params['id_pool'])) {
            $log_params .= ', id_pool=' .  $params['id_pool'];
        }
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::_importIds(' . $log_params . ')', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        $params['startIndex'] = $startIndex;
        $params['numItems'] = $numItems;
        $response = $this->_client->sendRequest('Text', 'search', $params);
        $tmp_import_folder = str_replace('APPLICATION_PATH', APPLICATION_PATH, $config['tmp_dir']) . DIRECTORY_SEPARATOR . $this->_tmp_import_folder;
        if(!is_dir($tmp_import_folder)) {
            mkdir($tmp_import_folder);
        }
        foreach ($response->result as $item) {
            file_put_contents($tmp_import_folder . DIRECTORY_SEPARATOR . $item->id_media_object, print_r($item, true));
        }
        if (count($response->result) >= $numItems && $startIndex < $response->count) {
            $nextStartIndex = $startIndex + $numItems;
            $this->_importIds($nextStartIndex, $params, $numItems);
        }
    }

    /**
     * @throws Exception
     */
    private function _importMediaObjectsFromFolder()
    {
        $config = Registry::getInstance()->get('config');
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectsFromFolder()', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        $dir = new DirectoryIterator(str_replace('APPLICATION_PATH', APPLICATION_PATH, $config['tmp_dir']) . DIRECTORY_SEPARATOR . $this->_tmp_import_folder);
        foreach ($dir as $file_info) {
            if (!$file_info->isDot()) {
                $id_media_object = $file_info->getFilename();
                $import_linked_media_objects = false;
                if(isset($conf['data']['primary_media_type_ids']) && !empty($conf['data']['primary_media_type_ids'])) {
                    $import_linked_media_objects = true;
                }
                if ($this->importMediaObject($id_media_object, $import_linked_media_objects)) {
                    unlink($file_info->getPathname());
                    $this->_imported_ids[] = $id_media_object;
                }
            }
        }

        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . 'Fullimport finished', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
    }

    /**
     * @param $media_object_ids
     * @param $import_linked_objects
     * @throws Exception
     */
    public function importMediaObjectsFromArray($media_object_ids, $import_linked_objects = true)
    {
        foreach ($media_object_ids as $media_object_id) {
            $this->importMediaObject($media_object_id, $import_linked_objects);
        }
    }

    /**
     * @param int $id_media_object
     * @param $import_linked_objects
     * @return bool
     * @throws Exception
     */
    public function importMediaObject($id_media_object, $import_linked_objects = true)
    {
        $id_media_object = intval($id_media_object);

        $config = Registry::getInstance()->get('config');
        $this->_start_time = microtime(true);
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . '--------------------------------------------------------------------------------', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . ')', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): REST Request started', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);

        try {
            $touristicOrigins = isset($config['data']['touristic']['origins']) && !empty($config['data']['touristic']['origins']) ? $config['data']['touristic']['origins'] : [0];
            $response = $this->_client->sendRequest('Text', 'getById', ['ids' => $id_media_object, 'withTouristicData' => 1, 'withDynamicData' => 1, 'byTouristicOrigin' => implode(',', $touristicOrigins)]);
        } catch (Exception $e) {
            $response = null;
        }

        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): REST Request done', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);

        $import_error = false;

        if (is_array($response) && count($response) > 0) {

            $old_object = null;
            $current_object = new ORM\Object\MediaObject();
            $current_object->setReadRelations(true);
            if($current_object->read($id_media_object)) {
                $old_object = clone $current_object;
                $current_object->delete(true);
            }

            $this->_start_time = microtime(true);
            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): parsing data', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);

            if (is_a($response[0]->touristic, 'stdClass')) {
                $touristic_data_importer = new TouristicData();
                $touristic_data_importer_result = $touristic_data_importer->import($response[0]->touristic, $id_media_object, $this->_import_type);
                $touristic_linked_media_object_ids = $touristic_data_importer_result['linked_media_object_ids'];
                $starting_point_ids = $touristic_data_importer_result['starting_point_ids'];

                if(is_array($starting_point_ids) && count($starting_point_ids) > 0) {
                    $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): importing starting point options';
                    $starting_point_options_importer = new StartingPointOptions($starting_point_ids);
                    $starting_point_options_importer->import();
                }

                if(is_array($touristic_linked_media_object_ids) && count($touristic_linked_media_object_ids) > 0) {
                    $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): found linked media objects in touristic data. Importing ...', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                    $this->importMediaObjectsFromArray($touristic_linked_media_object_ids, false);
                }
            }

            if(is_a($response[0]->insurance_group, 'stdClass') && intval($response[0]->id_insurance_group) != 0) {
                $insurance_info = [];
                foreach ($response[0]->insurance_group as $key => $value) {
                    $insurance_info['touristic_' . $key] = $value;
                }
                $insurance_data_importer = new TouristicData();
                $insurance_data_importer->import($insurance_info, $id_media_object, $this->_import_type);
            }

            if(is_array($response[0]->my_contents_to_media_object)) {
                $my_content_importer = new MyContent($response[0]->my_contents_to_media_object);
                $my_content_importer->import();
            }

            if (is_array($response[0]->data)) {
                $media_object_data_importer = new MediaObjectData($response[0], $id_media_object, $import_linked_objects);
                $media_object_data_importer_result = $media_object_data_importer->import();

                $linked_media_object_ids = $media_object_data_importer_result['linked_media_object_ids'];
                $category_tree_ids = $media_object_data_importer_result['category_tree_ids'];

                if(is_array($category_tree_ids) && count($category_tree_ids) > 0) {
                    $this->_log[] = ' Importer::_importMediaObjectData(' . $id_media_object . '): Importing Category Trees';
                    $category_tree_importer = new CategoryTree($category_tree_ids);
                    $category_tree_importer->import();
                }

                if(is_array($linked_media_object_ids) && count($linked_media_object_ids) > 0) {
                    $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): found linked media objects in media object data. Importing ...', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                    $this->importMediaObjectsFromArray($linked_media_object_ids, false);
                }
            }

            $brands_importer = new Brand();
            $brands_importer->import();

            $seasons_importer = new Season();
            $seasons_importer->import();

            $itinerary_importer = new Itinerary($id_media_object);
            $itinerary_importer->import();

            $media_object_importer = new Import\MediaObject();
            $media_object_importer->import($response[0]);

            if(isset($config['data']['touristic']['my_content_class_map']) && isset($response[0]->my_contents_to_media_object) && is_array($response[0]->my_contents_to_media_object)) {
                foreach($response[0]->my_contents_to_media_object as $my_content) {
                    if(isset($config['data']['touristic']['my_content_class_map'][$my_content->id_my_content])) {
                        $touristic_class_name = $config['data']['touristic']['my_content_class_map'][$my_content->id_my_content];
                        /** @var ImportInterface $custom_importer */
                        $custom_importer = new $touristic_class_name($my_content, $id_media_object);
                        $custom_importer->import();
                        $media_object = new ORM\Object\MediaObject($id_media_object, true);
                        $media_object->insertCheapestPrice();
                        unset($media_object);
                    }
                }
            }

            unset($response);
            unset($old_object);

            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '):  Objects removed from heap', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . '--------------------------------------------------------------------------------', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            $overall_time_elapsed = number_format(microtime(true) - $this->_overall_start_time, 4) . ' sec';
            $this->_log[] = Writer::write('Total import time: ' . $overall_time_elapsed, Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);

            return ($import_error == false);
        } else {
            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): RestClient-Request for Media Object ID: ' . $id_media_object . ' failed', Writer::OUTPUT_FILE, 'import', Writer::TYPE_ERROR);
            $this->_errors[] = 'Importer::importMediaObject(' . $id_media_object . '): RestClient-Request for Media Object ID: ' . $id_media_object . ' failed';
            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . '--------------------------------------------------------------------------------', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        }
        return false;
    }

    /**
     * @throws Exception
     */
    public function removeOrphans()
    {
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Finding and removing Orphans', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        $conf = Registry::getInstance()->get('config');
        $allowed_object_types = array_keys($conf['data']['media_types']);
        if(isset($conf['data']['primary_media_type_ids']) && !empty($conf['data']['primary_media_type_ids'])) {
            $allowed_object_types = $conf['data']['primary_media_type_ids'];
        }
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::removeOrphans()', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        foreach ($allowed_object_types as $allowed_object_type) {
            $allowed_visibilities = $conf['data']['media_types_allowed_visibilities'][$allowed_object_type];
            if(is_array($allowed_visibilities)) {
                foreach ($allowed_visibilities as $allowed_visibility) {
                    $params = [
                        'id_media_object_type' => $allowed_object_type,
                        'visibility' => $allowed_visibility
                    ];
                    $this->_importIds(0, $params);
                }
            }
        }
        $dir = new DirectoryIterator(str_replace('APPLICATION_PATH', APPLICATION_PATH, $conf['tmp_dir']) . DIRECTORY_SEPARATOR . $this->_tmp_import_folder);
        foreach ($dir as $file_info) {
            if (!$file_info->isDot()) {
                $id_media_object = $file_info->getFilename();
                unlink($file_info->getPathname());
                $this->_imported_ids[] = $id_media_object;
            }
        }
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . 'Importer::removeOrphans() Checking ' . count($this->_imported_ids) . ' mediaobjects', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        $this->_findAndRemoveOrphans();

    }

    /**
     * @throws Exception
     */
    private function _findAndRemoveOrphans()
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $query = "SELECT id FROM pmt2core_media_objects";
        $conf = Registry::getInstance()->get('config');
        if(isset($conf['data']['primary_media_type_ids']) && !empty($conf['data']['primary_media_type_ids'])) {
            $query = "SELECT id FROM pmt2core_media_objects WHERE id_object_type IN (" . implode(',', $conf['data']['primary_media_type_ids']) . ")";
        }
        $existing_media_objects = $db->fetchAll($query);
        foreach($existing_media_objects as $media_object) {
            if(!in_array($media_object->id, $this->_imported_ids)) {
                $media_object_to_remove = new MediaObject($media_object->id);
                $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Found Orphan: ' . $media_object->id . ' -> deleting ...', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                try {
                    $media_object_to_remove->delete(true);
                    $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Orphan: ' . $media_object->id . ' deleted', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                } catch (Exception $e) {
                    $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Deletion of Orphan ' . $media_object->id . ' failed: ' . $e->getMessage(), Writer::OUTPUT_FILE, 'import', Writer::TYPE_ERROR);
                    $this->_errors[] = 'Deletion of Orphan ' . $media_object->id . '): failed: ' . $e->getMessage();
                }
            }
        }
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Finding and removing Orphans done', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
    }

    /**
     * @throws Exception
     */
    public function postImport()
    {
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::postImport(): Starting post import processes ', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);

        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::postImport(): bash -c "exec nohup php ' . APPLICATION_PATH . '/cli/image_processor.php > /dev/null 2>&1 &"', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        exec('bash -c "exec nohup php ' . APPLICATION_PATH . '/cli/image_processor.php > /dev/null 2>&1 &"');

        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::postImport(): bash -c "exec nohup php ' . APPLICATION_PATH . '/cli/file_downloader.php > /dev/null 2>&1 &"', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        exec('bash -c "exec nohup php ' . APPLICATION_PATH . '/cli/file_downloader.php > /dev/null 2>&1 &"');
    }

    /**
     * @param $ids
     * @throws Exception
     */
    public function importMediaObjectTypes($ids)
    {
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObjectTypes(' . implode(',' ,$ids) . '): Starting import', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        $media_object_type_importer = new MediaObjectType($ids);
        $media_object_type_importer->import();
    }

    /**
     * @return array
     */
    public function getLog()
    {
        return $this->_log;
    }

    public function hasErrors()
    {
        return count($this->_errors) > 0;
    }

    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * @return string
     */
    private function _getElapsedTimeAndHeap()
    {
        $text = number_format(microtime(true) - $this->_start_time, 4) . ' sec | Heap: ';
        $text .= bcdiv(memory_get_usage(), (1000 * 1000), 2) . ' MByte';
        return $text;
    }
}
