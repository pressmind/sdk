<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\REST\Client;

class Brand extends AbstractImport implements ImportInterface
{

    /**
     * @return mixed|void
     */
    public function import()
    {
        $client = new Client();
        try {
            $response = $client->sendRequest('Brand', 'search');
            $this->_checkApiResponse($response);
            if (isset($response->result) && is_array($response->result)) {
                // Collect all brands for batch insert
                $brands = [];
                foreach ($response->result as $result) {
                    $brand = new \Pressmind\ORM\Object\Brand();
                    $brand->id = $result->id;
                    $brand->name = $result->name;
                    $brand->tags = $result->tags;
                    $brand->description = $result->description;
                    $brands[] = $brand;
                }
                // Batch insert all brands at once
                if (!empty($brands)) {
                    \Pressmind\ORM\Object\Brand::batchCreate($brands);
                }
            }
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
        }
    }
}
