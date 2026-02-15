<?php

namespace Pressmind\Backend\Auth;

/**
 * Custom authentication via callbacks. Use for arbitrary auth logic (e.g. custom session, API token).
 */
class CallbackProvider implements ProviderInterface
{
    /** @var callable(): bool */
    private $isAuthenticated;
    /** @var callable(): ?string */
    private $getCurrentUser;
    /** @var callable(string): ?string */
    private $getLoginUrl;
    /** @var callable(): ?string */
    private $getLogoutUrl;
    /** @var callable(): ?string */
    private $renderLoginForm;
    /** @var callable(): bool */
    private $handleLoginRequest;
    /** @var callable(string): ?string */
    private $createNonce;
    /** @var callable(string, string): bool */
    private $verifyNonce;

    public function __construct(array $callbacks = [])
    {
        $this->isAuthenticated = $callbacks['isAuthenticated'] ?? static function () {
            return false;
        };
        $this->getCurrentUser = $callbacks['getCurrentUser'] ?? static function () {
            return null;
        };
        $this->getLoginUrl = $callbacks['getLoginUrl'] ?? static function () {
            return null;
        };
        $this->getLogoutUrl = $callbacks['getLogoutUrl'] ?? static function () {
            return null;
        };
        $this->renderLoginForm = $callbacks['renderLoginForm'] ?? static function () {
            return null;
        };
        $this->handleLoginRequest = $callbacks['handleLoginRequest'] ?? static function () {
            return false;
        };
        $this->createNonce = $callbacks['createNonce'] ?? static function (string $action) {
            return bin2hex(random_bytes(16));
        };
        $this->verifyNonce = $callbacks['verifyNonce'] ?? static function () {
            return true;
        };
    }

    public function isAuthenticated(): bool
    {
        return (bool) ($this->isAuthenticated)();
    }

    public function getCurrentUser(): ?string
    {
        return ($this->getCurrentUser)();
    }

    public function getLoginUrl(string $returnUrl = ''): ?string
    {
        return ($this->getLoginUrl)($returnUrl);
    }

    public function getLogoutUrl(): ?string
    {
        return ($this->getLogoutUrl)();
    }

    public function renderLoginForm(): ?string
    {
        return ($this->renderLoginForm)();
    }

    public function handleLoginRequest(): bool
    {
        return (bool) ($this->handleLoginRequest)();
    }

    public function createNonce(string $action): ?string
    {
        return ($this->createNonce)($action);
    }

    public function verifyNonce(string $nonce, string $action): bool
    {
        return (bool) ($this->verifyNonce)($nonce, $action);
    }
}
