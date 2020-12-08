<?php


namespace Pressmind\MVC;

use \Exception;
use Pressmind\HelperFunctions;

class Snippet
{
    public static function render($pSnippteName, $pData = null) {
        $dispatcher = Dispatcher::getInstance();
        $data = $pData;
        $snippet_file = HelperFunctions::buildPathString([
            APPLICATION_PATH,
            ucfirst($dispatcher->getRequest()->getParameter('module')),
            'Snippet',
            ucfirst($pSnippteName) . '.php'
        ]);
        if(!file_exists($snippet_file)) {
            throw new Exception('Template file ' . $snippet_file . ' does not exist');
        }
        ob_start();
        require($snippet_file);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
