<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\ORM\Object\Touristic\Startingpoint\Option;
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
        if (is_a($response, 'stdClass') && isset($response->result) && is_array($response->result)) {
            foreach ($response->result as $result) {
                if(is_a($result, 'stdClass') && isset($result->options) && is_array($result->options)) {
                    $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): writing data';
                    foreach ($result->options as $option) {
                        $starting_point_option = new Option();
                        unset($option->zip_ranges);
                        $starting_point_option->fromStdClass($option);
                        $starting_point_option->id_startingpoint = $result->id;
                        try {
                            $starting_point_option->create();
                        } catch (Exception $e) {
                            $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): Error writing starting point option with ID ' . $starting_point_option->getId() . ': '. $e->getMessage();
                            $this->_errors[] = 'Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): Error writing starting point option with ID ' . $starting_point_option->getId() . ': '. $e->getMessage();
                        }
                        $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): Starting point option with ID ' . $starting_point_option->getId() . ' written';
                        unset($starting_point_option);
                        $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): Object removed from heap';
                    }
                }
            }
        }
        unset($response);
        $this->_log[] = ' Importer::_importMediaObjectTouristicStartingPointOptions(' . implode(',', $this->_ids) . '): Import finished';
    }
}
