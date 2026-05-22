<?php

declare(strict_types=1);

namespace Pressmind\Search\Embedding;

/**
 * Resolves a query embedding with optional MongoDB query cache.
 */
final class QueryEmbedding
{
    /**
     * @return list<float>
     */
    public static function getVector(string $queryText, array $vectorConfig): array
    {
        $provider = ProviderFactory::create($vectorConfig);
        $model = (string) ($vectorConfig['model'] ?? 'text-embedding-3-small');
        $dims = (int) ($vectorConfig['dimensions'] ?? 1536);
        $cacheEnabled = ! empty($vectorConfig['cache']['enabled']);
        $cache = null;
        if ($cacheEnabled) {
            $cache = EmbeddingCache::fromRegistry();
            $cache->ensureIndexes();
            $hit = $cache->getQueryEmbedding($queryText, $model, $dims);
            if ($hit !== null) {
                return $hit;
            }
        }
        $vec = $provider->embed($queryText);
        if ($cacheEnabled && $cache !== null) {
            $ttl = (int) ($vectorConfig['cache']['query_cache_ttl'] ?? 604800);
            $cache->putQueryEmbedding($queryText, $model, $dims, $vec, $ttl);
        }

        return $vec;
    }
}
