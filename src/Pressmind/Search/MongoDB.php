<?php

namespace Pressmind\Search;

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
    private $_conditions;

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
    public function getResult($getFilters = false, $returnFiltersOnly = false)
    {
        $this->_addLog('getResult(): starting query');
        $this->setGetFilters($getFilters);
        $this->setReturnFiltersOnly($returnFiltersOnly);
        $client = new \MongoDB\Client($this->_db_uri);
        $db_name = $this->_db_name;
        $collection_name = 'best_price_search_based_' . (!empty($this->_language) ? $this->_language.'_' : '') . 'origin_' . $this->_origin;
        $db = $client->$db_name;
        $collection = $db->$collection_name;
        $result = $collection->aggregate($this->buildQuery())->toArray()[0];
        $this->_addLog('getResult(): query completed');
        return $result;
    }

    /**
     * @return array
     */
    public function buildQuery()
    {
        $this->_addLog('_buildQuery(): building query based on conditions');
        $query['$and'] = [];
        $addFieldsConditions = [];
        foreach ($this->_conditions as $condition) {
            $query['$and'][] = $condition->getQuery();
            if(is_array($condition->getQuery('$addFields'))) {
                foreach ($condition->getQuery('$addFields') as $addFieldsCondition) {
                    $addFieldsConditions[] = $addFieldsCondition;
                }
            }
        }
        $match = ['$match' => $query];
        $sort = ['$sort' => ['prices.price_total' => 1]];
        $addFields = ['$addFields' => ['prices' => ['$first' => ['$filter' => ['input' => '$prices', 'cond' => ['$and' => []]]]]]];
        $addFields['$addFields']['prices']['$first']['$filter']['cond']['$and'] = $addFieldsConditions;

        if(true == $this->_get_filters || true == $this->_return_filters_only) {
            $facet = [
                '$facet' => [
                    'prices' => [
                        [
                            '$unwind' => '$prices'
                        ]
                    ],
                    'categoriesUnwound' => [
                        [
                            '$unwind' => '$categories'
                        ]
                    ],
                    'documents' => [
                        [
                            '$match' => [
                                'id_media_object' => [
                                    '$exists' => true
                                ],
                                'prices' => [
                                    '$exists' => true
                                ]
                            ],
                        ],
                        [
                            '$sort' => [
                                'prices.' . array_key_first($this->_sort) => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1
                            ]
                        ]
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

            $addFields2 = [
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

            $project = [
                '$project' => [
                    'documents' => 1,
                    'minDuration' => 1,
                    'maxDuration' => 1,
                    'minPrice' => 1,
                    'maxPrice' => 1,
                    'total' => 1,
                    'categoriesGrouped' => 1
                ]
            ];
        } else  {
            $facet = [
                '$facet' => [
                    'prices' => [
                        [
                            '$unwind' => '$prices'
                        ]
                    ],
                    'documents' => [
                        [
                            '$match' => [
                                'id_media_object' => [
                                    '$exists' => true
                                ]
                            ],
                        ],
                        [
                            '$sort' => [
                                'prices.' . array_key_first($this->_sort) => strtolower($this->_sort[array_key_first($this->_sort)]) == 'asc' ? 1 : -1
                            ]
                        ]
                    ]
                ]
            ];
            

            $addFields2 = [
                '$addFields' => [
                    'total' => [
                        '$size' => '$documents'
                    ]
                ]
            ];

            $project = [
                '$project' => [
                    'documents' => 1,
                    'total' => 1
                ]
            ];
        }

        if(!is_null($this->_paginator) && is_a($this->_paginator, \Pressmind\Search\Paginator::class)) {
            $addFields2['$addFields']['currentPage'] = $this->_paginator->getCurrentPage();
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

        $this->_addLog('_buildQuery(): query build');

        return [
            $match,
            $sort,
            $addFields,
            $facet,
            $addFields2,
            $project
        ];
    }
}
