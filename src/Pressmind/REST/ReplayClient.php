<?php

namespace Pressmind\REST;

use Exception;
use stdClass;

/**
 * Replays recorded API responses from JSON fixtures with optional date offset.
 * Implements the same sendRequest(controller, action, params) interface as Client.
 * Used for offline ImportIntegration tests.
 */
class ReplayClient
{
    private string $fixturePath;
    private int $dateOffsetDays = 0;
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(string $fixturePath)
    {
        $this->fixturePath = rtrim($fixturePath, DIRECTORY_SEPARATOR);
        $this->validateSnapshotApiVersion();
        $this->computeDateOffset();
    }

    /**
     * Ensures snapshot was recorded for the API version this SDK expects.
     * When Pressmind releases a new API version, re-record the snapshot.
     *
     * @throws Exception
     */
    private function validateSnapshotApiVersion(): void
    {
        $metaPath = $this->fixturePath . DIRECTORY_SEPARATOR . 'snapshot_meta.json';
        if (!is_file($metaPath)) {
            return;
        }
        $meta = json_decode(file_get_contents($metaPath));
        if (empty($meta->api_version)) {
            return;
        }
        $expected = Client::WEBCORE_API_VERSION;
        if ($meta->api_version !== $expected) {
            throw new Exception(
                'API snapshot version mismatch. Snapshot was recorded for API "' . $meta->api_version . '". '
                . 'This SDK expects "' . $expected . '". '
                . 'Re-record the snapshot: php tests/bin/record-api-snapshot.php'
            );
        }
    }

    private function computeDateOffset(): void
    {
        $metaPath = $this->fixturePath . DIRECTORY_SEPARATOR . 'snapshot_meta.json';
        if (!is_file($metaPath)) {
            return;
        }
        $meta = json_decode(file_get_contents($metaPath));
        if (empty($meta->recording_date)) {
            return;
        }
        $recording = new \DateTime($meta->recording_date);
        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $this->dateOffsetDays = (int) $now->diff($recording)->days;
        if ($recording > $now) {
            $this->dateOffsetDays = -$this->dateOffsetDays;
        }
    }

    /**
     * Return response from fixture or throw if not found.
     * Returns the same type as the original Client: stdClass or array depending on endpoint.
     *
     * @param string $controller
     * @param string $action
     * @param array|null $params
     * @return mixed stdClass or array
     * @throws Exception
     */
    public function sendRequest(string $controller, string $action, $params = null): mixed
    {
        $params = is_array($params) ? $params : [];
        ksort($params);
        $paramsHash = substr(md5(json_encode($params)), 0, 12);
        $safeController = preg_replace('/[^a-zA-Z0-9_-]/', '_', $controller);
        $safeAction = preg_replace('/[^a-zA-Z0-9_-]/', '_', $action);
        $filename = $safeController . '_' . $safeAction . '_' . $paramsHash . '.json';
        $filepath = $this->fixturePath . DIRECTORY_SEPARATOR . $filename;

        $cacheKey = $filename;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        if (!is_file($filepath)) {
            $empty = new stdClass();
            $empty->result = [];
            $empty->count = 0;
            $empty->_replay_fallback = true;
            $this->cache[$cacheKey] = $empty;
            return $empty;
        }

        $payload = json_decode(file_get_contents($filepath));
        if ($payload === null) {
            throw new Exception('Invalid JSON in fixture: ' . $filename);
        }
        $response = $payload->response ?? $payload;
        if ($this->dateOffsetDays !== 0) {
            $response = $this->applyDateOffset($response);
        }
        $this->cache[$cacheKey] = $response;
        return $response;
    }

    /**
     * Recursively shift date-like values in objects/arrays by dateOffsetDays.
     */
    private function applyDateOffset($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'applyDateOffset'], $data);
        }
        if ($data instanceof stdClass) {
            $out = new stdClass();
            foreach ($data as $key => $value) {
                $out->$key = $this->applyDateOffset($value);
            }
            return $out;
        }
        if (is_string($data) && $this->looksLikeDate($data)) {
            return $this->shiftDateString($data);
        }
        return $data;
    }

    private function looksLikeDate(string $s): bool
    {
        if ($s === '0000-00-00' || $s === '0000-00-00 00:00:00') {
            return false;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}(T|\s)/', $s)) {
            return true;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return true;
        }
        return false;
    }

    private function shiftDateString(string $s): string
    {
        try {
            $date = new \DateTime($s, new \DateTimeZone('UTC'));
        } catch (\Exception $e) {
            return $s;
        }
        $date->modify($this->dateOffsetDays . ' days');
        if (strpos($s, 'T') !== false) {
            return $date->format('Y-m-d\TH:i:sP');
        }
        if (strpos($s, ' ') !== false) {
            return $date->format('Y-m-d H:i:s');
        }
        return $date->format('Y-m-d');
    }

    public function getDateOffsetDays(): int
    {
        return $this->dateOffsetDays;
    }
}
