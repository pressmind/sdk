<?php

namespace Pressmind\Tests\Unit\Backend\Auth;

use Pressmind\Backend\Auth\WordPressProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Pressmind\Backend\Auth\WordPressProvider.
 * Tests behaviour when WordPress functions are not available (default test environment).
 */
class WordPressProviderTest extends TestCase
{
    public function testConstructorAcceptsCapability(): void
    {
        $provider = new WordPressProvider('edit_pages');
        $this->assertInstanceOf(WordPressProvider::class, $provider);
    }

    public function testConstructorDefaultsToEditPages(): void
    {
        $provider = new WordPressProvider();
        $this->assertInstanceOf(WordPressProvider::class, $provider);
    }

    public function testIsAuthenticatedReturnsFalseWhenWordPressNotLoaded(): void
    {
        $provider = new WordPressProvider();
        $this->assertFalse($provider->isAuthenticated());
    }

    public function testGetCurrentUserReturnsNullWhenWordPressNotLoaded(): void
    {
        $provider = new WordPressProvider();
        $this->assertNull($provider->getCurrentUser());
    }

    public function testGetLoginUrlReturnsNullWhenWordPressNotLoaded(): void
    {
        $provider = new WordPressProvider();
        $this->assertNull($provider->getLoginUrl(''));
        $this->assertNull($provider->getLoginUrl('https://example.com/'));
    }

    public function testGetLogoutUrlReturnsNullWhenWordPressNotLoaded(): void
    {
        $provider = new WordPressProvider();
        $this->assertNull($provider->getLogoutUrl());
    }

    public function testRenderLoginFormReturnsNull(): void
    {
        $provider = new WordPressProvider();
        $this->assertNull($provider->renderLoginForm());
    }

    public function testHandleLoginRequestReturnsFalse(): void
    {
        $provider = new WordPressProvider();
        $this->assertFalse($provider->handleLoginRequest());
    }

    public function testCreateNonceReturnsNullWhenWordPressNotLoaded(): void
    {
        $provider = new WordPressProvider();
        $this->assertNull($provider->createNonce('action'));
    }

    public function testVerifyNonceReturnsFalseWhenWordPressNotLoaded(): void
    {
        $provider = new WordPressProvider();
        $this->assertFalse($provider->verifyNonce('any', 'action'));
    }
}
