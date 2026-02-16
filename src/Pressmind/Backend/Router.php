<?php

namespace Pressmind\Backend;

use Pressmind\Backend\Auth\ProviderInterface;
use Pressmind\Backend\Controller\AbstractController;

/**
 * Simple request router: ?page=xxx&action=yyy → Controller::action().
 * Default page is "dashboard", default action is "index".
 */
class Router
{
    /** @var array<string, class-string<AbstractController>> */
    private $pageToController = [
        'dashboard' => Controller\DashboardController::class,
        'config' => Controller\ConfigController::class,
        'commands' => Controller\CommandController::class,
        'logs' => Controller\LogController::class,
        'data' => Controller\DataController::class,
        'search' => Controller\SearchController::class,
        'imagecache' => Controller\ImageCacheController::class,
        'import' => Controller\ImportController::class,
        'validation' => Controller\ValidationController::class,
    ];

    private ProviderInterface $auth;

    public function __construct(ProviderInterface $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Dispatch request: instantiate controller and run action.
     * Request array is built from $_GET and $_POST.
     */
    public function dispatch(): void
    {
        $request = array_merge($_GET, $_POST);
        $page = isset($request['page']) && is_string($request['page']) ? $request['page'] : 'dashboard';
        $action = isset($request['action']) && is_string($request['action']) ? $request['action'] : 'index';

        $controllerClass = $this->pageToController[$page] ?? $this->pageToController['dashboard'];
        if (!class_exists($controllerClass)) {
            $this->sendNotFound();
            return;
        }

        $controller = new $controllerClass($this->auth, $request);
        if (!($controller instanceof AbstractController)) {
            $this->sendNotFound();
            return;
        }

        $method = $this->actionToMethod($action);
        if (!method_exists($controller, $method)) {
            $method = 'index';
        }
        if (!method_exists($controller, $method)) {
            $this->sendNotFound();
            return;
        }

        $controller->$method();
    }

    private function actionToMethod(string $action): string
    {
        if ($action === '') {
            return 'index';
        }
        $parts = array_map('ucfirst', explode('_', $action));
        return lcfirst(implode('', $parts)) . 'Action';
    }

    private function sendNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not Found';
        exit;
    }
}
