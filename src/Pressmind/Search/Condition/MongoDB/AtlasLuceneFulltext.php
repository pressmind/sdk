<?php

/**
 * Atlas Fulltext Search based on lucene
 * https://www.mongodb.com/docs/atlas/atlas-search/
 */
namespace Pressmind\Search\Condition\MongoDB;
use Pressmind\ORM\Object\FulltextSearch;

#[\AllowDynamicProperties]
class AtlasLuceneFulltext
{
    /**
     * @var string
     */
    private $_searchString;

    /**
     * @var string
     */
    private $_searchStringRaw;

    /**
     * @var array
     */
    private $_searchDefinition;

    /**
     * @var float
     */
    private $_lon;

    /**
     * @var float
     */
    private $_lat;

    /**
     * @var float
     */
    private $_maxDistanceKm;

    /**
     * @var string (must | should)
     */
    private $_locationCondition;

    /**
     * @var string
     */
    private $_gson_property;


    /**
     * @param string $searchString
     */
    public function __construct($searchString, $definition = [], $gson_property = null, $lon = null, $lat = null, $maxDistanceKm = 100, $locationCondition = 'must')
    {
        $this->_searchStringRaw = $searchString;
        $this->_searchString  = !empty($searchString) ? trim(str_replace(["\\", '"'], ['', ''], FulltextSearch::replaceChars($searchString))) : null;
        if(!empty($definition)){
            foreach(['should', 'must'] as $type){
                $valid_queries = [];
                if(!empty($definition['compound'][$type])){
                    foreach($definition['compound'][$type] as $key => $condition){
                        if(str_contains($condition[array_key_first($condition)]['query'], '{term}') && !empty($this->_searchString)){
                            $definition['compound'][$type][$key][array_key_first($condition)]['query'] = str_replace("{term}", $this->_searchString, $definition['compound'][$type][$key][array_key_first($condition)]['query']);
                            $valid_queries[] = $definition['compound'][$type][$key];
                        }
                    }
                    $definition['compound'][$type] = $valid_queries;
                }
            }
            $this->_searchDefinition = $definition;
        }
        $this->_lon = (float)$lon;
        $this->_lat = (float)$lat;
        $this->_maxDistanceKm = (float)$maxDistanceKm;
        $this->_locationCondition = $locationCondition;
        $this->_gson_property = $gson_property;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        $is_valid = false;
        if(!empty($this->_searchDefinition)){
            foreach(['should', 'must'] as $type){
                if(isset($this->_searchDefinition['compound'][$type]) && is_array($this->_searchDefinition['compound'][$type]) && count($this->_searchDefinition['compound'][$type]) > 0){
                    $is_valid = true;
                }
             }
            if(!empty($this->_gson_property) && !empty($this->_lat) && !empty($this->_lon) && !empty($this->_maxDistanceKm)){
                $is_valid = true;
            }
        } else {
            if(!empty($this->_searchString) || (!empty($this->_lon) && !empty($this->_lat) && !empty($this->_maxDistanceKm) && !empty($this->_gson_property))){
                $is_valid = true;
            }
        }
        return $is_valid;
    }

    /**
     * @return string
     */
    public function getSearchStringRaw()
    {
        return $this->_searchStringRaw;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getQuery($type = 'base')
    {
        if(!$this->isValid()){
            return [];
        }
        if ($type == 'base') {
            if (empty($this->_searchDefinition)) {
                $q = [
                    '$search' => [
                        'index' => 'default',
                        'compound' => [
                            'should' => []
                        ],
                        'highlight' => [
                            'path' => [
                                'categories.path_str',
                                'fulltext',
                                'code'
                            ],
                            'maxCharsToExamine'=> 500000,
                            'maxNumPassages'=> 5
                        ]
                    ]
                ];
                if(!empty($this->_searchString)){
                    $q['$search']['compound']['should'] = [
                        [
                            'text' => [
                                'query' => $this->_searchString,
                                'path' => [
                                    'categories.path_str'
                                ],
                                'score' => [
                                    'boost' => [
                                        'value' => 5
                                    ]
                                ],
                                'fuzzy' => [
                                    'maxEdits' => 1,
                                    'prefixLength' => 3
                                ]
                            ],
                        ],
                        [
                            'text' => [
                                'query' => $this->_searchString,
                                'path' => [
                                    'fulltext'
                                ],
                                'fuzzy' => [
                                    'maxEdits' => 1,
                                    'prefixLength' => 3
                                ]
                            ],
                        ],
                        [
                            'phrase' => [
                                'query' => $this->_searchString,
                                'path' => [
                                    'fulltext'
                                ],
                                'slop' => 0,
                                'score' => [
                                    'boost' => [
                                        'value' => 5
                                    ]
                                ],
                            ],
                        ],
                        [
                            'wildcard' => [
                                'query' => $this->_searchString.'*',
                                'path' => [
                                    'code'
                                ],
                                'allowAnalyzedField' => true,
                                'score' => [
                                    'boost' => [
                                        'value' => 10
                                    ]
                                ],
                            ],
                        ],
                    ];
                }
            } else {
                $q = ['$search' => $this->_searchDefinition];
            }
            if(!empty($this->_gson_property) && !empty($this->_lat) && !empty($this->_lon) && !empty($this->_maxDistanceKm)){
                $q['$search']['compound'][$this->_locationCondition][] = [
                    'geoWithin' => [
                        'circle' => [
                            'center' =>
                                [
                                    'type' => 'Point',
                                    'coordinates' => [
                                        $this->_lon,
                                        $this->_lat
                                    ]
                                ],
                            'radius' => $this->_maxDistanceKm * 1000
                        ],
                        'path' => 'locations.'.$this->_gson_property
                    ],
                ];
            }
            return $q;
        }elseif($type == 'project') {
            return ['$project' => [
                        'best_price_meta' => 1,
                        'categories' => 1,
                        'code' => 1,
                        'dates_per_month' => 1,
                        'departure_date_count' => 1,
                        'description' => 1,
                        'groups' => 1,
                        'id_media_object' => 1,
                        'id_object_type' => 1,
                        'last_modified_date' => 1,
                        'locations' => 1,
                        'possible_durations' => 1,
                        'prices' => 1,
                        'url' => 1,
                        'valid_from' => 1,
                        'valid_to' => 1,
                        'visibility' => 30,
                        'highlights' => [
                            '$meta' => 'searchHighlights'
                        ],
                        'score' => [
                            '$meta' => 'searchScore'
                        ]
                ]
            ];
        }
        return [];
    }
}
