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
     * sets a index to a collection
     * @param $collection_name
     * @param $key
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function createCollectionIndex($collection_name){

        $this->db->$collection_name->dropIndexes();
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
                $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language']);
                $this->createCollectionIndex($collection_name);
            }
        }
        $mediaObjects = MediaObject::listAll(['id' => ['in', implode(',', $id_media_objects)]]);
        $ids = [];
        foreach($mediaObjects as $mediaObject){
            if(empty($this->_config['search']['build_for'][$mediaObject->id_object_type])){
                continue;
            }
            foreach ($this->_config['search']['build_for'][$mediaObject->id_object_type] as $build_info) {
                $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language']);
                $collection = $this->db->$collection_name;
                $document = $this->createIndex($mediaObject->id, $build_info['language'], $build_info['origin']);
                if($document === false){
                    continue;
                }
                $collection->updateOne(['_id' => $mediaObject->id], ['$set' => $document], ['upsert' => true]);
                $ids[] = $mediaObject->id;
            }
        }

        // remove the possible delta to aware the consistent
        foreach($id_media_objects as $id_media_object){
            if(in_array($id_media_object, $ids)){
                continue;
            }
            foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
                foreach ($build_infos as $build_info) {
                    $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language']);
                    $collection = $this->db->$collection_name;
                    $collection->deleteOne(['_id' => $id_media_object]);
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
                    $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language']);
                    $collection = $this->db->$collection_name;
                    $collection->deleteOne(['_id' => $id_media_object]);
                }
            }
        }
    }

    /**
     * @param int $origin
     * @param string $language
     * @return string
     */
    public function getCollectionName($origin = 0, $language = null){
        return 'best_price_search_based_' . (!empty($language) ? $language.'_' : '') . 'origin_' . $origin;
    }


    /**
     * @param integer $idMediaObject
     * @throws \Exception
     */
    public function createIndex($idMediaObject, $language, $origin)
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
        $searchObject->prices = $this->_aggregatePrices($origin);
        $searchObject->has_price = !empty($searchObject->prices);
        $searchObject->fulltext = $this->_createFulltext($language);
        $searchObject->departure_date_count = $this->_createDepartureDateCount($origin);
        $searchObject->valid_from = !is_null($this->mediaObject->valid_from) ?  $this->mediaObject->valid_from->format(DATE_RFC3339_EXTENDED) : null;
        $searchObject->valid_to = !is_null($this->mediaObject->valid_to) ? $this->mediaObject->valid_to->format(DATE_RFC3339_EXTENDED) : null;
        $searchObject->visibility = $this->mediaObject->visibility;
        $searchObject->recommendation_rate = $this->mediaObject->recommendation_rate;
        $searchObject->sales_priority = $this->mediaObject->sales_priority.$this->mediaObject->sales_position;

        //$searchObject->dates_per_month = null;
        if(!empty($this->_config['search']['five_dates_per_month_list'])){
            $searchObject->dates_per_month = $this->_createDatesPerMonth($origin);
        }

        //$searchObject->possible_durations = null;
        if(!empty($this->_config['search']['possible_duration_list'])){
            $searchObject->possible_durations = $this->_createPossibleDurations($origin);
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
                try {
                    $params = [$value];
                    if(!empty($item_info['params']) && is_array($item_info['params'])){
                        $params = array_merge($params, array_values($item_info['params']));
                    }

                    // @TODO parameter müssen in der richtigen named order definiert werden.
                    //var_dump($item_info['filter']);
                   // var_dump($params);
                    $value = call_user_func_array($item_info['filter'], $params);
                } catch (\Exception $e) {
                    echo 'Error in filter function ' .  $item_info['filter'] . ': ' . $e->getMessage();
                    exit; // @TODO
                }
            }
            $description[$index_name] = $value;
        }

        return $description;
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
     * @return array
     */
    private function _mapCategories($language) {
        $categories = [];
        if(empty($this->_config['search']['categories'][$this->mediaObject->id_object_type])){
            return $categories;
        }
        $categories_map = $this->_config['search']['categories'][$this->mediaObject->id_object_type];
        $data = $this->mediaObject->getDataForLanguage($language)->toStdClass();

        foreach ($categories_map as $varName => $additionalInfo) {
            if(empty($additionalInfo)) {
                if(is_array($data->$varName)) {
                    foreach ($data->$varName as $treeitem) {
                        $stdItem = new \stdClass();
                        $stdItem->id_item = $treeitem->item->id;
                        $stdItem->name = $treeitem->item->name;
                        $stdItem->id_tree = $treeitem->item->id_tree;
                        $stdItem->id_parent = $treeitem->item->id_parent;
                        $stdItem->field_name = $varName;
                        $stdItem->level = $this->getTreeDepth($data->$varName, $treeitem->id_item);
                        $stdItem->path_str = $this->getTreePath($data->$varName, $treeitem->id_item, 'name');
                        krsort($stdItem->path_str);
                        $stdItem->path_ids =$this->getTreePath($data->$varName, $treeitem->id_item, 'id');
                        krsort($stdItem->path_ids);
                        $categories[] = (array)$stdItem;
                    }
                }
            } else {
                if(!empty($additionalInfo['from'])) {
                    foreach ($this->_mapCategoriesFromObjectLinks($additionalInfo['from'], $varName, $language) as $linkedCategory) {
                        $categories[] = $linkedCategory;
                    }
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
    public function getTreeDepth($serialized_list, $id, $level = 0){
        foreach($serialized_list as $item){
            if($item->item->id == $id && !empty($item->item->id_parent)){
                $level++;
                return $this->getTreeDepth($serialized_list, $item->item->id_parent, $level);
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
    public function getTreePath($serialized_list, $id, $key, $path = []){
        foreach($serialized_list as $item){
            if($item->item->id == $id){
                $path[] = $item->item->{$key};
                return $this->getTreePath($serialized_list, $item->item->id_parent, $key, $path);
            }
        }
        return $path;
    }

    /**
     * @param string $varName
     * @param string $categoryVarName
     * @return array
     * @throws \Exception
     */
    private function _mapCategoriesFromObjectLinks($varName, $categoryVarName, $language) {
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
                        $stdItem->id_parent = $treeitem->item->id_parent;
                        $stdItem->field_name = $categoryVarName;
                        $stdItem->level = $this->getTreeDepth($linkedObjectData->$categoryVarName, $treeitem->id_item);
                        $stdItem->path_str = $this->getTreePath($linkedObjectData->$categoryVarName, $treeitem->id_item, 'name');
                        krsort($stdItem->path_str);
                        $stdItem->path_ids =$this->getTreePath($linkedObjectData->$categoryVarName, $treeitem->id_item, 'id');
                        krsort($stdItem->path_ids);
                        $categories[] = (array)$stdItem;
                    }
                }
            }
        }
        return $categories;
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function _aggregatePrices($origin)
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
                transport_type
                FROM pmt2core_cheapest_price_speed 
                WHERE 
                id_media_object = :id_media_object 
                  AND id_origin = :id_origin
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
                $results = $db->fetchAll($query, $values);
                if(!is_null($results)) {
                    foreach($results as $result) {
                        $date_departures = array_unique(explode(',', $result->date_departures));
                        asort($date_departures);
                        $formatted_date_departures = [];
                        foreach ($date_departures as $k => $date_departure) {
                            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $date_departure);
                            if (empty($date)) {
                                echo 'error: date is not valid'; // check group_concat max size see bootstrap.php
                                break(1);
                            }
                            $formatted_date_departures[] = $date->format(DATE_RFC3339_EXTENDED);
                        }
                        $result->date_departures = $formatted_date_departures;
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
                transport_type
                FROM pmt2core_cheapest_price_speed 
                WHERE 
                id_media_object = :id_media_object 
                  AND id_origin = :id_origin
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
                $results = $db->fetchAll($query, $values);
                if(!is_null($results)) {
                    foreach($results as $result){
                        $date_departures = array_unique(explode(',', $result->date_departures));
                        asort($date_departures);
                        $formatted_date_departures = [];
                        foreach ($date_departures as $k => $date_departure){
                            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $date_departure);
                            if(empty($date)){
                                echo 'error: date is not valid';
                                break(1);
                            }
                            $formatted_date_departures[] = $date->format(DATE_RFC3339_EXTENDED);
                        }
                        $result->date_departures = $formatted_date_departures;
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
     * @return integer|null
     * @throws \Exception
     */
    private function _createDepartureDateCount($origin)
    {
        $query = "SELECT COUNT(DISTINCT(date_departure)) as departure_date_count from pmt2core_cheapest_price_speed WHERE id_media_object = ? AND id_origin = ?";

        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');

        $result = $db->fetchRow($query, [$this->mediaObject->id, $origin]);

        return !is_null($result) ? intval($result->departure_date_count) : null;
    }

    private function _createDatesPerMonth($origin)
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
                    AND id_media_object = :id_media_object AND id_origin = :id_origin 
                    GROUP BY year, month ORDER BY year ASC, month ASC";
        $values = [
            ':id_media_object' => $this->mediaObject->id,
            ':id_origin' => $origin,
            ':departure_offset_from' => $config['departure_offset_from'],
            ':departure_offset_to' => $config['departure_offset_to']
        ];
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
                        price_total, price_regular_before_discount, earlybird_discount, earlybird_discount_f, earlybird_discount_date_to 
                            FROM pmt2core_cheapest_price_speed 
                        WHERE (date_departure BETWEEN :departure_from AND :departure_to) 
                          AND id_media_object = :id_media_object AND id_origin = :id_origin
                          AND ((option_occupancy = 2 AND price_mix = 'date_housing') OR (price_mix != 'date_housing'))
                          GROUP BY date_departure, duration ORDER BY date_departure, duration";
                $values = [
                    ':id_media_object' => $this->mediaObject->id,
                    ':id_origin' => $origin,
                    ':departure_from' => $year . "-" . $month . "-01",
                    ':departure_to' => $year . "-" . $month . "-" . $max_days
                ];
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
                                AND id_media_object = :id_media_object AND id_origin = :id_origin 
                                AND ((option_occupancy = 2 AND price_mix = 'date_housing') OR (price_mix != 'date_housing'))) t";
                $result = $db->fetchRow($count_query, $values);
                $object->dates_total = (int)$result->count;
                $objects[] = $object;
            }
        }
        return $objects;
    }

    private function _createPossibleDurations($origin)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $config = $this->_config['search']['touristic'];
        $query = "SELECT date_departure, date_arrival, option_occupancy_min, option_occupancy_max, option_occupancy, duration, price_total, price_regular_before_discount, earlybird_discount, earlybird_discount_f, earlybird_discount_date_to FROM pmt2core_cheapest_price_speed WHERE (date_departure BETWEEN DATE_ADD(NOW(), INTERVAL :departure_offset_from DAY) AND DATE_ADD(NOW(), INTERVAL :departure_offset_to DAY)) AND id_media_object = :id_media_object AND id_origin = :id_origin GROUP BY duration ORDER BY price_total";
        $values = [
            ':id_media_object' => $this->mediaObject->id,
            ':id_origin' => $origin,
            ':departure_offset_from' => $config['departure_offset_from'],
            ':departure_offset_to' => $config['departure_offset_to']
        ];
        $result = $db->fetchAll($query, $values);
        return $result;
    }
}
