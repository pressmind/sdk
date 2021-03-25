<?php


namespace Pressmind\REST\Controller;


use Exception;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;

class Import
{
    /**
     * @param $parameters
     * @return array
     * @throws Exception
     */
    public function index($parameters)
    {
        if(!isset($parameters['id_media_object'])) {
            return ['Status' => 'Code 500: Parameter id_media_object is missing'];
        }
        $importer = new \Pressmind\Import('mediaobject');
        $preview_url = null;
        $importer->importMediaObject($parameters['id_media_object']);
        $importer->postImport();

        $return = ['status' => 'Code 200: Import erfolgreich', 'url' => $preview_url, 'msg' => implode("\n", $importer->getLog())];
        if(isset($parameters['preview']) && $parameters['preview'] == 1) {
            $media_object = new MediaObject($parameters['id_media_object']);
            $config = Registry::getInstance()->get('config');
            $preview_url = str_replace(['{{id_media_object}}', '{{preview}}'], [$media_object->getId(), '1'], $config['data']['preview_url']);
            if (substr($preview_url, 0, 4) != 'http') {
                $preview_url = WEBSERVER_HTTP . $preview_url;
            }
            $return['redirect'] = $preview_url;
        }
        return $return;
    }
}
