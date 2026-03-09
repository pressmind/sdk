<?php

namespace Pressmind\Tests\Unit\Backend\Auth;

use Pressmind\Backend\Auth\CallbackProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Pressmind\Backend\Auth\CallbackProvider.
 */
class CallbackProviderTest extends TestCase
{
    public function testIsAuthenticatedDefaultsToFalse(): void
    {
        $provider = new CallbackProvider([]);
        $this->assertFalse($provider->isAuthenticated());
    }

    public function testGetCurrentUserDefaultsToNull(): void
    {
        $provider = new CallbackProvider([]);
        $this->assertNull($provider->getCurrentUser());
    }

    public function testGetLoginUrlDefaultsToNull(): void
    {
        $provider = new CallbackProvider([]);
        $this->assertNull($provider->getLoginUrl(''));
        $this->assertNull($provider->getLoginUrl('https://return.example/'));
    }

    public function testGetLogoutUrlDefaultsToNull(): void
    {
        $provider = new CallbackProvider([]);
        $this->assertNull($provider->getLogoutUrl());
    }

    public function testRenderLoginFormDefaultsToNull(): void
    {
        $provider = new CallbackProvider([]);
        $this->assertNull($provider->renderLoginForm());
    }

    public function testHandleLoginRequestDefaultsToFalse(): void
    {
        $provider = new CallbackProvider([]);
        $this->assertFalse($provider->handleLoginRequest());
    }

    public function testVerifyNonceDefaultsToTrue(): void
    {
        $provider = new CallbackProvider([]);
        $this->assertTrue($provider->verifyNonce('any', 'action'));
    }

    public function testCreateNonceReturnsString(): void
    {
        $provider = new CallbackProvider([]);
        $nonce = $provider->createNonce('test');
        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
    }

    public function testIsAuthenticatedUsesCallback(): void
    {
        $provider = new CallbackProvider([
            'isAuthenticated' => function () {
                return true;
            },
        ]);
        $this->assertTrue($provider->isAuthenticated());
    }

    public function testGetCurrentUserUsesCallback(): void
    {
        $provider = new CallbackProvider([
            'getCurrentUser' => function () {
                return 'testuser';
            },
        ]);
        $this->assertSame('testuser', $provider->getCurrentUser());
    }

    public function testGetLoginUrlUsesCallbackWithReturnUrl(): void
    {
        $provider = new CallbackProvider([
            'getLoginUrl' => function (string $returnUrl) {
                return 'https://login.example/?redirect=' . $returnUrl;
            },
        ]);
        $this->assertSame('https://login.example/?redirect=https://app.example/', $provider->getLoginUrl('https://app.example/'));
    }

    public function testGetLogoutUrlUsesCallback(): void
    {
        $provider = new CallbackProvider([
            'getLogoutUrl' => function () {
                return 'https://logout.example/';
            },
        ]);
        $this->assertSame('https://logout.example/', $provider->getLogoutUrl());
    }

    public function testRenderLoginFormUsesCallback(): void
    {
        $provider = new CallbackProvider([
            'renderLoginForm' => function () {
                return '<form>Custom</form>';
            },
        ]);
        $this->assertSame('<form>Custom</form>', $provider->renderLoginForm());
    }

    public function testHandleLoginRequestUsesCallback(): void
    {
        $provider = new CallbackProvider([
            'handleLoginRequest' => function () {
                return true;
            },
        ]);
        $this->assertTrue($provider->handleLoginRequest());
    }

    public function testCreateNonceUsesCallback(): void
    {
        $provider = new CallbackProvider([
            'createNonce' => function (string $action) {
                return 'nonce-' . $action;
            },
        ]);
        $this->assertSame('nonce-login', $provider->createNonce('login'));
    }

    public function testVerifyNonceUsesCallback(): void
    {
        $provider = new CallbackProvider([
            'verifyNonce' => function (string $nonce, string $action) {
                return $nonce === 'valid' && $action === 'save';
            },
        ]);
        $this->assertTrue($provider->verifyNonce('valid', 'save'));
        $this->assertFalse($provider->verifyNonce('invalid', 'save'));
    }
}
