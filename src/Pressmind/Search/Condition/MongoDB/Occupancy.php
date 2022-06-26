<?php

namespace Pressmind\Search\Condition\MongoDB;

class Occupancy
{
    private $_occupancies;


    public function __construct($occupancies)
    {
        if(!is_array($occupancies)) {
            $occupancies = [$occupancies];
        }

        // we add "null" to the occupancy to match even price_mixes that have no necessary occupancy
        $this->_occupancies = array_merge([null], $occupancies);
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getQuery($type = 'first_match', $allow_invalid_offers = false)
    {
        if($type == 'first_match') {
            if($allow_invalid_offers === false){
                return ['prices' => ['$elemMatch' => ['occupancy' => ['$in' => $this->_occupancies]]]];
            }else{
                return ['$or' => [
                    ['prices' => [
                        '$elemMatch' => [
                            'occupancy' => [
                                '$in' => $this->_occupancies
                            ]
                        ]
                    ]], [
                        'prices.occupancy.0' => [
                            '$exists' => false
                        ]
                    ]
                ]
                ];
            }
        } else if($type == 'prices_filter') {
            // @TODO: check if this query has an effect to $allow_invalid_offers
            return [
                ['$in' => ['$$this.occupancy', $this->_occupancies]]
            ];
        }
        return null;
    }
}
