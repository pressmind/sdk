<?php
namespace Pressmind\Import;
use Exception;
use Pressmind\Registry;
use Pressmind\REST\Client;

class Powerfilter extends AbstractImport implements ImportInterface
{

    /**
     * @return mixed|void
     */
    public function import()
    {
        $client = new Client();
        try {
            $response = $client->sendRequest('Filter', 'search', ['type' => 'power', 'active' => 'true']);
            $this->_checkApiResponse($response);
            if(isset($response->result) && is_array($response->result)) {
                $valid_ids = [];
                foreach ($response->result as $result) {
                    $filter = new \Pressmind\ORM\Object\Powerfilter();
                    $filter->id = $result->id;
                    $filter->name = $result->name;
                    $filter->create();
                    $valid_ids[] = $filter->id;
                    $responseText = $client->sendRequest('Text', 'getByFilterId', ['id' => $filter->id]);
                    $this->_checkApiResponse($response);
                    if(isset($responseText->result) && is_array($responseText->result)) {
                        $id_media_objects = [];
                        foreach ($responseText->result as $item) {
                            $id_media_objects[] = $item->id;
                        }
                        $result_set = new \Pressmind\ORM\Object\Powerfilter\ResultSet();
                        $result_set->id = $filter->id;
                        $result_set->id_media_objects = implode(',', $id_media_objects);
                        $result_set->create();
                    }

                }
            }
            $this->removeOrphans($valid_ids);
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
        }
    }

    public function removeOrphans($valid_ids = [])
    {
        if(empty($valid_ids)) {
            return;
        }
        $db = Registry::getInstance()->get('db');
        $db->execute('delete from pmt2core_powerfilter_result_set where id not in ('.implode(',', $valid_ids).')');
        $db->execute('delete from pmt2core_powerfilter where id not in ('.implode(',', $valid_ids).')');
    }
}
