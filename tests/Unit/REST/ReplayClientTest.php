<?php

namespace Pressmind\Tests\Unit\REST;

use Exception;
use Pressmind\REST\Client;
use Pressmind\REST\ReplayClient;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for ReplayClient: fixture replay, cache, date offset, error paths.
 */
class ReplayClientTest extends AbstractTestCase
{
    private string $realFixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->realFixturePath = dirname(__DIR__, 2) . '/Fixtures/api';
    }

    public function testConstructorWithPathWithoutMetaSucceeds(): void
    {
        $dir = sys_get_temp_dir() . '/pm-replay-' . uniqid('', true);
        mkdir($dir, 0775, true);
        try {
            $replay = new ReplayClient($dir);
            $this->assertSame(0, $replay->getDateOffsetDays());
        } finally {
            rmdir($dir);
        }
    }

    public function testConstructorWithTrailingSlashNormalizesPath(): void
    {
        $dir = sys_get_temp_dir() . '/pm-replay-' . uniqid('', true);
        mkdir($dir, 0775, true);
        try {
            $replay = new ReplayClient($dir . '/');
            $this->assertSame(0, $replay->getDateOffsetDays());
        } finally {
            rmdir($dir);
        }
    }

    public function testConstructorWithWrongApiVersionThrows(): void
    {
        $dir = sys_get_temp_dir() . '/pm-replay-' . uniqid('', true);
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/snapshot_meta.json', json_encode([
            'recording_date' => '2026-01-01',
            'api_version' => 'v1-old',
        ]));
        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('API snapshot version mismatch');
            new ReplayClient($dir);
        } finally {
            unlink($dir . '/snapshot_meta.json');
            rmdir($dir);
        }
    }

    public function testSendRequestReturnsFallbackWhenFileMissing(): void
    {
        $dir = sys_get_temp_dir() . '/pm-replay-' . uniqid('', true);
        mkdir($dir, 0775, true);
        try {
            $replay = new ReplayClient($dir);
            $result = $replay->sendRequest('NonExistent', 'list', []);
            $this->assertInstanceOf(\stdClass::class, $result);
            $this->assertSame([], $result->result);
            $this->assertSame(0, $result->count);
            $this->assertTrue($result->_replay_fallback);
        } finally {
            rmdir($dir);
        }
    }

    public function testSendRequestCachesResult(): void
    {
        $dir = $this->createTempFixtureDir('{"response":{"cached":true}}', [], 'Cached', 'get');
        $replay = new ReplayClient($dir);
        $first = $replay->sendRequest('Cached', 'get', []);
        $second = $replay->sendRequest('Cached', 'get', []);
        $this->assertSame($first, $second);
        $this->assertTrue($first->cached);
        $this->cleanupTempDir($dir);
    }

    public function testSendRequestReturnsFixtureResponse(): void
    {
        $dir = $this->createTempFixtureDir('{"controller":"F","action":"get","params":{},"response":{"id":42}}', [], 'F', 'get');
        $replay = new ReplayClient($dir);
        $result = $replay->sendRequest('F', 'get', []);
        $this->assertIsObject($result);
        $this->assertSame(42, $result->id);
        $this->cleanupTempDir($dir);
    }

    public function testSendRequestWithParamsMatchesFixture(): void
    {
        $params = ['id' => 1];
        $hash = substr(md5(json_encode($params)), 0, 12);
        $dir = sys_get_temp_dir() . '/pm-replay-' . uniqid('', true);
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/snapshot_meta.json', json_encode([
            'recording_date' => date('Y-m-d'),
            'api_version' => Client::WEBCORE_API_VERSION,
        ]));
        $payload = [
            'controller' => 'P',
            'action' => 'get',
            'params' => $params,
            'response' => (object)['value' => 'found'],
        ];
        file_put_contents($dir . '/P_get_' . $hash . '.json', json_encode($payload));
        try {
            $replay = new ReplayClient($dir);
            $result = $replay->sendRequest('P', 'get', $params);
            $this->assertSame('found', $result->value);
        } finally {
            unlink($dir . '/P_get_' . $hash . '.json');
            unlink($dir . '/snapshot_meta.json');
            rmdir($dir);
        }
    }

    public function testInvalidJsonInFixtureThrows(): void
    {
        $dir = $this->createTempFixtureDir('{}', [], 'Bad', 'get');
        $replay = new ReplayClient($dir);
        $hash = substr(md5(json_encode([])), 0, 12);
        file_put_contents($dir . '/Bad_get_' . $hash . '.json', 'invalid { json');
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid JSON in fixture');
        try {
            $replay->sendRequest('Bad', 'get', []);
        } finally {
            $this->cleanupTempDir($dir);
        }
    }

    public function testGetDateOffsetDays(): void
    {
        if (!is_dir($this->realFixturePath) || !is_file($this->realFixturePath . '/snapshot_meta.json')) {
            $this->markTestSkipped('Real API fixtures not present');
        }
        $replay = new ReplayClient($this->realFixturePath);
        $days = $replay->getDateOffsetDays();
        $this->assertIsInt($days);
    }

    public function testSendRequestFromRealFixturesReturnsArrayResponse(): void
    {
        if (!is_dir($this->realFixturePath)) {
            $this->markTestSkipped('Real API fixtures not present');
        }
        $replay = new ReplayClient($this->realFixturePath);
        $params = [
            'byTouristicOrigin' => '0',
            'ids' => 2552236,
            'withDynamicData' => 1,
            'withTouristicData' => 1,
        ];
        $result = $replay->sendRequest('Text', 'getById', $params);
        if (isset($result->_replay_fallback) && $result->_replay_fallback === true) {
            $this->assertSame(0, $result->count);
            return;
        }
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testResponseWithPayloadPropertyUsesResponseKey(): void
    {
        $dir = $this->createTempFixtureDir('{"controller":"R","action":"a","params":{},"response":{"nested":true}}', [], 'R', 'a');
        $replay = new ReplayClient($dir);
        $result = $replay->sendRequest('R', 'a', []);
        $this->assertTrue($result->nested);
        $this->cleanupTempDir($dir);
    }

    public function testDateOffsetAppliedWhenMetaHasRecordingDate(): void
    {
        $dir = sys_get_temp_dir() . '/pm-replay-' . uniqid('', true);
        mkdir($dir, 0775, true);
        $yesterday = (new \DateTime('now', new \DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d');
        file_put_contents($dir . '/snapshot_meta.json', json_encode([
            'recording_date' => $yesterday,
            'api_version' => Client::WEBCORE_API_VERSION,
        ]));
        $payload = [
            'controller' => 'D',
            'action' => 'get',
            'params' => [],
            'response' => (object)['date' => '2025-06-01'],
        ];
        $hash = substr(md5(json_encode([])), 0, 12);
        file_put_contents($dir . '/D_get_' . $hash . '.json', json_encode($payload));
        try {
            $replay = new ReplayClient($dir);
            $result = $replay->sendRequest('D', 'get', []);
            $this->assertIsObject($result);
            $this->assertNotEmpty($result->date);
            $this->assertNotSame('2025-06-01', $result->date);
        } finally {
            unlink($dir . '/D_get_' . $hash . '.json');
            unlink($dir . '/snapshot_meta.json');
            rmdir($dir);
        }
    }

    private function createTempFixtureDir(string $fixtureContent, array $params, string $controller = 'Cached', string $action = 'get'): string
    {
        $dir = sys_get_temp_dir() . '/pm-replay-' . uniqid('', true);
        mkdir($dir, 0775, true);
        ksort($params);
        $hash = substr(md5(json_encode($params)), 0, 12);
        file_put_contents($dir . '/snapshot_meta.json', json_encode([
            'recording_date' => date('Y-m-d'),
            'api_version' => Client::WEBCORE_API_VERSION,
        ]));
        $filename = $controller . '_' . $action . '_' . $hash . '.json';
        file_put_contents($dir . '/' . $filename, $fixtureContent);
        return $dir;
    }

    private function cleanupTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') as $f) {
            unlink($f);
        }
        rmdir($dir);
    }
}
