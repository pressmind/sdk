<?php

namespace Pressmind\Tests\Unit\Backend\Auth;

use Pressmind\Backend\Auth\ConfigPasswordProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Pressmind\Backend\Auth\ConfigPasswordProvider.
 * Uses a dedicated session save path to avoid affecting other tests.
 */
class ConfigPasswordProviderTest extends TestCase
{
    /** @var string */
    private $sessionPath;

    /** @var array */
    private $serverBackup = [];

    /** @var array */
    private $getBackup = [];

    /** @var array */
    private $postBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionPath = sys_get_temp_dir() . '/pm_auth_test_' . uniqid();
        if (!is_dir($this->sessionPath)) {
            mkdir($this->sessionPath, 0700, true);
        }
        $this->serverBackup['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? null;
        $this->serverBackup['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? null;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $_SERVER['REQUEST_URI'] = '/backend';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SERVER['REQUEST_URI'] = $this->serverBackup['REQUEST_URI'];
        $_SERVER['REQUEST_METHOD'] = $this->serverBackup['REQUEST_METHOD'];
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        if ($this->sessionPath !== null && is_dir($this->sessionPath)) {
            foreach (glob($this->sessionPath . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->sessionPath);
        }
        parent::tearDown();
    }

    private function startSessionForProvider(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_save_path($this->sessionPath);
            session_start();
        }
    }

    public function testConstructorReadsPasswordAndReturnUrlParam(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'mypass'], 'return_to');
        $this->startSessionForProvider();
        $_SERVER['REQUEST_URI'] = '/back';
        $url = $provider->getLoginUrl('https://example.com/');
        $this->assertStringContainsString('return_to=', $url);
        $this->assertStringContainsString('logout=1', $provider->getLogoutUrl());
    }

    public function testIsAuthenticatedFalseWhenNotLoggedIn(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'x']);
        $this->startSessionForProvider();
        $this->assertFalse($provider->isAuthenticated());
    }

    public function testIsAuthenticatedTrueAfterSessionSet(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'x']);
        $this->startSessionForProvider();
        $_SESSION['pressmind_backend_auth'] = true;
        $_SESSION['pressmind_backend_user'] = 'admin';
        $this->assertTrue($provider->isAuthenticated());
    }

    public function testGetCurrentUserReturnsAdminWhenAuthenticated(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'x']);
        $this->startSessionForProvider();
        $_SESSION['pressmind_backend_auth'] = true;
        $_SESSION['pressmind_backend_user'] = 'admin';
        $this->assertSame('admin', $provider->getCurrentUser());
    }

    public function testGetCurrentUserReturnsNullWhenNotAuthenticated(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'x']);
        $this->startSessionForProvider();
        $this->assertNull($provider->getCurrentUser());
    }

    public function testGetLoginUrlContainsBaseAndOptionalReturnUrl(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'x']);
        $this->startSessionForProvider();
        $_SERVER['REQUEST_URI'] = '/backend';
        $base = $provider->getLoginUrl('');
        $this->assertSame('/backend', $base);
        $withReturn = $provider->getLoginUrl('https://app.example/');
        $this->assertStringContainsString('return_url=', $withReturn);
        $this->assertStringContainsString(urlencode('https://app.example/'), $withReturn);
    }

    public function testGetLogoutUrlContainsLogoutParam(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'x']);
        $this->startSessionForProvider();
        $_SERVER['REQUEST_URI'] = '/backend';
        $this->assertStringContainsString('logout=1', $provider->getLogoutUrl());
    }

    public function testRenderLoginFormContainsFormAndNonce(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'x']);
        $this->startSessionForProvider();
        $_GET = [];
        $html = $provider->renderLoginForm();
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('_pm_backend_nonce', $html);
        $this->assertStringContainsString('password', $html);
    }

    public function testHandleLoginRequestReturnsFalseOnGet(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'secret']);
        $this->startSessionForProvider();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
        $this->assertFalse($provider->handleLoginRequest());
    }

    public function testHandleLoginRequestReturnsTrueWithCorrectPassword(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'secret']);
        $this->startSessionForProvider();
        $nonce = $provider->createNonce('backend_login');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_pm_backend_nonce' => $nonce, 'password' => 'secret'];
        $this->assertTrue($provider->handleLoginRequest());
        $this->assertTrue($provider->isAuthenticated());
    }

    public function testHandleLoginRequestReturnsFalseWithWrongPassword(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'secret']);
        $this->startSessionForProvider();
        $nonce = $provider->createNonce('backend_login');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_pm_backend_nonce' => $nonce, 'password' => 'wrong'];
        $this->assertFalse($provider->handleLoginRequest());
        $this->assertFalse($provider->isAuthenticated());
    }

    public function testHandleLoginRequestReturnsFalseWithInvalidNonce(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'secret']);
        $this->startSessionForProvider();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['_pm_backend_nonce' => 'invalid', 'password' => 'secret'];
        $this->assertFalse($provider->handleLoginRequest());
    }

    public function testCreateNonceAndVerifyNonceRoundtrip(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'x']);
        $this->startSessionForProvider();
        $nonce = $provider->createNonce('action1');
        $this->assertIsString($nonce);
        $this->assertTrue($provider->verifyNonce($nonce, 'action1'));
        $this->assertFalse($provider->verifyNonce($nonce, 'action2'));
        $this->assertFalse($provider->verifyNonce('wrong', 'action1'));
    }

    public function testLogoutClearsSession(): void
    {
        $provider = new ConfigPasswordProvider(['password' => 'x']);
        $this->startSessionForProvider();
        $_SESSION['pressmind_backend_auth'] = true;
        $_SESSION['pressmind_backend_user'] = 'admin';
        $this->assertTrue($provider->isAuthenticated());
        $provider->logout();
        $this->assertFalse($provider->isAuthenticated());
    }
}
