<?php

namespace Pressmind\Search\Condition\MongoDB;

class Occupancy
{
    private $_occupancies;
    private $_child_occupancies;


    /**
     * @param array|null $occupancies
     * @param array|null $child_occupancies
    */
    public function __construct($occupancies, $child_occupancies = [])
    {
        if(!is_array($occupancies)) {
            $occupancies = [$occupancies];
        }
        if(!is_array($occupancies)) {
            $child_occupancies = [$child_occupancies];
        }
        $this->_occupancies = array_merge([null], $occupancies);
        $this->_child_occupancies = array_merge([null], $child_occupancies);
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * Get the occupancies array (without null value)
     * 
     * @return array
     */
    public function getOccupancies(): array
    {
        return array_filter($this->_occupancies, fn($v) => $v !== null);
    }

    /**
     * Get the child occupancies array (without null value)
     * 
     * @return array
     */
    public function getChildOccupancies(): array
    {
        return array_filter($this->_child_occupancies, fn($v) => $v !== null);
    }

    public function getQuery($type = 'first_match', $allow_invalid_offers = false)
    {
        if($type == 'first_match') {
            if($allow_invalid_offers === false){
                $r = ['prices' => ['$elemMatch' => ['occupancy' => ['$in' => $this->_occupancies]]]];
                if(count($this->_child_occupancies) > 1) {
                    $r['prices']['$elemMatch']['occupancy_child'] = ['$in' => $this->_child_occupancies];
                }
                return $r;
            }else{
                $r = ['$or' => [
                    [
                        'prices' => [
                            '$elemMatch' => [
                                'occupancy' => [
                                    '$in' => $this->_occupancies
                                ]
                            ]
                        ]
                    ],
                    [
                        'prices.occupancy' => [
                            '$exists' => false
                        ]
                    ],
                    [
                        'prices' => [
                            '$size' => 0
                        ]
                    ]
                    ]
                ];
                if(count($this->_child_occupancies) > 1) {
                    $r['$or'][0]['prices']['$elemMatch']['occupancy_child'] = ['$in' => $this->_child_occupancies];
                }
                return $r;
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
