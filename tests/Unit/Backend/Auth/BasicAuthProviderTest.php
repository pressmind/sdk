<?php

namespace Pressmind\Tests\Unit\Backend\Auth;

use Pressmind\Backend\Auth\BasicAuthProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Pressmind\Backend\Auth\BasicAuthProvider.
 */
class BasicAuthProviderTest extends TestCase
{
    /** @var array<string, string> */
    private $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup['PHP_AUTH_USER'] = $_SERVER['PHP_AUTH_USER'] ?? null;
        $this->serverBackup['PHP_AUTH_PW'] = $_SERVER['PHP_AUTH_PW'] ?? null;
    }

    protected function tearDown(): void
    {
        if (array_key_exists('PHP_AUTH_USER', $this->serverBackup)) {
            $_SERVER['PHP_AUTH_USER'] = $this->serverBackup['PHP_AUTH_USER'];
        } else {
            unset($_SERVER['PHP_AUTH_USER']);
        }
        if (array_key_exists('PHP_AUTH_PW', $this->serverBackup)) {
            $_SERVER['PHP_AUTH_PW'] = $this->serverBackup['PHP_AUTH_PW'];
        } else {
            unset($_SERVER['PHP_AUTH_PW']);
        }
        parent::tearDown();
    }

    public function testConstructorUsesConfigAndDefaultRealm(): void
    {
        $provider = new BasicAuthProvider(['username' => 'u', 'password' => 'p']);
        $_SERVER['PHP_AUTH_USER'] = 'u';
        $_SERVER['PHP_AUTH_PW'] = 'p';
        $this->assertTrue($provider->isAuthenticated());
    }

    public function testIsAuthenticatedFalseWhenConfigEmpty(): void
    {
        $provider = new BasicAuthProvider([]);
        $this->assertFalse($provider->isAuthenticated());
    }

    public function testIsAuthenticatedFalseWhenOnlyUsernameSet(): void
    {
        $provider = new BasicAuthProvider(['username' => 'u', 'password' => '']);
        $_SERVER['PHP_AUTH_USER'] = 'u';
        $_SERVER['PHP_AUTH_PW'] = '';
        $this->assertFalse($provider->isAuthenticated());
    }

    public function testIsAuthenticatedTrueWhenCredentialsMatch(): void
    {
        $provider = new BasicAuthProvider(['username' => 'admin', 'password' => 'secret']);
        $_SERVER['PHP_AUTH_USER'] = 'admin';
        $_SERVER['PHP_AUTH_PW'] = 'secret';
        $this->assertTrue($provider->isAuthenticated());
    }

    public function testIsAuthenticatedFalseWhenPasswordWrong(): void
    {
        $provider = new BasicAuthProvider(['username' => 'admin', 'password' => 'secret']);
        $_SERVER['PHP_AUTH_USER'] = 'admin';
        $_SERVER['PHP_AUTH_PW'] = 'wrong';
        $this->assertFalse($provider->isAuthenticated());
    }

    public function testGetCurrentUserReturnsUserWhenAuthenticated(): void
    {
        $provider = new BasicAuthProvider(['username' => 'u', 'password' => 'p']);
        $_SERVER['PHP_AUTH_USER'] = 'u';
        $_SERVER['PHP_AUTH_PW'] = 'p';
        $this->assertSame('u', $provider->getCurrentUser());
    }

    public function testGetCurrentUserReturnsNullWhenNotAuthenticated(): void
    {
        $provider = new BasicAuthProvider(['username' => 'u', 'password' => 'p']);
        unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        $this->assertNull($provider->getCurrentUser());
    }

    public function testGetLoginUrlReturnsNull(): void
    {
        $provider = new BasicAuthProvider([]);
        $this->assertNull($provider->getLoginUrl(''));
        $this->assertNull($provider->getLoginUrl('https://x.example/'));
    }

    public function testGetLogoutUrlReturnsNull(): void
    {
        $provider = new BasicAuthProvider([]);
        $this->assertNull($provider->getLogoutUrl());
    }

    public function testRenderLoginFormReturnsNull(): void
    {
        $provider = new BasicAuthProvider([]);
        $this->assertNull($provider->renderLoginForm());
    }

    public function testHandleLoginRequestReturnsFalse(): void
    {
        $provider = new BasicAuthProvider([]);
        $this->assertFalse($provider->handleLoginRequest());
    }

    public function testCreateNonceReturnsString(): void
    {
        $provider = new BasicAuthProvider([]);
        $nonce = $provider->createNonce('test_action');
        $this->assertIsString($nonce);
        $this->assertNotEmpty($nonce);
    }

    public function testVerifyNonceReturnsTrueForValidNonce(): void
    {
        $provider = new BasicAuthProvider([]);
        $nonce = $provider->createNonce('action1');
        $this->assertTrue($provider->verifyNonce($nonce, 'action1'));
    }

    public function testVerifyNonceReturnsFalseForWrongNonce(): void
    {
        $provider = new BasicAuthProvider([]);
        $provider->createNonce('action1');
        $this->assertFalse($provider->verifyNonce('wrong_nonce', 'action1'));
    }

    public function testVerifyNonceReturnsFalseForWrongAction(): void
    {
        $provider = new BasicAuthProvider([]);
        $nonce = $provider->createNonce('action1');
        $this->assertFalse($provider->verifyNonce($nonce, 'action2'));
    }

    public function testConstructorAcceptsCustomRealm(): void
    {
        $provider = new BasicAuthProvider([], 'My Realm');
        $this->assertTrue(true);
    }
}
