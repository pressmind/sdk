<?php


namespace Pressmind\REST\Controller;


use Exception;
use Pressmind\ORM\Object\Import\Queue;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;

class Import
{
    /**
     * @param $parameters
     * @return array
     * @throws Exception
     */
    public function addToQueue($parameters)
    {
        if(!isset($parameters['id_media_object']) || !preg_match('/^[0-9]+$/', $parameters['id_media_object'])) {
            return [
                'success' => false,
                'msg' => 'Error: Parameter id_media_object is missing or is not a number',
                'data' => null
            ];
        }
        $id_media_object = (int)$parameters['id_media_object'];

        if(Queue::exists($id_media_object)) {
            return [
                'success' => true,
                'msg' => 'Info: ID '.$id_media_object.' is already in queue',
                'data' => null
            ];
        }

        try {
            Queue::addToQueue($id_media_object, 'api_import');
        } catch (Exception $e) {
            return [
                'success' => false,
                'msg' => 'Error: Can not add to queue: ' . $e->getMessage(),
                'data' => null
            ];
        }

        return [
            'success' => true,
            'msg' => 'Success: object added to queue',
            'data' => null
        ];
    }

    public function fullimport($parameters)
    {
        try{
            $Import = new \Pressmind\Import();
            $Import->getIDsToImport();
        }catch (Exception $e) {
            return [
                'success' => false,
                'msg' => 'Error: '.$e->getMessage(),
                'data' => null
            ];
        }
        return [
            'success' => true,
            'msg' => 'Success: objects added to queue',
            'data' => null
        ];
    }

}

