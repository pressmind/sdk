<?php


namespace Pressmind\Import;

use Exception;
use Pressmind\Log\Writer;
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
            $this->_checkApiResponse($response);
            if(isset($response->result) && is_array($response->result)) {
                foreach ($response->result as $result) {
                    $existing_dataviews = \Pressmind\ORM\Object\DataView::listAll(['id' => $result->id]);
                    if(count($existing_dataviews) > 0) {
                        $oldview = new \Pressmind\ORM\Object\DataView(intval($result->id));
                        $oldview->delete(true);
                    }
                    $data_view = new \Pressmind\ORM\Object\DataView();
                    $data_view->id = $result->id;
                    $data_view->name = $result->name;
                    $data_view->active = $result->active == 1;
                    $data_view->search_conditions = [];
                    if(is_null($result->filter)) {
                        $result->filter = [];
                        $this->_log[] = Writer::write('DataView::import(): no filters are defined in ' . $result->name, Writer::OUTPUT_BOTH, 'import', Writer::TYPE_INFO);
                    }
                    foreach ($result->filter as $filter_name => $filter_values) {
                        $search_condition = new SearchCondition();
                        $search_condition->class_name = $this->_class_map[$filter_name];
                        $condition_config_name = $this->_config_map[$filter_name];
                        $search_condition->values = json_encode([$condition_config_name => $filter_values]);
                        $data_view->search_conditions[] = $search_condition;
                    }
                    $data_view->create();
                }
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            $this->_errors[] = $e->getMessage();
        }
    }
}