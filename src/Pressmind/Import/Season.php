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
                    $season->season_from = !empty($result->saison_from) ? \DateTime::createFromFormat('Y-m-d', $result->saison_from) : null;
                    $season->season_to = !empty($result->saison_to) ? \DateTime::createFromFormat('Y-m-d', $result->saison_to) : null;
                    $season->time_of_year = $result->time_of_year;
                    $season->create();
                }
            }
        } catch (\Exception $e) {
            $this->_errors[] = $e->getMessage();
        }
    }
}
