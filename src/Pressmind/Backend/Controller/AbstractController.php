<?php

namespace Pressmind\Backend\Controller;

use Pressmind\Backend\Auth\ProviderInterface;

/**
 * Base controller for Backend. Provides auth, render helpers, redirect, json response.
 */
abstract class AbstractController
{
    protected ProviderInterface $auth;
    /** @var array<string, mixed> */
    protected array $request;

    public function __construct(ProviderInterface $auth, array $request = [])
    {
        $this->auth = $auth;
        $this->request = $request;
    }

    protected function get(string $key, $default = null)
    {
        return $this->request[$key] ?? $default;
    }

    /**
     * Output HTML from a view script with optional variables.
     * Uses layout.php unless viewPath is a partial or layout is disabled.
     *
     * @param string $viewPath path to view file (relative to Backend/View or absolute)
     * @param array<string, mixed> $vars
     * @param bool $useLayout whether to wrap in layout (default true)
     */
    protected function render(string $viewPath, array $vars = [], bool $useLayout = true): void
    {
        $title = $vars['title'] ?? '';
        if ($useLayout && strpos($viewPath, 'partials/') !== 0) {
            $contentView = $viewPath;
            $contentVars = array_merge(['currentPage' => $this->get('page', 'dashboard'), 'currentAction' => $this->get('action', 'index')], $vars);
            $layoutPath = $this->resolveViewPath('layout.php');
            if (is_file($layoutPath)) {
                include $layoutPath;
                return;
            }
        }
        extract($vars, EXTR_SKIP);
        $viewFullPath = $this->resolveViewPath($viewPath);
        if (is_file($viewFullPath)) {
            include $viewFullPath;
        }
    }

    /**
     * Resolve view path: if not absolute, look under Backend/View.
     */
    protected function resolveViewPath(string $viewPath): string
    {
        if ($viewPath !== '' && $viewPath[0] === '/') {
            return $viewPath;
        }
        $base = dirname(__DIR__) . '/View/';
        return $base . ltrim($viewPath, '/');
    }

    /**
     * Redirect to URL and exit.
     */
    protected function redirect(string $url, int $code = 302): void
    {
        header('Location: ' . $url, true, $code);
        exit;
    }

    /**
     * Send JSON response and exit.
     *
     * @param mixed $data
     */
    protected function json($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Get auth provider (e.g. for nonce in forms).
     */
    protected function getAuth(): ProviderInterface
    {
        return $this->auth;
    }
}
