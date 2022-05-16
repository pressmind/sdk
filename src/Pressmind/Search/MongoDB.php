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
        if(is_null($language)) {
           // $this->_language = $config['data']['languages']['default'];
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
     * @return mixed
     */
    public function getResult($getFilters = false, $returnFiltersOnly = false, $ttl = 0, $output = null)
    {
        $this->_addLog('getResult(): starting query');
        if (!empty($ttl) && Registry::getInstance()->get('config')['cache']['enabled'] && in_array('MONGODB', Registry::getInstance()->get('config')['cache']['types']) && $this->_skip_cache == false) {
            $key = $this->generateCacheKey();
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
        $result = $collection->aggregate($this->buildQuery($output))->toArray()[0];
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
     * @return string|null
     * @throws \Exception
     */
    public function updateCache($key = null){
        $cache_adapter = Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
        if(is_null($key)) {
            $key = $this->generateCacheKey();
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
     * @return array
     */

    /**
     * @param null $output
     * @param false $is_preview
     * @param \DateTime|null $preview_date
     * @return array
     */
    public function buildQuery($output = null, $is_preview = false, $preview_date = null)
    {
        $stages = [];

        // stage 1 first_match
        $elemMatchPrices = [];
        $andQuery['$and'] = [];
        foreach ($this->_conditions as $condition_name => $condition) {
            $andQuery['$and'][]  = $condition->getQuery('first_match');
        }

        if(!empty($andQuery['$and'])){
            $stages[] = ['$match' => $andQuery];
        }

        // stage 1.5 only valid objects if its not a preview OR display the a preview of a defined date
        if($is_preview === false || ($is_preview === true && $preview_date != null)){
            $preview_date = null;
            if($preview_date == null){
                $now = new \DateTime();
                $now->setTimezone(new \DateTimeZone('Europe/Berlin'));
            }else{
                $now = $preview_date;
            }
            $stages[] = [
            '$match' => [
                '$and' => [
                    [
                        '$or' => [
                            [
                                'valid_from' => [
                                    '$lte' => $now->format(DATE_RFC3339_EXTENDED)
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
                                    '$gte' => $now->format(DATE_RFC3339_EXTENDED)
                                ]
                            ],
                            [
                                'valid_to' => null
                            ]
                        ]
                    ]
                ]
            ]
        ];
        }

        // stage 2, remove unneccessary data
        $stages[] = ['$unset' => ['fulltext']];

        // stage 3, filter prices array (by occuppancy, pricerange, durationrange)
        $addFieldsConditions = [];
        $prices_filter = ['$addFields' => ['prices' => ['$first' => ['$filter' => ['input' => '$prices', 'cond' => ['$and' => []]]]]]];
        $prices_filter_cleanup = ['$addFields' => ['prices' => ['$filter' => ['input' => '$prices', 'cond' => ['$and' => []]]]]];
        foreach ($this->_conditions as $condition_name => $condition) {
            $query['$and'][] = $condition->getQuery();
            if(is_array($condition->getQuery('prices_filter'))) {
                foreach ($condition->getQuery('prices_filter') as $addFieldsCondition) {
                    $addFieldsConditions[] = $addFieldsCondition;
                }
            }
        }
        $prices_filter['$addFields']['prices']['$first']['$filter']['cond']['$and'] = $addFieldsConditions;
        $prices_filter_cleanup['$addFields']['prices']['$filter']['cond']['$and'] = $addFieldsConditions;


        // stage 4-x, filter by departure dates
        $departure_range_filter = [];
        if($this->hasCondition('DateRange')){
            $stages[] = $prices_filter_cleanup;
            $condition = $this->getConditionByType('DateRange');
            $stages = array_merge($stages, $condition->getQuery('departure_filter'));
        }
        $stages[] = $prices_filter;

        // stage n - output as date_list
        if($output == 'date_list'){
            $stages[] = ['$unwind' => ['path' => '$prices.date_departures']];
        }

        // stage n, build the filter stages
        if($this->_get_filters === true || $this->_return_filters_only === true) {
            $facetStage = [
                '$facet' => [
                    'prices' => [
                        [
                            '$unwind' => '$prices'
                        ]
                    ],
                    'documents' => [
                        [
                            '$match' => [
                                'prices' => [
                                    '$exists' => true
                                ]
                            ],

                        ],
                    ],
                    'categoriesGrouped' => [
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
                    ]
                ]
            ];

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
                    'categoriesGrouped' => 1
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
                    'documents' => [
                        [
                            '$match' => [
                                'prices' => [
                                    '$exists' => true
                                ]
                            ],
                        ]
                    ]
                ]
            ];
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
            $sort = ['$sort' => [
                        'prices.price_total' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1
                    ]
            ];
        }elseif(array_key_first($this->_sort) == 'date_departure'){
            if($output == 'date_list'){
                $sort = ['$sort' => ['prices.date_departures' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1]];
            }else{
                $addFieldsForDepatureSort = ['$addFields' => ['fst_date_departure' => ['$first' => '$prices.date_departures']]];
                $stages[] = $addFieldsForDepatureSort;
                $sort = ['$sort' => ['fst_date_departure' => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1]];
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
     * @return false|string
     */
    public function buildQueryAsJson($getFilters = true){
        $this->setGetFilters($getFilters);
        return json_encode($this->buildQuery());
    }

    /**
     * @param string $add
     * @return string
     */
    public function generateCacheKey($add = '')
    {
        return 'MONGODB:' . $add . md5(serialize($this->buildQuery()));
    }
}
