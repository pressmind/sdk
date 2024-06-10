<?php


namespace Pressmind\REST\Controller;


use Exception;
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
        if(!isset($parameters['id_media_object']) && preg_match('/^[0-9]+$/', $parameters['id_media_object'])) {
            return [
                'success' => false,
                'msg' => 'Code 500: Parameter id_media_object is missing or is not a number',
                'data' => null
            ];
        }
        $id_media_object = $parameters['id_media_object'];
        $config = Registry::getInstance()->get('config');
        $tmp_import_folder = str_replace('APPLICATION_PATH', APPLICATION_PATH, $config['tmp_dir']) . DIRECTORY_SEPARATOR . 'import_ids';
        if(!file_exists($tmp_import_folder)) {
            @mkdir($tmp_import_folder, 0770, true);
        }
        if(file_exists($tmp_import_folder . DIRECTORY_SEPARATOR . $id_media_object)) {
            return [
                'success' => false,
                'msg' => 'Code 531: ID '.$id_media_object.' is already in queue',
                'data' => null
            ];
        }
        if(!file_put_contents($tmp_import_folder . DIRECTORY_SEPARATOR . $id_media_object, 'created_from: api_import')) {
            return [
                'success' => false,
                'msg' => 'Code 531: Can not write file in queue storage',
                'data' => null
            ];
        }
        return [
            'success' => true,
            'msg' => 'object added to queue',
            'data' => null
        ];
    }
}

