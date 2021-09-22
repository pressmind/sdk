<?php

namespace Pressmind\Search\Condition\MongoDB;

class PriceRange
{
    private $_priceFrom;

    private $_priceTo;

    public function __construct($priceFrom, $priceTo)
    {
        $this->_priceFrom = intval($priceFrom);
        $this->_priceTo = intval($priceTo);
    }

    public function getQuery($type = '$match')
    {
        if($type == '$match') {
            return [
                'prices' => [
                    '$elemMatch' => [
                        'price_total' => ['$gte' => $this->_priceFrom, '$lte' => $this->_priceTo]
                    ]
                ]
            ];
        } else if($type == '$addFields') {
            return [
                ['$gte' => ['$$this.price_total', $this->_priceFrom]],
                ['$lte' => ['$$this.price_total', $this->_priceTo]],
            ];
        }
        return null;
    }
}
