<?php

namespace Pressmind\Backend\Auth;

/**
 * Authentication provider interface for the SDK Backend.
 * Implementations can use WordPress, config password, Pressmind API, or custom callbacks.
 */
interface ProviderInterface
{
    /**
     * Whether the current request is authenticated.
     */
    public function isAuthenticated(): bool;

    /**
     * Current user identifier (e.g. username or email), or null if not authenticated.
     */
    public function getCurrentUser(): ?string;

    /**
     * URL to redirect to for login (e.g. WordPress login or backend login page).
     *
     * @param string $returnUrl URL to return to after successful login
     */
    public function getLoginUrl(string $returnUrl = ''): ?string;

    /**
     * URL for logout, or null if not applicable.
     */
    public function getLogoutUrl(): ?string;

    /**
     * HTML for a login form, or null if provider uses external redirect (e.g. WordPress).
     */
    public function renderLoginForm(): ?string;

    /**
     * Handle POST login request (e.g. validate password). Returns true if login succeeded.
     */
    public function handleLoginRequest(): bool;

    /**
     * Create a nonce for the given action (CSRF protection).
     */
    public function createNonce(string $action): ?string;

    /**
     * Verify a nonce for the given action.
     */
    public function verifyNonce(string $nonce, string $action): bool;
}
