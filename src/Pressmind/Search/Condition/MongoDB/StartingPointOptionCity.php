<?php

namespace Pressmind\Search\Condition\MongoDB;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;

class StartingPointOptionCity
{
    private $_id_cities = [];


    public function __construct($id_city)
    {
        $this->_id_cities = !is_array($id_city) ? [$id_city] : $id_city;
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    /**
     * @param $type
     * @return array[]|null
     */
    public function getQuery($type = 'first_match')
    {
        if(empty($this->_id_cities)){
            return null;
        }
        if($type == 'first_match') {
            return ['prices.startingpoint_option.id_city' => ['$in' => $this->_id_cities]];
        }
        if($type == 'stage_after_match') {
            return ['$set' => [
                'prices' => [
                    '$filter' => [
                        'input' => '$prices',
                        'as' => 'price',
                        'cond' => [
                            '$in' => [
                                '$$price.startingpoint_option.id_city',
                                $this->_id_cities
                            ]
                        ]
                    ]
                ]
            ]];
        }
        return null;
    }
}
