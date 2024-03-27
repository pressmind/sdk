<?php

namespace Pressmind\Search\MongoDB;

use Pressmind\DB\Adapter\Pdo;
use Pressmind\HelperFunctions;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\Registry;

class Indexer extends AbstractIndex
{

    public function __construct() {
        parent::__construct();
    }


    /**
     * @param $collection_name
     * @return bool
     */
    public function collectionExists($collection_name){
        foreach($this->db->collection_names as $collection){
            if($collection === $collection_name){
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

        if($this->collectionExists($collection_name)){
            $this->db->$collection_name->dropIndexes();
        }
        $this->db->$collection_name->createIndex( ['prices.price_total' => 1]);
        $this->db->$collection_name->createIndex( ['prices.price_total' => -1]);
        $this->db->$collection_name->createIndex( ['prices.date_departure' => 1]);
        $this->db->$collection_name->createIndex( ['prices.date_departure' => -1]);
        $this->db->$collection_name->createIndex( ['prices.duration' => 1]);
        $this->db->$collection_name->createIndex( ['prices.occupancy' => 1]);
        $this->db->$collection_name->createIndex( ['prices.occupancy_additional' => 1]);
        $this->db->$collection_name->createIndex( ['prices.price_total' => 1, 'prices.duration' => 1, 'prices.occupancy' => 1]);
        $this->db->$collection_name->createIndex( ['categories.it_item' => 1]);
        $this->db->$collection_name->createIndex( ['categories.name' => 1]);
        $this->db->$collection_name->createIndex( ['categories.it_item' => 1, 'categories.field_name' => 1]);
        $this->db->$collection_name->createIndex( ['id_media_object' => 1], ['unique' => 1]);
        $this->db->$collection_name->createIndex( [
            'fulltext' => 'text',
            'categories.path_str' => 'text',
            'code' => 'text'
        ], [
            'default_language' => 'none',
            'weights' => [
                        'fulltext' => 5,
                        'categories.path_str' => 10,
                        'code' => 15
            ],
            'name' => 'fulltext_text'

        ]);
        $this->db->$collection_name->createIndex( ['groups' => 1]);
    }

    public function createIndexes()
    {
        $ids = [];
        foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
            $mediaObjects = MediaObject::listAll(['id_object_type' => $id_object_type]);
            foreach ($mediaObjects as $mediaObject) {
                echo $mediaObject->id."\n";
                $ids[] = $mediaObject->id;
            }
        }
        $this->upsertMediaObject($ids);
    }

    /**
     * @param string|int|array $id_media_objects
     * @throws \Exception
     */
    public function upsertMediaObject($id_media_objects)
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
        $mediaObjects = MediaObject::listAll(['id' => ['in', implode(',', $id_media_objects)]]);
        $ids = [];
        foreach($mediaObjects as $mediaObject){
            if(empty($this->_config['search']['build_for'][$mediaObject->id_object_type])){
                continue;
            }
            foreach ($this->_config['search']['build_for'][$mediaObject->id_object_type] as $build_info) {
                 foreach($this->_agencies as $agency) {
                    $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language'], $agency);
                    $collection = $this->db->$collection_name;
                    $document = $this->createIndex($mediaObject->id, $build_info['language'], $build_info['origin'], $agency);
                    if ($document === false) {
                        continue;
                    }
                    $collection->updateOne(['_id' => $mediaObject->id], ['$set' => $document], ['upsert' => true]);
                    $ids[] = $mediaObject->id;
                }
            }
        }
        foreach($id_media_objects as $id_media_object){
            if(in_array($id_media_object, $ids)){
                continue;
            }
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
     * @return string
     */
    public function getCollectionName($origin = 0, $language = null, $agency = null){
        return 'best_price_search_based_' . (!empty($language) ? $language.'_' : '') . 'origin_' . $origin.(!empty($agency) ? '_agency_'. $agency: '');
    }


    /**
     * @param int $idMediaObject
     * @param string $language
     * @param int $origin
     * @param string $agency
     * @return false|mixed
     * @throws \Exception
     */
    public function createIndex($idMediaObject, $language, $origin, $agency = null)
    {
        $searchObject = new \stdClass();
        $this->mediaObject = new MediaObject($idMediaObject, true, true);
        $searchObject->_id = $this->mediaObject->id;
        $searchObject->id_object_type = $this->mediaObject->id_object_type;
        $searchObject->id_media_object = $this->mediaObject->id;
        $searchObject->url = $this->mediaObject->getPrettyUrl($language);
        foreach($this->_config['search']['build_for'][$this->mediaObject->id_object_type] as $v){
            if($v['language'] == $language && !empty($v['disable_language_prefix_in_url'])){
                $searchObject->url = $this->mediaObject->getPrettyUrl();
                break;
            }
        }
        $searchObject->code = array_filter(array_map('trim', explode(',', (string)$this->mediaObject->code)));;
        $searchObject->description = $this->_mapDescriptions($language);
        $searchObject->categories = $this->_mapCategories($language);
        $searchObject->groups = $this->_mapGroups($language);
        $searchObject->locations = $this->_mapLocations($language);
        $searchObject->prices = $this->_aggregatePrices($origin, $agency);
        $searchObject->has_price = !empty($searchObject->prices);
        $searchObject->fulltext = $this->_createFulltext($language);
        $searchObject->departure_date_count = $this->_createDepartureDateCount($origin, $agency);
        $searchObject->valid_from = !is_null($this->mediaObject->valid_from) ?  $this->mediaObject->valid_from->format(DATE_RFC3339_EXTENDED) : null;
        $searchObject->valid_to = !is_null($this->mediaObject->valid_to) ? $this->mediaObject->valid_to->format(DATE_RFC3339_EXTENDED) : null;
        $searchObject->visibility = $this->mediaObject->visibility;
        $searchObject->recommendation_rate = $this->mediaObject->recommendation_rate;
        $searchObject->sales_priority = $this->mediaObject->sales_priority.$this->mediaObject->sales_position;

        //$searchObject->dates_per_month = null;
        if(!empty($this->_config['search']['five_dates_per_month_list'])){
            $searchObject->dates_per_month = $this->_createDatesPerMonth($origin, $agency);
        }

        //$searchObject->possible_durations = null;
        if(!empty($this->_config['search']['possible_duration_list'])){
            $searchObject->possible_durations = $this->_createPossibleDurations($origin, $agency);
        }
        $now = new \DateTime();
        $now->setTimezone(new \DateTimeZone('Europe/Berlin'));
        $searchObject->last_modified_date = $now->format(DATE_RFC3339_EXTENDED);
        if(is_array($searchObject->prices) && count($searchObject->prices) > 0) {
            $searchObject->best_price_meta = $searchObject->prices[0];
        }

        if(empty($searchObject->prices) && (empty($this->_config['search']['allow_invalid_offers']) && $searchObject->visibility != 10) ){
            return false;
        }

        return json_decode(json_encode($searchObject));
    }

    /**
     * @return array
     */
    private function _mapDescriptions($language)
    {
        $data = $this->mediaObject->getDataForLanguage($language)->toStdClass();
        $description = [];
        if(empty($this->_config['search']['descriptions'][$this->mediaObject->id_object_type])){
            return $description;
        }
        $description_map = $this->_config['search']['descriptions'][$this->mediaObject->id_object_type];
        foreach ($description_map as $index_name => $item_info) {
            $value = null;
            if(!empty($item_info['from']) && !empty($data->{$item_info['from']})){
                foreach ($data->{$item_info['from']} as $objectlink) {
                    $linkedObject = new MediaObject($objectlink->id_media_object_link);
                    $linkedObjectData = $linkedObject->getDataForLanguage($language);
                    if(empty($item_info['field']) === true){
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
                if($item_info['field'] == 'name') {
                    $value = $this->mediaObject->name;
                }else{
                    $value = !empty($data->{$item_info['field']}) ? $data->{$item_info['field']} : null;
                }
            }
            if(isset($item_info['filter']) && !empty ($item_info['filter'])) {
                $value = $this->_filterFunction($item_info, $value);
            }
            $description[$index_name] = $value;
        }

        return $description;
    }

    /**
     * @param array $item
     * @param mixed $first_param legacy
     * @return void
     * @throws \ReflectionException
     */
    private function _filterFunction($item, $first_param = null){
        try {
            return $this->_callMethod($item['filter'], !empty($item['params']) ? $item['params'] : [], $first_param);
        } catch (\Exception $e) {
            echo 'Error in filter function ' .  $item['filter'] . ': ' . $e->getMessage();
            return false;
        }
    }

    /**
     * @param string $method
     * @param array $params
     * @param mixed $first_param legacy
     * @return mixed
     * @throws \ReflectionException
     */
    private function _callMethod($method, $params = [], $first_param = 'undefined'){
        $p = explode('::', $method);
        $Filter = new $p[0]();
        $Filter->mediaObject = $this->mediaObject;
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
        $data = $this->mediaObject->getDataForLanguage($language)->toStdClass();
        $groups = [];
        if(empty($this->_config['search']['groups'][$this->mediaObject->id_object_type])){
            return $groups;
        }
        $group_map = $this->_config['search']['groups'][$this->mediaObject->id_object_type];
        if(empty($group_map['field'])){
            echo 'Error: field must be set (if you plan to use groups!)';
            exit; // @TODO
        }
        $field_name = $group_map['field'];
        if($field_name == 'agencies'){
            foreach($this->mediaObject->agencies as $agency){
                $groups[] = $agency->getId(). "";
            }
        }elseif($field_name == 'id_pool'){
            $groups[] = $this->mediaObject->id_pool ."";
        }elseif($field_name == 'brand'){
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
                echo 'Error: "'.$v.'" is not allowed as group value';
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
        $data = $this->mediaObject->getDataForLanguage($language)->toStdClass();
        $locations = new \stdClass();
        if(empty($this->_config['search']['locations'][$this->mediaObject->id_object_type])){
            return $locations;
        }
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
        if(empty($this->_config['search']['categories'][$this->mediaObject->id_object_type])){
            return $categories;
        }
        $categories_map = $this->_config['search']['categories'][$this->mediaObject->id_object_type];
        $data = $this->mediaObject->getDataForLanguage($language)->toStdClass();

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
            if($type == 'categorytree') {
                if(is_array($data->$varName)) {
                    foreach ($data->$varName as $treeitem) {
                        $stdItem = new \stdClass();
                        $stdItem->id_item = $treeitem->item->id;
                        $stdItem->name = $treeitem->item->name;
                        $stdItem->id_tree = $treeitem->item->id_tree;
                        $stdItem->id_parent = $treeitem->item->id_parent;
                        $stdItem->code = $treeitem->item->code;
                        $stdItem->sort = $treeitem->item->sort;
                        $stdItem->field_name = $varName;
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
                foreach ($this->_mapPlaintextFromObjectLinks($object_link_field, $varName, $field, $virtual_id_tree, $language) as $linkedCategory) {
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
            if($item->item->id == $id && !empty($item->item->id_parent)){
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
    private function _mapPlaintextFromObjectLinks($varName, $plaintextVarName, $field, $virtual_id_tree, $language) {
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
                $linkedObject = new MediaObject($objectlink->id_media_object_link);
                $linkedObjectData = $linkedObject->getDataForLanguage($language);
                if(!is_null($linkedObjectData) ) {
                  $stdItem = new \stdClass();
                  $stdItem->name = $plaintextVarName == 'name' ? $linkedObject->name : $linkedObjectData->$plaintextVarName ;
                  $stdItem->id_item = md5($objectlink->id_media_object_link.'-'.$varName.'-'.$plaintextVarName.'-'.$stdItem->name.'-'.$virtual_id_tree);
                  $stdItem->id_tree = $virtual_id_tree;
                  $stdItem->id_parent = null;
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
                $linkedObject = new MediaObject($objectlink->id_media_object_link);
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
     * @param int $origin
     * @param string $agency
     * @return array
     * @throws \Exception
     */
    private function _aggregatePrices($origin, $agency = null)
    {
        $config = $this->_config['search']['touristic'];
        $prices = [];
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');

        // price mix date_housing
        foreach ($config['occupancies'] as $occupancy) {
            foreach ($config['duration_ranges'] as $duration_range) {
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
                option_board_type, 
                price_mix,
                transport_type,
                guaranteed
                FROM pmt2core_cheapest_price_speed 
                WHERE 
                id_media_object = :id_media_object 
                  AND id_origin = :id_origin
                  ".(empty($agency) ? "" : " AND agency = :agency")."
                  AND (earlybird_discount = 0 OR earlybird_discount_date_to >= NOW()) 
                  AND(duration BETWEEN :duration_range_from AND :duration_range_to) 
                  AND (option_occupancy = :occupancy ) 
                  AND price_mix = 'date_housing'
                  AND (date_departure BETWEEN DATE_ADD(NOW(), INTERVAL :departure_offset_from DAY) 
                  AND DATE_ADD(NOW(), INTERVAL :departure_offset_to DAY)) 
                 GROUP BY price_total ORDER BY price_total";
                $values = [
                    ':id_media_object' => $this->mediaObject->id,
                    ':id_origin' => $origin,
                    ':duration_range_from' => $duration_range[0],
                    ':duration_range_to' => $duration_range[1].'.9',
                    ':occupancy' => $occupancy,
                    ':departure_offset_from' => $config['departure_offset_from'],
                    ':departure_offset_to' => $config['departure_offset_to']
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
                        $result->duration = floatval($result->duration);
                        $result->price_total = floatval($result->price_total); // @TODO pseudo price handling is missing
                        $result->price_regular_before_discount = floatval($result->price_regular_before_discount);
                        $result->earlybird_discount = floatval($result->earlybird_discount);
                        $result->earlybird_discount_f = floatval($result->earlybird_discount_f);
                        $prices[] = $result;
                    }
                }
            }
        }


        // price mixes NOT date_housing, this price_mix type doesn't have a occupancy!
        foreach ($config['duration_ranges'] as $duration_range) {
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
                option_board_type, 
                price_mix,
                transport_type,
                guaranteed
                FROM pmt2core_cheapest_price_speed 
                WHERE 
                id_media_object = :id_media_object 
                  AND id_origin = :id_origin
                  ".(empty($agency) ? "" : " AND agency = :agency")."
                  AND (earlybird_discount = 0 OR earlybird_discount_date_to >= NOW()) 
                  AND(duration BETWEEN :duration_range_from AND :duration_range_to) 
                  AND price_mix != 'date_housing'
                  AND (date_departure BETWEEN DATE_ADD(NOW(), INTERVAL :departure_offset_from DAY) 
                  AND DATE_ADD(NOW(), INTERVAL :departure_offset_to DAY)) 
                GROUP BY price_total ORDER BY price_total";
            $values = [
                ':id_media_object' => $this->mediaObject->id,
                ':id_origin' => $origin,
                ':duration_range_from' => $duration_range[0],
                ':duration_range_to' => $duration_range[1].'.9',
                ':departure_offset_from' => $config['departure_offset_from'],
                ':departure_offset_to' => $config['departure_offset_to']
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
                    $result->duration = floatval($result->duration);
                    $result->price_total = floatval($result->price_total);
                    $result->price_regular_before_discount = floatval($result->price_regular_before_discount);
                    $result->earlybird_discount = floatval($result->earlybird_discount);
                    $result->earlybird_discount_f = floatval($result->earlybird_discount_f);
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
     * @return string|null
     * @throws \Exception
     */
    private function _createFulltext($language = null)
    {
        $text = [];
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $query = "SELECT fulltext_values from pmt2core_fulltext_search WHERE id_media_object = ? AND var_name = ?";
        $param = [$this->mediaObject->id, 'fulltext'];
        if(!empty($language)) {
            $query .= " AND language = ?";
            $param[] = $language;
        }
        $result = $db->fetchAll($query, $param);
        if (is_array($result)) {
            foreach($result as $row){
                $text[] = $row->fulltext_values;
            }
        }
        if(!empty($this->_allowed_fulltext_fields[$this->mediaObject->id_object_type])){
            $allowed_fields = '"'.implode('","', array_values($this->_allowed_fulltext_fields[$this->mediaObject->id_object_type]) ).'"';
            $query = 'select fl.fulltext_values from pmt2core_media_object_object_links ol
                        left join pmt2core_fulltext_search fl on (fl.id_media_object = ol.id_media_object_link)
                        where ol.id_media_object = ? and fl.var_name = ? and ol.var_name in('.$allowed_fields.')';
            $param = [$this->mediaObject->id, 'fulltext'];
            if(!empty($language)) {
                $query .= " AND fl.language = ?";
                $param[] = $language;
            }
            $result = $db->fetchAll($query, $param);
            if (is_array($result)) {
                foreach($result as $row){
                    $text[] = $row->fulltext_values;
                }
            }
        }
        return implode(' ', $text);
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
     * @param $origin
     * @param $agency
     * @return array
     * @throws \Exception
     */
    private function _createDatesPerMonth($origin, $agency = null)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $config = $this->_config['search']['touristic'];
        $query = "SELECT cast(date_format(date_departure, '%Y') as SIGNED) as year, 
                         cast(date_format(date_departure, '%c') as SIGNED) as month 
                    FROM pmt2core_cheapest_price_speed 
                    WHERE (date_departure BETWEEN DATE_ADD(NOW(), 
                        INTERVAL :departure_offset_from DAY) AND DATE_ADD(NOW(), 
                      INTERVAL :departure_offset_to DAY)) 
                    AND id_media_object = :id_media_object 
                    AND id_origin = :id_origin ".(
                        empty($agency) ? "" : " AND agency = :agency"
            )." GROUP BY year, month ORDER BY year ASC, month ASC";
        $values = [
            ':id_media_object' => $this->mediaObject->id,
            ':id_origin' => $origin,
            ':departure_offset_from' => $config['departure_offset_from'],
            ':departure_offset_to' => $config['departure_offset_to'],
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
                $query = "SELECT date_departure, date_arrival, option_occupancy_min, option_occupancy_max, option_occupancy, duration, 
                        price_total, price_regular_before_discount, earlybird_discount, earlybird_discount_f, earlybird_discount_date_to, guaranteed
                            FROM pmt2core_cheapest_price_speed 
                        WHERE (date_departure BETWEEN :departure_from AND :departure_to) 
                          AND id_media_object = :id_media_object AND id_origin = :id_origin ".(
                            empty($agency) ? "" : " AND agency = :agency").
                          " AND ((option_occupancy = 2 AND price_mix = 'date_housing') OR (price_mix != 'date_housing'))
                          GROUP BY date_departure, duration ORDER BY date_departure, duration";
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
                $count_query = "select count(*) as count from (SELECT distinct date_departure FROM pmt2core_cheapest_price_speed
                                WHERE (date_departure BETWEEN :departure_from AND :departure_to) 
                                AND id_media_object = :id_media_object AND id_origin = :id_origin ".(
                                empty($agency) ? "" : " AND agency = :agency")."
                                AND ((option_occupancy = 2 AND price_mix = 'date_housing') OR (price_mix != 'date_housing'))) t";
                $result = $db->fetchRow($count_query, $values);
                $object->dates_total = (int)$result->count;
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
                       (date_departure BETWEEN DATE_ADD(NOW(), INTERVAL :departure_offset_from DAY) 
                           AND DATE_ADD(NOW(), INTERVAL :departure_offset_to DAY)) 
                     AND id_media_object = :id_media_object 
                     AND id_origin = :id_origin ".(
                    empty($agency) ? "" : " AND agency = :agency")."
                   GROUP BY duration ORDER BY price_total";
        $values = [
            ':id_media_object' => $this->mediaObject->id,
            ':id_origin' => $origin,
            ':departure_offset_from' => $config['departure_offset_from'],
            ':departure_offset_to' => $config['departure_offset_to']
        ];
        if(!empty($agency)){
            $values[':agency'] = $agency;
        }
        $result = $db->fetchAll($query, $values);
        return $result;
    }
}
