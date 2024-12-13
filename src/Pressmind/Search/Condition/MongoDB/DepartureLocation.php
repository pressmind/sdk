<?php

namespace Pressmind\Search\Condition\MongoDB;

use Pressmind\Registry;
use Pressmind\Search\MongoDB;

class DepartureLocation
{
    private $_departureLocations = [];

    private $_startingpoint_option_ids = [];

    public function __construct($departureLocation)
    {
        $this->_departureLocations = !is_array($departureLocation) ? [$departureLocation] : $departureLocation;
    }

    /**
     * @return string
     */
    public function getType(){
        return (new \ReflectionClass($this))->getShortName();
    }

    public function getQuery($type = 'first_match')
    {
        if(empty($this->_startingpoint_option_ids)){
            return null;
        }
        if($type == 'first_match') {
            return ['prices.startingpoint_option.id' => ['$in' => $this->_startingpoint_option_ids]];
        }
        if($type == 'stage_after_match') {
            return ['$set' => [
                'prices' => [
                    '$filter' => [
                        'input' => '$prices',
                        'as' => 'price',
                        'cond' => [
                            '$in' => [
                                '$$price.startingpoint_option.id',
                                $this->_startingpoint_option_ids
                            ]
                        ]
                    ]
                ]
            ]];
        }
        return null;
    }

    public function prepare($language = null, $origin = null, $agency = null){
        $config = Registry::getInstance()->get('config');
        $or_query = [];
        foreach($this->_departureLocations as $departureLocation) {
            $or_query[] = [
                'zip_ranges' => [
                    '$elemMatch' => [
                        'f' => [
                            '$lte' => $departureLocation
                        ],
                        't' => [
                            '$gte' => $departureLocation
                        ]
                    ]
                ]
            ];
        }
        if(count($or_query) == 0) {
            return false;
        }
        $pipeline = [];
        $pipeline[] = ['$match' => ['$or' => $or_query]];
        $pipeline[] = [ '$group' => [
            '_id' => '$_id',
        ]];
        $collection_name = MongoDB::getCollectionName('startingpoint_option_to_zip_range_', $language, $origin, $agency);
        $client = new \MongoDB\Client($config['data']['search_mongodb']['database']['uri']);
        $db = $client->{$config['data']['search_mongodb']['database']['db']};
        $collection = $db->$collection_name;
        try{
            $result = $collection->aggregate($pipeline, ['allowDiskUse' => true])->toArray();
            $this->_startingpoint_option_ids = [];
            foreach($result as $item){
                $this->_startingpoint_option_ids[] = $item->_id;
            }
            $this->_startingpoint_option_ids = array_unique($this->_startingpoint_option_ids);
        }catch (\Exception $exception){
            echo $exception->getMessage();
            exit;
        }
        if(!empty($_GET['debug']) || (defined('PM_SDK_DEBUG') && PM_SDK_DEBUG)) {
            echo '<pre>Prepare Query: '.json_encode($pipeline).'</pre>';
            echo '<pre>Result: '.json_encode($this->_startingpoint_option_ids).'</pre>';
        }
        return null;
    }
}
