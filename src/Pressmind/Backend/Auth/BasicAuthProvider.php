<?php

namespace Pressmind\Backend\Auth;

/**
 * HTTP Basic Auth: validates against username/password from config (backend.auth.config).
 * Browser shows native login dialog. No custom login form.
 */
class BasicAuthProvider implements ProviderInterface
{
    private string $username;
    private string $password;
    private string $realm;

    public function __construct(array $config = [], string $realm = 'Pressmind SDK Backend')
    {
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->realm = $realm;
    }

    public function isAuthenticated(): bool
    {
        if ($this->username === '' || $this->password === '') {
            return false;
        }
        $user = $_SERVER['PHP_AUTH_USER'] ?? '';
        $pass = $_SERVER['PHP_AUTH_PW'] ?? '';
        return $user === $this->username && $pass === $this->password;
    }

    public function getCurrentUser(): ?string
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return $_SERVER['PHP_AUTH_USER'] ?? $this->username;
    }

    public function getLoginUrl(string $returnUrl = ''): ?string
    {
        return null;
    }

    public function getLogoutUrl(): ?string
    {
        return null;
    }

    public function renderLoginForm(): ?string
    {
        return null;
    }

    public function handleLoginRequest(): bool
    {
        return false;
    }

    public function createNonce(string $action): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $key = 'pm_backend_nonce_' . $action;
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(16));
        }
        return $_SESSION[$key];
    }

    public function verifyNonce(string $nonce, string $action): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $key = 'pm_backend_nonce_' . $action;
        return isset($_SESSION[$key]) && hash_equals($_SESSION[$key], $nonce);
    }

    /**
     * Send 401 with WWW-Authenticate header to trigger browser login dialog.
     */
    public function requireAuth(): void
    {
        header('WWW-Authenticate: Basic realm="' . str_replace('"', '\\"', $this->realm) . '"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Authentication required.';
        exit;
    }
}
