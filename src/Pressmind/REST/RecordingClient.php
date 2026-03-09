<?php

namespace Pressmind\REST;

use Exception;
use stdClass;

/**
 * Wraps the real REST Client and records every API response as JSON fixtures.
 * Used once to capture API responses for offline replay in tests.
 *
 * All string values in the recorded response are replaced with same-length Lorem Ipsum
 * so no customer or non-public text is stored in the repository.
 *
 * Fixtures are stored under a configurable directory (e.g. tests/fixtures/api/)
 * with filenames: {Controller}_{action}_{hash(params)}.json
 * and snapshot_meta.json with recording_date for date-offset calculation.
 */
class RecordingClient
{
    private const LOREM = 'Lorem ipsum dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. ';

    private Client $client;
    private string $fixturePath;
    private bool $metaWritten = false;

    public function __construct(Client $client, string $fixturePath)
    {
        $this->client = $client;
        $this->fixturePath = rtrim($fixturePath, DIRECTORY_SEPARATOR);
        if (!is_dir($this->fixturePath)) {
            mkdir($this->fixturePath, 0775, true);
        }
    }

    /**
     * Send request to live API and record response to fixture file.
     *
     * @param string $controller
     * @param string $action
     * @param array|null $params
     * @return mixed stdClass or array depending on endpoint
     * @throws Exception
     */
    public function sendRequest(string $controller, string $action, $params = null): mixed
    {
        $response = $this->client->sendRequest($controller, $action, $params);

        $this->writeFixtureFile($controller, $action, is_array($params) ? $params : [], $response);

        return $response;
    }

    /**
     * Write a fixture from an already-fetched response (no API call).
     * Used to split a batch getById response into per-object fixtures.
     *
     * @param string $controller
     * @param string $action
     * @param array $params
     * @param mixed $response stdClass or array
     * @return void
     */
    public function writeRecordedFixture(string $controller, string $action, array $params, mixed $response): void
    {
        $this->writeFixtureFile($controller, $action, $params, $response);
    }

    /**
     * Controllers whose responses must not be Lorem-replaced because they
     * contain structural metadata (type names, field definitions, etc.)
     * that the SDK uses for schema generation and logic decisions.
     */
    private const CONTROLLERS_SKIP_LOREM = ['ObjectType', 'Text'];

    private function writeFixtureFile(string $controller, string $action, array $params, mixed $response): void
    {
        ksort($params);
        $paramsHash = substr(md5(json_encode($params)), 0, 12);
        $safeController = preg_replace('/[^a-zA-Z0-9_-]/', '_', $controller);
        $safeAction = preg_replace('/[^a-zA-Z0-9_-]/', '_', $action);
        $filename = $safeController . '_' . $safeAction . '_' . $paramsHash . '.json';
        $filepath = $this->fixturePath . DIRECTORY_SEPARATOR . $filename;

        $this->writeMetaOnce();

        $storedResponse = in_array($controller, self::CONTROLLERS_SKIP_LOREM, true)
            ? $response
            : $this->replaceTextWithLoremIpsum($response);

        $payload = [
            'controller' => $controller,
            'action' => $action,
            'params' => $params,
            'recorded_at' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'response' => $storedResponse,
        ];
        file_put_contents($filepath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Recursively replace string values with same-length Lorem Ipsum.
     * Numeric strings and date-like strings are left unchanged (structural data).
     */
    private function replaceTextWithLoremIpsum(mixed $data): mixed
    {
        if (is_string($data)) {
            if (is_numeric($data)) {
                return $data;
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
                return $data;
            }
            return $this->loremOfLength(mb_strlen($data));
        }
        if (is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $out[$k] = $this->replaceTextWithLoremIpsum($v);
            }
            return $out;
        }
        if ($data instanceof stdClass) {
            $out = new stdClass();
            foreach ($data as $k => $v) {
                $out->$k = $this->replaceTextWithLoremIpsum($v);
            }
            return $out;
        }
        return $data;
    }

    private function loremOfLength(int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        $base = self::LOREM;
        $baseLen = mb_strlen($base);
        if ($baseLen <= 0) {
            return str_repeat(' ', $length);
        }
        $result = '';
        while (mb_strlen($result) < $length) {
            $result .= $base;
        }
        return mb_substr($result, 0, $length);
    }

    private function writeMetaOnce(): void
    {
        if ($this->metaWritten) {
            return;
        }
        $meta = [
            'recording_date' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d'),
            'api_version' => Client::WEBCORE_API_VERSION,
        ];
        file_put_contents(
            $this->fixturePath . DIRECTORY_SEPARATOR . 'snapshot_meta.json',
            json_encode($meta, JSON_PRETTY_PRINT)
        );
        $this->metaWritten = true;
    }
}
