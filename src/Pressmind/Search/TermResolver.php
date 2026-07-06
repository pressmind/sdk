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
     * Default ship name prefixes (e.g. "MS Amadea", "MY ...") that are treated like
     * stopwords: they are stripped from both the indexed ship name and the search term
     * so that "Amadea", "MS Amadea" and "MY Amadea" all resolve to the same ship.
     * Overridable via config search_mongodb.term_resolver.ship_prefixes.
     */
    private const DEFAULT_SHIP_PREFIXES = ['ms', 'my', 'msy', 'ts', 'mps', 'ps', 'rms', 'mv', 'sy'];

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
     * Configured ship name prefixes (lowercased), falling back to DEFAULT_SHIP_PREFIXES.
     * Prefixes are treated like stopwords: prefixed forms ("MS Amadea") are stored as
     * their own dictionary keys during indexing so they resolve to the ship.
     *
     * @return string[]
     */
    public static function getShipPrefixes(): array
    {
        $config = Registry::getInstance()->get('config');
        $prefixes = $config['data']['search_mongodb']['term_resolver']['ship_prefixes'] ?? null;
        if ($prefixes === null || !is_array($prefixes)) {
            $prefixes = self::DEFAULT_SHIP_PREFIXES;
        }
        $normalized = [];
        foreach ($prefixes as $prefix) {
            $prefix = mb_strtolower(trim((string) $prefix));
            if ($prefix !== '') {
                $normalized[$prefix] = true;
            }
        }
        return array_keys($normalized);
    }

    /**
     * Ship names that are ambiguous with other categories (e.g. "Deutschland" is both a
     * ship and a destination). For these, ONLY the prefixed form ("MS Deutschland")
     * resolves to the ship; the bare name is left to the normal fulltext search.
     * Configured via search_mongodb.term_resolver.ambiguous_ship_names (lowercased).
     *
     * @return string[]
     */
    public static function getAmbiguousShipNames(): array
    {
        $config = Registry::getInstance()->get('config');
        $names = $config['data']['search_mongodb']['term_resolver']['ambiguous_ship_names'] ?? [];
        if (!is_array($names)) {
            return [];
        }
        $normalized = [];
        foreach ($names as $name) {
            $name = mb_strtolower(trim((string) $name));
            if ($name !== '') {
                $normalized[$name] = true;
            }
        }
        return array_keys($normalized);
    }

    /**
     * Strip a single leading ship prefix (followed by whitespace) from a normalized term.
     * Returns the remainder, or null if no configured prefix matched.
     *
     * @param string $normalizedTerm lowercased/trimmed term
     * @param string[] $prefixes lowercased prefixes
     */
    public static function stripShipPrefix(string $normalizedTerm, array $prefixes): ?string
    {
        foreach ($prefixes as $prefix) {
            if ($prefix === '') {
                continue;
            }
            if (mb_strpos($normalizedTerm, $prefix . ' ') === 0) {
                $rest = trim(mb_substr($normalizedTerm, mb_strlen($prefix) + 1));
                if ($rest !== '') {
                    return $rest;
                }
            }
        }
        return null;
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

        // Optional whitelist: restrict the dictionary to specific category fields
        // (e.g. only ship names) to keep the additive term matches precise.
        $whitelist = $config['data']['search_mongodb']['term_resolver']['fields'] ?? [];
        if (!empty($whitelist) && is_array($whitelist)) {
            return array_values($whitelist);
        }

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
     * Return the configured ship category fields that should receive alias keys
     * (plain name without the "(...)" display suffix plus a leading "MS " variant).
     *
     * @return string[]
     */
    public static function getShipFields(): array
    {
        $config = Registry::getInstance()->get('config');
        $shipFields = $config['data']['search_mongodb']['term_resolver']['ship_fields'] ?? [];
        return is_array($shipFields) ? array_values($shipFields) : [];
    }

    /**
     * Generate the dictionary alias keys for a category name.
     *
     * For regular fields this is just the lowercased name.
     *
     * For ship fields it additionally yields a prefixed key for every configured ship
     * prefix (e.g. "ms amadea", "my amadea") so user input like "MS Amadea" resolves to
     * the ship. The bare name ("amadea") is also indexed, EXCEPT for ambiguous names
     * (e.g. "Deutschland", which is also a destination): those resolve to the ship only
     * via their prefixed form, leaving the bare name to the normal fulltext search.
     *
     * @param string $name The category name as stored in the search collection
     * @param bool $isShipField Whether the field is configured as a ship field
     * @param string[] $shipPrefixes Configured ship prefixes (lowercased)
     * @param string[] $ambiguousNames Ship names requiring a prefix (lowercased)
     * @return string[]
     */
    public static function buildAliasKeys(
        string $name,
        bool $isShipField,
        array $shipPrefixes = [],
        array $ambiguousNames = []
    ): array {
        $base = mb_strtolower(trim($name));
        if ($base === '') {
            return [];
        }
        if (!$isShipField) {
            return [$base];
        }

        // Strip a trailing "(...)" display suffix, e.g. "Amadea (S4,5)" -> "amadea".
        $core = mb_strtolower(trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $name)));
        if ($core === '') {
            $core = $base;
        }
        // If the stored name itself carries a prefix ("MS Deutschland"), reduce it to the
        // prefixless core so ambiguity detection and prefix generation are consistent.
        $prefixless = self::stripShipPrefix($core, $shipPrefixes) ?? $core;

        $isAmbiguous = in_array($prefixless, $ambiguousNames, true);

        $keys = [];
        if (!$isAmbiguous) {
            $keys[$base] = true;
            $keys[$core] = true;
            $keys[$prefixless] = true;
        }
        foreach ($shipPrefixes as $prefix) {
            $prefix = mb_strtolower(trim($prefix));
            if ($prefix !== '') {
                $keys[$prefix . ' ' . $prefixless] = true;
            }
        }
        return array_keys($keys);
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
