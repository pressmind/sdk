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

    /**
     * Get the from date
     * 
     * @return DateTime|null
     */
    public function getDateFrom(): ?DateTime
    {
        return $this->_dateFrom;
    }

    /**
     * Get the to date
     * 
     * @return DateTime|null
     */
    public function getDateTo(): ?DateTime
    {
        return $this->_dateTo;
    }

    /**
     * Upper bound for date range: $lt with next day handles ISO date strings
     * stored as "2026-04-30T00:00:00.000+02:00" which lexicographically
     * exceed the short format "2026-04-30" used by $lte.
     */
    private function _getExclusiveUpperBound(): string
    {
        $nextDay = clone $this->_dateTo;
        $nextDay->modify('+1 day');
        return $nextDay->format('Y-m-d');
    }

    public function getQuery($type = 'first_match')
    {
        if($type == 'first_match') {
            return ['prices' => [
                '$elemMatch' => [
                    'date_departures' => [
                        '$elemMatch' => [
                            '$gte' => $this->_dateFrom->format('Y-m-d'),
                            '$lt' => $this->_getExclusiveUpperBound()
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
                                                                            '$lt' => [
                                                                                '$$a2',
                                                                                $this->_getExclusiveUpperBound()
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
