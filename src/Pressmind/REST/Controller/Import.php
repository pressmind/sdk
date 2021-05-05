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
        $config = Registry::getInstance()->get('config');

        if(!isset($parameters['id_media_object'])) {
            return ['Status' => 'Code 500: Parameter id_media_object is missing'];
        }
        if(isset($parameters['backgroundMode'])) {
            $response = [
                'status' => 200,
                'msg' => 'BackgroundMode active: import run in background',
                'url' => null,
                'warnings' => null
            ];
            $import_script_path = APPLICATION_PATH . '/cli/import.php';
            $php_binary = isset($config['server']['php_cli_binary']) && !empty($config['server']['php_cli_binary']) ? $config['server']['php_cli_binary'] : 'php';

            $cmd = 'bash -c "exec nohup ' . $php_binary . ' '. $import_script_path .' mediaobject ' . $parameters['id_media_object'] . ' >/dev/null 2>&1 &"';

            exec($cmd);

            return $response;
        }
        $importer = new \Pressmind\Import('mediaobject');
        $preview_url = null;
        $importer->importMediaObject($parameters['id_media_object']);

        $return = ['status' => 'Code 200: Import erfolgreich', 'url' => $preview_url, 'msg' => implode("\n", $importer->getLog())];
        if(isset($parameters['preview']) && $parameters['preview'] == 1) {
            $config = Registry::getInstance()->get('config');
            $preview_url = str_replace(['{{id_media_object}}', '{{preview}}'], [$parameters['id_media_object'], '1'], $config['data']['preview_url']);
            if (substr($preview_url, 0, 4) != 'http') {
                $preview_url = WEBSERVER_HTTP . $preview_url;
            }
            $return['redirect'] = $preview_url;
        }
        return $return;
    }
}

