<?php

/**
 * Atlas Fulltext Search based on lucene
 * https://www.mongodb.com/docs/atlas/atlas-search/
 */
namespace Pressmind\Search\Condition\MongoDB;

class AtlasLuceneFulltext
{
    /**
     * @var string
     */
    private $_searchString;

    /**
     * @var array
     */
    private $_searchDefinition;

    /**
     * @param string $searchString
     */
    public function __construct($searchString, $definition = [])
    {
        $this->_searchString = trim(str_replace(["\\", '"'], ['', ''], $searchString));
        array_walk_recursive($definition, function (&$element, $index) {
            if(is_string($element)){
                $element = str_replace("{term}", $this->_searchString, $element);
            }
        });
        $this->_searchDefinition = $definition;
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
        if ($type == 'base') {
            if (empty($this->_searchDefinition)) {
                return [
                    '$search' => [
                        'index' => 'default',
                        'compound' => [
                            'should' => [
                                [
                                    'text' => [
                                        'query' => $this->_searchDefinition,
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
                                        'query' => $this->_searchDefinition,
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
                                        'query' => $this->_searchDefinition,
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
                                        'query' => $this->_searchDefinition.'*',
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
                            ]
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
            } else {
                return ['$search' => $this->_searchDefinition];
            }
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
                'prices' => 1,
                'url' => 1,
                'valid_from' => 1,
                'valid_to' => 1,
                'highlights' => [
                    '$meta' => 'searchHighlights'
                ],
                'score' => [
                    '$meta' => 'searchScore'
                ]
            ]
            ];
        }
        return null;
    }
}
