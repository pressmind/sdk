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

    public function getQuery($type = '$match')
    {
        if($type == '$match') {
            return [
                'prices' => [
                    '$elemMatch' => [
                        'date_departure' => ['$gte' => $this->_dateFrom->format(DATE_RFC3339_EXTENDED), '$lte' => $this->_dateTo->format(DATE_RFC3339_EXTENDED)]
                    ]
                ]
            ];
        } else if($type == '$addFields') {
            return [
                ['$gte' => ['$$this.date_departure', $this->_dateFrom->format(DATE_RFC3339_EXTENDED)]],
                ['$lte' => ['$$this.date_departure', $this->_dateTo->format(DATE_RFC3339_EXTENDED)]],
            ];
        }
        return null;
    }
}
