<?php


namespace Pressmind\Import;


use Pressmind\REST\Client;

class Season extends AbstractImport implements ImportInterface
{

    public function import()
    {
        $client = new Client();
        try {
            $response = $client->sendRequest('Saison', 'search');
            $this->_checkApiResponse($response);
            if(isset($response->result) && is_array($response->result)) {
                foreach ($response->result as $result) {
                    $season = new \Pressmind\ORM\Object\Season();
                    $season->id = $result->id;
                    $season->name = $result->name;
                    $season->active = $result->active;
                    $season->season_from = $result->saison_from;
                    $season->season_to = $result->saison_to;
                    $season->create();
                }
            }
        } catch (\Exception $e) {
            $this->_errors[] = $e->getMessage();
        }
    }
}
