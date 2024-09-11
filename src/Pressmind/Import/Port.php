<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\REST\Client;

class Port extends AbstractImport implements ImportInterface
{

    /**
     * @return mixed|void
     */
    public function import()
    {
        $client = new Client();
        try {
            $response = $client->sendRequest('Ports', 'getAll');
            $this->_checkApiResponse($response);
            if (isset($response->result) && is_array($response->result)) {
                foreach ($response->result as $result) {
                    try {
                        $port = new \Pressmind\ORM\Object\Port();
                        $port->id = $result->id;
                        $port->code = $result->code;
                        $port->active = $result->active;
                        $port->name = $result->name;
                        $port->description = $result->description;
                        $port->create();
                    } catch (Exception $e) {
                        $this->_errors[] = $e->getMessage();
                    }
                }
            }
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
        }
    }
}
