<?php

namespace Pressmind\Tests\Unit\REST;

use Exception;
use Pressmind\REST\Client;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for REST Client: request building, response parsing, error paths.
 * HTTP is mocked via a test subclass that overrides executeRequest().
 */
class ClientTest extends AbstractTestCase
{
    private string $endpoint = 'https://test.example/rest/';
    private string $apiKey = 'test-key';
    private string $apiUser = 'user';
    private string $apiPassword = 'pass';

    protected function tearDown(): void
    {
        Client::closeAllHandles();
        parent::tearDown();
    }

    public function testConstructorWithExplicitParams(): void
    {
        $client = new Client($this->endpoint, $this->apiKey, $this->apiUser, $this->apiPassword);
        $this->assertInstanceOf(Client::class, $client);
        $result = $this->sendRequestWithFakeResponse('{"ok":true}', 200);
        $this->assertIsObject($result);
        $this->assertTrue($result->ok);
    }

    public function testConstructorFromRegistryConfig(): void
    {
        $config = $this->createMockConfig([
            'rest' => [
                'client' => [
                    'api_key' => 'cfg-key',
                    'api_user' => 'cfg-user',
                    'api_password' => 'cfg-pass',
                ],
            ],
        ]);
        \Pressmind\Registry::getInstance()->add('config', $config);
        $client = new class() extends Client {
            protected function executeRequest($ch, $url): array
            {
                return ['body' => '{"from":"config"}', 'http_code' => 200];
            }
        };
        $result = $client->sendRequest('Test', 'test', []);
        $this->assertSame('config', $result->from);
    }

    public function testSendRequestAddsCacheZeroToParams(): void
    {
        $captured = new \stdClass();
        $client = $this->createClientThatCapturesUrl($captured);
        $client->sendRequest('Controller', 'action', ['foo' => 'bar']);
        $this->assertStringContainsString('cache=0', $captured->url);
        $this->assertStringContainsString('foo=bar', $captured->url);
    }

    public function testSendRequestBuildsUrlWithControllerAndAction(): void
    {
        $captured = new \stdClass();
        $client = $this->createClientThatCapturesUrl($captured);
        $client->sendRequest('MediaObject', 'list', []);
        $this->assertStringContainsString('/MediaObject/list', $captured->url);
        $this->assertStringContainsString($this->endpoint . $this->apiKey, $captured->url);
    }

    public function testSendRequestSuccessReturnsDecodedJson(): void
    {
        $result = $this->sendRequestWithFakeResponse('{"count":42,"items":[]}', 200);
        $this->assertSame(42, $result->count);
        $this->assertIsArray($result->items);
    }

    public function testSendRequestThrowsWhenCurlReturnsFalse(): void
    {
        $client = $this->createFakeResponseClient(false, 0);
        $this->expectException(Exception::class);
        $client->sendRequest('C', 'a');
    }

    public function testSendRequestThrowsOnNon200(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Response status code is: 404');
        $this->sendRequestWithFakeResponse('Not Found', 404);
    }

    public function testSendRequestThrowsOnInvalidJson(): void
    {
        $this->expectException(Exception::class);
        $this->sendRequestWithFakeResponse('not json at all', 200);
    }

    public function testCloseAllHandles(): void
    {
        $client = $this->createFakeResponseClient('{}', 200);
        $client->sendRequest('T', 't', []);
        Client::closeAllHandles();
        $client->sendRequest('T', 't', []);
        $this->addToAssertionCount(1);
    }

    public function testExpectedSnapshotApiVersionConstant(): void
    {
        $this->assertSame('v2-27', Client::WEBCORE_API_VERSION);
    }

    /** @param string|false $body */
    private function sendRequestWithFakeResponse($body, int $httpCode): \stdClass
    {
        $client = $this->createFakeResponseClient($body, $httpCode);
        return $client->sendRequest('Test', 'test', []);
    }

    /** @param string|false $body */
    private function createFakeResponseClient($body, int $httpCode): Client
    {
        return new class($this->endpoint, $this->apiKey, $this->apiUser, $this->apiPassword, $body, $httpCode) extends Client {
            private $fakeBody;
            private $fakeCode;

            public function __construct(string $e, string $k, string $u, string $p, $body, int $code)
            {
                parent::__construct($e, $k, $u, $p);
                $this->fakeBody = $body;
                $this->fakeCode = $code;
            }

            protected function executeRequest($ch, $url): array
            {
                return ['body' => $this->fakeBody, 'http_code' => $this->fakeCode];
            }
        };
    }

    private function createClientThatCapturesUrl(\stdClass $captured): Client
    {
        return new class($this->endpoint, $this->apiKey, $this->apiUser, $this->apiPassword, $captured) extends Client {
            private $captured;

            public function __construct(string $e, string $k, string $u, string $p, \stdClass $captured)
            {
                parent::__construct($e, $k, $u, $p);
                $this->captured = $captured;
            }

            protected function executeRequest($ch, $url): array
            {
                $this->captured->url = $url;
                return ['body' => '{}', 'http_code' => 200];
            }
        };
    }
}
