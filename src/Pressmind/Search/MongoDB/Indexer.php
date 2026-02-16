<?php

namespace Pressmind\Search\MongoDB;

use Pressmind\DB\Adapter\Pdo;
use Pressmind\HelperFunctions;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\FulltextSearch;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Powerfilter\ResultSet;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\Registry;
use Pressmind\Search\CheapestPrice;

class Indexer extends AbstractIndex
{
    /**
     * Cache for linked MediaObjects during index creation
     * @var array<int, MediaObject>
     */
    private $_linkedObjectCache = [];

    /**
     * Static cache to track which collections have had their indexes checked this session.
     * Prevents redundant index checks on every upsertMediaObject call.
     * @var array<string, bool>
     */
    private static $_indexCheckedCollections = [];

    public function __construct() {
        parent::__construct();
    }

    /**
     * Get a linked MediaObject, using cache to avoid duplicate loads
     * @param int $idMediaObject
     * @return MediaObject
     */
    private function _getLinkedMediaObject($idMediaObject)
    {
        if (!isset($this->_linkedObjectCache[$idMediaObject])) {
            $this->_linkedObjectCache[$idMediaObject] = new MediaObject($idMediaObject);
        }
        return $this->_linkedObjectCache[$idMediaObject];
    }


    /**
     * @param $collection_name
     * @return bool
     */
    public function collectionExists($collection_name){
        $collections = $this->db->listCollections();
        foreach($collections as $collection){
            if($collection->getName() === $collection_name){
                return true;
            }
        }
        return false;
    }

    /**
     * sets a index to a collection
     * @param $collection_name
     * @param $key
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function createCollectionIndex($collection_name){
        // Skip if already checked this session (major performance optimization ~870ms saved per call)
        if (isset(self::$_indexCheckedCollections[$collection_name])) {
            return;
        }

        $this->createCollectionIndexIfNotExists($collection_name, ['groups' => 1], ['name' => 'groups_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['prices.price_total' => 1], ['name' => 'prices.price_total_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['prices.price_total' => -1], ['name' => 'prices.price_total_-1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['prices.date_departure' => 1], ['name' => 'prices.date_departure_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['prices.date_departure' => -1], ['name' => 'prices.date_departure_-1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['prices.duration' => 1], ['name' => 'prices.duration_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['prices.occupancy' => 1], ['name' => 'prices.occupancy_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['prices.occupancy_additional' => 1], ['name' => 'prices.occupancy_additional_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['prices.id_startingpoint_option' => 1], ['name' => 'prices.id_startingpoint_option_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['prices.price_total' => 1, 'prices.duration' => 1, 'prices.occupancy' => 1], ['name' => 'prices.price_total_1_prices.duration_1_prices.occupancy_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['categories.it_item' => 1], ['name' => 'categories.it_item_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['categories.name' => 1], ['name' => 'categories.name_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['categories.it_item' => 1, 'categories.field_name' => 1], ['name' => 'categories.it_item_1_categories.field_name_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['id_media_object' => 1], ['name' => 'id_media_object_1', 'unique' => 1]);
        if(!$this->_use_opensearch){
            $this->createCollectionIndexIfNotExists($collection_name, ['fulltext' => 'text', 'categories.path_str' => 'text', 'code' => 'text'], ['default_language' => 'none', 'weights' => ['fulltext' => 5, 'categories.path_str' => 10, 'code' => 15], 'name' => 'fulltext_text']);
        }
        $this->createCollectionIndexIfNotExists($collection_name, ['sold_out' => 1], ['name' => 'sold_out_1']);
        $this->createCollectionIndexIfNotExists($collection_name, ['sales_priority' => 1], ['name' => 'sales_priority_1']);
        
        // Create custom_order indexes dynamically based on config
        if(!empty($this->_config['search']['custom_order'])){
            foreach($this->_config['search']['custom_order'] as $id_object_type => $custom_orders){
                foreach($custom_orders as $shortname => $fieldConfig){
                    $indexField = 'custom_order.' . $shortname;
                    // Create ascending index
                    $this->createCollectionIndexIfNotExists(
                        $collection_name, 
                        [$indexField => 1], 
                        ['name' => $indexField . '_1']
                    );
                    // Create descending index for better performance
                    $this->createCollectionIndexIfNotExists(
                        $collection_name, 
                        [$indexField => -1], 
                        ['name' => $indexField . '_-1']
                    );
                }
            }
        }

        // Mark this collection as checked for this session
        self::$_indexCheckedCollections[$collection_name] = true;
    }

    /**
     * Reset the collection index check cache (useful for testing or forced re-check)
     */
    public static function resetIndexCheckCache()
    {
        self::$_indexCheckedCollections = [];
    }

    public function createIndexes()
    {
        $ids = [];
        foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
            $mediaObjects = MediaObject::listAll(['id_object_type' => $id_object_type]);
            foreach ($mediaObjects as $mediaObject) {
                $ids[] = $mediaObject->id;
            }
        }
        $this->upsertMediaObject($ids);
    }

    /**
     * @param string|int|array $id_media_objects
     * @param MediaObject|null $preloadedMediaObject Optional preloaded MediaObject to avoid reloading
     * @throws \Exception
     */
    public function upsertMediaObject($id_media_objects, $preloadedMediaObject = null)
    {
        if(!is_array($id_media_objects)){
            $id_media_objects = [$id_media_objects];
        }

        foreach ($this->_config['search']['build_for'] as $build_infos) {
            foreach($build_infos as $build_info){
                foreach($this->_agencies as $agency){
                    $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language'], $agency);
                    $this->createCollectionIndex($collection_name);
                }
            }
        }

        if ($preloadedMediaObject !== null && count($id_media_objects) === 1) {
            $mediaObjects = [$preloadedMediaObject];
        } else {
            $mediaObjects = MediaObject::listAll(['id' => ['in', implode(',', $id_media_objects)]]);
        }

        $ids = [];

        foreach($mediaObjects as $mediaObject){
            if(empty($this->_config['search']['build_for'][$mediaObject->id_object_type])){
                $configured_types = array_keys($this->_config['search']['build_for']);
                Writer::write(
                    sprintf(
                        'MongoDB Indexer: MediaObject %d NOT INDEXED - id_object_type %d is not in build_for config. ' .
                        'CONFIGURED object_types: [%s]. ' .
                        'FIX: Add id_object_type %d to search.build_for in pm-config.php',
                        $mediaObject->id,
                        $mediaObject->id_object_type,
                        implode(', ', $configured_types),
                        $mediaObject->id_object_type
                    ),
                    Writer::OUTPUT_FILE, 'mongodb_indexer', Writer::TYPE_WARNING
                );
                continue;
            }
            foreach ($this->_config['search']['build_for'][$mediaObject->id_object_type] as $build_info) {
                foreach($this->_agencies as $agency) {
                    if(!isset($ids[$agency])){
                        $ids[$agency] = [];
                    }
                    $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language'], $agency);
                    $collection = $this->db->$collection_name;
                    $collection_name_description = $this->getCollectionName($build_info['origin'], $build_info['language'], $agency, 'description_');
                    $collection_description = $this->db->$collection_name_description;

                    $document = $this->createIndex($mediaObject->id, $build_info['language'], $build_info['origin'], $agency, false, $mediaObject);

                    if ($document === false) {
                        continue;
                    }

                    $document_description = new \stdClass();
                    $document_description->_id = $mediaObject->id;
                    $document_description->description = $document->description;
                    unset($document->description);
                    $collection->updateOne(['_id' => $mediaObject->id], ['$set' => $document], ['upsert' => true]);
                    $collection_description->updateOne(['_id' => $mediaObject->id], ['$set' => $document_description], ['upsert' => true]);

                    $ids[$agency][] = $mediaObject->id;
                }
            }
        }

        foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
            foreach ($build_infos as $build_info) {
                foreach($this->_agencies as $agency) {
                    foreach($id_media_objects as $id_media_object){
                        if(!empty($ids[$agency]) && in_array($id_media_object, $ids[$agency])){
                            continue;
                        }
                        $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language'], $agency);
                        $collection = $this->db->$collection_name;
                        $collection->deleteOne(['_id' => $id_media_object]);
                        $collection_name_description = $this->getCollectionName($build_info['origin'], $build_info['language'], $agency, 'description_');
                        $collection_description = $this->db->$collection_name_description;
                        $collection_description->deleteOne(['_id' => $id_media_object]);
                    }
                }
            }
        }
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function upsertPowerfilter(){
        $ResultSets = ResultSet::listAll();
        $items = [];
        $valid_ids = [];
        foreach($ResultSets as $ResultSet){
            $valid_ids[] = $ResultSet->id;
            $item = new \stdClass();
            $item->_id = $ResultSet->id;
            $item->id_media_objects = explode(',', $ResultSet->id_media_objects ?? '');
            $item->id_media_objects = array_map('intval', $item->id_media_objects ?? []);
            $items[] = $item;
        }
        $collection = $this->db->{'powerfilter'};
        foreach($items as $item){
            $collection->updateOne(['_id' => $item->_id], ['$set' => $item], ['upsert' => true]);
        }
        $collection->deleteMany(['_id' => ['$nin' => $valid_ids]]);
    }

    /**
     * @return int
     */
    public function removeTempCollections(){
        $collections = $this->db->listCollections();
        $c = 0;
        foreach($collections as $collection){
            $collection_name = $collection->getName();
            if(strpos($collection_name, 'temp_') === 0){
                $this->db->$collection_name->drop();
                $c++;
            }
        }
        return $c;
    }

    /**
     * @param string|int|array $id_media_object
     * @throws \Exception
     */
    public function deleteMediaObject($id_media_objects)
    {
        if(!is_array($id_media_objects)){
            $id_media_objects = array_map('intval', explode(',', $id_media_objects));
        }
        foreach($id_media_objects as $id_media_object){
            foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
                foreach ($build_infos as $build_info) {
                    foreach($this->_agencies as $agency) {
                        $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language'], $agency);
                        $collection = $this->db->$collection_name;
                        $collection->deleteOne(['_id' => $id_media_object]);
                    }
                }
            }
        }
    }

    /**
     * @param int $origin
     * @param string $language
     * @param string $agency
     * @param string $prefix
     * @return string
     */
    public function getCollectionName($origin = 0, $language = null, $agency = null, $prefix = 'best_price_search_based_'){
        return $prefix . (!empty($language) ? $language.'_' : '') . 'origin_' . $origin.(!empty($agency) ? '_agency_'. $agency: '');
    }


    /**
     * @param int $idMediaObject
     * @param string $language
     * @param int $origin
     * @param string $agency
     * @param bool $force
     * @param MediaObject|null $preloadedMediaObject Optional preloaded MediaObject to avoid reloading
     * @return false|mixed
     * @throws \Exception
     */
    public function createIndex($idMediaObject, $language, $origin, $agency = null, $force = false, $preloadedMediaObject = null)
    {
        // Clear linked object cache for fresh index creation
        $this->_linkedObjectCache = [];
        
        $config = Registry::getInstance()->get('config');
        $searchObject = new \stdClass();

        if ($preloadedMediaObject !== null) {
            $this->mediaObject = $preloadedMediaObject;
        } else {
            $this->mediaObject = new MediaObject($idMediaObject, true, true);
        }

        $searchObject->departure_date_count = $this->_createDepartureDateCount($origin, $agency);

        if(empty($searchObject->departure_date_count) && !empty($agency)){
            Writer::write(
                sprintf(
                    'MongoDB Indexer: MediaObject %d NOT INDEXED for agency "%s" - departure_date_count is 0. ' .
                    'ORIGIN: %d. ' .
                    'CHECK: SELECT COUNT(*) FROM pmt2core_cheapest_price_speed WHERE id_media_object = %d AND id_origin = %d AND agency = "%s"',
                    $this->mediaObject->id,
                    $agency,
                    $origin,
                    $this->mediaObject->id,
                    $origin,
                    $agency
                ),
                Writer::OUTPUT_FILE, 'mongodb_indexer', Writer::TYPE_WARNING
            );
            return false;
        }
        $searchObject->_id = $this->mediaObject->id;
        $searchObject->id_object_type = $this->mediaObject->id_object_type;
        $searchObject->object_type_order = array_search($this->mediaObject->id_object_type, $config['data']['primary_media_type_ids']);
        $searchObject->id_media_object = $this->mediaObject->id;
        $searchObject->url = $this->mediaObject->getPrettyUrl($language);
        foreach($this->_config['search']['build_for'][$this->mediaObject->id_object_type] as $v){
            if($v['language'] == $language && !empty($v['disable_language_prefix_in_url'])){
                $searchObject->url = $this->mediaObject->getPrettyUrl();
                break;
            }
        }
        $searchObject->code = array_filter(array_map('trim', explode(',', (string)$this->mediaObject->code)));

        $searchObject->description = $this->_mapDescriptions($language, $agency);
        $searchObject->categories = $this->_mapCategories($language);
        $searchObject->groups = $this->_mapGroups($language);
        $searchObject->locations = $this->_mapLocations($language);
        $searchObject->prices = $this->_aggregatePrices($origin, $agency);
        $searchObject->has_price = !empty($searchObject->prices);

        if(!$this->_use_opensearch){
            $searchObject->fulltext = FulltextSearch::getFullTextWords($this->mediaObject->id, $this->mediaObject->id_object_type, $language);
        }

        $searchObject->sold_out = $this->_soldOut($origin, $agency);
        $searchObject->is_running = $this->_isRunning($origin, $agency);
        $searchObject->has_guaranteed_departures = $this->_hasGuaranteedDepartures($searchObject->prices);

        $searchObject->ports = $this->_getPorts();
        $searchObject->departure_ports = $this->_getDeparturePorts();
        $searchObject->arrival_ports = $this->_getArrivalPorts();

        $searchObject->valid_from = !is_null($this->mediaObject->valid_from) ?  $this->mediaObject->valid_from->format(DATE_RFC3339_EXTENDED) : null;
        $searchObject->valid_to = !is_null($this->mediaObject->valid_to) ? $this->mediaObject->valid_to->format(DATE_RFC3339_EXTENDED) : null;
        $searchObject->visibility = $this->mediaObject->visibility;
        $searchObject->recommendation_rate = $this->mediaObject->recommendation_rate;
        $searchObject->sales_priority = $this->mediaObject->sales_priority.str_pad($this->mediaObject->sales_position, 6, '0', STR_PAD_LEFT);

        $searchObject->custom_order = $this->_custom_order($language, $agency);

        if(!empty($this->_config['search']['five_dates_per_month_list'])){
            $searchObject->dates_per_month = $this->_createDatesPerMonth($origin, $agency);
        }

        if(!empty($this->_config['search']['possible_duration_list'])){
            $searchObject->possible_durations = $this->_createPossibleDurations($origin, $agency);
        }

        $now = new \DateTime();
        $now->setTimezone(new \DateTimeZone('Europe/Berlin'));
        $searchObject->last_modified_date = $now->format(DATE_RFC3339_EXTENDED);
        if(is_array($searchObject->prices) && count($searchObject->prices) > 0) {
            $searchObject->best_price_meta = $searchObject->prices[0];
        }

        if($force === true){
            return json_decode(json_encode($searchObject));
        }
        $allow_invalid_offers = false;
        if(isset($this->_config['search']['allow_invalid_offers']) && is_bool($this->_config['search']['allow_invalid_offers'])){ // legacy
            $allow_invalid_offers = $this->_config['search']['allow_invalid_offers'];
        }elseif(isset($this->_config['search']['allow_invalid_offers']) && is_array($this->_config['search']['allow_invalid_offers']) && in_array($this->mediaObject->id_object_type, $this->_config['search']['allow_invalid_offers'])){
            $allow_invalid_offers = true;
        }
        if(empty($searchObject->prices) && $allow_invalid_offers === false && $searchObject->visibility != 10){
            $actualDurations = $this->_getActualDurationsFromDb($origin, $agency);
            $configuredRanges = $this->_config['search']['touristic']['duration_ranges'] ?? [];
            $rangesStr = implode(', ', array_map(function($r) { return $r[0].'-'.$r[1].' days'; }, $configuredRanges));
            $durationsStr = !empty($actualDurations) ? implode(', ', $actualDurations) . ' days' : 'NONE FOUND';
            $fixHint = !empty($actualDurations) ? min($actualDurations).'-'.max($actualDurations) : 'N/A';
            
            Writer::write(
                sprintf(
                    'MongoDB Indexer: MediaObject %d NOT INDEXED - No prices after aggregation. ' .
                    'ACTUAL DURATIONS in DB: [%s]. ' .
                    'CONFIGURED duration_ranges: [%s]. ' .
                    'VISIBILITY: %d (10=hidden). ALLOW_INVALID_OFFERS: %s. ' .
                    'FIX: Add a duration_range covering %s days to search.touristic.duration_ranges in pm-config.php',
                    $this->mediaObject->id,
                    $durationsStr,
                    $rangesStr ?: 'NOT CONFIGURED',
                    $searchObject->visibility,
                    $allow_invalid_offers ? 'true' : 'false',
                    $fixHint
                ),
                Writer::OUTPUT_FILE, 'mongodb_indexer', Writer::TYPE_WARNING
            );
            return false;
        }
        return json_decode(json_encode($searchObject));
    }

    /**
     * Validates if a MediaObject can be indexed and returns detailed error messages.
     * Used by MediaObject::validate() to show indexing issues.
     * @param int $idMediaObject
     * @param MediaObject|null $preloadedMediaObject
     * @return array Array of validation messages (with ✅ or ❌ prefixes)
     * @throws \Exception
     */
    public function validateMediaObject($idMediaObject, $preloadedMediaObject = null)
    {
        $result = [];
        $result[] = 'Validation: MongoDB Indexer';
        
        if ($preloadedMediaObject !== null) {
            $this->mediaObject = $preloadedMediaObject;
        } else {
            $this->mediaObject = new MediaObject($idMediaObject, true, true);
        }
        
        // Check 1: Object Type in build_for
        if (empty($this->_config['search']['build_for'][$this->mediaObject->id_object_type])) {
            $configured_types = array_keys($this->_config['search']['build_for']);
            $result[] = sprintf(
                ' ❌  id_object_type %d is not configured in search.build_for. CONFIGURED: [%s]',
                $this->mediaObject->id_object_type,
                implode(', ', $configured_types)
            );
            return $result;
        }
        $result[] = ' ✅  id_object_type ' . $this->mediaObject->id_object_type . ' is configured in build_for';
        
        // Check for each origin/agency combination
        foreach ($this->_config['search']['build_for'][$this->mediaObject->id_object_type] as $build_info) {
            $origin = $build_info['origin'];
            $language = $build_info['language'];
            
            foreach ($this->_agencies as $agency) {
                $agencyLabel = !empty($agency) ? "agency '$agency'" : 'no agency';
                $prefix = " Origin $origin, $agencyLabel: ";
                
                // Check 2: Departure date count
                $departureDateCount = $this->_createDepartureDateCount($origin, $agency);
                if (empty($departureDateCount) && !empty($agency)) {
                    $result[] = $prefix . '❌  departure_date_count is 0. No prices for this agency in pmt2core_cheapest_price_speed';
                    continue;
                }
                
                // Check 3: Duration ranges and prices
                $actualDurations = $this->_getActualDurationsFromDb($origin, $agency);
                $configuredRanges = $this->_config['search']['touristic']['duration_ranges'] ?? [];
                
                if (empty($actualDurations)) {
                    $result[] = $prefix . '❌  No durations found in pmt2core_cheapest_price_speed';
                    continue;
                }
                
                // Check if durations match any configured range
                $matchedRange = false;
                foreach ($configuredRanges as $range) {
                    foreach ($actualDurations as $duration) {
                        if ($duration >= $range[0] && $duration <= $range[1]) {
                            $matchedRange = true;
                            break 2;
                        }
                    }
                }
                
                $rangesStr = implode(', ', array_map(function($r) { return $r[0].'-'.$r[1]; }, $configuredRanges));
                $durationsStr = implode(', ', $actualDurations);
                
                if (!$matchedRange && !empty($configuredRanges)) {
                    $result[] = $prefix . sprintf(
                        '❌  Duration mismatch! ACTUAL: [%s days]. CONFIGURED duration_ranges: [%s]',
                        $durationsStr,
                        $rangesStr
                    );
                } else {
                    $result[] = $prefix . sprintf(
                        '✅  Durations [%s days] match configured ranges [%s]',
                        $durationsStr,
                        $rangesStr
                    );
                }
            }
        }
        
        // Check 4: Groups/Brand configuration
        if (!empty($this->_config['search']['groups'][$this->mediaObject->id_object_type])) {
            $group_map = $this->_config['search']['groups'][$this->mediaObject->id_object_type];
            if (empty($group_map['field'])) {
                $result[] = ' ❌  Groups config missing "field" key for id_object_type ' . $this->mediaObject->id_object_type;
            } elseif ($group_map['field'] == 'brand' && empty($this->mediaObject->brand)) {
                $result[] = ' ❌  Groups field is "brand" but MediaObject has no brand assigned';
            } else {
                $result[] = ' ✅  Groups config OK (field: ' . $group_map['field'] . ')';
            }
        }
        
        return $result;
    }

    /**
     * @param $prices
     * @return bool
     */
    private function _hasGuaranteedDepartures($prices){
        if(!empty($prices)){
            foreach($prices as $price){
                if(!empty($price->guaranteed_departures)){
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @param string $language
     * @param string|null $agency
     * @return array|null
     */
    private function _custom_order($language, $agency = null){
        if(empty($this->_config['search']['custom_order'])){
           return null;
        }
        
        // 1. Collect ALL unique shortnames from ALL object types
        // This ensures all documents have the same custom_order fields for deterministic sorting
        $allShortnames = [];
        foreach($this->_config['search']['custom_order'] as $id_object_type => $custom_orders){
            foreach($custom_orders as $shortname => $fieldConfig){
                $allShortnames[$shortname] = true;
            }
        }
        
        // 2. Initialize all fields with 'ZZZZZ' so they sort at the end
        $result = [];
        foreach(array_keys($allShortnames) as $shortname){
            $result[$shortname] = 'ZZZZZ';
        }
        
        // 3. Overwrite with actual values for matching object type
        foreach($this->_config['search']['custom_order'] as $id_object_type => $custom_orders){
            // Compare as integers to avoid type mismatch issues
            if((int)$this->mediaObject->id_object_type != (int)$id_object_type){
                continue;
            }
            foreach($custom_orders as $shortname => $fieldConfig){
                $value = null;
                $moc = $this->mediaObject->getDataForLanguage($language);
                
                if(empty($moc) || empty($fieldConfig['field'])){
                    $result[$shortname] = 'ZZZZZ';
                    continue;
                }
                
                $data = $moc->toStdClass();
                
                // Read field value (similar to _mapDescriptions)
                if(!empty($fieldConfig['from']) && !empty($data->{$fieldConfig['from']})){
                    // Read from linked object
                    foreach ($data->{$fieldConfig['from']} as $objectlink) {
                        $linkedObject = $this->_getLinkedMediaObject($objectlink->id_media_object_link);
                        $linkedObjectData = $linkedObject->getDataForLanguage($language);
                        if($fieldConfig['field'] == 'name') {
                            $value = $linkedObject->name;
                        }elseif($fieldConfig['field'] == 'code') {
                            $value = $linkedObject->code;
                        }else{
                            $value = !empty($linkedObjectData->{$fieldConfig['field']}) ? $linkedObjectData->{$fieldConfig['field']} : null;
                        }
                        break;
                    }
                } else {
                    // Read directly from MediaObject
                    if($fieldConfig['field'] == 'name') {
                        $value = $this->mediaObject->name;
                    }else{
                        $value = !empty($data->{$fieldConfig['field']}) ? $data->{$fieldConfig['field']} : null;
                    }
                }
                
                // Apply filter if configured
                // Note: Filter is applied even if value is empty (null, empty array, etc.)
                // This allows filters to handle empty values or transform data structures
                if(isset($fieldConfig['filter']) && !empty($fieldConfig['filter'])){
                    $value = $this->_filterFunction($fieldConfig, $value, $agency);
                }
                
                // Use 'ZZZZZ' for null/empty values so they sort at the end
                $result[$shortname] = ($value !== null && ($stripped = strip_tags($value)) !== '') ? $stripped : 'ZZZZZ';
            }
        }
        
        return $result;
    }

    /**
     * @param string $language
     * @param string $agency
     * @return array
     * @throws \ReflectionException
     */
    private function _mapDescriptions($language, $agency = null)
    {
        $moc = $this->mediaObject->getDataForLanguage($language);
        $description = [];
        if(empty($moc) || empty($this->_config['search']['descriptions'][$this->mediaObject->id_object_type])){
            return $description;
        }
        $data = $moc->toStdClass();
        $description_map = $this->_config['search']['descriptions'][$this->mediaObject->id_object_type];
        foreach ($description_map as $index_name => $item_info) {
            $value = null;
            if(!empty($item_info['from']) && !empty($data->{$item_info['from']})){
                foreach ($data->{$item_info['from']} as $objectlink) {
                    $linkedObject = $this->_getLinkedMediaObject($objectlink->id_media_object_link);
                    $linkedObjectData = $linkedObject->getDataForLanguage($language);
                    if(empty($item_info['field']) === true){
                        Writer::write(
                            sprintf(
                                'MongoDB Indexer: MediaObject %d - Missing "field" key in descriptions config. ' .
                                'INDEX_NAME: "%s". id_object_type: %d. ' .
                                'CONFIG ENTRY: %s. ' .
                                'FIX: Add "field" => "fieldname" to search.descriptions.%d.%s in pm-config.php',
                                $this->mediaObject->id,
                                $index_name,
                                $this->mediaObject->id_object_type,
                                json_encode($item_info),
                                $this->mediaObject->id_object_type,
                                $index_name
                            ),
                            Writer::OUTPUT_FILE, 'mongodb_indexer', Writer::TYPE_WARNING
                        );
                        break;
                    }
                    if($item_info['field'] == 'name') {
                        $value = $linkedObject->name;
                    }else{
                        $value = !empty($linkedObjectData->{$item_info['field']}) ? $linkedObjectData->{$item_info['field']} : null;
                    }
                    break;
                }
            } else {
                if(empty($item_info['field']) === true){
                    Writer::write(
                        sprintf(
                            'MongoDB Indexer: MediaObject %d - Missing "field" key in descriptions config. ' .
                            'INDEX_NAME: "%s". id_object_type: %d. ' .
                            'CONFIG ENTRY: %s. ' .
                            'FIX: Add "field" => "fieldname" to search.descriptions.%d.%s in pm-config.php',
                            $this->mediaObject->id,
                            $index_name,
                            $this->mediaObject->id_object_type,
                            json_encode($item_info),
                            $this->mediaObject->id_object_type,
                            $index_name
                        ),
                        Writer::OUTPUT_FILE, 'mongodb_indexer', Writer::TYPE_WARNING
                    );
                    continue;
                }
                if($item_info['field'] == 'name') {
                    $value = $this->mediaObject->name;
                }else{
                    $value = !empty($data->{$item_info['field']}) ? $data->{$item_info['field']} : null;
                }
            }
            if(isset($item_info['filter']) && !empty ($item_info['filter'])) {
                $value = $this->_filterFunction($item_info, $value, $agency);
            }
            $description[$index_name] = $value;
        }

        return $description;
    }

    /**
     * @param array $item
     * @param mixed $first_param legacy
     * @param string $agency
     * @return void
     * @throws \ReflectionException
     */
    private function _filterFunction($item, $first_param = null, $agency = null){
        try {
            return $this->_callMethod($item['filter'], !empty($item['params']) ? $item['params'] : [], $first_param, $agency);
        } catch (\Exception $e) {
            echo 'Error in filter function ' .  $item['filter'] . ': ' . $e->getMessage();
            return false;
        }
    }

    /**
     * @param string $method
     * @param array $params
     * @param mixed $first_param legacy
     * @param mixed $agency
     * @return mixed
     * @throws \ReflectionException
     */
    private function _callMethod($method, $params = [], $first_param = 'undefined', $agency = null){
        $p = explode('::', $method);
        $Filter = new $p[0]();
        $Filter->mediaObject = $this->mediaObject;
        $Filter->agency = $agency;
        $ReflectionMethod = new \ReflectionMethod($method);
        $atts = [];
        if($first_param != 'undefined'){
            $atts = [$first_param];
        }
        if(!empty($params)){
            foreach($params as $name => $value){
                foreach($ReflectionMethod->getParameters() as $parameter){
                    if($parameter->getName() === $name){
                        $atts[] = $value;
                    }
                }
            }
        }
        return call_user_func_array([$Filter, $p[1]], $atts);
    }

    private function _mapGroups($language)
    {
        $moc = $this->mediaObject->getDataForLanguage($language);
        $groups = [];
        if(empty($moc) || empty($this->_config['search']['groups'][$this->mediaObject->id_object_type])){
            return $groups;
        }
        $data = $moc->toStdClass();
        $group_map = $this->_config['search']['groups'][$this->mediaObject->id_object_type];
        if(empty($group_map['field'])){
            $errorMsg = sprintf(
                'MongoDB Indexer: MediaObject %d - CRITICAL CONFIG ERROR. ' .
                'Missing "field" key in search.groups config for id_object_type %d. ' .
                'CURRENT CONFIG: %s. ' .
                'FIX: Add "field" => "brand" (or agencies/id_pool) to search.groups.%d in pm-config.php',
                $this->mediaObject->id,
                $this->mediaObject->id_object_type,
                json_encode($group_map),
                $this->mediaObject->id_object_type
            );
            Writer::write($errorMsg, Writer::OUTPUT_FILE, 'mongodb_indexer', Writer::TYPE_ERROR);
            throw new \Exception($errorMsg);
        }
        $field_name = $group_map['field'];
        if($field_name == 'agencies'){
            foreach($this->mediaObject->agencies as $agency){
                $groups[] = $agency->getId(). "";
            }
        }elseif($field_name == 'id_pool'){
            $groups[] = $this->mediaObject->id_pool ."";
        }elseif($field_name == 'brand'){
            if(empty($this->mediaObject->brand)){
                Writer::write(
                    sprintf(
                        'MongoDB Indexer: MediaObject %d - brand property is NULL but groups.field = "brand". ' .
                        'id_object_type: %d. ' .
                        'CHECK: Does this MediaObject have a brand assigned in Pressmind? ' .
                        'FIX: Assign a brand in Pressmind OR change search.groups.%d.field to another value (e.g. "agencies")',
                        $this->mediaObject->id,
                        $this->mediaObject->id_object_type,
                        $this->mediaObject->id_object_type
                    ),
                    Writer::OUTPUT_FILE, 'mongodb_indexer', Writer::TYPE_WARNING
                );
                return $groups;
            }
            $groups[] = $this->mediaObject->brand->id ."";
        }else{
            if(empty($data->$field_name)){
                return $groups;
            }
            if(is_array($data->$field_name)){
                foreach ($data->$field_name as $treeitem) {
                    $groups[] = HelperFunctions::human_to_machine($treeitem->item->name);
                }
            }
        }
        try {
            if(!empty($group_map['filter'])){
                $params = [$groups, $this->mediaObject];
                if(!empty($group_map['params']) && is_array($group_map['params'])){
                    $params = array_merge($params, $group_map['params']);
                }
                $groups = call_user_func_array($group_map['filter'], $params);
            }
        } catch (\Exception $e) {
            echo 'Error in filter function ' .  $group_map['filter'] . ': ' . $e->getMessage();
            exit; // @TODO
        }
        foreach($groups as $v){
            if(empty($v)){
                /**
                 * If this occurs and the group is build from "agencies", check these tables: pmt2core_agency_to_media_object, pmt2core_agencies
                 * it's possible that the agency is not correct mapped to the media object
                 * (if "pmt2core_agency_to_media_object.id_agency == 0 or pmt2core_agencies.id == 0", this is a actually a bug in the pressmind PIM)
                 * To solve it, remap the agency to the media object
                 */
                echo 'Warning: "'.$v.'" is not allowed as group value. Some product are not correct mapped and maybe not visible.'."\n";
            }
        }
        return $groups;
    }


    /**
     * @param $language
     * @return \stdClass
     */
    private function _mapLocations($language)
    {
        $moc = $this->mediaObject->getDataForLanguage($language);
        $locations = new \stdClass();
        if(empty($moc) || empty($this->_config['search']['locations'][$this->mediaObject->id_object_type])){
            return $locations;
        }
        $data = $moc->toStdClass();
        $location_fields = array_keys($this->_config['search']['locations'][$this->mediaObject->id_object_type]);
        foreach($location_fields as $field){
            $geojson = new \stdClass();
            $geojson->type = 'MultiPoint';
            $geojson->coordinates = [];
            if(empty($data->$field) || !is_array($data->$field) ){
                continue;
            }
            foreach($data->$field as $location){
                $geojson->coordinates[] = [$location->lng, $location->lat];
            }
            $locations->$field = $geojson;
        }
        return $locations;
    }

    /**
     * @return array
     */
    private function _mapCategories($language) {
        $categories = [];
        $moc = $this->mediaObject->getDataForLanguage($language);
        if(empty($moc) || empty($this->_config['search']['categories'][$this->mediaObject->id_object_type])){
            return $categories;
        }
        $data = $moc->toStdClass();
        $categories_map = $this->_config['search']['categories'][$this->mediaObject->id_object_type];
        foreach ($categories_map as $field => $additionalInfo) {
            if(!empty($additionalInfo['aggregation']['method'])){
                $aggregated_result = $this->_callMethod($additionalInfo['aggregation']['method'], $additionalInfo['aggregation']['params']);
                if(is_array($aggregated_result)){
                    $categories = array_merge($categories, $aggregated_result);
                }
                continue;
            }
            $type = 'categorytree';
            $object_link_field = null;
            $filter = null;
            $varName = $field;
            if(!empty($additionalInfo['field'])){
                $varName = $additionalInfo['field'];
            }
            if(!empty($additionalInfo['from'])){
                $type = 'categorytree_from_objectlink'; // legacy
                $object_link_field = $additionalInfo['from'];
            }
            if(!empty($additionalInfo['type'])){
                $type = $additionalInfo['type'];
            }
            if(!empty($additionalInfo['virtual_id_tree'])){
                $virtual_id_tree = $additionalInfo['virtual_id_tree'];
            }
            if(!empty($additionalInfo['filter'])){
                $filter = $additionalInfo['filter'];
            }
            if($type == 'categorytree') {
                if(!empty($data->$varName) && is_array($data->$varName)) {
                    foreach ($data->$varName as $treeitem) {
                        if(empty($treeitem->item->id)){
                            continue;
                        }
                        $stdItem = new \stdClass();
                        $stdItem->id_item = $treeitem->item->id;
                        $stdItem->name = $treeitem->item->name;
                        $stdItem->id_tree = $treeitem->item->id_tree;
                        $stdItem->id_parent = $treeitem->item->id_parent;
                        $stdItem->code = $treeitem->item->code;
                        $stdItem->sort = $treeitem->item->sort;
                        $stdItem->field_name = !empty($additionalInfo['field']) ? $field : $varName;
                        $stdItem->level = self::getTreeDepth($data->$varName, $treeitem->id_item);
                        $stdItem->path_str = self::getTreePath($data->$varName, $treeitem->id_item, 'name');
                        krsort($stdItem->path_str);
                        $stdItem->path_ids = self::getTreePath($data->$varName, $treeitem->id_item, 'id');
                        krsort($stdItem->path_ids);
                        $categories[] = (array)$stdItem;
                    }
                }
            } elseif($type == 'categorytree_from_objectlink') {
                foreach ($this->_mapCategoriesFromObjectLinks($object_link_field, $varName, $field, $language) as $linkedCategory) {
                    $categories[] = $linkedCategory;
                }
            }elseif($type == 'plaintext_from_objectlink') {
                foreach ($this->_mapPlaintextFromObjectLinks($object_link_field, $varName, $field, $virtual_id_tree, $language, $filter) as $linkedCategory) {
                    $categories[] = $linkedCategory;
                }
            }
        }

        return $categories;
    }

    /**
     * @param array $serialized_list
     * @param string $id
     * @param int $level
     * @return int
     */
    public static function getTreeDepth($serialized_list, $id, $level = 0){
        foreach($serialized_list as $item){
            if(!empty($item->item->id) && $item->item->id == $id && !empty($item->item->id_parent)){
                $level++;
                return self::getTreeDepth($serialized_list, $item->item->id_parent, $level);
            }
        }
        return $level;
    }

    /**
     * @param array $serialized_list
     * @param string $id
     * @param string $key
     * @param array $path
     * @return array
     */
    public static function getTreePath($serialized_list, $id, $key, $path = []){
        foreach($serialized_list as $item){
            if(empty($item->item->id)){
                continue;
            }
            if($item->item->id == $id){
                $path[] = $item->item->{$key};
                return self::getTreePath($serialized_list, $item->item->id_parent, $key, $path);
            }
        }
        return $path;
    }

    /**
     * @param $varName
     * @param $plaintextVarName
     * @param $field_name
     * @param $virtual_id_tree
     * @param $language
     * @return array
     * @throws \Exception
     */
    private function _mapPlaintextFromObjectLinks($varName, $plaintextVarName, $field, $virtual_id_tree, $language, $filter) {
        if(empty($virtual_id_tree)){
            throw new \Exception('virtual_id_tree is required for plaintext_from_objectlink');
        }
        if(empty($field)){
            throw new \Exception('field is required for plaintext_from_objectlink');
        }
        $data = $this->mediaObject->getDataForLanguage($language);
        $categories = [];
        if(is_array($data->$varName)){
            foreach ($data->$varName as $objectlink) {
                $linkedObject = $this->_getLinkedMediaObject($objectlink->id_media_object_link);
                $linkedObjectData = $linkedObject->getDataForLanguage($language);
                if(!is_null($linkedObjectData) ) {
                    $stdItem = new \stdClass();
                    $stdItem->name = $plaintextVarName == 'name' ? $linkedObject->name : $linkedObjectData->$plaintextVarName;
                    if(!empty($filter)){
                        $r = $this->_filterFunction([
                            'filter' => $filter,
                            'params' => [
                                'mediaObject' => $linkedObject
                            ]
                        ],
                            $stdItem->name
                        );
                        if(!empty($r)){
                            $stdItem->name = $r;
                        }
                    }
                    $stdItem->id_item = md5($objectlink->id_media_object_link.'-'.$varName.'-'.$plaintextVarName.'-'.$stdItem->name.'-'.$virtual_id_tree);
                    $stdItem->id_tree = $virtual_id_tree;
                    $stdItem->id_parent = null;
                    $stdItem->id_media_object = $objectlink->id_media_object_link;
                    $stdItem->field_name = $field;
                    $stdItem->level = 0;
                    $stdItem->path_str = [$stdItem->name];
                    $stdItem->path_ids = [$stdItem->id_item];
                    $categories[] = (array)$stdItem;
                }
            }
        }
        return $categories;
    }

    /**
     * @param $varName
     * @param $categoryVarName
     * @param $field
     * @param $language
     * @return array
     * @throws \Exception
     */
    private function _mapCategoriesFromObjectLinks($varName, $categoryVarName, $field, $language) {
        $data = $this->mediaObject->getDataForLanguage($language);
        $categories = [];
        if(is_array($data->$varName)){
            foreach ($data->$varName as $objectlink) {
                $linkedObject = $this->_getLinkedMediaObject($objectlink->id_media_object_link);
                $linkedObjectData = $linkedObject->getDataForLanguage($language);
                if(!is_null($linkedObjectData) && is_array($linkedObjectData->$categoryVarName)) {
                    foreach ($linkedObjectData->$categoryVarName as $treeitem) {
                        $stdItem = new \stdClass();
                        $stdItem->id_item = $treeitem->item->id;
                        $stdItem->name = $treeitem->item->name;
                        $stdItem->id_tree = $treeitem->item->id_tree;
                        $stdItem->id_parent = $treeitem->item ->id_parent;
                        $stdItem->field_name = $field;
                        $stdItem->level = self::getTreeDepth($linkedObjectData->$categoryVarName, $treeitem->id_item);
                        $stdItem->path_str = self::getTreePath($linkedObjectData->$categoryVarName, $treeitem->id_item, 'name');
                        krsort($stdItem->path_str);
                        $stdItem->path_ids = self::getTreePath($linkedObjectData->$categoryVarName, $treeitem->id_item, 'id');
                        krsort($stdItem->path_ids);
                        $categories[] = (array)$stdItem;
                    }
                }
            }
        }
        return $categories;
    }


    /**
     * @param $origin
     * @param $agency
     * @return bool
     * @throws \Exception
     */
    private function _soldOut($origin, $agency = null)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $query = "select distinct state from pmt2core_cheapest_price_speed where 
                         id_media_object = :id_media_object AND
                         id_origin = :id_origin".(empty($agency) ? "" : " AND agency = :agency");
        $values = [
            ':id_media_object' => $this->mediaObject->id,
            ':id_origin' => $origin,
        ];
        if(!empty($agency)){
            $values[':agency'] = $agency;
        }
        $results = $db->fetchAll($query, $values);
        $sold_out = true;
        if(!is_null($results)) {
            foreach($results as $result) {
                if($result->state === CheapestPrice::STATE_BOOKABLE || $result->state === CheapestPrice::STATE_REQUEST){
                    $sold_out = false;
                }
            }
        }
        return $sold_out;
    }

    private function _getDeparturePorts()
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $query = "select p.id, p.name from pmt2core_itinerary_steps s
                    left join pmt2core_itinerary_step_ports sp on (s.id = sp.id_step)
                    left join pmt2core_ports p on (sp.id_port = p.id)
                    where s.id_media_object = :id_media_object
                          and s.order = 0
                          and (s.type = 'course_port' OR s.type = 'port')
                    limit 1;";
        $values = [
            ':id_media_object' => $this->mediaObject->id,
        ];
        $results = $db->fetchAll($query, $values);
        $ports = [];
        if(!is_null($results)) {
            foreach($results as $result) {
                if(empty($result->id)){
                    continue;
                }
                $port = new \stdClass();
                $port->id = $result->id;
                $port->name = $result->name;
                $ports[] = $port;
            }
        }
        return $ports;
    }

    /**
     * Notice: if the itinerary has no port in the last step,
     * @return array
     * @throws \Exception
     */
    private function _getArrivalPorts()
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $query = "select p.id, p.name from pmt2core_itinerary_steps s
                    left join pmt2core_itinerary_step_ports sp on (s.id = sp.id_step)
                    left join pmt2core_ports p on (sp.id_port = p.id)
                    where s.id_media_object = :id_media_object
                        and s.order = (select MAX(`order`) from pmt2core_itinerary_steps where id_media_object = :id_media_object and (type = 'course_port' OR type = 'port'))
                        order by sp.id DESC -- missing order attribute
                    limit 1;";
        $values = [
            ':id_media_object' => $this->mediaObject->id,
        ];
        $results = $db->fetchAll($query, $values);
        $ports = [];
        if(!is_null($results)) {
            foreach($results as $result) {
                if(empty($result->id)){
                    continue;
                }
                $port = new \stdClass();
                $port->id = $result->id;
                $port->name = $result->name;
                $ports[] = $port;
            }
        }
        return $ports;
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function _getPorts()
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $query = "select p.id, p.name from pmt2core_itinerary_steps s
                    left join pmt2core_itinerary_step_ports sp on (s.id = sp.id_step)
                    left join pmt2core_ports p on (sp.id_port = p.id)
                    where s.id_media_object = :id_media_object and (s.type = 'course_port' OR s.type = 'port')";
        $values = [
            ':id_media_object' => $this->mediaObject->id,
        ];
        $results = $db->fetchAll($query, $values);
        $ports = [];
        if(!is_null($results)) {
            foreach($results as $result) {
                if(empty($result->id)){
                    continue;
                }
                $port = new \stdClass();
                $port->id = $result->id;
                $port->name = $result->name;
                $ports[$port->id] = $port;
            }
        }
        return array_values($ports);
    }

    /**
     * @param $origin
     * @param $agency
     * @return bool
     * @throws \Exception
     */
    private function _isRunning($origin, $agency = null)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $query = "select * from pmt2core_cheapest_price_speed where id_media_object = :id_media_object and current_date BETWEEN date_departure AND date_arrival and id_origin = :id_origin".(empty($agency) ? "" : " AND agency = :agency");
        $values = [
            ':id_media_object' => $this->mediaObject->id,
            ':id_origin' => $origin,
        ];
        if(!empty($agency)){
            $values[':agency'] = $agency;
        }
        $results = $db->fetchAll($query, $values);
        if(!is_null($results) && count($results) > 0) {
            return true;
        }
        return false;
    }

    /**
     * Builds a SQL CASE statement for duration bucket partitioning
     * @param string $column Column name to use in CASE
     * @return string SQL CASE statement
     */
    private function _buildDurationBucketCase($column = 'duration')
    {
        $config = $this->_config['search']['touristic'];
        $cases = [];
        foreach ($config['duration_ranges'] as $idx => $range) {
            $cases[] = "WHEN {$column} BETWEEN {$range[0]} AND {$range[1]} THEN " . ($idx + 1);
        }
        return "CASE " . implode(' ', $cases) . " END";
    }

    /**
     * Aggregate prices for MongoDB index - consolidated version (2 queries instead of 21)
     * @param int $origin
     * @param string|null $agency
     * @return array
     * @throws \Exception
     */
    private function _aggregatePrices($origin, $agency = null)
    {
        $config = $this->_config['search']['touristic'];
        $prices = [];
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        
        $durationBucketCase = $this->_buildDurationBucketCase();
        $occupancyList = implode(',', $config['occupancies']);
        
        // Query 1: price_mix = 'date_housing' - all occupancies and duration ranges in one query
        $query = "SELECT 
                    option_occupancy as occupancy,
                    option_occupancy_child as occupancy_child,
                    group_concat(date_departure) as date_departures,
                    max(duration) as duration,
                    price_total,
                    price_regular_before_discount,
                    earlybird_discount,
                    earlybird_discount_f,
                    earlybird_discount_date_to,
                    earlybird_name,
                    option_name,
                    option_board_type,
                    price_mix,
                    transport_type,
                    guaranteed,
                    startingpoint_id_city,
                    startingpoint_city,
                    housing_package_name,
                    housing_package_id_name,
                    quota_pax,
                    CASE
                        WHEN state = 3 THEN 100
                        WHEN state = 1 THEN 200
                        WHEN state = 5 THEN 300
                        ELSE 300
                    END AS state
                FROM (
                    SELECT state,
                        option_occupancy,
                        option_occupancy_child, 
                        date_departure,
                        price_total,
                        price_regular_before_discount,
                        earlybird_discount,
                        earlybird_discount_f,
                        earlybird_discount_date_to,
                        earlybird_name,
                        option_name,
                        option_board_type,
                        price_mix,
                        transport_type,
                        guaranteed,
                        duration,
                        startingpoint_city,
                        startingpoint_id_city,
                        housing_package_name,
                        housing_package_id_name,
                        quota_pax,
                        ROW_NUMBER() OVER (
                            PARTITION BY date_departure, option_occupancy, {$durationBucketCase}
                            ".(empty($this->_config_touristic['generate_offer_for_each_transport_type']) ? "" : ", transport_type")."
                            ".(empty($this->_config_touristic['generate_offer_for_each_startingpoint_option']) ? "" : ", startingpoint_id_city")."
                            ".(empty($this->_config_touristic['generate_offer_for_each_option_board_type']) ? "" : ", option_board_type")."
                            ORDER BY FIELD(state, 3, 1, 5, 0), price_total ASC
                        ) AS r
                    FROM pmt2core_cheapest_price_speed
                    WHERE id_media_object = :id_media_object 
                        AND id_origin = :id_origin
                        ".(empty($agency) ? "" : " AND agency = :agency")."
                        AND (earlybird_discount = 0 OR earlybird_discount_date_to >= NOW())
                        AND option_occupancy IN ({$occupancyList})
                        AND price_mix = 'date_housing'
                ) as t
                WHERE r = 1
                GROUP BY option_occupancy, {$durationBucketCase}, price_total"
                .(empty($this->_config_touristic['generate_offer_for_each_startingpoint_option']) ? "" : ", startingpoint_id_city");
        
        $values = [
            ':id_media_object' => $this->mediaObject->id,
            ':id_origin' => $origin,
        ];
        if (!empty($agency)) {
            $values[':agency'] = $agency;
        }
        
        $results = $db->fetchAll($query, $values);
        if (!is_null($results)) {
            foreach ($results as $result) {
                $date_departures = array_unique(explode(',', $result->date_departures));
                asort($date_departures);
                $formatted_date_departures = [];
                $formatted_guaranteed_departures = [];
                foreach ($date_departures as $k => $date_departure) {
                    $date = \DateTime::createFromFormat('Y-m-d H:i:s', $date_departure);
                    if (empty($date)) {
                        echo 'error: date is not valid'; // check group_concat max size see bootstrap.php
                        break(1);
                    }
                    $formatted_date_departures[] = $date->format(DATE_RFC3339_EXTENDED);
                    if (!empty($result->guaranteed)) {
                        $formatted_guaranteed_departures[] = $date->format(DATE_RFC3339_EXTENDED);
                    }
                }
                $result->date_departures = $formatted_date_departures;
                unset($result->guaranteed);
                $result->guaranteed_departures = $formatted_guaranteed_departures;
                $result->occupancy = intval($result->occupancy);
                $result->occupancy_child = intval($result->occupancy_child);
                $result->duration = floatval($result->duration);
                $result->price_total = floatval($result->price_total);
                $result->price_regular_before_discount = floatval($result->price_regular_before_discount);
                $result->earlybird_discount = floatval($result->earlybird_discount);
                $result->earlybird_discount_f = floatval($result->earlybird_discount_f);
                $result->quota_pax = intval($result->quota_pax);
                $result->state = intval($result->state);
                if (!empty($result->startingpoint_id_city)) {
                    $result->startingpoint_option = new \stdClass();
                    $result->startingpoint_option->id_city = $result->startingpoint_id_city;
                    $result->startingpoint_option->city = $result->startingpoint_city;
                }
                unset($result->startingpoint_id_city);
                unset($result->startingpoint_city);
                $prices[] = $result;
            }
        }
        
        // Query 2: price_mix != 'date_housing' - all duration ranges in one query
        $query = "SELECT 
                    option_occupancy as occupancy,
                    group_concat(date_departure) as date_departures,
                    max(duration) as duration,
                    price_total,
                    price_regular_before_discount,
                    earlybird_discount,
                    earlybird_discount_f,
                    earlybird_discount_date_to,
                    earlybird_name,
                    option_name,
                    option_board_type,
                    price_mix,
                    transport_type,
                    guaranteed,
                    startingpoint_city,
                    startingpoint_id_city,
                    housing_package_name,
                    housing_package_id_name,
                    quota_pax,
                    CASE
                        WHEN state = 3 THEN 100
                        WHEN state = 1 THEN 200
                        WHEN state = 5 THEN 300
                        ELSE 300
                    END AS state
                FROM (
                    SELECT state,
                        option_occupancy,
                        date_departure,
                        price_total,
                        price_regular_before_discount,
                        earlybird_discount,
                        earlybird_discount_f,
                        earlybird_discount_date_to,
                        earlybird_name,
                        option_name,
                        option_board_type,
                        price_mix,
                        transport_type,
                        guaranteed,
                        duration,
                        startingpoint_city,
                        startingpoint_id_city,
                        housing_package_name,
                        housing_package_id_name,
                        quota_pax,
                        ROW_NUMBER() OVER (
                            PARTITION BY date_departure, {$durationBucketCase}
                            ".(empty($this->_config_touristic['generate_offer_for_each_transport_type']) ? "" : ", transport_type")."
                            ".(empty($this->_config_touristic['generate_offer_for_each_startingpoint_option']) ? "" : ", startingpoint_id_city")."
                            ".(empty($this->_config_touristic['generate_offer_for_each_option_board_type']) ? "" : ", option_board_type")."
                            ORDER BY FIELD(state, 3, 1, 5, 0), price_total ASC
                        ) AS r
                    FROM pmt2core_cheapest_price_speed
                    WHERE id_media_object = :id_media_object 
                        AND id_origin = :id_origin
                        ".(empty($agency) ? "" : " AND agency = :agency")."
                        AND (earlybird_discount = 0 OR earlybird_discount_date_to >= NOW())
                        AND price_mix != 'date_housing'
                ) as t
                WHERE r = 1
                GROUP BY {$durationBucketCase}, price_total"
                .(empty($this->_config_touristic['generate_offer_for_each_startingpoint_option']) ? "" : ", startingpoint_id_city");
        
        $values = [
            ':id_media_object' => $this->mediaObject->id,
            ':id_origin' => $origin,
        ];
        if (!empty($agency)) {
            $values[':agency'] = $agency;
        }
        
        $results = $db->fetchAll($query, $values);
        $used_departures = [];
        if (!is_null($results)) {
            foreach ($results as $result) {
                $date_departures = array_unique(explode(',', $result->date_departures));
                asort($date_departures);
                $formatted_date_departures = [];
                $formatted_guaranteed_departures = [];
                foreach ($date_departures as $k => $date_departure) {
                    if (in_array($date_departure, $used_departures)) {
                        continue;
                    }
                    $used_departures[] = $date_departure;
                    $date = \DateTime::createFromFormat('Y-m-d H:i:s', $date_departure);
                    if (empty($date)) {
                        echo 'error: date is not valid';
                        break(1);
                    }
                    $formatted_date_departures[] = $date->format(DATE_RFC3339_EXTENDED);
                    if (!empty($result->guaranteed)) {
                        $formatted_guaranteed_departures[] = $date->format(DATE_RFC3339_EXTENDED);
                    }
                }
                $result->date_departures = $formatted_date_departures;
                unset($result->guaranteed);
                $result->guaranteed_departures = $formatted_guaranteed_departures;
                $result->occupancy = null;
                $result->occupancy_child = null;
                $result->duration = floatval($result->duration);
                $result->price_total = floatval($result->price_total);
                $result->price_regular_before_discount = floatval($result->price_regular_before_discount);
                $result->earlybird_discount = floatval($result->earlybird_discount);
                $result->earlybird_discount_f = floatval($result->earlybird_discount_f);
                $result->quota_pax = intval($result->quota_pax);
                $result->state = intval($result->state);
                if (!empty($result->startingpoint_city)) {
                    $result->startingpoint_option = new \stdClass();
                    $result->startingpoint_option->id_city = $result->startingpoint_id_city;
                    $result->startingpoint_option->city = $result->startingpoint_city;
                }
                unset($result->startingpoint_id_city);
                unset($result->startingpoint_city);
                $prices[] = $result;
            }
        }
        
        usort($prices, [$this, '_priceSort']);
        return $prices;
    }

    /**
     * @deprecated Use _aggregatePrices() instead. This legacy method executes 21 queries.
     *             Kept as fallback for worst-case scenarios.
     * @param int $origin
     * @param string $agency
     * @return array
     * @throws \Exception
     */
    private function _aggregatePricesLegacy($origin, $agency = null)
    {
        $config = $this->_config['search']['touristic'];
        $prices = [];
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        // price mix date_housing
        foreach ($config['occupancies'] as $occupancy) {
            foreach ($config['duration_ranges'] as $duration_range) {
                $query = "select 
                                  option_occupancy as occupancy,
                                  option_occupancy_child as occupancy_child,
                                  group_concat(date_departure) as date_departures,
                                  max(duration) as duration,
                                  price_total,
                                  price_regular_before_discount,
                                  earlybird_discount,
                                  earlybird_discount_f,
                                  earlybird_discount_date_to,
                                  earlybird_name,
                                  option_name,
                                  option_board_type,
                                  price_mix,
                                  transport_type,
                                  guaranteed,
                                  startingpoint_id_city,
                                  startingpoint_city,
                                  housing_package_name,
                                  housing_package_id_name,
                                  quota_pax,
                                  CASE
                                    WHEN state = 3 THEN 100
                                    WHEN state = 1 THEN 200
                                    WHEN state = 5 THEN 300
                                  ELSE 300
                                  END AS state
                            from (SELECT state,
                                         option_occupancy,
                                         option_occupancy_child, 
                                         date_departure,
                                         price_total,
                                         price_regular_before_discount,
                                         earlybird_discount,
                                         earlybird_discount_f,
                                         earlybird_discount_date_to,
                                         earlybird_name,
                                         option_name,
                                         option_board_type,
                                         price_mix,
                                         transport_type,
                                         guaranteed,
                                         duration,
                                         startingpoint_city,
                                         startingpoint_id_city,
                                         housing_package_name,
                                         housing_package_id_name,
                                         quota_pax,
                                         ROW_NUMBER() OVER (PARTITION BY date_departure
                                             ".(empty($this->_config_touristic['generate_offer_for_each_transport_type']) ? "" : ", transport_type")."
                                             ".(empty($this->_config_touristic['generate_offer_for_each_startingpoint_option']) ? "" : ", startingpoint_id_city")."
                                             ".(empty($this->_config_touristic['generate_offer_for_each_option_board_type']) ? "" : ", option_board_type")."
                                             ORDER BY FIELD(state, 3, 1, 5, 0), price_total ASC) AS r
                                  from pmt2core_cheapest_price_speed
                                  where   
                                      id_media_object = :id_media_object 
                                    AND id_origin = :id_origin
                                    ".(empty($agency) ? "" : " AND agency = :agency")."
                                    AND (earlybird_discount = 0 OR earlybird_discount_date_to >= NOW())
                                    AND(duration BETWEEN :duration_range_from AND :duration_range_to) 
                                    AND (option_occupancy = :occupancy ) 
                                    AND price_mix = 'date_housing') as t
                            where r = 1
                            group by price_total".(empty($this->_config_touristic['generate_offer_for_each_startingpoint_option']) ? "" : ", startingpoint_id_city").";";
                $values = [
                    ':id_media_object' => $this->mediaObject->id,
                    ':id_origin' => $origin,
                    ':duration_range_from' => $duration_range[0],
                    ':duration_range_to' => $duration_range[1].'.9',
                    ':occupancy' => $occupancy,
                ];
                if(!empty($agency)){
                    $values[':agency'] = $agency;
                }
                $results = $db->fetchAll($query, $values);
                if(!is_null($results)) {
                    foreach($results as $result) {
                        $date_departures = array_unique(explode(',', $result->date_departures));
                        asort($date_departures);
                        $formatted_date_departures = [];
                        $formatted_guaranteed_departures = [];
                        foreach ($date_departures as $k => $date_departure) {
                            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $date_departure);
                            if (empty($date)) {
                                echo 'error: date is not valid'; // check group_concat max size see bootstrap.php
                                break(1);
                            }
                            $formatted_date_departures[] = $date->format(DATE_RFC3339_EXTENDED);
                            if(!empty($result->guaranteed)){
                                $formatted_guaranteed_departures[] = $date->format(DATE_RFC3339_EXTENDED);
                            }
                        }
                        $result->date_departures = $formatted_date_departures;
                        unset($result->guaranteed);
                        $result->guaranteed_departures = $formatted_guaranteed_departures;
                        $result->occupancy = intval($result->occupancy);
                        $result->occupancy_child = intval($result->occupancy_child);
                        $result->duration = floatval($result->duration);
                        $result->price_total = floatval($result->price_total); // @TODO pseudo price handling is missing
                        $result->price_regular_before_discount = floatval($result->price_regular_before_discount);
                        $result->earlybird_discount = floatval($result->earlybird_discount);
                        $result->earlybird_discount_f = floatval($result->earlybird_discount_f);
                        $result->quota_pax = intval($result->quota_pax);
                        if(!empty($result->startingpoint_id_city)){
                            $result->startingpoint_option = new \stdClass();
                            $result->startingpoint_option->id_city = $result->startingpoint_id_city;
                            $result->startingpoint_option->city = $result->startingpoint_city;
                        }
                        unset($result->startingpoint_id_city);
                        unset($result->startingpoint_city);
                        $prices[] = $result;
                    }
                }
            }
        }
        // price mixes NOT date_housing, this price_mix type doesn't have a occupancy!
        foreach ($config['duration_ranges'] as $duration_range) {
            $query = "select option_occupancy as occupancy,
                                 group_concat(date_departure) as date_departures,
                                  max(duration) as duration,
                                  price_total,
                                  price_regular_before_discount,
                                  earlybird_discount,
                                  earlybird_discount_f,
                                  earlybird_discount_date_to,
                                  earlybird_name,
                                  option_name,
                                  option_board_type,
                                  price_mix,
                                  transport_type,
                                  guaranteed,
                                  startingpoint_city,
                                  startingpoint_id_city,
                                  housing_package_name,
                                  housing_package_id_name,
                                  quota_pax,
                                  CASE
                                    WHEN state = 3 THEN 100
                                    WHEN state = 1 THEN 200
                                    WHEN state = 5 THEN 300
                                  ELSE 300
                                  END AS state
                            from (SELECT state,
                                         option_occupancy,
                                         date_departure,
                                         price_total,
                                         price_regular_before_discount,
                                         earlybird_discount,
                                         earlybird_discount_f,
                                         earlybird_discount_date_to,
                                         earlybird_name,
                                         option_name,
                                         option_board_type,
                                         price_mix,
                                         transport_type,
                                         guaranteed,
                                         duration,
                                         startingpoint_city,
                                         startingpoint_id_city,
                                         housing_package_name,
                                         housing_package_id_name,
                                         quota_pax,
                                         ROW_NUMBER() OVER (PARTITION BY date_departure
                                         ".(empty($this->_config_touristic['generate_offer_for_each_transport_type']) ? "" : ", transport_type")."
                                         ".(empty($this->_config_touristic['generate_offer_for_each_startingpoint_option']) ? "" : ", startingpoint_id_city")."
                                         ".(empty($this->_config_touristic['generate_offer_for_each_option_board_type']) ? "" : ", option_board_type")."
                                         ORDER BY FIELD(state, 3, 1, 5, 0), price_total ASC) AS r
                                  from pmt2core_cheapest_price_speed
                                  where   
                                      id_media_object = :id_media_object 
                                    AND id_origin = :id_origin
                                    ".(empty($agency) ? "" : " AND agency = :agency")."
                                    AND (earlybird_discount = 0 OR earlybird_discount_date_to >= NOW())
                                    AND(duration BETWEEN :duration_range_from AND :duration_range_to) 
                                    AND price_mix != 'date_housing') as t
                            where r = 1
                            group by price_total".(empty($this->_config_touristic['generate_offer_for_each_startingpoint_option']) ? "" : ", startingpoint_id_city").";";
            $values = [
                ':id_media_object' => $this->mediaObject->id,
                ':id_origin' => $origin,
                ':duration_range_from' => $duration_range[0],
                ':duration_range_to' => $duration_range[1].'.9',
            ];
            if(!empty($agency)){
                $values[':agency'] = $agency;
            }
            $results = $db->fetchAll($query, $values);
            $used_departures = [];
            if(!is_null($results)) {
                foreach($results as $result){
                    $date_departures = array_unique(explode(',', $result->date_departures));
                    asort($date_departures);
                    $formatted_date_departures = [];
                    $formatted_guaranteed_departures = [];
                    foreach ($date_departures as $k => $date_departure){
                        if(in_array($date_departure, $used_departures) ){
                            continue;
                        }
                        $used_departures[] = $date_departure;
                        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $date_departure);
                        if(empty($date)){
                            echo 'error: date is not valid';
                            break(1);
                        }
                        $formatted_date_departures[] = $date->format(DATE_RFC3339_EXTENDED);
                        if(!empty($result->guaranteed)){
                            $formatted_guaranteed_departures[] = $date->format(DATE_RFC3339_EXTENDED);
                        }
                    }
                    $result->date_departures = $formatted_date_departures;
                    unset($result->guaranteed);
                    $result->guaranteed_departures = $formatted_guaranteed_departures;
                    $result->occupancy = null;
                    $result->occupancy_child = null;
                    $result->duration = floatval($result->duration);
                    $result->price_total = floatval($result->price_total);
                    $result->price_regular_before_discount = floatval($result->price_regular_before_discount);
                    $result->earlybird_discount = floatval($result->earlybird_discount);
                    $result->earlybird_discount_f = floatval($result->earlybird_discount_f);
                    $result->quota_pax = intval($result->quota_pax);
                    if(!empty($result->startingpoint_city)){
                        $result->startingpoint_option = new \stdClass();
                        $result->startingpoint_option->id_city = $result->startingpoint_id_city;
                        $result->startingpoint_option->city = $result->startingpoint_city;
                    }
                    unset($result->startingpoint_id_city);
                    unset($result->startingpoint_city);
                    $prices[] = $result;
                }
            }
        }
        usort($prices, [$this, '_priceSort']);
        return $prices;
    }

    /**
     * @param $pricea
     * @param $priceb
     * @return int
     */
    private function _priceSort($pricea, $priceb) {
        return $priceb->price_total <=> $pricea->price_total;
    }

    /**
     * @param int $origin
     * @param string $agency
     * @return array
     * @return integer|null
     * @throws \Exception
     */
    private function _createDepartureDateCount($origin, $agency = null)
    {
        $query = "SELECT COUNT(DISTINCT(date_departure)) as departure_date_count from pmt2core_cheapest_price_speed WHERE id_media_object = ? AND id_origin = ?";
        $values = [$this->mediaObject->id, $origin];
        if(!empty($agency)) {
            $query .= " AND agency = ?";
            $values[] = $agency;
        }
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');

        $result = $db->fetchRow($query, $values);

        return !is_null($result) ? intval($result->departure_date_count) : null;
    }

    /**
     * Get all distinct durations from the database for logging purposes.
     * Used to show the actual duration values when price aggregation fails.
     * @param int $origin
     * @param string|null $agency
     * @return array
     * @throws \Exception
     */
    private function _getActualDurationsFromDb($origin, $agency = null)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $query = "SELECT DISTINCT duration FROM pmt2core_cheapest_price_speed 
                  WHERE id_media_object = ? AND id_origin = ?";
        $values = [$this->mediaObject->id, $origin];
        if (!empty($agency)) {
            $query .= " AND agency = ?";
            $values[] = $agency;
        }
        $query .= " ORDER BY duration";
        $results = $db->fetchAll($query, $values);
        return $results ? array_map(function($r) { return (int)$r->duration; }, $results) : [];
    }

    /**
     * @param $origin
     * @param $agency
     * @return array
     * @throws \Exception
     */
    private function _createDatesPerMonth($origin, $agency = null)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $query = "SELECT cast(date_format(date_departure, '%Y') as SIGNED) as year, 
                         cast(date_format(date_departure, '%c') as SIGNED) as month 
                    FROM pmt2core_cheapest_price_speed 
                    WHERE id_media_object = :id_media_object 
                        AND id_origin = :id_origin ".(empty($agency) ? "" : " AND agency = :agency")." 
                        GROUP BY year, month ORDER BY year ASC, month ASC";
        $values = [
            ':id_media_object' => $this->mediaObject->id,
            ':id_origin' => $origin,
        ];
        if(!empty($agency)){
            $values[':agency'] = $agency;
        }
        $result = $db->fetchAll($query, $values);
        $years = [];
        if (is_array($result)) {
            foreach ($result as $item) {
                if(!isset($years[$item->year])) $years[$item->year] = [];
                $years[$item->year][] = $item->month;
            }
        }
        $objects = [];
        foreach ($years as $year => $months) {
            foreach ($months as $month) {
                $object = new \stdClass();
                $object->year = (int)$year;
                $object->month = (int)$month;
                $date = new \DateTime($year . '-' . $month . '-01');
                $max_days = $date->format('t');
                $query = "select * from (select date_departure,
                            date_arrival,
                            state,
                            option_occupancy_min,
                            option_occupancy_max,
                            option_occupancy,
                            duration,
                            price_total,
                            price_regular_before_discount,
                            earlybird_discount,
                            earlybird_discount_f,
                            earlybird_discount_date_to,
                            guaranteed,
                            ROW_NUMBER() OVER (PARTITION BY date_departure ORDER BY FIELD(state, 3, 1, 5, 0), FIELD(option_occupancy, 2, 1, 3, 4, 5, 6, 8, 9, 10) ASC, date_departure ASC, duration ASC) AS r
                          from pmt2core_cheapest_price_speed
                          where 
                            date_departure BETWEEN :departure_from AND :departure_to
                            AND id_media_object = :id_media_object
                            AND id_origin = :id_origin 
                            ".(empty($agency) ? "" : " AND agency = :agency"). " 
                          ) as t
                            where r = 1
                            GROUP BY date_departure, duration";
                $values = [
                    ':id_media_object' => $this->mediaObject->id,
                    ':id_origin' => $origin,
                    ':departure_from' => $year . "-" . $month . "-01",
                    ':departure_to' => $year . "-" . $month . "-" . $max_days,
                ];
                if(!empty($agency)){
                    $values[':agency'] = $agency;
                }
                $result = $db->fetchAll($query, $values);
                $date_list = [];
                $c = 0;
                foreach($result as $v){
                    $v->price_total = (float)$v->price_total;
                    $v->price_regular_before_discount = (float)$v->price_regular_before_discount;
                    $v->earlybird_discount = (float)$v->earlybird_discount;
                    $v->earlybird_discount_f = (float)$v->earlybird_discount_f;
                    $v->option_occupancy_min = (int)$v->option_occupancy_min;
                    $v->option_occupancy_max = (int)$v->option_occupancy_max;
                    $v->option_occupancy = (int)$v->option_occupancy;
                    $v->state = (int)$v->state;
                    $v->duration = (int)$v->duration;
                    if(!isset($date_list[$v->date_departure])){
                        if($c <  5){
                            $v->durations_from_this_departure = [$v->duration];
                            $date_list[$v->date_departure] = $v;
                        }
                    }else{
                        $date_list[$v->date_departure]->durations_from_this_departure[] = $v->duration;
                        $date_list[$v->date_departure]->durations_from_this_departure = array_unique($date_list[$v->date_departure]->durations_from_this_departure);
                    }
                    $c++;
                }
                $object->five_dates_in_month = $date_list;
                $object->dates_total = $c;
                $objects[] = $object;
            }
        }
        return $objects;
    }

    /**
     * @param int $origin
     * @param string $agency
     * @return array|null
     * @throws \Exception
     */
    private function _createPossibleDurations($origin, $agency = null)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $config = $this->_config['search']['touristic'];
        $query = "SELECT date_departure, date_arrival, option_occupancy_min, 
                         option_occupancy_max, option_occupancy, duration, price_total, 
                         price_regular_before_discount, earlybird_discount, earlybird_discount_f, 
                         earlybird_discount_date_to, transport_type 
                   FROM pmt2core_cheapest_price_speed 
                   WHERE 
                     id_media_object = :id_media_object 
                     AND id_origin = :id_origin ".(
            empty($agency) ? "" : " AND agency = :agency")."
                   GROUP BY duration, transport_type ORDER BY price_total";
        $values = [
            ':id_media_object' => $this->mediaObject->id,
            ':id_origin' => $origin,
        ];
        if(!empty($agency)){
            $values[':agency'] = $agency;
        }
        $result = $db->fetchAll($query, $values);
        return $result;
    }
}
