<?php


namespace Pressmind\Import;

use Exception;
use Pressmind\ORM\Object\DataView\SearchCondition;
use Pressmind\REST\Client;

class DataView extends AbstractImport implements ImportInterface
{

    private $_class_map = [
        'type' => 'ObjectType',
        'visibility' => 'Visibility',
        'state' => 'State',
        'brand' => 'Brand',
        'pool' => 'Pool',
        'categorys' => 'CategoryGlobal'
    ];

    private $_config_map = [
        'type' => 'object_type_id',
        'visibility' => 'visibilities',
        'state' => 'states',
        'brand' => 'brands',
        'pool' => 'pools',
        'categorys' => 'item_ids'
    ];

    public function import()
    {
        $client = new Client();
        try {
            $response = $client->sendRequest('Filter', 'search');
            //print_r($response);
            $this->_checkApiResponse($response);
            if(isset($response->result) && is_array($response->result)) {
                foreach ($response->result as $result) {
                    $data_view = new \Pressmind\ORM\Object\DataView();
                    $data_view->id = $result->id;
                    $data_view->name = $result->name;
                    $data_view->active = $result->active == 1;
                    $data_view->search_conditions = [];
                    foreach ($result->filter as $filter_name => $filter_values) {
                        $search_condition = new SearchCondition();
                        $search_condition->class_name = $this->_class_map[$filter_name];
                        $condition_config_name = $this->_config_map[$filter_name];
                        $search_condition->values = json_encode([$condition_config_name => $filter_values]);
                        $data_view->search_conditions[] = $search_condition;
                    }
                    $data_view->create();
                    /*$brand->id = $result->id;
                    $brand->name = $result->name;
                    $brand->tags = $result->tags;
                    $brand->description = $result->description;
                    $brand->create();*/
                    //print_r($result);
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            $this->_errors[] = $e->getMessage();
        }
    }
}