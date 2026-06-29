<?php

namespace Pressmind\Search;

use Pressmind\Registry;

/**
 * Resolves search terms against a pre-built category dictionary.
 *
 * When a user searches for a term that matches a known category name
 * (e.g. "Berlin", "Italien", "Radreisen", "MS Deutschland"),
 * the fulltext search can be converted into an exact category filter.
 *
 * The dictionary is stored in a dedicated MongoDB collection (term_resolver_*)
 * and rebuilt during the indexing process (Indexer::rebuildTermDictionary).
 */
class TermResolver
{
    private static array $_cache = [];

    /**
     * Resolve a search term against the pre-built category dictionary.
     *
     * @param string $term The search term to resolve
     * @param string|null $language Language code (e.g. 'de') or null
     * @param int $origin Touristic origin ID
     * @return array{field: string, id: string, name: string, count: int}|null
     */
    public static function resolve(string $term, ?string $language = null, int $origin = 0): ?array
    {
        $normalized = mb_strtolower(trim($term));
        if (empty($normalized)) {
            return null;
        }
        $dict = self::_loadDictionary($language, $origin);
        return $dict[$normalized] ?? null;
    }

    /**
     * Return the complete pre-built category dictionary.
     *
     * @param string|null $language
     * @param int $origin
     * @return array<string, array{field: string, id: string, name: string, count: int}>
     */
    public static function all(?string $language = null, int $origin = 0): array
    {
        return self::_loadDictionary($language, $origin);
    }

    /**
     * Load the full dictionary from the pre-built term_resolver collection.
     * Result is cached per process (static).
     *
     * @param string|null $language
     * @param int $origin
     * @return array<string, array{field: string, id: string, name: string, count: int}>
     */
    private static function _loadDictionary(?string $language, int $origin): array
    {
        $cacheKey = ($language ?? '') . '_' . $origin;
        if (isset(self::$_cache[$cacheKey])) {
            return self::$_cache[$cacheKey];
        }

        $config = Registry::getInstance()->get('config');
        if (empty($config['data']['search_mongodb']['database']['uri'])) {
            self::$_cache[$cacheKey] = [];
            return [];
        }

        $uri = $config['data']['search_mongodb']['database']['uri'];
        $dbName = $config['data']['search_mongodb']['database']['db'];
        $db = MongoDB::getDatabase($uri, $dbName);

        $collectionName = 'term_resolver_'
            . (!empty($language) ? $language . '_' : '')
            . 'origin_' . $origin;

        try {
            $docs = $db->{$collectionName}->find()->toArray();
        } catch (\Exception $e) {
            self::$_cache[$cacheKey] = [];
            return [];
        }

        $dict = [];
        foreach ($docs as $doc) {
            $dict[$doc->_id] = [
                'field' => $doc->field,
                'id' => (string)$doc->id_item,
                'name' => $doc->name,
                'count' => (int)$doc->count,
            ];
        }

        self::$_cache[$cacheKey] = $dict;
        return $dict;
    }

    /**
     * Get all category field names from the search_mongodb.categories config.
     * Returns the union of all field names across all object types.
     *
     * @return string[]
     */
    public static function getCategoryFields(): array
    {
        $config = Registry::getInstance()->get('config');
        $categories = $config['data']['search_mongodb']['search']['categories'] ?? [];
        $fields = [];
        foreach ($categories as $objectTypeCategories) {
            if (!is_array($objectTypeCategories)) {
                continue;
            }
            foreach (array_keys($objectTypeCategories) as $field) {
                $fields[$field] = true;
            }
        }
        return array_keys($fields);
    }

    /**
     * Clear the static dictionary cache.
     * Useful for CLI tools and testing.
     */
    public static function clearCache(): void
    {
        self::$_cache = [];
    }
}
