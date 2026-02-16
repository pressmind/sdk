<?php

namespace Pressmind\Backend;

use Pressmind\Backend\Auth\ProviderInterface;
use Pressmind\Backend\Auth\Factory as AuthFactory;
use Pressmind\Backend\Auth\ConfigPasswordProvider;
use Pressmind\Backend\Auth\BasicAuthProvider;
use Pressmind\Registry;

/**
 * Main entry point for the SDK Backend.
 * Uses Auth provider (from config or passed in), routes requests via Router.
 */
class Application
{
    private ProviderInterface $auth;
    private bool $backendEnabled;

    /**
     * @param ProviderInterface|null $auth If null, provider is built from config (backend.auth).
     */
    public function __construct(?ProviderInterface $auth = null)
    {
        if ($auth !== null) {
            $this->auth = $auth;
        } else {
            $this->auth = AuthFactory::createFromRegistry();
        }
        $this->backendEnabled = $this->readBackendEnabled();
    }

    /**
     * Handle current request: auth, logout, login POST, then dispatch.
     */
    public function handle(): void
    {
        if (!$this->backendEnabled) {
            http_response_code(404);
            echo 'Backend disabled.';
            exit;
        }

        $request = array_merge($_GET, $_POST);

        if (!empty($request['logout']) && $this->auth instanceof ConfigPasswordProvider) {
            $this->auth->logout();
            $redirect = $this->auth->getLoginUrl('');
            if ($redirect !== null) {
                header('Location: ' . $redirect, true, 302);
                exit;
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $this->auth->handleLoginRequest()) {
            $returnUrl = $request['return_url'] ?? $_SERVER['REQUEST_URI'] ?? '';
            $redirect = $this->sanitizeRedirectUrl($returnUrl);
            if ($redirect === null || $redirect === '') {
                $redirect = $_SERVER['REQUEST_URI'] ?? '?page=dashboard';
            }
            header('Location: ' . $redirect, true, 302);
            exit;
        }

        if (!$this->auth->isAuthenticated()) {
            if ($this->auth instanceof BasicAuthProvider) {
                $this->auth->requireAuth();
                exit;
            }
            $form = $this->auth->renderLoginForm();
            if ($form !== null && $form !== '') {
                $this->renderLoginPage($form);
                exit;
            }
            $loginUrl = $this->auth->getLoginUrl($_SERVER['REQUEST_URI'] ?? '');
            if ($loginUrl !== null && $loginUrl !== '' && $this->auth instanceof \Pressmind\Backend\Auth\WordPressProvider) {
                $this->renderWordPressLoginRedirectPage($loginUrl);
                exit;
            }
            if ($loginUrl !== null && $loginUrl !== '') {
                header('Location: ' . $loginUrl, true, 302);
                exit;
            }
            http_response_code(401);
            echo 'Unauthorized';
            exit;
        }

        $router = new Router($this->auth);
        $router->dispatch();
    }

    /**
     * Allow only relative URLs to prevent open redirect. Rejects absolute URLs (http/https) and protocol-relative.
     */
    private function sanitizeRedirectUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (strpos($url, '://') !== false || strpos($url, '//') === 0) {
            return null;
        }
        if ($url[0] !== '/' && $url[0] !== '?') {
            return null;
        }
        return $url;
    }

    private function readBackendEnabled(): bool
    {
        try {
            $config = Registry::getInstance()->get('config');
        } catch (\Throwable $e) {
            return false;
        }
        $backend = $config['backend'] ?? null;
        if (!is_array($backend)) {
            return false;
        }
        return ($backend['enabled'] ?? false) === true;
    }

    private function renderLoginPage(string $formHtml): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Backend Login</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '</head><body><div class="container py-5"><div class="row justify-content-center"><div class="col-md-4">';
        echo '<h1 class="h3 mb-4">Backend Login</h1>';
        echo $formHtml;
        echo '</div></div></div></body></html>';
    }

    /**
     * Show a page with login link instead of redirecting (avoids white page when wp-login.php fails or is not reachable).
     */
    private function renderWordPressLoginRedirectPage(string $loginUrl): void
    {
        header('Content-Type: text/html; charset=utf-8');
        $loginUrlEscaped = htmlspecialchars($loginUrl);
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Backend – Login required</title>';
        echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '</head><body><div class="container py-5"><div class="row justify-content-center"><div class="col-md-6">';
        echo '<h1 class="h3 mb-4">Backend</h1>';
        echo '<p>You must be logged in to WordPress to use the backend.</p>';
        echo '<p><a href="' . $loginUrlEscaped . '" class="btn btn-primary">Go to WordPress Login</a></p>';
        echo '</div></div></div></body></html>';
    }
}
