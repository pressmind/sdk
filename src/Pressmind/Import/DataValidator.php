<?php

namespace Pressmind\Import;

use Exception;

/**
 * Validates raw API import data for duplicate IDs in nested entities.
 * Use before persisting to DB to detect data that would cause REPLACE INTO to overwrite records incorrectly.
 */
class DataValidator
{
    /**
     * Validates that all entity IDs are unique across the entire nested structure.
     * IDs are collected globally per nesting level (e.g. all zip_range IDs from all options from all parents).
     *
     * @param array|object $data Raw API response data (array of stdClass or single stdClass)
     * @param string $context Label for error messages (e.g. "startingPoints")
     * @param string $path Internal path for recursion (do not pass)
     * @throws Exception when duplicate IDs are found in nested entities
     */
    public static function validateUniqueIds($data, $context = '', $path = '')
    {
        if (is_array($data)) {
            self::validateArray($data, $context, $path);
            return;
        }
        if (is_object($data)) {
            self::validateObject($data, $context, $path);
            return;
        }
    }

    /**
     * @param array $data
     * @param string $context
     * @param string $path
     * @throws Exception
     */
    private static function validateArray(array $data, $context, $path)
    {
        if (empty($data)) {
            return;
        }

        $first = reset($data);
        if (!is_object($first)) {
            return;
        }

        $ids = [];
        foreach ($data as $item) {
            if (is_object($item) && isset($item->id)) {
                $ids[] = $item->id;
            }
        }

        if (!empty($ids)) {
            $duplicates = self::findDuplicateIds($ids);
            if (!empty($duplicates)) {
                self::throwDuplicateException($context, $path, $duplicates);
            }
        }

        $childCollections = self::gatherChildCollections($data);
        foreach ($childCollections as $propertyName => $childArray) {
            $childPath = $path !== '' ? $path . '.' . $propertyName : $propertyName;
            self::validateArray($childArray, $context, $childPath);
        }
    }

    /**
     * @param object $data
     * @param string $context
     * @param string $path
     * @throws Exception
     */
    private static function validateObject($data, $context, $path)
    {
        foreach (get_object_vars($data) as $propertyName => $value) {
            if (is_array($value)) {
                $childPath = $path !== '' ? $path . '.' . $propertyName : $propertyName;
                self::validateArray($value, $context, $childPath);
            } elseif (is_object($value)) {
                $childPath = $path !== '' ? $path . '.' . $propertyName : $propertyName;
                self::validateObject($value, $context, $childPath);
            }
        }
    }

    /**
     * @param array $data Array of objects (e.g. all starting points)
     * @return array<string, array> Property name => merged array of all child items from all parents
     */
    private static function gatherChildCollections(array $data)
    {
        $first = reset($data);
        if (!is_object($first)) {
            return [];
        }

        $result = [];
        foreach (array_keys(get_object_vars($first)) as $propertyName) {
            $merged = [];
            foreach ($data as $item) {
                if (!is_object($item) || !isset($item->$propertyName)) {
                    continue;
                }
                $value = $item->$propertyName;
                if (is_array($value)) {
                    foreach ($value as $child) {
                        if (is_object($child)) {
                            $merged[] = $child;
                        }
                    }
                }
            }
            if (!empty($merged)) {
                $result[$propertyName] = $merged;
            }
        }
        return $result;
    }

    /**
     * @param array $ids
     * @return array<string, int> ID => count (only IDs that appear more than once)
     */
    private static function findDuplicateIds(array $ids)
    {
        $counts = array_count_values($ids);
        $duplicates = [];
        foreach ($counts as $id => $count) {
            if ($count > 1) {
                $duplicates[$id] = $count;
            }
        }
        return $duplicates;
    }

    /**
     * @param string $context
     * @param string $path
     * @param array<string, int> $duplicates
     * @throws Exception
     */
    private static function throwDuplicateException($context, $path, array $duplicates)
    {
        $message = 'DataValidator: Duplicate IDs detected in import data.';
        if ($context !== '') {
            $message .= ' Context: ' . $context;
        }
        if ($path !== '') {
            $message .= ' Path: ' . $path;
        }
        $message .= ' Duplicate IDs (id => count): ' . json_encode($duplicates);
        throw new Exception($message);
    }
}
