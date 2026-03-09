#!/usr/bin/env php
<?php

/**
 * Generate anonymized, date-relative JSON fixtures from a live pmt2core_cheapest_price_speed table.
 * Usage: php tests/bin/generate-fixtures.php <id_media_object> <scenario_name> [table_prefix]
 *
 * Requires DB_HOST, DB_NAME, DB_USER, DB_PASS in environment (e.g. from pm-config or .env).
 * Output: tests/fixtures/touristic/scenario_<name>.json
 *
 * All customer-related text is anonymized; numeric IDs and business data (prices, duration, state) are kept.
 */

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Run composer install first.\n");
    exit(1);
}
require $autoload;

$idMediaObject = $argv[1] ?? null;
$scenarioName = $argv[2] ?? null;
$tablePrefix = $argv[3] ?? 'pmt2core_';

if (empty($idMediaObject) || empty($scenarioName)) {
    fwrite(STDERR, "Usage: php generate-fixtures.php <id_media_object> <scenario_name> [table_prefix]\n");
    exit(1);
}

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

if (empty($dbname)) {
    fwrite(STDERR, "Set DB_NAME (and optionally DB_HOST, DB_USER, DB_PASS).\n");
    exit(1);
}

$table = $tablePrefix . 'cheapest_price_speed';
$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE id_media_object = ?");
$stmt->execute([$idMediaObject]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    fwrite(STDERR, "No rows found for id_media_object={$idMediaObject}\n");
    exit(1);
}

$dateColumns = [
    'date_departure', 'date_arrival', 'earlybird_discount_date_to',
    'transport_1_date_from', 'transport_2_date_from', 'transport_1_date_to', 'transport_2_date_to',
];
$now = new DateTime();

foreach ($rows as &$row) {
    foreach ($dateColumns as $col) {
        if (!isset($row[$col]) || $row[$col] === null || $row[$col] === '') {
            continue;
        }
        try {
            $dt = new DateTime($row[$col]);
            $diff = $now->diff($dt);
            $days = (int) $diff->days;
            if ($dt < $now) {
                $days = -$days;
            }
            $row[$col . '_offset'] = $days;
        } catch (Exception $e) {
            $row[$col . '_offset'] = 0;
        }
        unset($row[$col]);
    }

    $row['option_name'] = 'Test Option ' . substr(md5($row['option_name'] ?? ''), 0, 6);
    $row['housing_package_name'] = isset($row['housing_package_name']) && $row['housing_package_name'] !== ''
        ? 'Test Housing ' . substr(md5($row['housing_package_name']), 0, 6) : ($row['housing_package_name'] ?? null);
    $row['booking_package_name'] = isset($row['booking_package_name']) && $row['booking_package_name'] !== ''
        ? 'Test Package ' . substr(md5($row['booking_package_name']), 0, 6) : ($row['booking_package_name'] ?? null);
    $row['startingpoint_name'] = isset($row['startingpoint_name']) && $row['startingpoint_name'] !== ''
        ? 'Test City ' . substr(md5($row['startingpoint_name']), 0, 4) : ($row['startingpoint_name'] ?? null);
    $row['startingpoint_city'] = isset($row['startingpoint_city']) && $row['startingpoint_city'] !== '' ? 'Teststadt' : ($row['startingpoint_city'] ?? null);
    $row['earlybird_name'] = !empty($row['earlybird_name']) ? 'Earlybird Test' : null;
    $row['transport_1_description'] = !empty($row['transport_1_description']) ? 'Transport Hin' : null;
    $row['transport_2_description'] = !empty($row['transport_2_description']) ? 'Transport Rueck' : null;
    $row['infotext'] = null;
    $row['agency'] = !empty($row['agency']) ? 'agency_' . crc32($row['agency']) : null;
    if (isset($row['transport_1_airport_name'])) {
        $row['transport_1_airport_name'] = !empty($row['transport_1_airport_name']) ? 'Airport A' : null;
    }
    if (isset($row['transport_2_airport_name'])) {
        $row['transport_2_airport_name'] = !empty($row['transport_2_airport_name']) ? 'Airport B' : null;
    }
}

$outDir = dirname(__DIR__) . '/Fixtures/touristic';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}
$filename = preg_replace('/[^a-z0-9_]/', '_', strtolower($scenarioName));
$path = $outDir . '/scenario_' . $filename . '.json';
file_put_contents($path, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Written " . count($rows) . " rows to {$path}\n";
