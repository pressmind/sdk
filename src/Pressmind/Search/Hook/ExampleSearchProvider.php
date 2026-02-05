<?php

namespace Pressmind\Search\Hook;

/**
 * Example Search Hook Provider
 * 
 * This is a template/example class showing how to implement a SearchHookInterface.
 * Copy this class to your project and customize it for your external API integration.
 * 
 * Configuration in pm-config.php:
 * 
 * 'data' => [
 *     'search_hooks' => [
 *         [
 *             'class' => '\\YourNamespace\\YourSearchProvider',
 *             'config' => [
 *                 'enabled' => true,
 *                 'api_url' => 'https://api.example.com/offers',
 *                 'object_types' => [123, 456], // empty = all
 *                 'priority' => 10,
 *             ]
 *         ]
 *     ]
 * ]
 * 
 * @see SearchHookInterface
 * @see SearchHookManager
 * @see SearchHookResult
 */
class ExampleSearchProvider implements SearchHookInterface
{
    /**
     * @var array Configuration passed from pm-config.php
     */
    private array $config;
    
    /**
     * @var array Cached data from external API (for use in postSearch)
     */
    private array $externalDataByCode = [];
    
    /**
     * Constructor receives config from pm-config.php
     * 
     * @param array $config Provider-specific configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enabled' => false,
            'api_url' => '',
            'object_types' => [],
            'priority' => 10,
        ], $config);
    }
    
    /**
     * Pre-Search Hook
     * 
     * Called BEFORE the MongoDB search is executed.
     * Use this to:
     * - Call external APIs to get available product codes
     * - Store external data for later enrichment in postSearch
     * - Signal which SDK conditions should be removed (handled by external API)
     * - Force a specific sort order
     * 
     * @param array $conditions Current SDK search conditions
     * @param array $context Search context (language, origin, agency, sort, etc.)
     * @return SearchHookResult|null Return result to modify search, null to skip
     */
    public function preSearch(array $conditions, array $context): ?SearchHookResult
    {
        // Skip if not active for this context
        if (!$this->isActive($context)) {
            return null;
        }
        
        // Example: Map SDK conditions to external API parameters
        $apiParams = $this->mapConditionsToApiParams($conditions);
        
        // Example: Call external API
        $response = $this->callExternalApi($apiParams);
        
        if (empty($response['data'])) {
            // No results from external API - return special code to ensure no matches
            return new SearchHookResult(
                codes: ['__NO_RESULTS__'],
                forceOrder: 'list',
                removeConditions: ['DateRange', 'DurationRange']
            );
        }
        
        // Group external data by product code for later use in postSearch
        $this->externalDataByCode = [];
        foreach ($response['data'] as $item) {
            $code = $item['product_code'] ?? null;
            if ($code !== null) {
                if (!isset($this->externalDataByCode[$code])) {
                    $this->externalDataByCode[$code] = [];
                }
                $this->externalDataByCode[$code][] = $item;
            }
        }
        
        $codes = array_keys($this->externalDataByCode);
        
        // Return result that tells SDK:
        // - Filter by these codes (in this order)
        // - Use 'list' sort to preserve our order
        // - Remove conditions we already handled
        return new SearchHookResult(
            codes: $codes,
            forceOrder: 'list',
            removeConditions: ['DateRange', 'DurationRange'], // Conditions handled by external API
            data: ['total' => $response['meta']['total'] ?? count($codes)]
        );
    }
    
    /**
     * Post-Search Hook
     * 
     * Called AFTER the MongoDB search is executed.
     * Use this to:
     * - Enrich SDK results with external data (prices, availability, etc.)
     * - Add computed fields
     * - Modify result structure
     * 
     * @param object $result The search result from MongoDB
     * @param array $context Search context
     * @return object Modified result
     */
    public function postSearch(object $result, array $context): object
    {
        if (!$this->isActive($context)) {
            return $result;
        }
        
        // Enrich each document with external data
        if (!empty($result->documents)) {
            foreach ($result->documents as $key => $document) {
                $doc = is_object($document) ? (array)$document : $document;
                $code = $doc['code'] ?? null;
                
                if ($code !== null && isset($this->externalDataByCode[$code])) {
                    $externalItems = $this->externalDataByCode[$code];
                    
                    // Add all external offers
                    $doc['external_offers'] = $externalItems;
                    
                    // Add cheapest offer summary
                    $cheapest = $this->findCheapest($externalItems);
                    if ($cheapest !== null) {
                        $doc['external_cheapest'] = [
                            'price' => (int)$cheapest['price'],
                            'currency' => $cheapest['currency'] ?? 'EUR',
                            'start_date' => $cheapest['start_date'] ?? null,
                        ];
                    }
                    
                    // Update document in result
                    if (is_object($result->documents[$key])) {
                        foreach ($doc as $k => $v) {
                            $result->documents[$key]->$k = $v;
                        }
                    } else {
                        $result->documents[$key] = $doc;
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Check if this hook should be active
     * 
     * @param array $context Search context
     * @return bool
     */
    public function isActive(array $context): bool
    {
        // Check if enabled
        if (empty($this->config['enabled'])) {
            return false;
        }
        
        // Check object types if configured
        if (!empty($this->config['object_types'])) {
            // Extract object types from context/conditions
            $requestedTypes = [];
            
            if (!empty($context['conditions'])) {
                foreach ($context['conditions'] as $condition) {
                    if (method_exists($condition, 'getType') && $condition->getType() === 'ObjectType') {
                        if (method_exists($condition, 'getObjectTypes')) {
                            $requestedTypes = array_merge($requestedTypes, $condition->getObjectTypes());
                        }
                    }
                }
            }
            
            if (!empty($requestedTypes)) {
                $intersection = array_intersect($requestedTypes, $this->config['object_types']);
                return !empty($intersection);
            }
        }
        
        return true;
    }
    
    /**
     * Get hook priority (lower = earlier execution)
     * 
     * @return int
     */
    public function getPriority(): int
    {
        return $this->config['priority'] ?? 10;
    }
    
    /**
     * Map SDK conditions to external API parameters
     * 
     * @param array $conditions SDK conditions
     * @return array API parameters
     */
    private function mapConditionsToApiParams(array $conditions): array
    {
        $params = [];
        
        foreach ($conditions as $condition) {
            if (!is_object($condition) || !method_exists($condition, 'getType')) {
                continue;
            }
            
            switch ($condition->getType()) {
                case 'DateRange':
                    if (method_exists($condition, 'getDateFrom')) {
                        $from = $condition->getDateFrom();
                        if ($from) {
                            $params['date_from'] = $from->format('Y-m-d');
                        }
                    }
                    if (method_exists($condition, 'getDateTo')) {
                        $to = $condition->getDateTo();
                        if ($to) {
                            $params['date_to'] = $to->format('Y-m-d');
                        }
                    }
                    break;
                    
                case 'DurationRange':
                    if (method_exists($condition, 'getDurationFrom')) {
                        $params['duration_min'] = (int)$condition->getDurationFrom();
                    }
                    if (method_exists($condition, 'getDurationTo')) {
                        $params['duration_max'] = (int)$condition->getDurationTo();
                    }
                    break;
                    
                case 'Code':
                    if (method_exists($condition, 'getCodes')) {
                        $params['codes'] = implode(',', $condition->getCodes());
                    }
                    break;
            }
        }
        
        return $params;
    }
    
    /**
     * Call external API
     * 
     * @param array $params Query parameters
     * @return array|null Response data
     */
    private function callExternalApi(array $params): ?array
    {
        $apiUrl = $this->config['api_url'] ?? '';
        
        if (empty($apiUrl)) {
            return null;
        }
        
        $url = $apiUrl . '?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Find cheapest item from list
     * 
     * @param array $items
     * @return array|null
     */
    private function findCheapest(array $items): ?array
    {
        if (empty($items)) {
            return null;
        }
        
        usort($items, fn($a, $b) => (int)$a['price'] - (int)$b['price']);
        
        return $items[0];
    }
}
