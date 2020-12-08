<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\DB\Scaffolder\Mysql;
use Pressmind\ObjectTypeScaffolder;
use Pressmind\REST\Client;

class MediaObjectType extends AbstractImport implements ImportInterface
{

    private $_ids = [];

    public function __construct($ids)
    {
        $this->_ids = $ids;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function import()
    {
        $client = new Client();
        $this->_log[] = ' Importer::importMediaObjectTypes(' . implode(',' ,$this->_ids) . '): Starting import';
        $response = $client->sendRequest('ObjectType', 'getById', ['ids' => implode(',', $this->_ids)]);
        $this->_checkApiResponse($response);
        foreach ($response->result as $result) {
            $this->_log[] = ' Importer::importMediaObjectTypes(' . implode(',' ,$this->_ids) . '): Starting scaffolding for ID: ' . $result->id;
            $table_name = $result->id;
            $scaffolder = new ObjectTypeScaffolder($result, $table_name);
            $scaffolder->parse();
            if($scaffolder->hasErrors()) {
                $this->_errors[] = $scaffolder->getErrors();
            }
            $this->_log[] = ' Importer::importMediaObjectTypes(' . implode(',' ,$this->_ids) . '): Scaffolding for ID: ' . $result->id . ' finished';
        }
    }
}
