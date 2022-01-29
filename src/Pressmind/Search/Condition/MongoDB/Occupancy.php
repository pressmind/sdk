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

    public function getQuery($type = 'first_match')
    {
        if($type == 'first_match') {
            /*if (count($this->_occupancies) > 1) {
                $foo = [];
                foreach ($this->_occupancies as $occupancy) {
                    $foo[] = ['occupancy' => $occupancy];
                }
                $query['$or'] = $foo;
            } else {
                $query['occupancy'] = $this->_occupancies[0];
            }*/
            return ['prices' => ['$elemMatch' => ['occupancy' => ['$in' => $this->_occupancies]]]];
        } else if($type == 'prices_filter') {
            return [
                ['$in' => ['$$this.occupancy', $this->_occupancies]]
            ];
        }
        return null;
    }
}
