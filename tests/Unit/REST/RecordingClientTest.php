<?php

namespace Pressmind\Tests\Unit\REST;

use Exception;
use Pressmind\REST\Client;
use Pressmind\REST\RecordingClient;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

/**
 * Unit tests for RecordingClient: record/replay fixture writing, Lorem replacement.
 */
class RecordingClientTest extends AbstractTestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixtureDir = sys_get_temp_dir() . '/pm-recording-test-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->fixtureDir)) {
            array_map('unlink', glob($this->fixtureDir . '/*'));
            rmdir($this->fixtureDir);
        }
        parent::tearDown();
    }

    public function testConstructorCreatesFixtureDirectory(): void
    {
        $this->assertDirectoryDoesNotExist($this->fixtureDir);
        new RecordingClient($this->createMockClient(), $this->fixtureDir);
        $this->assertDirectoryExists($this->fixtureDir);
    }

    public function testConstructorNormalizesPathWithTrailingSlash(): void
    {
        $withSlash = $this->fixtureDir . '/';
        $rec = new RecordingClient($this->createMockClient(), $withSlash);
        $rec->writeRecordedFixture('C', 'a', [], (object)['x' => 1]);
        $this->assertFileExists($this->fixtureDir . '/C_a_' . substr(md5(json_encode([])), 0, 12) . '.json');
    }

    public function testSendRequestDelegatesToClientAndWritesFixture(): void
    {
        $response = (object)['result' => [1, 2, 3]];
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->with('MediaObject', 'list', [])
            ->willReturn($response);
        $rec = new RecordingClient($client, $this->fixtureDir);
        $out = $rec->sendRequest('MediaObject', 'list', []);
        $this->assertSame($response, $out);
        $hash = substr(md5(json_encode([])), 0, 12);
        $file = $this->fixtureDir . '/MediaObject_list_' . $hash . '.json';
        $this->assertFileExists($file);
        $payload = json_decode(file_get_contents($file));
        $this->assertSame('MediaObject', $payload->controller);
        $this->assertSame('list', $payload->action);
        $this->assertObjectHasProperty('response', $payload);
    }

    public function testSendRequestWithParams(): void
    {
        $response = (object)['id' => 123];
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->with('Text', 'getById', $this->callback(function ($p) {
                return isset($p['ids']) && $p['ids'] === [123];
            }))
            ->willReturn($response);
        $rec = new RecordingClient($client, $this->fixtureDir);
        $rec->sendRequest('Text', 'getById', ['ids' => [123]]);
        $this->addToAssertionCount(1);
    }

    public function testWriteRecordedFixtureWritesFileWithoutApiCall(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('sendRequest');
        $rec = new RecordingClient($client, $this->fixtureDir);
        $response = (object)['foo' => 'bar', 'n' => 42];
        $rec->writeRecordedFixture('Text', 'action', ['p' => 1], $response);
        $hash = substr(md5(json_encode(['p' => 1])), 0, 12);
        $path = $this->fixtureDir . '/Text_action_' . $hash . '.json';
        $this->assertFileExists($path);
        $data = json_decode(file_get_contents($path));
        $this->assertSame('Text', $data->controller);
        $this->assertSame('action', $data->action);
        $this->assertEquals(['p' => 1], (array) $data->params);
        $this->assertEquals($response, $data->response);
    }

    public function testTextControllerSkipsLoremReplacement(): void
    {
        $client = $this->createMock(Client::class);
        $rec = new RecordingClient($client, $this->fixtureDir);
        $response = (object)['name' => 'Original Name', 'value' => 'Keep this text'];
        $rec->writeRecordedFixture('Text', 'getById', ['ids' => 1], $response);
        $hash = substr(md5(json_encode(['ids' => 1])), 0, 12);
        $path = $this->fixtureDir . '/Text_getById_' . $hash . '.json';
        $data = json_decode(file_get_contents($path));
        $this->assertSame('Original Name', $data->response->name);
        $this->assertSame('Keep this text', $data->response->value);
    }

    public function testObjectTypeControllerSkipsLoremReplacement(): void
    {
        $client = $this->createMock(Client::class);
        $rec = new RecordingClient($client, $this->fixtureDir);
        $response = (object)['type_name' => 'Reise'];
        $rec->writeRecordedFixture('ObjectType', 'list', [], $response);
        $hash = substr(md5(json_encode([])), 0, 12);
        $path = $this->fixtureDir . '/ObjectType_list_' . $hash . '.json';
        $data = json_decode(file_get_contents($path));
        $this->assertSame('Reise', $data->response->type_name);
    }

    public function testOtherControllerReplacesStringsWithSameLengthLorem(): void
    {
        $client = $this->createMock(Client::class);
        $rec = new RecordingClient($client, $this->fixtureDir);
        $response = (object)['title' => 'Hi', 'count' => 10];
        $rec->writeRecordedFixture('MediaObject', 'get', [], $response);
        $hash = substr(md5(json_encode([])), 0, 12);
        $path = $this->fixtureDir . '/MediaObject_get_' . $hash . '.json';
        $data = json_decode(file_get_contents($path));
        $this->assertSame(10, $data->response->count);
        $this->assertSame(2, strlen($data->response->title));
        $this->assertNotSame('Hi', $data->response->title);
    }

    public function testSnapshotMetaWrittenOnce(): void
    {
        $client = $this->createMock(Client::class);
        $rec = new RecordingClient($client, $this->fixtureDir);
        $rec->writeRecordedFixture('A', 'b', [], (object)[]);
        $rec->writeRecordedFixture('A', 'c', [], (object)[]);
        $metaPath = $this->fixtureDir . '/snapshot_meta.json';
        $this->assertFileExists($metaPath);
        $meta = json_decode(file_get_contents($metaPath));
        $this->assertObjectHasProperty('recording_date', $meta);
        $this->assertSame(Client::WEBCORE_API_VERSION, $meta->api_version);
    }

    public function testSendRequestThrowsWhenClientThrows(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('sendRequest')->willThrowException(new Exception('API error'));
        $rec = new RecordingClient($client, $this->fixtureDir);
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API error');
        $rec->sendRequest('X', 'y', []);
    }

    private function createMockClient(): Client
    {
        $client = $this->createMock(Client::class);
        $client->method('sendRequest')->willReturn(new stdClass());
        return $client;
    }
}
