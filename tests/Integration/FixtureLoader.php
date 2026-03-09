<?php

namespace Pressmind\Tests\Integration;

use DateTime;

/**
 * Loads JSON fixtures and resolves date offsets to real dates relative to NOW().
 * Used for touristic fixtures so tests do not depend on fixed calendar dates.
 */
class FixtureLoader
{
    /**
     * @var string Base path for fixture files (touristic/, mongodb/, etc.)
     */
    private static $fixtureBasePath;

    /**
     * Resolve an offset in days to a DateTime relative to now.
     */
    public static function resolveDate(int $offsetDays): DateTime
    {
        $date = new DateTime();
        $date->modify($offsetDays >= 0 ? "+{$offsetDays} days" : "{$offsetDays} days");
        return $date;
    }

    /**
     * Load CheapestPriceSpeed-style rows from JSON; keys ending with _offset are
     * resolved to date strings (Y-m-d H:i:s) and the _offset key is removed.
     *
     * @param string $name Fixture name without path and without .json (e.g. scenario_1_pauschalreise)
     * @param string $subdir Subdirectory under fixtures (e.g. touristic, mongodb)
     * @return array<int, array<string, mixed>>
     */
    public static function loadCheapestPriceFixture(string $name, string $subdir = 'touristic'): array
    {
        $path = self::getFixturePath($subdir, $name . '.json');
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Fixture not found or not readable: {$path}");
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid fixture JSON: {$path}");
        }
        // Support both array of rows and single object
        $rows = isset($data[0]) && is_array($data[0]) ? $data : [$data];
        foreach ($rows as &$row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $key => $value) {
                if (substr($key, -7) === '_offset' && is_numeric($value)) {
                    $realKey = str_replace('_offset', '', $key);
                    $row[$realKey] = self::resolveDate((int) $value)->format('Y-m-d H:i:s');
                    unset($row[$key]);
                }
            }
        }
        return $rows;
    }

    /**
     * Load raw JSON fixture (no date resolution). Returns decoded array.
     *
     * @param string $filename Filename with extension (e.g. search_document_standard.json)
     * @param string $subdir Subdirectory under fixtures
     * @return array<string, mixed>
     */
    public static function loadJsonFixture(string $filename, string $subdir = 'mongodb'): array
    {
        $path = self::getFixturePath($subdir, $filename);
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Fixture not found or not readable: {$path}");
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid fixture JSON: {$path}");
        }
        return $data;
    }

    /**
     * Recursively resolve DYNAMIC_ date placeholders in a fixture array.
     * Strings like "DYNAMIC_+30" become Y-m-d date strings offset from today.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function resolveDynamicDates(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = self::resolveDynamicDates($value);
            } elseif (is_string($value) && strpos($value, 'DYNAMIC_') === 0) {
                $offset = (int) substr($value, strlen('DYNAMIC_'));
                $value = self::resolveDate($offset)->format('Y-m-d');
            }
        }
        return $data;
    }

    private static function getFixturePath(string $subdir, string $filename): string
    {
        if (self::$fixtureBasePath === null) {
            self::$fixtureBasePath = dirname(__DIR__) . '/Fixtures';
        }
        $path = self::$fixtureBasePath . '/' . $subdir . '/' . $filename;
        if (!is_file($path)) {
            throw new \RuntimeException("Fixture file does not exist: {$path}");
        }
        return $path;
    }

    /**
     * Allow tests to override fixture base path.
     */
    public static function setFixtureBasePath(string $path): void
    {
        self::$fixtureBasePath = rtrim($path, '/');
    }
}
