<?php

namespace Pressmind\Search\Condition\MongoDB;

use DateTime;

class DateRange
{
    /**
     * @var DateTime
     */
    private $_dateFrom;

    /**
     * @var DateTime
     */
    private $_dateTo;

    /**
     * @param DateTime $dateFrom
     * @param DateTime $dateTo
     */
    public function __construct($dateFrom, $dateTo)
    {
        $this->_dateFrom = $dateFrom;
        $this->_dateTo = $dateTo;
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getQuery($type = 'first_match')
    {
        if($type == 'first_match') {
            return ['prices' => [
                '$elemMatch' => [
                    'date_departures' => [
                        '$elemMatch' => [
                            '$gte' => $this->_dateFrom->format('Y-m-d'),
                            '$lte' => $this->_dateTo->format('Y-m-d')
                            ]
                        ]
                ]
            ]
            ];
        } else if($type == 'departure_filter') {
            return [
                [
                    '$addFields' =>
                        [
                            'prices' => [
                                '$filter' => [
                                    'input' => [
                                        '$map' => [
                                            'input' => '$prices',
                                            'as' => 'prices',
                                            'in' => [
                                                '$mergeObjects' => [
                                                    '$$prices',
                                                    [
                                                        'date_departures' => [
                                                            '$filter' => [
                                                                'input' => '$$prices.date_departures',
                                                                'as' => 'a2',
                                                                'cond' => [
                                                                    '$and' => [
                                                                        [
                                                                            '$gte' => [
                                                                                '$$a2',
                                                                                $this->_dateFrom->format('Y-m-d')
                                                                            ]
                                                                        ],
                                                                        [
                                                                            '$lte' => [
                                                                                '$$a2',
                                                                                $this->_dateTo->format('Y-m-d')
                                                                            ]
                                                                        ]
                                                                    ]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ],
                                    'as' => 'a3',
                                    'cond' => [
                                        '$gt' => [
                                            [
                                                '$size' => '$$a3.date_departures'
                                            ],
                                            0
                                        ]
                                    ]
                                ]
                            ]
                        ]
                ]
            ];
        }
        return null;
    }
}
