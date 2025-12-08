<?php

namespace Pressmind\Search;

use Pressmind\Cache\Adapter\Factory;
use Pressmind\Log\Writer;
use Pressmind\Registry;

class MongoDB extends AbstractSearch
{
    /**
     * @var string[]
     */
    private $_log = [];

    /**
     * @var array
     */
    private $_conditions = [];

    /**
     * @var Paginator|null
     */
    private $_paginator = null;

    /**
     * @var string[]
     */
    private $_sort;

    /**
     * @var string
     */
    private $_db_uri;

    /**
     * @var string
     */
    private $_db_name;

    /**
     * @var string
     */
    private $_language;

    /**
     * @var integer
     */
    private $_origin;

    /**
     * @var string
     */
    private $_agency;

    /**
     * @var boolean
     */
    private $_get_filters;

    /**
     * @var boolean
     */
    private $_return_filters_only;

    /**
     * @var bool
     */
    private $_skip_cache = false;

    /**
     * @var string
     */
    private $_collection_name;

    /**
     * @var string
     */
    private $_collection_name_description;

    /**
     * @var bool
     */
    private $_use_opensearch;

    /**
     * @param $conditions
     * @param $sort
     * @param $language
     * @param $origin
     * @param $agency
     * @param $search_type
     */
    public function __construct($conditions, $sort = ['price_total' => 'asc'], $language = null, $origin = 0, $agency = null)
    {
        $config = Registry::getInstance()->get('config');
        $this->_addLog('__construct()');
        foreach ($conditions as $name => $condition) {
            $this->addCondition($name, $condition);
        }
        $this->_sort = $sort;
        $this->_db_uri = $config['data']['search_mongodb']['database']['uri'];
        $this->_db_name = $config['data']['search_mongodb']['database']['db'];
        $this->_origin = $origin;
        $this->_agency = $agency;
        $this->_use_opensearch = !empty($config['data']['search_opensearch']['enabled']) && !empty($config['data']['search_opensearch']['enabled_in_mongo_search']);
        if(is_null($language) && count($config['data']['languages']['allowed'])) {
            $this->_language = $config['data']['languages']['default'];
        }elseif(!is_null($language)){
            $this->_language = $language;
        }
        $this->_collection_name = self::getCollectionName('best_price_search_based_', $this->_language, $this->_origin, $this->_agency);
        $this->_collection_name_description = self::getCollectionName('description_', $this->_language, $this->_origin, $this->_agency);
    }

    public function getAgency(){
        return $this->_agency;
    }

    public static function getCollectionName($prefix = 'best_price_search_based_', $language = null, $origin = null, $agency = null){
        return $prefix . (!empty($language) ? $language.'_' : '') . 'origin_' . $origin.(!empty($agency) ? '_agency_'. $agency: '') ;
    }

    /**
     * @return mixed|string
     */
    public function getCurrentCollectionName(){
        return $this->_collection_name;
    }

    private function _addLog($text) {
        $config = Registry::getInstance()->get('config');
        if(isset($config['logging']['enable_advanced_object_log']) && $config['logging']['enable_advanced_object_log'] == true) {
            $now = new \DateTime();
            $this->_log[] = '[' . $now->format(DATE_RFC3339_EXTENDED) . '] ' . $text;
        }
    }

    /**
     * @return string[]
     */
    public function getLog()
    {
        $config = Registry::getInstance()->get('config');
        if(isset($config['logging']['enable_advanced_object_log']) && $config['logging']['enable_advanced_object_log'] == true) {
            return $this->_log;
        }
        return ['Logging is disabled in config (logging.enable_advanced_object_log)'];
    }

    /**
     * @param string $name
     * @param $condition
     */
    public function addCondition($name, $condition)
    {
        $this->_conditions[$name] = $condition;
    }

    /**
     * @param $type
     * @return void
     */
    public function removeCondition($type){
        foreach($this->_conditions as $k => $condition){
            if(strtolower($condition->getType()) == strtolower($type)){
                unset($this->_conditions[$k]);
            }
        }
    }

    /**
     * @param string $type
     * @return object
     */
    public function getConditionByType($type){
        foreach($this->_conditions as $condition){
            if(strtolower($condition->getType()) == strtolower($type)){
                return $condition;
            }
        }
        return false;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function hasCondition($type){
        foreach($this->_conditions as $condition){
            if(strtolower($condition->getType()) == strtolower($type)){
                return true;
            }
        }
        return false;
    }


    /**
     * @return array
     */
    public function listConditions(){
        $output = [];
        foreach($this->_conditions as $condition){
            $output[] = $condition->getType();
        }
        return $output;
    }


    /**
     * @param Paginator $paginator
     */
    public function setPaginator($paginator) {
        $this->_paginator = $paginator;
    }

    /**
     * @param boolean $getFilters
     */
    public function setGetFilters($getFilters)
    {
        $this->_get_filters = $getFilters;
    }

    /**
     * @param boolean $returnFiltersOnly
     */
    public function setReturnFiltersOnly($returnFiltersOnly)
    {
        $this->_return_filters_only = $returnFiltersOnly;
    }

    /**
     * @param boolean $getFilters
     * @param boolean $returnFiltersOnly
     * @param \DateTime|null $preview_date
     * @return mixed
     */
    public function getResult($getFilters = false, $returnFiltersOnly = false, $ttl = 0, $output = null, $preview_date = null, $allowed_visibilities = [30], SearchType $search_type = SearchType::DEFAULT)
    {
        $this->_addLog('getResult(): starting query');
        if (!empty($ttl) && Registry::getInstance()->get('config')['cache']['enabled'] && in_array('MONGODB', Registry::getInstance()->get('config')['cache']['types']) && $this->_skip_cache == false) {
            $key = $this->generateCacheKey('', $output, $preview_date, $allowed_visibilities);
            $cache_adapter = Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            $key_exists = false;
            if ($cache_adapter->exists($key)) {
                $key_exists = true;
                Writer::write(get_class($this) . ' exec() reading from cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
                $cache_contents = json_decode($cache_adapter->get($key));

            }
            if(!empty($cache_contents) && $key_exists === true){
                return $cache_contents;
            }
        }
        if($this->_use_opensearch && ($this->hasCondition('AtlasLuceneFulltext') || $this->hasCondition('Fulltext'))) {
            $searchString = '';
            $condition = $this->getConditionByType('AtlasLuceneFulltext');
            if($condition) {
                $searchString = $condition->getSearchStringRaw();
                $this->_addLog('getResult(): removing AtlasLuceneFulltext condition');
                $this->removeCondition('AtlasLuceneFulltext');
            }
            $condition = $this->getConditionByType('Fulltext');
            if($condition) {
                $searchString = $condition->getSearchStringRaw();
                $this->_addLog('getResult(): removing Fulltext condition');
                $this->removeCondition('Fulltext');
            }
            if(!empty($searchString)) {
                $this->_addLog('getResult(): adding OpenSearch and MediaObject (ids in) condition');
                try{
                    $OpenSearch = new OpenSearch($searchString, $this->_language, 10000);
                    $ids = $OpenSearch->getResult($search_type === SearchType::AUTOCOMPLETE);
                    if(!empty($ids)){
                        $ConditionMediaObject = new Condition\MongoDB\MediaObject($ids);
                        $this->addCondition('MediaObject', $ConditionMediaObject);
                    }
                }catch (\Exception $e) {
                    $this->_addLog('getResult(): OpenSearch error: ' . $e->getMessage());
                    if(!empty($_GET['debug']) || (defined('PM_SDK_DEBUG') && PM_SDK_DEBUG)) {
                        echo '<pre>opensearch: '.json_encode($e->getMessage()).'</pre>';
                    }
                    exit;
                }
            }
        }
        $this->setGetFilters($getFilters);
        $this->setReturnFiltersOnly($returnFiltersOnly);
        $client = new \MongoDB\Client($this->_db_uri);
        $db_name = $this->_db_name;
        $db = $client->$db_name;
        $collection = $db->{$this->_collection_name};
        $prepare = $this->prepareQuery();
        $query = $this->buildQuery($output, $preview_date, $allowed_visibilities);
        try{
            $result = $collection->aggregate($query, ['allowDiskUse' => true])->toArray()[0];
            if(!isset($result->documents)){
                $result->documents = [];
            }
            // avoid $facet limit
            $ids = [];
            foreach ($result->documents as $document) {
                $document = json_decode(json_encode($document), true);
                $ids[] = $document['_id'];
            }
            $collection_description = $db->{$this->_collection_name_description};
            $query_description = [
                [
                    '$match' => [
                        '_id' => [
                            '$in' => $ids
                        ]
                    ]
                ]
            ];
            $result_description = $collection_description->aggregate($query_description, ['allowDiskUse' => true])->toArray();
            $mapped_result = [];
            foreach ($result_description as $document) {
                $mapped_result[$document['_id']] = $document;
            }
            foreach ($result->documents as $key => $document) {
                if(!empty($mapped_result[$document['_id']])) {
                    $document['description'] = !empty($mapped_result[$document['_id']]->description) ? $mapped_result[$document['_id']]->description : '';
                    $result->documents[$key] = $document;
                }
            }
        }catch (\Exception $exception){
            echo $exception->getMessage();
            if(!empty($_GET['debug']) || (defined('PM_SDK_DEBUG') && PM_SDK_DEBUG)) {
                echo '<pre>'.json_encode($query).'</pre>';
            }
            exit;
        }
        if (!empty($ttl) && Registry::getInstance()->get('config')['cache']['enabled'] && in_array('MONGODB', Registry::getInstance()->get('config')['cache']['types']) && $this->_skip_cache == false) {
            Writer::write(get_class($this) . ' exec() writing to cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
            $info = new \stdClass();
            $info->type = 'MONGODB';
            $info->classname = self::class;
            $info->method = 'updateCache';
            $info->parameters = ['aggregate' => $this->buildQuery($output)];
            $cache_adapter->add($key, json_encode($result), $info, $ttl);
        }

        $this->_addLog('getResult(): query completed');
        return $result;
    }

    /**
     * @param null $key
     * @param null $output
     * @param null $preview_date
     * @param int[] $allowed_visibilities
     * @return string|null
     * @throws \Exception
     */
    public function updateCache($key = null, $output = null, $preview_date = null, $allowed_visibilities = [30]){
        $cache_adapter = Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
        if(is_null($key)) {
            $key = $this->generateCacheKey('', $output, $preview_date, $allowed_visibilities);
        }
        $info = $this->getCacheInfo($key);
        $params = isset($info['info']) && !empty($info['info']->parameters) ? $info['info']->parameters : null;
        if(!is_null($params)) {
            try {
                $client = new \MongoDB\Client($this->_db_uri);
                $db_name = $this->_db_name;
                $db = $client->$db_name;
                $collection = $db->{$this->_collection_name};
                $result = $collection->aggregate($params->aggregate)->toArray()[0];
                $info = new \stdClass();
                $info->type = 'MONGODB';
                $info->classname = self::class;
                $info->method = 'updateCache';
                $info->parameters = ['aggregate' => $params->aggregate];
                Writer::write(get_class($this) . ' exec() writing to cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
                $cache_adapter->add($key, json_encode($result), $info);
                return $key . ': ' . $params->aggregate;
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }
        return null;
    }

    /**
     * @return array
     */
    public function prepareQuery(){
        $output = [];
        foreach ($this->_conditions as $condition_name => $condition) {
            if(method_exists($condition, 'prepare')){
                $output[$condition_name] = $condition->prepare($this->_language, $this->_origin, $this->_agency);
            }
        }
        return $output;
    }

    /**
     * @TODO Refactor!
     * @param null $output
     * @param \DateTime|null $preview_date
     * @param array $allowed_visibilities
     * @return array
     */
    public function buildQuery($output = null, $preview_date = null, $allowed_visibilities = [30])
    {
        $config = Registry::getInstance()->get('config');
        $allow_invalid_offers = !empty($config['data']['search_mongodb']['search']['allow_invalid_offers']);
        $has_startingpoint_index = !empty($config['data']['touristic']['generate_offer_for_each_startingpoint_option']);
        $stages = [];

        if($this->hasCondition('Powerfilter')){
            $condition = $this->getConditionByType('Powerfilter');
            $stages[] = $condition->getQuery('lookup');
            $stages[] = $condition->getQuery('match');
        }

        if($this->hasCondition('AtlasLuceneFulltext')){
            $condition = $this->getConditionByType('AtlasLuceneFulltext');
            $stages[] = $condition->getQuery('base');
            $stages[] = $condition->getQuery('project');
            $stages = array_filter($stages);
        }

        // stage 1 first_match
        $andQuery['$and'] = [];
        foreach ($this->_conditions as $condition_name => $condition) {
            if (empty($condition->getQuery('first_match', $allow_invalid_offers))) {
                continue;
            }
            $andQuery['$and'][] = $condition->getQuery('first_match', $allow_invalid_offers);
        }

        // merge prices conditions
        $fst_key = null;
        foreach($andQuery['$and'] as $key => $item){
            if($fst_key === null && isset($item['prices']['$elemMatch'])){
                $fst_key = $key;
                continue;
            }
            if(isset($item['prices']['$elemMatch'])){
                $andQuery['$and'][$fst_key]['prices']['$elemMatch'] = array_merge(  $andQuery['$and'][$fst_key]['prices']['$elemMatch'], $item['prices']['$elemMatch']);
                if(count($andQuery['$and'][$key]) == 1){
                    unset($andQuery['$and'][$key]);
                }else{
                    unset($andQuery['$and'][$key]['prices']);
                }
            }
        }
        $andQuery['$and'] = array_values($andQuery['$and']);

        if (!empty($andQuery['$and'])) {
            $stages[] = ['$match' => $andQuery];
            if ($this->hasCondition('Fulltext')) {
                $condition = $this->getConditionByType('Fulltext');
                $stages[] = $condition->getQuery('project');
            }
        }

        // stage 1.1 dynamic stages from conditions
        foreach ($this->_conditions as $condition_name => $condition) {
            if (!empty($condition->getQuery('stage_after_match'))) {
                $stages[] = $condition->getQuery('stage_after_match');
            }
        }

        // stage 1.5 only valid objects if it's not a preview
        $current_date = empty($preview_date) ? new \DateTime() : $preview_date;
        $stages[] = [
            '$match' => [
                '$and' => [
                    [
                        '$or' => [
                            [
                                'valid_from' => [
                                    '$lte' => $current_date->format(DATE_RFC3339_EXTENDED)
                                ]
                            ],
                            [
                                'valid_from' => null
                            ]
                        ]
                    ],
                    [
                        '$or' => [
                            [
                                'valid_to' => [
                                    '$gte' => $current_date->format(DATE_RFC3339_EXTENDED)
                                ]
                            ],
                            [
                                'valid_to' => null
                            ]
                        ]
                    ]
                ]
            ]];

        // stage 1.6 - respect visibility
        if(!empty($allowed_visibilities) && empty($preview_date)){
            $stages[] = [
                '$match' => [
                    'visibility' => [
                        '$in' =>
                            $allowed_visibilities
                    ],
                ]
            ];
        }

        // stage 2, remove useless data
        if(!$this->_use_opensearch){
            $stages[] = ['$unset' => ['fulltext']];
        }
        // stage 3, filter prices array (by occupancy, pricerange, durationrange)
        $addFieldsConditions = [];
        $prices_filter = ['$addFields' => ['prices' =>  ['$filter' => ['input' => '$prices', 'cond' => ['$and' => []]]]]];
        $prices_filter_cleanup = ['$addFields' => ['prices' => ['$filter' => ['input' => '$prices', 'cond' => ['$and' => []]]]]];
        foreach ($this->_conditions as $condition_name => $condition) {
            $query['$and'][] = $condition->getQuery();
            if(is_array($condition->getQuery('prices_filter', $allow_invalid_offers))) {
                foreach ($condition->getQuery('prices_filter', $allow_invalid_offers) as $addFieldsCondition) {
                    $addFieldsConditions[] = $addFieldsCondition;
                }
            }
        }
        // TODO remove if empty
        $prices_filter['$addFields']['prices']['$filter']['cond']['$and'] = $addFieldsConditions;
        $prices_filter_cleanup['$addFields']['prices']['$filter']['cond']['$and'] = $addFieldsConditions;

        // stage 4-x, filter by departure dates
        if($this->hasCondition('DateRange')){
            $stages[] = $prices_filter_cleanup;
            $condition = $this->getConditionByType('DateRange');
            $stages = array_merge($stages, $condition->getQuery('departure_filter', $allow_invalid_offers));
        }
        $stages[] = $prices_filter;

        // stage 4.1 - respect starting_point_options
        if($has_startingpoint_index){
            $stages[] = [
                '$group' => [
                    '_id' => '$_id',
                    'startingpoint_options' => [
                        '$push' => '$prices.startingpoint_option'
                    ],
                    'doc' => [
                        '$first' => '$$ROOT'
                    ]
                ]
            ];
            $stages[] = [
                '$addFields' => [
                    'doc.startingpoint_options' => [
                        '$reduce' => [
                            'input' => '$startingpoint_options',
                            'initialValue' => [],
                            'in' => [
                                '$setUnion' => ['$$value', '$$this']
                            ]
                        ]
                    ]
                ]
            ];
            $stages[] = [
                '$replaceRoot' => [
                    'newRoot' => '$doc'
                ]
            ];
        }

        // stage n projection split board_types, transports and prices
        $projectStage = [
            '$project' => [
                '_id' => 1,
                'best_price_meta' => 1,
                'categories' => 1,
                'code' => 1,
                'departure_date_count' => 1,
                'description' => 1,
                'groups' => 1,
                'id_media_object' => 1,
                'id_object_type' => 1,
                'last_modified_date' => 1,
                'recommendation_rate' => 1,
                'possible_durations' => 1,
                'startingpoint_options' => 1,
                'locations' => 1,
                'url' => 1,
                'valid_from' => 1,
                'valid_to' => 1,
                'visibility' => 1,
                'dates_per_month' => 1,
                'sales_priority' => 1,
                'sold_out' => 1,
                'is_running' => 1,
                'object_type_order' => 1,
                'has_price' => ['$gt' => [['$size' => '$prices'], 0 ] ],
                'prices' => [
                    '$reduce' => [
                        'input' => '$prices',
                        'initialValue' => [],
                        'in' => [
                            '$cond' => [
                                'if' => [
                                    '$or' => [
                                        ['$lt' =>
                                            ['$$this.state', '$$value.state']
                                        ],
                                        ['$and' => [
                                                ['$eq' => ['$$this.state', '$$value.state']],
                                                ['$lt' => ['$$this.price_total', '$$value.price_total']]
                                            ]
                                        ],
                                        ['$and' => [
                                                ['$eq' => ['$$this.state', '$$value.state']],
                                                ['$eq' => ['$$this.price_total', '$$value.price_total']],
                                                ['$gt' => ['$$this.duration', '$$value.duration']]
                                            ]
                                        ]
                                    ]
                                ],
                                'then' => '$$this',
                                'else' => '$$value',
                            ]
                        ]
                    ],
                ],
                'transport_types' => [
                    '$reduce' => [
                        'input' => '$prices',
                        'initialValue' => [],
                        'in' => [
                            '$cond' => [
                                'if' => [
                                    '$in' => ['$$this.transport_type', '$$value.transport_type']
                                ],
                                'then' => '$$value',
                                'else' => [
                                    '$concatArrays' =>
                                        ['$$value', ['$$this']]
                                ],
                            ]
                        ]
                    ],
                ],
                'board_types' => [
                    '$reduce' => [
                        'input' => '$prices',
                        'initialValue' => [],
                        'in' => [
                            '$cond' => [
                                'if' => [
                                    '$in' => ['$$this.option_board_type', '$$value.option_board_type']
                                ],
                                'then' => '$$value',
                                'else' => [
                                    '$concatArrays' =>
                                        ['$$value', ['$$this']]
                                ],
                            ]
                        ]
                    ],
                ],
            ]
        ];

        if($output == 'date_list'){
            $stages[] = ['$unwind' => ['path' => '$prices', 'preserveNullAndEmptyArrays' => false]];
            $stages[] = ['$unwind' => ['path' => '$prices.date_departures', 'preserveNullAndEmptyArrays' => false]];
            $stages[] = ['$sort' => ['prices.date_departures' => 1, 'prices.price_total' => 1, 'prices.duration' => -1]];
            $stages[] = ['$group' => [
                '_id' => '$_id',
                'best_price_meta' => ['$first' => '$$ROOT.best_price_meta'],
                'categories' => ['$first' => '$$ROOT.categories'],
                'code' => ['$first' => '$$ROOT.code'],
                'departure_date_count' => ['$first' => '$$ROOT.departure_date_count'],
                'description' => ['$first' => '$$ROOT.description'],
                'groups' => ['$first' => '$$ROOT.groups'],
                'id_media_object' => ['$first' => '$$ROOT.id_media_object'],
                'id_object_type' => ['$first' => '$$ROOT.id_object_type'],
                'last_modified_date' => ['$first' => '$$ROOT.last_modified_date'],
                'recommendation_rate' => ['$first' => '$$ROOT.recommendation_rate'],
                'url' => ['$first' => '$$ROOT.url'],
                'valid_from' => ['$first' => '$$ROOT.valid_from'],
                'valid_to' => ['$first' => '$$ROOT.valid_to'],
                'visibility' => ['$first' => '$$ROOT.visibility'],
                'has_price' => ['$first' => '$$ROOT.has_price'],
                'sold_out' => ['$first' => '$$ROOT.sold_out'],
                'is_running' => ['$first' => '$$ROOT.is_running'],
                'object_type_order' => ['$first' => '$$ROOT.object_type_order'],
                'dates_per_month' => ['$first' => '$$ROOT.dates_per_month'],
                'prices' => ['$push' => '$$ROOT.prices'],
            ]
            ];
            $projectStage['$project']['has_price'] = ['$gt' => ['$prices.price_total', 0]];
            $projectStage['$project']['prices'] =  [
                '$reduce' => [
                    'input' => '$prices',
                    'initialValue' => [],
                    'in' => [
                            '$cond' => [
                                'if' => [
                                   '$gt' => [
                                       [
                                           '$size' => [
                                               '$filter' => [
                                                   'input' => '$$value',
                                                   'as' => 'price',
                                                   'cond' => [
                                                       '$and' => [
                                                           ['$eq' => ['$$price.date_departures', '$$this.date_departures']],
                                                       ]
                                                   ]
                                               ]
                                            ]
                                        ],
                                       0
                                   ]
                                ],
                                'then' => '$$value',
                                'else' => ['$concatArrays' => ['$$value', ['$$this']]],
                            ],
                        ]
                    ]
                ];
            $stages[] = $projectStage;
            $stages[] = ['$unwind' => ['path' => '$prices', 'preserveNullAndEmptyArrays' => false]];
        }else{
            $stages[] = $projectStage;
        }

        if(array_key_first($this->_sort) == 'list' && $this->hasCondition('MediaObject')){
            $MediaObjectCondition = $this->getConditionByType('MediaObject');
            $order_by_list = ['$addFields' =>
                ['sort' => ['$indexOfArray' => [$MediaObjectCondition->getValue(), '$_id']]]];
            $stages[] = $order_by_list;
        }

        // stage n, build the filter stages
        if($this->_get_filters === true || $this->_return_filters_only === true) {
            $facetStage = [
                '$facet' => [
                    'prices' => [
                        [
                            '$unwind' => '$prices'
                        ]
                    ]
                ]
            ];
            if($allow_invalid_offers === false){
                $facetStage['$facet']['documents'] =  [
                    [
                        '$match' => [
                            'prices' => [
                                '$exists' => true
                            ]
                        ],
                    ],
                ];
            }
            $facetStage['$facet']['categoriesGrouped'] = [
                [
                    '$unwind' => '$categories'
                ],
                [
                    '$sortByCount' => '$categories'
                ],
                [
                    '$sort' => [
                        '_id.name' => 1
                    ]
                ]
            ];
            $facetStage['$facet']['sold_out'] = [
                [
                    '$group' => [
                        '_id' => '$sold_out',
                        'count' => [
                            '$sum' => 1
                        ]
                    ]
                ]
            ];
            $facetStage['$facet']['is_running'] = [
                [
                    '$group' => [
                        '_id' => '$is_running',
                        'count' => [
                            '$sum' => 1
                        ]
                    ]
                ]
            ];
            if($output == 'date_list'){
                $facetStage['$facet']['boardTypesGrouped'] = [
                    [
                        '$sortByCount' => '$prices.option_board_type'
                    ],
                    [
                        '$sort' => [
                            '_id' => 1
                        ]
                    ]
                ];
                $facetStage['$facet']['transportTypesGrouped'] = [
                    [
                        '$sortByCount' => '$prices.transport_type'
                    ],
                    [
                        '$sort' => [
                            '_id' => 1
                        ]
                    ]
                ];
                $facetStage['$facet']['startingPointsGrouped'] = [
                    [
                        '$sortByCount' => '$startingpoint_options'
                    ],
                    [
                        '$sort' => [
                            '_id' => 1
                        ]
                    ]
                ];
            }else{
                $facetStage['$facet']['startingPointsGrouped'] = [
                    [
                        '$unwind' => '$startingpoint_options'
                    ],
                    [
                        '$sortByCount' => '$startingpoint_options'
                    ],
                    [
                        '$sort' => [
                            '_id' => 1
                        ]
                    ]
                ];
                $facetStage['$facet']['boardTypesGrouped'] = [
                    [
                        '$unwind' => '$board_types'
                    ],
                    [
                        '$sortByCount' => '$board_types.option_board_type'
                    ],
                    [
                        '$sort' => [
                            '_id' => 1
                        ]
                    ]
                ];
                $facetStage['$facet']['transportTypesGrouped'] = [
                    [
                        '$unwind' => '$transport_types'
                    ],
                    [
                        '$sortByCount' => '$transport_types.transport_type'
                    ],
                    [
                        '$sort' => [
                            '_id' => 1
                        ]
                    ]
                ];
            }

            $addFieldsStage = [
                '$addFields' => [
                    'minDuration' => [
                        '$min' => '$prices.prices.duration'
                    ],
                    'maxDuration' => [
                        '$max' => '$prices.prices.duration'
                    ],
                    'minPrice' => [
                        '$min' => '$prices.prices.price_total'
                    ],
                    'maxPrice' => [
                        '$max' => '$prices.prices.price_total'
                    ],
                    'total' => [
                        '$size' => '$documents'
                    ]
                ]
            ];

            if($output == 'date_list'){
                $addFieldsStage['$addFields']['minDeparture'] = [
                    '$min' => '$prices.prices.date_departures'
                ];
                $addFieldsStage['$addFields']['maxDeparture'] = [
                   '$max' => '$prices.prices.date_departures'
                ];
            }else{
                $addFieldsStage['$addFields']['minDeparture'] = [
                    '$min' => [
                        '$reduce' => [
                            'input' => '$prices.prices.date_departures',
                            'initialValue' => [],
                            'in' => [
                                '$concatArrays' => ['$$value', '$$this']
                            ]
                        ]
                    ]
                ];
                $addFieldsStage['$addFields']['maxDeparture'] = [
                    '$max' => [
                        '$reduce' => [
                            'input' => '$prices.prices.date_departures',
                            'initialValue' => [],
                            'in' => [
                              '$concatArrays' => ['$$value', '$$this']
                            ]
                        ]
                    ]
                ];
            }

            $project = [
                '$project' => [
                    'documents' => 1,
                    'minDuration' => 1,
                    'maxDuration' => 1,
                    'minDeparture' => 1,
                    'maxDeparture' => 1,
                    'minPrice' => 1,
                    'maxPrice' => 1,
                    'total' => 1,
                    'categoriesGrouped' => 1,
                    'startingPointsGrouped' => 1,
                    'boardTypesGrouped' => 1,
                    'transportTypesGrouped' => 1,
                    'sold_out' => 1,
                    'is_running' => 1
                ]
            ];
            if($this->_return_filters_only === true){
                unset($project['$project']['documents']);
            }
        } else { // stage n, if we don't need the filter, we use just the required methods
            $facetStage = [
                '$facet' => [
                    'prices' => [
                        [
                            '$unwind' => '$prices'
                        ]
                    ],
                ]
            ];
            if($allow_invalid_offers === false){
                $facetStage['$facet']['documents'] =  [
                    [
                        '$match' => [
                            'prices' => [
                                '$exists' => true
                            ]
                        ],
                    ],
                ];
            }
            $addFieldsStage = [
                '$addFields' => [
                    'total' => [
                        '$size' => '$documents'
                    ]
                ]
            ];
            $project = [
                '$project' => [
                    'total' => 1
                ]
            ];
        }

        // stage n, sort stages
        if(array_key_first($this->_sort) == 'rand'){
            $sort = ['$sample' => [
                'size' =>   $this->_paginator->getPageSize()
                ]
            ];
        }elseif(array_key_first($this->_sort) == 'price_total'){
            $sort = [
                    '$sort' => [
                        'prices.price_total' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1,
                        'prices.duration' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? -1 : 1,
                        'sales_priority' => 1
                    ]
                ];
        }elseif(array_key_first($this->_sort) == 'score'){
            $sort = ['$sort' => [
                        'score' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1,
                        'sales_priority' => 1
                    ]
            ];
        }elseif(array_key_first($this->_sort) == 'date_departure'){
            if($output == 'date_list'){
                $sort_direction = strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1;
                $sort = ['$sort' => [
                            'prices.date_departures' => $sort_direction,
                            'sales_priority' => 1,
                            'prices.price_total' => 1,
                            'prices.duration' => -1,
                            'id_media_object' => 1
                        ]
                ];
            }else{
                $addFieldsForDepartureSort = ['$addFields' => ['fst_date_departure' => ['$first' => '$prices.date_departures']]];
                $stages[] = $addFieldsForDepartureSort;
                $sort = ['$sort' => [
                                'fst_date_departure' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1,
                                'sales_priority' => 1
                        ]
                ];
            }
        }elseif(array_key_first($this->_sort) == 'recommendation_rate'){
            $sort = ['$sort' => [
                        'recommendation_rate' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1,
                        'sales_priority' => 1
                ]
            ];
        }elseif(array_key_first($this->_sort) == 'priority'){
            $sort = ['$sort' => [
                        'sales_priority' => 1
                ]
            ];
        }elseif(array_key_first($this->_sort) == 'list' && $this->hasCondition('MediaObject')){
            $sort = ['$sort' => [
                    'sort' => 1
                ]
            ];
        }elseif(array_key_first($this->_sort) == 'valid_from'){
            $sort = ['$sort' => [
                        'valid_from' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1
                ]
            ];
        }else{
            $sort = ['$sort' => [
                    'sales_priority' => 1
                ]
            ];
        }
        if($allow_invalid_offers && isset($sort['$sort'])){
            $sort['$sort'] = array_merge(['has_price' => -1], $sort['$sort']);
        }
        if(!empty($config['data']['search_mongodb']['search']['order_by_primary_object_type_priority'])){
            if(isset($sort['$sort']) && is_array($sort['$sort'])){
                $sort['$sort'] = array_merge(['object_type_order' => 1], $sort['$sort']);
            }else{
                $sort = ['$sort' => ['object_type_order' => 1]];
            }
        }
        $facetStage['$facet']['documents'][] = $sort;
        $stages[] = $facetStage;

        if(!is_null($this->_paginator) && is_a($this->_paginator, \Pressmind\Search\Paginator::class)) {
            $addFieldsStage['$addFields']['currentPage'] = $this->_paginator->getCurrentPage();
            $project['$project']['documents'] = [
                '$slice' => [
                    '$documents',
                    ($this->_paginator->getCurrentPage() -1) * $this->_paginator->getPageSize(),
                    $this->_paginator->getPageSize()
                ]
            ];
            $project['$project']['currentPage'] = 1;
            $project['$project']['pages'] = [
                '$ceil' => [
                    '$divide' => [
                        '$total',
                        $this->_paginator->getPageSize()
                    ]
                ]
            ];
        }
        $stages[] = $addFieldsStage;
        $stages[] = $project;
        return $stages;
    }


    /**
     * @param bool $getFilters
     * @param null $output
     * @param \DateTime|null  $preview_date
     * @param int[] $allowed_visibilities
     * @return false|string
     */
    public function buildQueryAsJson($getFilters = true, $output = null, $preview_date = null, $allowed_visibilities = [30]){
        $this->setGetFilters($getFilters);
        return json_encode($this->buildQuery($output, $preview_date, $allowed_visibilities));
    }

    /**
     * @param string $add
     * @return string
     */
    public function generateCacheKey($add, $output, $preview_date, $allowed_visibilities)
    {
        return 'MONGODB:' . $add . md5(serialize($this->buildQuery($output, $preview_date, $allowed_visibilities)));
    }
}
