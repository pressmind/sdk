<?php

namespace Pressmind\Backend\Auth;

use Pressmind\Registry;

/**
 * Simple password-based auth: single password from config (backend.auth.config.password).
 * Login state stored in PHP session. No username.
 */
class ConfigPasswordProvider implements ProviderInterface
{
    private const SESSION_KEY = 'pressmind_backend_auth';
    private const SESSION_USER = 'pressmind_backend_user';
    private const SESSION_FAILED_ATTEMPTS = 'pressmind_backend_failed_attempts';
    private const SESSION_LAST_ATTEMPT = 'pressmind_backend_last_attempt';
    private const RATE_LIMIT_MAX_ATTEMPTS = 5;
    private const RATE_LIMIT_WINDOW_SECONDS = 900;

    private string $password;
    private string $returnUrlParam;

    public function __construct(array $config = [], string $returnUrlParam = 'return_url')
    {
        $this->password = $config['password'] ?? '';
        $this->returnUrlParam = $returnUrlParam;
    }

    public function isAuthenticated(): bool
    {
        $this->ensureSession();
        return !empty($_SESSION[self::SESSION_KEY]);
    }

    public function getCurrentUser(): ?string
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        return $_SESSION[self::SESSION_USER] ?? 'admin';
    }

    public function getLoginUrl(string $returnUrl = ''): ?string
    {
        $base = $this->getCurrentBaseUrl();
        if ($returnUrl !== '') {
            return $base . '&' . $this->returnUrlParam . '=' . urlencode($returnUrl);
        }
        return $base;
    }

    public function getLogoutUrl(): ?string
    {
        $base = $this->getCurrentBaseUrl();
        return $base . '&logout=1';
    }

    public function renderLoginForm(): ?string
    {
        $this->ensureSession();
        $returnUrl = $_GET[$this->returnUrlParam] ?? '';
        $error = '';
        if ($this->isLoginRateLimited()) {
            $error = 'Too many failed login attempts. Please try again later.';
        }
        $nonce = $this->createNonce('backend_login');
        ob_start();
        ?>
        <form method="post" action="" class="p-4">
            <input type="hidden" name="_pm_backend_nonce" value="<?php echo htmlspecialchars($nonce ?? ''); ?>">
            <?php if ($returnUrl !== '') { ?>
                <input type="hidden" name="<?php echo htmlspecialchars($this->returnUrlParam); ?>" value="<?php echo htmlspecialchars($returnUrl); ?>">
            <?php } ?>
            <div class="mb-3">
                <label for="pm_backend_password" class="form-label">Password</label>
                <input type="password" class="form-control" id="pm_backend_password" name="password" autofocus required>
            </div>
            <?php if ($error !== '') { ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
        <?php
        return ob_get_clean();
    }

    public function handleLoginRequest(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }
        $this->ensureSession();
        if ($this->isLoginRateLimited()) {
            return false;
        }
        $nonce = $_POST['_pm_backend_nonce'] ?? '';
        if (!$this->verifyNonce($nonce, 'backend_login')) {
            return false;
        }
        $password = $_POST['password'] ?? '';
        if ($password === '' || $password !== $this->password) {
            $this->recordFailedLoginAttempt();
            return false;
        }
        $this->clearFailedLoginAttempts();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = true;
        $_SESSION[self::SESSION_USER] = 'admin';
        return true;
    }

    private function isLoginRateLimited(): bool
    {
        $attempts = (int) ($_SESSION[self::SESSION_FAILED_ATTEMPTS] ?? 0);
        $last = (int) ($_SESSION[self::SESSION_LAST_ATTEMPT] ?? 0);
        $now = time();
        if ($attempts >= self::RATE_LIMIT_MAX_ATTEMPTS && ($now - $last) < self::RATE_LIMIT_WINDOW_SECONDS) {
            $_SESSION[self::SESSION_FAILED_ATTEMPTS] = $attempts;
            return true;
        }
        if (($now - $last) >= self::RATE_LIMIT_WINDOW_SECONDS) {
            $this->clearFailedLoginAttempts();
        }
        return false;
    }

    private function recordFailedLoginAttempt(): void
    {
        $attempts = (int) ($_SESSION[self::SESSION_FAILED_ATTEMPTS] ?? 0);
        $_SESSION[self::SESSION_FAILED_ATTEMPTS] = $attempts + 1;
        $_SESSION[self::SESSION_LAST_ATTEMPT] = time();
    }

    private function clearFailedLoginAttempts(): void
    {
        unset($_SESSION[self::SESSION_FAILED_ATTEMPTS], $_SESSION[self::SESSION_LAST_ATTEMPT]);
    }

    public function createNonce(string $action): ?string
    {
        $this->ensureSession();
        $key = 'pm_backend_nonce_' . $action;
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(16));
        }
        return $_SESSION[$key];
    }

    public function verifyNonce(string $nonce, string $action): bool
    {
        $this->ensureSession();
        $key = 'pm_backend_nonce_' . $action;
        return isset($_SESSION[$key]) && hash_equals($_SESSION[$key], $nonce);
    }

    /**
     * Call this on logout=1 to clear session.
     */
    public function logout(): void
    {
        $this->ensureSession();
        unset($_SESSION[self::SESSION_KEY], $_SESSION[self::SESSION_USER]);
    }

    /**
     * Start session with secure cookie params (HttpOnly, Secure when HTTPS, SameSite).
     */
    private function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $options = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        session_set_cookie_params($options);
        session_start();
    }

    private function getCurrentBaseUrl(): string
    {
        $url = ($_SERVER['REQUEST_URI'] ?? '');
        $q = strpos($url, '?');
        return $q !== false ? substr($url, 0, $q) : $url;
    }
}
