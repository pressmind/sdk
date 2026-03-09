<?php

/**
 * One-time script to record Pressmind API responses to tests/fixtures/api/
 * for offline replay in ImportIntegration tests.
 *
 * Usage (from SDK project root):
 *   php tests/bin/record-api-snapshot.php
 *   (reads PM_API_KEY, PM_API_USER, PM_API_PASSWORD from .env or environment)
 *
 * Or: cp .env.example .env and fill in credentials, then run the script.
 *
 * Or with config file:
 *   php tests/bin/record-api-snapshot.php --config=path/to/pm-config.php
 */

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer autoload not found. Run composer install.\n");
    exit(1);
}
require $autoload;

$sdkRoot = dirname(__DIR__, 2);
if (is_file($sdkRoot . DIRECTORY_SEPARATOR . '.env')) {
    $lines = file($sdkRoot . DIRECTORY_SEPARATOR . '.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));
        if ($key === '') {
            continue;
        }
        if ((strpos($value, '"') === 0 && substr($value, -1) === '"') || (strpos($value, "'") === 0 && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        if (!getenv($key)) {
            putenv($key . '=' . $value);
        }
    }
}

// Minimal Registry config so REST Client / Writer do not trigger "Undefined array key" warnings when run standalone
if (!defined('APPLICATION_PATH')) {
    define('APPLICATION_PATH', $sdkRoot);
}
\Pressmind\Registry::getInstance()->add('config', [
    'logging' => [
        'mode' => 'NONE',
        'storage' => 'filesystem',
        'categories' => [],
    ],
]);

$fixturePath = dirname(__DIR__) . '/Fixtures/api';
$configFile = null;
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--config=') === 0) {
        $configFile = substr($arg, 9);
        break;
    }
}

if ($configFile && is_file($configFile)) {
    $config = null;
    include $configFile;
    $env = $config['development'] ?? [];
    $apiKey = $env['rest']['client']['api_key'] ?? '';
    $apiUser = $env['rest']['client']['api_user'] ?? '';
    $apiPassword = $env['rest']['client']['api_password'] ?? '';
} else {
    $apiKey = getenv('PM_API_KEY') ?: getenv('API_KEY');
    $apiUser = getenv('PM_API_USER') ?: getenv('API_USER');
    $apiPassword = getenv('PM_API_PASSWORD') ?: getenv('API_PASSWORD');
}

if (empty($apiKey) || empty($apiUser) || empty($apiPassword)) {
    fwrite(STDERR, "Set PM_API_KEY, PM_API_USER, PM_API_PASSWORD in .env or environment (or use --config=pm-config.php).\n");
    exit(1);
}

$baseUrl = 'https://webcore.pressmind.io/' . \Pressmind\REST\Client::WEBCORE_API_VERSION . '/rest/';
$client = new \Pressmind\REST\Client($baseUrl, $apiKey, $apiUser, $apiPassword);
$recorder = new \Pressmind\REST\RecordingClient($client, $fixturePath);
$getByIdBatchSize = 10;

echo "Recording API responses to " . $fixturePath . "\n\n";

$recordStart = microtime(true);
try {
    $response = $recorder->sendRequest('ObjectType', 'getAll');
    if (empty($response->result) || !is_array($response->result)) {
        throw new \RuntimeException('ObjectType getAll returned no result');
    }
    echo "  ObjectType/getAll: " . count($response->result) . " types\n";

    $allTypeIds = [];
    $primaryIds = [];
    foreach ($response->result as $item) {
        $allTypeIds[] = $item->id_type;
        if (in_array($item->gtxf_product_type ?? '', ['TOUR', 'DAYTRIP', 'HOLIDAYHOME'], true)) {
            $primaryIds[] = $item->id_type;
        }
    }
    if (!empty($primaryIds)) {
        $recorder->sendRequest('ObjectType', 'getById', ['ids' => implode(',', $primaryIds)]);
        echo "  ObjectType/getById (primary): " . count($primaryIds) . " ids\n";
    }
    if (!empty($allTypeIds)) {
        $recorder->sendRequest('ObjectType', 'getById', ['ids' => implode(',', $allTypeIds)]);
        echo "  ObjectType/getById (all): " . count($allTypeIds) . " ids\n";
    }

    $allIds = [];
    $typeStats = [];
    foreach ($response->result as $item) {
        $startIndex = 0;
        $numItems = 50;
        $typeName = $item->type_name ?? (string) $item->id_type;
        $typeTotal = 0;
        do {
            $searchResponse = $recorder->sendRequest('Text', 'search', [
                'id_media_object_type' => $item->id_type,
                'visibility' => 30,
                'startIndex' => $startIndex,
                'numItems' => $numItems,
            ]);
            $rawResult = $searchResponse->result ?? null;
            $items = is_array($rawResult) ? $rawResult : ($rawResult !== null ? [$rawResult] : []);
            $total = $searchResponse->count ?? count($items);
            foreach ($items as $mo) {
                $id = $mo->id_media_object ?? $mo->id ?? null;
                if ($id !== null) {
                    $allIds[] = $id;
                }
            }
            $typeTotal += count($items);
            $startIndex += $numItems;
        } while ($startIndex < $total);
        $typeStats[] = ['name' => $typeName, 'id' => $item->id_type, 'count' => $typeTotal];
    }

    echo "\n  Media Object Types:\n";
    foreach ($typeStats as $ts) {
        $countStr = str_pad((string)$ts['count'], 5, ' ', STR_PAD_LEFT);
        echo "    " . $countStr . "  " . $ts['name'] . " (id=" . $ts['id'] . ")\n";
    }

    $uniqueIds = array_values(array_unique($allIds));
    echo "\n  Total unique IDs: " . count($uniqueIds) . "\n";

    foreach ($response->result as $item) {
        $orphanResponse = $recorder->sendRequest('Text', 'search', [
            'id_media_object_type' => $item->id_type,
            'visibility' => 60,
            'startIndex' => 0,
            'numItems' => 50,
        ]);
    }

    $totalRecorded = 0;
    if (!empty($uniqueIds)) {
        $baseParams = [
            'withTouristicData' => 1,
            'withDynamicData' => 1,
            'byTouristicOrigin' => '0',
        ];
        foreach (array_chunk($uniqueIds, $getByIdBatchSize) as $batchIdx => $batch) {
            $idsParam = implode(',', $batch);
            $batchResponse = $client->sendRequest('Text', 'getById', array_merge($baseParams, ['ids' => $idsParam]));
            if (is_object($batchResponse) && !empty($batchResponse->error)) {
                fwrite(STDERR, "  Warning: Text/getById error for batch: " . ($batchResponse->msg ?? 'unknown') . "\n");
                continue;
            }
            $results = is_array($batchResponse) ? $batchResponse : [$batchResponse];
            foreach ($results as $obj) {
                if (!is_object($obj)) {
                    continue;
                }
                $id = $obj->id_media_object ?? $obj->id ?? null;
                if ($id === null) {
                    continue;
                }
                $recorder->writeRecordedFixture('Text', 'getById', array_merge($baseParams, ['ids' => intval($id)]), [$obj]);
                $totalRecorded++;
            }
        }
    }

    $recordDuration = round(microtime(true) - $recordStart, 1);
    $fixtureFiles = glob($fixturePath . '/*.json');
    $fixtureSizeMB = 0;
    foreach ($fixtureFiles ?: [] as $f) {
        $fixtureSizeMB += filesize($f);
    }
    $fixtureSizeMB = round($fixtureSizeMB / 1024 / 1024, 1);

    echo "\n  ───────────────────────────────────────────────────────────\n";
    echo "  Snapshot Summary\n";
    echo "  ───────────────────────────────────────────────────────────\n";
    echo "    Object Types:     " . count($response->result) . "\n";
    echo "    Media Objects:    " . $totalRecorded . " recorded\n";
    echo "    Fixture Files:    " . count($fixtureFiles ?: []) . " (" . $fixtureSizeMB . " MB)\n";
    echo "    Duration:         " . $recordDuration . "s\n";
    echo "    Output:           " . $fixturePath . "\n";
    echo "  ───────────────────────────────────────────────────────────\n\n";
} catch (\Throwable $e) {
    fwrite(STDERR, "Recording failed: " . $e->getMessage() . "\n");
    exit(1);
}
