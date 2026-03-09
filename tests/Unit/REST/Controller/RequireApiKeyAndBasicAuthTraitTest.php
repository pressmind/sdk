<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Exception;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Tests for the RequireApiKeyAndBasicAuthTrait, used by Redis and Command controllers.
 * We test via a minimal stub class that uses the trait.
 */
class RequireApiKeyAndBasicAuthTraitTest extends AbstractTestCase
{
    private array $originalServer;

    protected $defaultConfig = [
        'cache' => ['enabled' => false, 'types' => []],
        'database' => ['dbname' => 'test'],
        'logging' => ['enable_advanced_object_log' => false],
        'rest' => ['server' => []],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalServer = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        parent::tearDown();
    }

    private function createAuthStub(): AuthTraitStub
    {
        return new AuthTraitStub();
    }

    // --- API key not configured ---

    public function testThrowsWhenApiKeyNotConfigured(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Configure rest.server.api_key');
        $this->createAuthStub()->callRequireAuth([]);
    }

    // --- API key configured but not provided ---

    public function testThrowsWhenApiKeyNotProvided(): void
    {
        $this->setConfig(['api_key' => 'secret', 'api_user' => 'u', 'api_password' => 'p']);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Send it in the X-Api-Key header');
        $this->createAuthStub()->callRequireAuth([]);
    }

    // --- API key wrong ---

    public function testThrowsWhenApiKeyWrong(): void
    {
        $this->setConfig(['api_key' => 'secret', 'api_user' => 'u', 'api_password' => 'p']);
        $_SERVER['HTTP_X_API_KEY'] = 'wrong';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('X-Api-Key');
        $this->createAuthStub()->callRequireAuth([]);
    }

    // --- API key correct via X-Api-Key, but Basic Auth not configured ---

    public function testThrowsWhenBasicAuthNotConfigured(): void
    {
        $this->setConfig(['api_key' => 'secret']);
        $_SERVER['HTTP_X_API_KEY'] = 'secret';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Configure rest.server.api_user');
        $this->createAuthStub()->callRequireAuth([]);
    }

    // --- API key OK, Basic Auth missing ---

    public function testThrowsWhenBasicAuthNotProvided(): void
    {
        $this->setConfig(['api_key' => 'secret', 'api_user' => 'u', 'api_password' => 'p']);
        $_SERVER['HTTP_X_API_KEY'] = 'secret';
        unset($_SERVER['HTTP_AUTHORIZATION']);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Basic Auth');
        $this->createAuthStub()->callRequireAuth([]);
    }

    // --- API key OK, Basic Auth wrong ---

    public function testThrowsWhenBasicAuthWrong(): void
    {
        $this->setConfig(['api_key' => 'secret', 'api_user' => 'admin', 'api_password' => 'pass']);
        $_SERVER['HTTP_X_API_KEY'] = 'secret';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:wrong');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Basic Auth');
        $this->createAuthStub()->callRequireAuth([]);
    }

    // --- All OK ---

    public function testSucceedsWithCorrectApiKeyAndBasicAuth(): void
    {
        $this->setConfig(['api_key' => 'secret', 'api_user' => 'admin', 'api_password' => 'pass']);
        $_SERVER['HTTP_X_API_KEY'] = 'secret';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:pass');
        $this->createAuthStub()->callRequireAuth([]);
        $this->assertTrue(true);
    }

    // --- Bearer token for API key ---

    public function testBearerTokenAcceptedAsApiKey(): void
    {
        $this->setConfig(['api_key' => 'bearer-secret', 'api_user' => 'admin', 'api_password' => 'pass']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer bearer-secret';
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Basic Auth');
        $this->createAuthStub()->callRequireAuth([]);
    }

    // --- api_key in parameters (fallback) ---

    public function testApiKeyFromParametersFallback(): void
    {
        $this->setConfig(['api_key' => 'param-secret', 'api_user' => 'admin', 'api_password' => 'pass']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode('admin:pass');
        $this->createAuthStub()->callRequireAuth(['api_key' => 'param-secret']);
        $this->assertTrue(true);
    }

    // --- Invalid Basic Auth encoding ---

    public function testInvalidBase64InBasicAuthFails(): void
    {
        $this->setConfig(['api_key' => 'secret', 'api_user' => 'admin', 'api_password' => 'pass']);
        $_SERVER['HTTP_X_API_KEY'] = 'secret';
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic !!!invalid!!!';
        $this->expectException(Exception::class);
        $this->createAuthStub()->callRequireAuth([]);
    }

    private function setConfig(array $serverConfig): void
    {
        $config = $this->createMockConfig(['rest' => ['server' => $serverConfig]]);
        Registry::getInstance()->add('config', $config);
    }
}

/**
 * Stub class exposing the trait's private method for testing.
 */
class AuthTraitStub
{
    use \Pressmind\REST\Controller\RequireApiKeyAndBasicAuthTrait;

    public function callRequireAuth(array $parameters): void
    {
        $this->requireApiKeyAndBasicAuth($parameters);
    }
}
