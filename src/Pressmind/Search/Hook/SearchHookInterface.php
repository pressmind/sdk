<?php

namespace Pressmind\Search\Hook;

/**
 * Interface for search hooks that can modify the search process
 * 
 * Hooks can:
 * - Pre-Search: Add external data sources (e.g., Infx API), modify conditions
 * - Post-Search: Enrich results with additional data
 */
interface SearchHookInterface
{
    /**
     * Pre-Search Hook: Called before the MongoDB search is executed
     * 
     * Can be used to:
     * - Call external APIs (e.g., Infx) to get available codes
     * - Modify or add conditions
     * - Set sorting order
     * 
     * @param array $conditions Current search conditions
     * @param array $context Search context (language, origin, agency, sort, etc.)
     * @return SearchHookResult|null Return SearchHookResult to modify search, null to skip
     */
    public function preSearch(array $conditions, array $context): ?SearchHookResult;
    
    /**
     * Post-Search Hook: Called after the MongoDB search is executed
     * 
     * Can be used to:
     * - Enrich results with external data (e.g., Infx offers, prices)
     * - Modify result structure
     * 
     * @param object $result The search result from MongoDB
     * @param array $context Search context
     * @return object Modified result
     */
    public function postSearch(object $result, array $context): object;
    
    /**
     * Check if this hook should be active for the given context
     * 
     * @param array $context Search context (may contain object_type, etc.)
     * @return bool True if hook should be executed
     */
    public function isActive(array $context): bool;
    
    /**
     * Get the priority of this hook (lower = earlier execution)
     * 
     * @return int Priority value (default: 10)
     */
    public function getPriority(): int;
}
