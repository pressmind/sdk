<?php

namespace Pressmind;

use Pressmind\DB\Adapter\Pdo;
use Pressmind\Import\Agency;
use Pressmind\Import\Brand;
use Pressmind\Import\CategoryTree;
use Pressmind\Import\EarlyBird;
use Pressmind\Import\ImportInterface;
use Pressmind\Import\Itinerary;
use Pressmind\Import\ManualCheapestPrice;
use Pressmind\Import\MediaObjectCheapestPrice;
use Pressmind\Import\MediaObjectData;
use Pressmind\Import\MediaObjectDiscount;
use Pressmind\Import\MediaObjectType;
use Pressmind\Import\MyContent;
use Pressmind\Import\Port;
use Pressmind\Import\Season;
use Pressmind\Import\StartingPointOptions;
use Pressmind\Import\TouristicData;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Route;
use Pressmind\REST\Client;
use \DirectoryIterator;
use \Exception;
use Pressmind\Search\MongoDB\Indexer;

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
        $this->checkLegacyIssues();
    }

    /**
     * Checks for legacy/upgrade issues and aborts the import.
     * @return void
     * @throws Exception
     */
    public function checkLegacyIssues(){
        $config = Registry::getInstance()->get('config');
        if(isset($config['data']['search_mongodb']['search']['touristic']['departure_offset_from']) || isset($config['data']['search_mongodb']['search']['touristic']['departure_offset_to'])){
            throw new Exception('Legacy config issue detected: search_mongodb.touristic.departure_offset_from and search_mongodb.touristic.departure_offset_to is not longer supported. Please remove this keys and configure this in data.touristic.date_filter instead.'."\n");
        }
    }

    /**
     * @return array
     */
    public function getImportedIds(){
        return $this->_imported_ids;
    }

    /**
     * @param integer|null $id_pool
     * @param array|null $allowed_object_types
     * @param array|null $allowed_visibilities
     * @throws Exception
     */
    public function import($id_pool = null, $allowed_object_types = null, $allowed_visibilities = null)
    {
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::import()', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        $this->getIDsToImport($id_pool, $allowed_object_types, $allowed_visibilities);
        $this->importMediaObjectsFromFolder();
        $this->removeOrphans();
    }

    /**
     * @param $id_pool
     * @param $allowed_object_types
     * @param $allowed_visibilities
     * @return void
     * @throws Exception
     */
    public function getIDsToImport($id_pool = null, $allowed_object_types = null, $allowed_visibilities = null){
        $conf = Registry::getInstance()->get('config');
        if(is_null($allowed_object_types)) {
            $allowed_object_types = array_keys($conf['data']['media_types']);
            if(isset($conf['data']['primary_media_type_ids']) && !empty($conf['data']['primary_media_type_ids'])) {
                $allowed_object_types = $conf['data']['primary_media_type_ids'];
            }
        }
        foreach ($allowed_object_types as $allowed_object_type) {
            if(is_null($allowed_visibilities)) {
                $allowed_visibilities = $conf['data']['media_types_allowed_visibilities'][$allowed_object_type];
            }
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
        if(!file_exists($tmp_import_folder)) {
            @mkdir($tmp_import_folder, 0770, true);
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
    public function importMediaObjectsFromFolder()
    {
        $config = Registry::getInstance()->get('config');
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectsFromFolder()', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        $tmp_import_folder = str_replace('APPLICATION_PATH', APPLICATION_PATH, $config['tmp_dir']) . DIRECTORY_SEPARATOR . $this->_tmp_import_folder;
        $ids = $this->getMediaObjectsFromFolder();
        foreach ($ids as $id_media_object) {
            $import_linked_media_objects = false;
            if(isset($config['data']['primary_media_type_ids']) && !empty($config['data']['primary_media_type_ids'])) {
                $import_linked_media_objects = true;
            }
            if ($this->importMediaObject($id_media_object, $import_linked_media_objects)) {
                unlink($tmp_import_folder. DIRECTORY_SEPARATOR . $id_media_object);
                $this->_imported_ids[] = $id_media_object;
            }

        }
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . 'Fullimport finished', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
    }

    /**
     * @throws Exception
     */
    public function getMediaObjectsFromFolder()
    {
        $config = Registry::getInstance()->get('config');
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectsFromFolder()', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        $dir = new DirectoryIterator(str_replace('APPLICATION_PATH', APPLICATION_PATH, $config['tmp_dir']) . DIRECTORY_SEPARATOR . $this->_tmp_import_folder);
        $ids = [];
        foreach ($dir as $file_info) {
            if (!$file_info->isDot()) {
                $id_media_object = $file_info->getFilename();
                $ids[] = $id_media_object;
            }
        }
        return $ids;
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
            $this->_imported_ids[] = $media_object_id;
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
        global $_RUNTIME_IMPORTED_IDS;
        $id_media_object = intval($id_media_object);

        if(!empty($_RUNTIME_IMPORTED_IDS) && in_array($id_media_object, $_RUNTIME_IMPORTED_IDS)){
            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . '--------------------------------------------------------------------------------', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . ') is already imported in this run', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            return true;
        }
        $_RUNTIME_IMPORTED_IDS[] = $id_media_object;

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
            $this->_start_time = microtime(true);
            $current_object = new ORM\Object\MediaObject($id_media_object, false, true);
            $disable_touristic_data_import = (isset($config['data']['touristic']['disable_touristic_data_import']) && in_array($response[0]->id_media_objects_data_type, $config['data']['touristic']['disable_touristic_data_import']));
            if (false == $disable_touristic_data_import) {
                foreach ($current_object->booking_packages as $booking_package) {
                    $booking_package->delete(true);
                }
            }
            $current_object->delete(true);

            if (!empty($response[0]->insurance_group) && is_a($response[0]->insurance_group, 'stdClass')) {
                Writer::write('import insurances from main media_object (alternative booking tab in pm)', WRITER::OUTPUT_SCREEN, 'media_object_insurance_import', WRITER::TYPE_INFO);
                $tmpObject = new \stdClass();
                $tmpObject->touristic_insurance_groups = !empty($response[0]->insurance_group->insurance_groups) ? $response[0]->insurance_group->insurance_groups : [];
                $tmpObject->touristic_insurance_to_group = !empty($response[0]->insurance_group->insurance_to_group) ? $response[0]->insurance_group->insurance_to_group : [];
                $tmpObject->touristic_insurances = !empty($response[0]->insurance_group->insurances) ? $response[0]->insurance_group->insurances : [];
                $tmpObject->touristic_insurances_to_price_table = !empty($response[0]->insurance_group->insurances_to_price_table) ? $response[0]->insurance_group->insurances_to_price_table : [];
                $tmpObject->touristic_insurances_price_tables = !empty($response[0]->insurance_group->insurances_price_tables) ? $response[0]->insurance_group->insurances_price_tables : [];
                $touristic_data_importer = new TouristicData();
                $touristic_data_importer->import($tmpObject, $id_media_object, $this->_import_type);
            }

            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): parsing data', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            if (is_a($response[0]->touristic, 'stdClass') && false == $disable_touristic_data_import) {
                $touristic_data_importer = new TouristicData();
                $touristic_data_importer_result = $touristic_data_importer->import($response[0]->touristic, $id_media_object, $this->_import_type);
                $touristic_linked_media_object_ids = $touristic_data_importer_result['linked_media_object_ids'];
                $starting_point_ids = $touristic_data_importer_result['starting_point_ids'];

                foreach ($touristic_data_importer->getLog() as $log) {
                    Writer::write($log, WRITER::OUTPUT_FILE, 'touristic_data_import', WRITER::TYPE_INFO);
                }
                foreach ($touristic_data_importer->getErrors() as $error) {
                    Writer::write($error, WRITER::OUTPUT_FILE, 'touristic_data_import', WRITER::TYPE_ERROR);
                }
                if (count($touristic_data_importer->getErrors()) > 0) {
                    $this->_errors[] = 'Error in ' . TouristicData::class . '. See log "touristic_data_import" for details';
                }

                if (is_array($starting_point_ids) && count($starting_point_ids) > 0) {
                    $this->_log[] = ' Importer::_importMediaObjectTouristicData(' . $id_media_object . '): importing starting point options';
                    $starting_point_options_importer = new StartingPointOptions($starting_point_ids);
                    $starting_point_options_importer->import();
                }

                if (is_array($touristic_linked_media_object_ids) && count($touristic_linked_media_object_ids) > 0) {
                    $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): found linked media objects in touristic data. Importing ...', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                    $this->importMediaObjectsFromArray($touristic_linked_media_object_ids, false);
                }
            }

            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): touristic import finished', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);

            if (is_a($response[0]->touristic->touristic_base, 'stdClass') && true == $disable_touristic_data_import) {
                $fake_data = new \stdClass();
                $fake_data->touristic_base = $response[0]->touristic->touristic_base;
                $touristic_data_importer = new TouristicData();
                $touristic_data_importer->import($fake_data, $id_media_object, $this->_import_type);
            }

            $disable_virtual_price_calculation = (isset($config['data']['touristic']['disable_virtual_price_calculation']) && in_array($response[0]->id_media_objects_data_type, $config['data']['touristic']['disable_virtual_price_calculation']));
            if (!empty($response[0]->cheapest_prices) && !$disable_virtual_price_calculation) {
                $touristic_data_importer = new MediaObjectCheapestPrice();
                $touristic_data_importer->import($response[0]->cheapest_prices, $id_media_object, $this->_import_type);
            }

            $disable_manual_cheapest_price_import = (isset($config['data']['touristic']['disable_manual_cheapest_price_import']) && in_array($response[0]->id_media_objects_data_type, $config['data']['touristic']['disable_manual_cheapest_price_import']));
            if (is_array($response[0]->cheapest_prices) && !$disable_manual_cheapest_price_import) {
                $manual_cheapest_price_importer = new ManualCheapestPrice($response[0]->cheapest_prices);
                $manual_cheapest_price_importer->import();
            }

            if (is_array($response[0]->my_contents_to_media_object)) {
                $my_content_importer = new MyContent($response[0]->my_contents_to_media_object);
                $my_content_importer->import();
            }

            if (isset($response[0]->agencies) && is_array($response[0]->agencies) && count($response[0]->agencies) > 0) {
                $agency_importer = new Agency($response[0]->agencies, $id_media_object);
                $agency_importer->import();
                foreach ($agency_importer->getLog() as $log) {
                    Writer::write($log, WRITER::OUTPUT_FILE, 'agency_import', WRITER::TYPE_INFO);
                }
                foreach ($agency_importer->getErrors() as $error) {
                    Writer::write($error, WRITER::OUTPUT_FILE, 'agency_import', WRITER::TYPE_ERROR);
                }
                if (count($agency_importer->getErrors()) > 0) {
                    $this->_errors[] = 'Error in agency import. See log "agency_import" for details';
                }
            }

            $brands_importer = new Brand();
            $brands_importer->import();

            $seasons_importer = new Season();
            $seasons_importer->import();

            $ports_importer = new Port();
            $ports_importer->import();

            $powerfilter_importer = new Import\Powerfilter();
            $powerfilter_importer->import();
            if(isset($config['data']['search_mongodb']['enabled']) && $config['data']['search_mongodb']['enabled'] === true) {
                $Indexer = new Search\MongoDB\Indexer();
                $Indexer->upsertPowerfilter();
            }
            $itinerary_importer = new Itinerary($id_media_object);
            $itinerary_importer->import();

            $media_object_importer = new Import\MediaObject();
            $media_object = $media_object_importer->import($response[0]);

            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): media object imported', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);

            if (false == $disable_touristic_data_import && count($_RUNTIME_IMPORTED_IDS) === 1) {
                $db = Registry::getInstance()->get('db');
                $early_bird_importer = new EarlyBird();
                $early_bird_importer->import();
                $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Deleting CheapestPriceSpeed entries';
                $db->delete('pmt2core_cheapest_price_speed', ['id_media_object = ?', $media_object->id]);
                $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Inserting CheapestPriceSpeed entries';
            }

            if(false == $disable_touristic_data_import){
                $discount_importer = new MediaObjectDiscount();
                $discount_importer->import($response[0]->discounts, $id_media_object, $this->_import_type);
                MediaObject\ManualDiscount::convertManualDiscountsToEarlyBird($id_media_object);
                $media_object->readRelations();
                try {
                    $media_object->insertCheapestPrice();
                } catch (Exception $e) {
                    $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Creating cheapest price failed: ' . $e->getMessage();
                    $this->_errors[] = 'Importer::importMediaObject(' . $media_object->getId() . '):  Creating cheapest price failed: ' . $e->getMessage();
                }
            }

            if (is_array($response[0]->data)) {
                $media_object_data_importer = new MediaObjectData($response[0], $id_media_object, $this->_import_type, $import_linked_objects);
                $media_object_data_importer_result = $media_object_data_importer->import();

                $linked_media_object_ids = $media_object_data_importer_result['linked_media_object_ids'];
                $category_tree_ids = $media_object_data_importer_result['category_tree_ids'];
                $imported_languages = $media_object_data_importer_result['languages'];

                if(is_array($category_tree_ids) && count($category_tree_ids) > 0) {
                    $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::_importMediaObjectData(' . $id_media_object . '): Importing Category Trees', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                    $category_tree_importer = new CategoryTree($category_tree_ids);
                    $category_tree_importer_result = $category_tree_importer->import();
                    if(isset($category_tree_importer_result['linked_media_object_ids'])){
                        $linked_media_object_ids = array_merge($linked_media_object_ids, $category_tree_importer_result['linked_media_object_ids']);
                    }
                }

                if(is_array($linked_media_object_ids) && count($linked_media_object_ids) > 0) {
                    $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): found linked media objects in media object data. Importing ...', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                    $this->importMediaObjectsFromArray($linked_media_object_ids);
                }

                //$media_object->readRelations();
                /**@var Pdo $db**/
                $db = Registry::getInstance()->get('db');
                $this->_log[] = ' Deleting Route entries for media object id: ' . $id_media_object;
                $db->delete('pmt2core_routes', ['id_media_object = ?', $id_media_object]);
                $this->_log[] = ' Inserting Route entries for media object id: ' . $id_media_object;

                if(is_array($imported_languages)) {
                    foreach ($imported_languages as $language) {
                        try {
                            $urls = $media_object->buildPrettyUrls($language);
                            foreach ($urls as $url) {
                                $route = new Route();
                                $route->id_media_object = $id_media_object;
                                $route->id_object_type = $media_object->id_object_type;
                                $route->route = $url;
                                $route->language = $language;
                                $route->create();
                                unset($route);
                            }
                        } catch (Exception $e) {
                            $this->_log[] = ' Creating routes failed for media object id ' . $id_media_object . ': ' . $e->getMessage();
                            $this->_errors[] = ' Creating routes failed for media object id ' . $id_media_object . ': ' . $e->getMessage();
                        }
                    }
                    $this->_log[] = ' Routes inserted for media object id: ' . $id_media_object;
                }
            }

            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): media object data imported', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);

            $my_content_importer_has_run = false;

            if(false == $disable_touristic_data_import && isset($config['data']['touristic']['my_content_class_map']) && isset($response[0]->my_contents_to_media_object) && is_array($response[0]->my_contents_to_media_object)) {
                foreach($response[0]->my_contents_to_media_object as $my_content) {
                    if(isset($config['data']['touristic']['my_content_class_map'][$my_content->id_my_content])) {
                        $touristic_class_name = $config['data']['touristic']['my_content_class_map'][$my_content->id_my_content];
                        /** @var ImportInterface $custom_importer */
                        $custom_importer = new $touristic_class_name($my_content, $id_media_object);
                        $custom_importer->import();
                        foreach ($custom_importer->getLog() as $log) {
                            Writer::write($log, WRITER::OUTPUT_FILE, 'my_content_class_map', WRITER::TYPE_INFO);
                        }
                        foreach ($custom_importer->getErrors() as $error) {
                            Writer::write($error, WRITER::OUTPUT_FILE, 'my_content_class_map', WRITER::TYPE_ERROR);
                        }
                        if(count($custom_importer->getErrors()) > 0) {
                            $this->_errors[] = 'Error in hook invoked by MyContent class map. See log "my_content_class_map" for details';
                        }
                    }
                }
            }

            if(isset($config['data']['media_type_custom_import_hooks'][$response[0]->id_media_objects_data_type]) &&
                is_array($config['data']['media_type_custom_import_hooks'][$response[0]->id_media_objects_data_type]) &&
                $my_content_importer_has_run == false) {
                foreach ($config['data']['media_type_custom_import_hooks'][$response[0]->id_media_objects_data_type] as $custom_import_class_name) {
                    $custom_import_class = new $custom_import_class_name($id_media_object);
                    $custom_import_class->import();
                    foreach ($custom_import_class->getLog() as $log) {
                        Writer::write($log, WRITER::OUTPUT_FILE, 'custom_import_hook', WRITER::TYPE_INFO);
                    }
                    foreach ($custom_import_class->getErrors() as $error) {
                        Writer::write($error, WRITER::OUTPUT_BOTH, 'custom_import_hook', WRITER::TYPE_ERROR);
                    }
                    if(count($custom_import_class->getErrors()) > 0) {
                        $this->_errors[] = count($custom_import_class->getErrors()). ' errors in custom import hook. See log "custom_import_hook" for details';
                    }
                    if(method_exists($custom_import_class, 'getWarnings')){
                        foreach ($custom_import_class->getWarnings() as $warning) {
                            Writer::write($warning, WRITER::OUTPUT_BOTH, 'custom_import_hook', WRITER::TYPE_WARNING);
                        }
                        if(count($custom_import_class->getWarnings()) > 0) {
                            $this->_errors[] = count($custom_import_class->getWarnings()). ' warnings in custom import hook. See log "custom_import_hook" for details';
                        }
                    }
                }
                // reinitialize
                $media_object = new MediaObject($id_media_object);
                $media_object->insertCheapestPrice();
            }

            if($config['cache']['enabled'] == true && in_array('OBJECT', $config['cache']['types'])) {
                $media_object->updateCache($id_media_object);
                $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '):  Cache has been updated', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
            }

            /**@TODO make creating search index more effective, complete reload of the media object is absolutely unnecessary**/
            $media_object->setReadRelations(true);
            $media_object->readRelations();
            $media_object->createSearchIndex();
            if(isset($config['data']['search_mongodb']['enabled']) && $config['data']['search_mongodb']['enabled'] === true) {
                try {
                    $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): createMongoDBIndex', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                    $media_object->createMongoDBIndex();
                } catch (Exception $e) {
                    $this->_log[] = 'Error during creating MongoDBIndex: ' . $e->getMessage();
                    $this->_errors[] = 'Error during creating MongoDBIndex: ' . $e->getMessage();
                }
                try {
                    $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): createMongoDBCalendar', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                    $media_object->createMongoDBCalendar();
                } catch (Exception $e) {
                    $this->_log[] = 'Error during creating createMongoDBCalendar: ' . $e->getMessage();

                    echo $e->getTraceAsString();
                    $this->_errors[] = 'Error during creating createMongoDBCalendar: ' . $e->getMessage();
                }
            }

            if(isset($config['data']['search_opensearch']['enabled']) && $config['data']['search_opensearch']['enabled'] === true) {
                try {
                    $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $id_media_object . '): createOpenSearchIndex', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                    $media_object->createOpenSearchIndex();
                } catch (Exception $e) {
                    $this->_log[] = 'Error during creating OpenSearch: ' . $e->getMessage();
                    $this->_errors[] = 'Error during creating OpenSearch: ' . $e->getMessage();
                }
            }

            unset($response);
            unset($media_object);

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
            $allowed_visibilities[] = 60; // the hidden visibility is always allowed
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
        $config = Registry::getInstance()->get('config');
        if(isset($config['data']['primary_media_type_ids']) && !empty($config['data']['primary_media_type_ids'])) {
            $query = "SELECT id FROM pmt2core_media_objects WHERE id_object_type IN (" . implode(',', $config['data']['primary_media_type_ids']) . ")";
        }
        $existing_media_objects = $db->fetchAll($query);
        foreach($existing_media_objects as $media_object) {
            if(!in_array($media_object->id, $this->_imported_ids)) {
                $media_object_to_remove = new MediaObject($media_object->id);
                $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Found Orphan: ' . $media_object->id . ' -> deleting ...', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                try {
                    if(isset($config['data']['search_mongodb']['enabled']) && $config['data']['search_mongodb']['enabled'] === true) {
                        $Indexer = new Indexer();
                        $Indexer->deleteMediaObject($media_object->id);
                        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Orphan: ' . $media_object->id . ' deleted from mongodb index', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                    }
                    $media_object_to_remove->delete(true);
                    $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Orphan: ' . $media_object->id . ' deleted from mysql', Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
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
    public function postImport($id_media_object = null)
    {
        if(!is_array($id_media_object)){
            $id_media_object = [$id_media_object];
        }
        $config = Registry::getInstance()->get('config');
        if(isset($config['data']['media_type_custom_post_import_hooks']) &&
            is_array($config['data']['media_type_custom_post_import_hooks'])) {
            foreach ($config['data']['media_type_custom_post_import_hooks'] as $id_object_type => $hooks){
                if(!isset($config['data']['media_types'][$id_object_type])){
                    continue;
                }
                foreach ($hooks as $custom_import_class_name) {
                    $custom_import_class = new $custom_import_class_name($id_media_object);
                    $custom_import_class->import($id_media_object);
                    foreach ($custom_import_class->getLog() as $log) {
                        Writer::write($log, WRITER::OUTPUT_FILE, 'custom_post_import_hook', WRITER::TYPE_INFO);
                    }
                    foreach ($custom_import_class->getErrors() as $error) {
                        Writer::write($error, WRITER::OUTPUT_FILE, 'custom_post_import_hook', WRITER::TYPE_ERROR);
                    }
                    if(count($custom_import_class->getErrors()) > 0) {
                        $this->_errors[] = count($custom_import_class->getErrors()). ' errors in custom post import hook. See log "custom_post_import_hook" for details';
                    }
                    if(method_exists($custom_import_class, 'getWarnings')){
                        foreach ($custom_import_class->getWarnings() as $warning) {
                            Writer::write($warning, WRITER::OUTPUT_FILE, 'custom_post_import_hook', WRITER::TYPE_WARNING);
                        }
                        if(count($custom_import_class->getWarnings()) > 0) {
                            $this->_errors[] = count($custom_import_class->getWarnings()). ' warnings in custom post import hook. See log "custom_post_import_hook" for details';
                        }
                    }
                }
            }
        }        $image_processor_path = APPLICATION_PATH . '/cli/image_processor.php' . (empty($id_media_object) ? '' : ' mediaobject ' . implode(',',$id_media_object));

        $php_binary = isset($config['server']['php_cli_binary']) && !empty($config['server']['php_cli_binary']) ? $config['server']['php_cli_binary'] : 'php';
        if(php_sapi_name() === 'cli' && !file_exists($php_binary)) {
            throw new Exception('can not run post import scripts, php binary not found at "' . $php_binary. '", check pm-config.php');
            return;
        }
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::postImport(): Starting post import processes ', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::postImport(): bash -c "exec nohup php ' . $image_processor_path . ' > /dev/null 2>&1 &"', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        if(!$this->checkRunFile($image_processor_path)) {
            $cmd = 'bash -c "exec nohup ' . $php_binary . ' ' . $image_processor_path . ' > /dev/null 2>&1 &"';
            exec($cmd);
            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::postImport(): '.$cmd, Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
        }
        $file_downloader_path = APPLICATION_PATH . '/cli/file_downloader.php';
        if(!$this->checkRunFile($image_processor_path))
        {
            $cmd = 'bash -c "exec nohup ' . $php_binary . ' ' . $file_downloader_path . ' > /dev/null 2>&1 &"';
            exec($cmd);
            $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::postImport(): '.$cmd, Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);

        }
    }

    /**
     * Check run command line path in with active PID
     *
     * @param string $path
     *
     * @return bool
     */
    private function checkRunFile($path)
    {
        if(strtolower(PHP_OS) == 'linux') { //ps -C will only work on Linux this way
            $outputPS = array();
            exec('ps -C php -f', $outputPS);

            foreach ($outputPS as $line) {
                if (strpos($line, $path) !== false) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param $ids
     * @param bool $drop_tables
     * @throws Exception
     */
    public function importMediaObjectTypes($ids, $drop_tables = false)
    {
        $this->_log[] = Writer::write($this->_getElapsedTimeAndHeap() . ' Importer::importMediaObjectTypes(' . implode(',' ,$ids) . '): Starting import', Writer::OUTPUT_FILE, 'import', Writer::TYPE_INFO);
        $media_object_type_importer = new MediaObjectType($ids);
        $media_object_type_importer->import($drop_tables);
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
