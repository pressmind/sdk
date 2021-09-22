<?php

namespace Pressmind\Search\Condition\MongoDB;

class DurationRange
{
    private $_durationFrom;

    private $_durationTo;

    public function __construct($durationFrom, $durationTo)
    {
        $this->_durationFrom = intval($durationFrom);
        $this->_durationTo = intval($durationTo);
    }

    public function getQuery($type = '$match')
    {
        if($type == '$match') {
            return [
                'prices' => [
                    '$elemMatch' => [
                        'duration' => ['$gte' => $this->_durationFrom, '$lte' => $this->_durationTo]
                    ]
                ]
            ];
        } else if($type == '$addFields') {
            return [
                ['$gte' => ['$$this.duration', $this->_durationFrom]],
                ['$lte' => ['$$this.duration', $this->_durationTo]],
            ];
        }
        return null;
    }
}
