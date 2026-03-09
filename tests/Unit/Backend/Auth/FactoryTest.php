<?php

namespace Pressmind\Tests\Unit\Backend\Auth;

use Pressmind\Backend\Auth\BasicAuthProvider;
use Pressmind\Backend\Auth\CallbackProvider;
use Pressmind\Backend\Auth\ConfigPasswordProvider;
use Pressmind\Backend\Auth\Factory;
use Pressmind\Backend\Auth\ProviderInterface;
use Pressmind\Backend\Auth\WordPressProvider;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Pressmind\Backend\Auth\Factory.
 */
class FactoryTest extends AbstractTestCase
{
    public function testCreateWithPasswordReturnsConfigPasswordProvider(): void
    {
        $provider = Factory::create(['provider' => 'password', 'config' => ['password' => 'secret']]);
        $this->assertInstanceOf(ConfigPasswordProvider::class, $provider);
        $this->assertInstanceOf(ProviderInterface::class, $provider);
    }

    public function testCreateWithBasicAuthReturnsBasicAuthProvider(): void
    {
        $provider = Factory::create([
            'provider' => 'basic_auth',
            'config' => ['username' => 'u', 'password' => 'p'],
        ]);
        $this->assertInstanceOf(BasicAuthProvider::class, $provider);
    }

    public function testCreateWithWordpressReturnsWordPressProvider(): void
    {
        $provider = Factory::create(['provider' => 'wordpress', 'config' => []]);
        $this->assertInstanceOf(WordPressProvider::class, $provider);
    }

    public function testCreateWithWordpressPassesCapability(): void
    {
        $provider = Factory::create([
            'provider' => 'wordpress',
            'config' => ['capability' => 'manage_options'],
        ]);
        $this->assertInstanceOf(WordPressProvider::class, $provider);
    }

    public function testCreateWithCallbackReturnsCallbackProvider(): void
    {
        $provider = Factory::create(['provider' => 'callback', 'config' => []]);
        $this->assertInstanceOf(CallbackProvider::class, $provider);
    }

    public function testCreateWithUnknownProviderReturnsConfigPasswordProvider(): void
    {
        $provider = Factory::create(['provider' => 'unknown', 'config' => []]);
        $this->assertInstanceOf(ConfigPasswordProvider::class, $provider);
    }

    public function testCreateWithMissingProviderKeyDefaultsToPassword(): void
    {
        $provider = Factory::create(['config' => ['password' => 'x']]);
        $this->assertInstanceOf(ConfigPasswordProvider::class, $provider);
    }

    public function testCreateFromRegistryWithBackendAuthReturnsProvider(): void
    {
        $config = $this->createMockConfig([
            'backend' => [
                'auth' => [
                    'provider' => 'password',
                    'config' => ['password' => 'test'],
                ],
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        $provider = Factory::createFromRegistry();
        $this->assertInstanceOf(ConfigPasswordProvider::class, $provider);
    }

    public function testCreateFromRegistryWithoutBackendAuthReturnsConfigPasswordProvider(): void
    {
        $config = $this->createMockConfig([]);
        Registry::getInstance()->add('config', $config);
        $provider = Factory::createFromRegistry();
        $this->assertInstanceOf(ConfigPasswordProvider::class, $provider);
    }

    public function testCreateFromRegistryWithBackendButNoAuthReturnsConfigPasswordProvider(): void
    {
        $config = $this->createMockConfig(['backend' => []]);
        Registry::getInstance()->add('config', $config);
        $provider = Factory::createFromRegistry();
        $this->assertInstanceOf(ConfigPasswordProvider::class, $provider);
    }
}
