<?php

namespace Pressmind\Backend\Auth;

/**
 * WordPress authentication: uses current_user_can($capability).
 * Must be used when Backend is loaded from WordPress (e.g. tools/backend.php).
 * Login is handled by WordPress; getLoginUrl returns wp_login_url().
 */
class WordPressProvider implements ProviderInterface
{
    private string $capability;

    public function __construct(string $capability = 'edit_pages')
    {
        $this->capability = $capability;
    }

    public function isAuthenticated(): bool
    {
        if (!function_exists('current_user_can')) {
            return false;
        }
        return current_user_can($this->capability);
    }

    public function getCurrentUser(): ?string
    {
        if (!function_exists('wp_get_current_user')) {
            return null;
        }
        $user = wp_get_current_user();
        if (!$user->exists()) {
            return null;
        }
        return $user->user_login ?? $user->user_email ?? null;
    }

    public function getLoginUrl(string $returnUrl = ''): ?string
    {
        if (!function_exists('wp_login_url') || !function_exists('site_url')) {
            return null;
        }
        $redirect = $returnUrl !== '' ? $returnUrl : ($_SERVER['REQUEST_URI'] ?? '');
        return wp_login_url($redirect);
    }

    public function getLogoutUrl(): ?string
    {
        if (!function_exists('wp_logout_url')) {
            return null;
        }
        return wp_logout_url($_SERVER['REQUEST_URI'] ?? '');
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
        if (!function_exists('wp_create_nonce')) {
            return null;
        }
        return wp_create_nonce($action);
    }

    public function verifyNonce(string $nonce, string $action): bool
    {
        if (!function_exists('wp_verify_nonce')) {
            return false;
        }
        return (bool) wp_verify_nonce($nonce, $action);
    }
}
