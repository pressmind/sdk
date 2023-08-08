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
     * @param array $conditions
     */
    public function __construct($conditions, $sort = ['price_total' => 'asc'], $language = null, $origin = 0)
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
        if(is_null($language) && count($config['data']['languages']['allowed']) > 1) {
            $this->_language = $config['data']['languages']['default'];
        }elseif(!is_null($language)){
            $this->_language = $language;
        }
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
     * @param string $type
     * @return array
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
    public function getResult($getFilters = false, $returnFiltersOnly = false, $ttl = 0, $output = null, $preview_date = null, $allowed_visibilities = [30])
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
        $this->setGetFilters($getFilters);
        $this->setReturnFiltersOnly($returnFiltersOnly);
        $client = new \MongoDB\Client($this->_db_uri);
        $db_name = $this->_db_name;
        $collection_name = 'best_price_search_based_' . (!empty($this->_language) ? $this->_language.'_' : '') . 'origin_' . $this->_origin;
        $db = $client->$db_name;
        $collection = $db->$collection_name;
        $query = $this->buildQuery($output, $preview_date, $allowed_visibilities);
        try{
            $result = $collection->aggregate($query, ['allowDiskUse' => true])->toArray()[0];
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
                $collection_name = 'best_price_search_based_' . (!empty($this->_language) ? $this->_language.'_' : '') . 'origin_' . $this->_origin;
                $db = $client->$db_name;
                $collection = $db->$collection_name;
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
        $stages = [];

        // stage zero, lucene atlas search
        if($this->hasCondition('AtlasLuceneFulltext')){
            $condition = $this->getConditionByType('AtlasLuceneFulltext');
            $stages[] = $condition->getQuery('base');
            $stages[] = $condition->getQuery('project');
        }

        // stage 1 first_match
        $andQuery['$and'] = [];
        foreach ($this->_conditions as $condition_name => $condition) {
            if(empty($condition->getQuery('first_match', $allow_invalid_offers))){
                continue;
            }
            $andQuery['$and'][]  = $condition->getQuery('first_match', $allow_invalid_offers);
        }

        if(!empty($andQuery['$and'])){
            $stages[] = ['$match' => $andQuery];
            if($this->hasCondition('Fulltext')){
                $condition = $this->getConditionByType('Fulltext');
                $stages[] = $condition->getQuery('project');
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
        $stages[] = ['$unset' => ['fulltext']];

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
        $prices_filter['$addFields']['prices']['$filter']['cond']['$and'] = $addFieldsConditions;
        $prices_filter_cleanup['$addFields']['prices']['$filter']['cond']['$and'] = $addFieldsConditions;


        // stage 4-x, filter by departure dates
        if($this->hasCondition('DateRange')){
            $stages[] = $prices_filter_cleanup;
            $condition = $this->getConditionByType('DateRange');
            $stages = array_merge($stages, $condition->getQuery('departure_filter', $allow_invalid_offers));
        }
        $stages[] = $prices_filter;

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
                'url' => 1,
                'valid_from' => 1,
                'valid_to' => 1,
                'visibility' => 1,
                'dates_per_month' => 1,
                'sales_priority' => 1,
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
                                            ['$$this.price_total', '$$value.price_total']
                                        ],
                                        ['$and' => [
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
            }else{
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
                    '$first' => [ '$min' => '$prices.prices.date_departures']
                ];
                $addFieldsStage['$addFields']['maxDeparture'] = [
                    '$first' => [ '$max' => '$prices.prices.date_departures']
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
                    'boardTypesGrouped' => 1,
                    'transportTypesGrouped' => 1
                ]
            ];
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
            if($allow_invalid_offers){
                $sort = ['$sort' => [
                            'has_price' => -1,
                            'prices.price_total' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1,
                            'prices.duration' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? -1 : 1,
                            'sales_priority' => 1
                        ]
                ];
            }else{
                $sort = ['$sort' => [
                    'prices.price_total' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1,
                    'prices.duration' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? -1 : 1,
                    'sales_priority' => 1
                    ]
                ];
            }
        }elseif(array_key_first($this->_sort) == 'score'){
            $sort = ['$sort' => [
                        'score' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1,
                        'sales_priority' => 1
                    ]
            ];
        }elseif(array_key_first($this->_sort) == 'date_departure'){
            if($output == 'date_list'){
                $sort = ['$sort' => [
                            'prices.date_departures' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1,
                            'sales_priority' => 1
                        ]
                ];
            }else{
                $addFieldsForDepatureSort = ['$addFields' => ['fst_date_departure' => ['$first' => '$prices.date_departures']]];
                $stages[] = $addFieldsForDepatureSort;
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
        }else{
            $sort = ['$sort' => [
                    'sales_priority' => 1
                ]
            ];
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
