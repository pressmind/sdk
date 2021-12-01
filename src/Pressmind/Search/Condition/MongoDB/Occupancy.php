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

    public function getQuery($type = '$match')
    {
        if($type == '$match') {
            /*if (count($this->_occupancies) > 1) {
                $foo = [];
                foreach ($this->_occupancies as $occupancy) {
                    $foo[] = ['occupancy' => $occupancy];
                }
                $query['$or'] = $foo;
            } else {
                $query['occupancy'] = $this->_occupancies[0];
            }*/
            $query = ['prices' => [
                '$elemMatch' => [
                    'occupancy' => ['$in' => $this->_occupancies]
                ]
            ]];
            return $query;
        } else if($type == '$addFields')
        {
            return [
                ['$in' => ['$$this.occupancy', $this->_occupancies]]
            ];
        }
        return null;
    }
}
