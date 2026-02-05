<?php

namespace Pressmind\Search\Hook;

/**
 * Data Transfer Object for pre-search hook results
 * 
 * Contains the data returned by a pre-search hook to modify the search behavior
 */
class SearchHookResult
{
    /**
     * @param array $codes Object codes to filter by (e.g., from Infx API)
     * @param string|null $forceOrder Force a specific sort order (e.g., 'list' to preserve code order)
     * @param array $removeConditions Condition types to remove from SDK search (handled by external API)
     * @param array $data Additional data to store (e.g., cached offers for post-processing)
     */
    public function __construct(
        public readonly array $codes = [],
        public readonly ?string $forceOrder = null,
        public readonly array $removeConditions = [],
        public readonly array $data = []
    ) {}
    
    /**
     * Check if this result has codes to filter by
     * 
     * @return bool
     */
    public function hasCodes(): bool
    {
        return !empty($this->codes);
    }
    
    /**
     * Check if sorting should be forced
     * 
     * @return bool
     */
    public function shouldForceOrder(): bool
    {
        return $this->forceOrder !== null;
    }
    
    /**
     * Check if conditions should be removed
     * 
     * @return bool
     */
    public function hasConditionsToRemove(): bool
    {
        return !empty($this->removeConditions);
    }
    
    /**
     * Get additional data by key
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getData(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
