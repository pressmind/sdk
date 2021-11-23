<?php

namespace Pressmind\Search\MongoDB;

use Pressmind\DB\Adapter\Pdo;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;

class Indexer
{
    /**
     * @var MediaObject null
     */
    public $mediaObject = null;

    /**
     * @var \MongoDB\Database
     */
    public $db;

    /**
     * @var \MongoDB\Collection
     */
    public $collection;

    /**
     * @var array
     */
    private $_config;

    /**
     * @var array
     */
    private $_allowed_visibilities;

    public function __construct() {
        $this->_config = Registry::getInstance()->get('config')['data']['search_mongodb'];
        $this->_allowed_visibilities = Registry::getInstance()->get('config')['data']['media_types_allowed_visibilities'];
        $uri = $this->_config['database']['uri'];
        $db_name = $this->_config['database']['db'];
        $client = new \MongoDB\Client($uri);
        $this->db = $client->$db_name;
    }

    /**
     * sets a index to a collection
     * @param $collection_name
     * @param $key
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function createCollectionIndex($collection_name){
        
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
        $this->db->$collection_name->createIndex( ['fulltext' => 'text'], ['default_language' => 'german', 'collation' => ['locale' => 'simple']]);
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


    public function createCollectionIfNotExists($collection_name)
    {
        foreach($this->db->listCollections() as $collection){
            if($collection['name'] == $collection_name){
                return true;
            }else{
                $this->db->createCollection($collection_name, ['collation' => [ 'locale' => 'de' ]]);
                $this->createCollectionIndex($collection_name);
                return true;
            }
        }
    }

    public function createCollectionsIfNotExists()
    {
        foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
            foreach ($build_infos as $build_info) {
                $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language']);
                $this->createCollectionIfNotExists($collection_name);
            }
        }
    }


    /**
     * Create the required indexes for each collection
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function createCollectionIndexes()
    {
        foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
            foreach ($build_infos as $build_info) {
                $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language']);
                $this->createCollectionIndex($collection_name);
            }
        }
    }

    public function flushCollections()
    {
        foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
            foreach ($build_infos as $build_info) {
                $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language']);
                $this->flushCollection($collection_name);
            }
        }
    }

    public function flushCollection($collection_name)
    {
        $this->db->$collection_name->deleteMany([]);
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
        $mediaObjects = MediaObject::listAll(['id' => ['in', implode(',', $id_media_objects)]]);

        $ids = [];
        foreach($mediaObjects as $mediaObject){
            if(!in_array($mediaObject->visibility, $this->_allowed_visibilities[$mediaObject->id_object_type]) ||
                empty($this->_config['search']['build_for'][$mediaObject->id_object_type])){
                continue;
            }
            foreach ($this->_config['search']['build_for'][$mediaObject->id_object_type] as $build_info) {
                $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language']);
                $this->createCollectionIndex($collection_name); // TODO might not the best place todo this..
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
        $this->mediaObject = new MediaObject($idMediaObject);
        $searchObject->_id = $this->mediaObject->id;
        $searchObject->id_object_type = $this->mediaObject->id_object_type;
        $searchObject->id_media_object = $this->mediaObject->id;
        $searchObject->url = $this->mediaObject->getPrettyUrl($language);
        $searchObject->code = array_filter(array_map('trim', explode(',', $this->mediaObject->code)));;
        $searchObject->description = $this->_mapDescriptions($language);
        $searchObject->categories = $this->_mapCategories($language);
        $searchObject->prices = $this->_aggregatePrices($origin);
        $searchObject->fulltext = $this->_createFulltext($language);
        $searchObject->departure_date_count = $this->_createDepartureDateCount($origin);

        //$searchObject->dates_per_month = null;
        if(!empty($this->_config['search']['five_dates_per_month_list'])){
            $searchObject->dates_per_month = $this->_createDatesPerMonth($origin);
        }

        //$searchObject->possible_durations = null;
        if(!empty($this->_config['search']['possible_duration_list'])){
            $searchObject->possible_durations = $this->_createPossibleDurations($origin);
        }
        $now = new \DateTime();
        $searchObject->last_modified_date = $now->format(DATE_ISO8601);
        if(is_array($searchObject->prices) && count($searchObject->prices) > 0) {
            $searchObject->best_price_meta = $searchObject->prices[0];
        }

        if(empty($searchObject->prices)){
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
        $description_map = $this->_config['search']['descriptions'][$this->mediaObject->id_object_type];

        foreach ($description_map as $index_name => $item_info) {
            $value = null;
            if(!empty($item_info['from']) && !empty($data->{$item_info['from']})){
                foreach ($data->{$item_info['from']} as $objectlink) {
                    $linkedObject = new MediaObject($objectlink->id_media_object_link);
                    $linkedObjectData = $linkedObject->getDataForLanguage($language);
                    if($item_info['field'] == 'name') {
                        $value = $linkedObject->name;
                    }else{
                        $value = $linkedObjectData->{$item_info['field']};
                    }
                    break;
                }
            } else {
                if($item_info['field'] == 'name') {
                    $value = $this->mediaObject->name;
                }else{
                    $value = $data->{$item_info['field']};
                }
            }
            if(isset($item_info['filter']) && !empty ($item_info['filter'])) {
                try {
                    $params = [$value];
                    if(!empty($item_info['params']) && is_array($item_info['params'])){
                        $params = array_merge($params, $item_info['params']);
                    }
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


    /**
     * @return array
     */
    private function _mapCategories($language) {
        $categories = [];
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

        foreach ($config['occupancies'] as $occupancy) {
            foreach ($config['duration_ranges'] as $duration_range) {

                $query = "SELECT 
                option_occupancy as occupancy, 
                date_departure, 
                date_arrival, 
                duration, 
                price_total, 
                price_regular_before_discount, 
                earlybird_discount, 
                earlybird_discount_f, 
                earlybird_discount_date_to, 
                option_board_type, 
                DATE_ADD(NOW(), INTERVAL :departure_offset_to DAY) as departure_range_from, 
                DATE_ADD(NOW(), INTERVAL :departure_offset_to DAY) as departure_range_to 
                FROM pmt2core_cheapest_price_speed 
                WHERE 
                id_media_object = :id_media_object 
                  AND id_origin = :id_origin
                  AND (earlybird_discount = 0 OR earlybird_discount_date_to >= NOW()) 
                  AND(duration BETWEEN :duration_range_from AND :duration_range_to) 
                  AND (option_occupancy = :occupancy) 
                  AND (date_departure BETWEEN DATE_ADD(NOW(), INTERVAL :departure_offset_from DAY) AND DATE_ADD(NOW(), INTERVAL :departure_offset_to DAY)) 
                GROUP BY price_total ORDER BY price_total";

                $values = [
                    ':id_media_object' => $this->mediaObject->id,
                    ':id_origin' => $origin,
                    ':duration_range_from' => $duration_range[0],
                    ':duration_range_to' => $duration_range[1],
                    ':occupancy' => $occupancy,
                    ':departure_offset_from' => $config['departure_offset_from'],
                    ':departure_offset_to' => $config['departure_offset_to']
                ];

                $result = $db->fetchRow($query, $values);

                if(!is_null($result)) {
                    $result->date_departure = !is_null($result->date_departure) ? \DateTime::createFromFormat('Y-m-d H:i:s', $result->date_departure)->format(DATE_RFC3339_EXTENDED) : null;
                    $result->date_arrival = !is_null($result->date_arrival) ? \DateTime::createFromFormat('Y-m-d H:i:s', $result->date_arrival)->format(DATE_RFC3339_EXTENDED) : null;
                    $result->departure_range_from = !is_null($result->departure_range_from) ? \DateTime::createFromFormat('Y-m-d H:i:s', $result->departure_range_from)->format(DATE_RFC3339_EXTENDED) : null;
                    $result->departure_range_to = !is_null($result->departure_range_to) ? \DateTime::createFromFormat('Y-m-d H:i:s', $result->departure_range_to)->format(DATE_RFC3339_EXTENDED) : null;
                    $result->occupancy = intval($result->occupancy);
                    $result->duration = intval($result->duration);
                    $result->price_total = intval($result->price_total);
                    $result->price_regular_before_discount = intval($result->price_regular_before_discount);
                    $result->earlybird_discount = intval($result->earlybird_discount);
                    $result->earlybird_discount_f = intval($result->earlybird_discount_f);
                    $result->option_board_type = $result->option_board_type;
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
     * @return bool
     */
    private function _priceSort($pricea, $priceb) {
        return $pricea->price_total > $priceb->price_total;
    }

    /**
     * @return string|null
     * @throws \Exception
     */
    private function _createFulltext($language = null)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $query = "SELECT fulltext_values from pmt2core_fulltext_search WHERE id_media_object = ? AND var_name = ?";
        $param = [$this->mediaObject->id, 'fulltext'];
        if(!empty($language)) {
            $query .= " AND language = ?";
            $param[] = $language;
        }
        $result = $db->fetchRow($query, $param);
        $fulltext = !is_null($result) ? $result->fulltext_values : '';
        $query = "select fl.fulltext_values from pmt2core_media_object_object_links ol
                    left join pmt2core_fulltext_search fl on (fl.id_media_object = ol.id_media_object_link)
                    where ol.id_media_object = ? and fl.var_name = ?";
        $param = [$this->mediaObject->id, 'fulltext'];
        if(!empty($language)) {
            $query .= " AND fl.language = ?";
            $param[] = $language;
        }
        $result = $db->fetchRow($query, $param);
        $fulltext_object_links = !is_null($result) ? $result->fulltext_values : '';
        return trim($fulltext.' '.$fulltext_object_links);
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
        $query = "SELECT date_format(date_departure, '%Y') as year, 
                        date_format(date_departure, '%c') as month 
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
                $object->year = $year;
                $object->month = $month;
                //$max_days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $date = new \DateTime($year . '-' . $month . '-01');
                $max_days = $date->format('t');
                $query = "SELECT date_departure, date_arrival, option_occupancy_min, option_occupancy_max, option_occupancy, duration, 
                        price_total, price_regular_before_discount, earlybird_discount, 
                        earlybird_discount_f, earlybird_discount_date_to 
                            FROM pmt2core_cheapest_price_speed 
                        WHERE (date_departure BETWEEN :departure_from AND :departure_to) 
                          AND id_media_object = :id_media_object AND id_origin = :id_origin 
                          AND option_occupancy = 2 ORDER BY date_departure LIMIT 0,5";
                $values = [
                    ':id_media_object' => $this->mediaObject->id,
                    ':id_origin' => $origin,
                    ':departure_from' => $year . "-" . $month . "-01",
                    ':departure_to' => $year . "-" . $month . "-" . $max_days
                ];
                $result = $db->fetchAll($query, $values);
                $object->five_dates_in_month = $result;
                $count_query = "SELECT count(id) as count FROM pmt2core_cheapest_price_speed WHERE (date_departure BETWEEN :departure_from AND :departure_to) AND id_media_object = :id_media_object AND id_origin = :id_origin AND option_occupancy = 2 ORDER BY price_total LIMIT 0,5";
                $count_result = $db->fetchRow($count_query, $values);
                $object->dates_total = $count_result->count;
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
