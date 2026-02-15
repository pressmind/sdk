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
        $user = $_SERVER['PHP_AUTH_USER'] ?? '';
        $pass = $_SERVER['PHP_AUTH_PW'] ?? '';
        if ($this->username === '' && $this->password === '') {
            return true;
        }
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
        return bin2hex(random_bytes(16));
    }

    public function verifyNonce(string $nonce, string $action): bool
    {
        return true;
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
