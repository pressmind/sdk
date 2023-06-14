<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\ORM\Object\Touristic\Startingpoint\Option;
use Pressmind\Registry;
use Pressmind\REST\Client;

class StartingPointOptions extends AbstractImport implements ImportInterface
{

    /**
     * @var array
     */
    private $_ids = [];

    /**
     * StartingPointOptions constructor.
     * @param $ids
     */
    public function __construct($ids)
    {
        $this->_ids = $ids;
    }

    /**
     * @return mixed|void
     * @throws Exception
     */
    public function import()
    {
        $client = new Client();
        $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): REST request started';
        $response = $client->sendRequest('StartingPoint', 'getById', ['ids' => implode(',', $this->_ids)]);
        $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): REST request done';
        $starting_point_option_ids = [];
        if (is_a($response, 'stdClass') && isset($response->result) && is_array($response->result)) {
            foreach ($response->result as $result) {
                if(is_a($result, 'stdClass') && isset($result->options) && is_array($result->options)) {
                    $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): writing data';
                    foreach ($result->options as $option) {
                        $starting_point_option = new Option();
                        $starting_point_option->fromStdClass($option);
                        $starting_point_option->id_startingpoint = $result->id;
                        try {
                            $starting_point_option->create();
                            $starting_point_option_ids[] = $starting_point_option->id;
                        } catch (Exception $e) {
                            $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): Error writing starting point option with ID ' . $starting_point_option->getId() . ': '. $e->getMessage();
                            $this->_errors[] = 'Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): Error writing starting point option with ID ' . $starting_point_option->getId() . ': '. $e->getMessage();
                        }
                        $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): Starting point option with ID ' . $starting_point_option->getId() . ' written';
                        unset($starting_point_option);
                        $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): Object removed from heap';
                    }
                }
                $this->remove_orphans($result->id, $starting_point_option_ids);
            }
        }
        unset($response);
        $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): Import finished';
    }

    /**
     * @param string $id_starting_point
     * @param string[] $id_starting_point_options
     * @return void
     * @throws Exception
     */
    public function remove_orphans($id_starting_point, $id_starting_point_options){
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $StartingointOption = new Option();
        $id_starting_point_options_str = '"'.implode('","', $id_starting_point_options).'"';
        $db->execute('delete from '.$StartingointOption->getDbTableName().' where id_startingpoint = '.$id_starting_point.' and id not in('.$id_starting_point_options_str.')');
    }

}
