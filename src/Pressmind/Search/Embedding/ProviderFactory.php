<?php

declare(strict_types=1);

namespace Pressmind\Search\Embedding;

use InvalidArgumentException;

/**
 * Builds an embedding provider from search_opensearch.vector config.
 */
final class ProviderFactory
{
    /**
     * @param  array<string, mixed>  $vectorConfig  search_opensearch.vector block
     */
    public static function create(array $vectorConfig): ProviderInterface
    {
        $provider = strtolower((string) ($vectorConfig['provider'] ?? 'openai'));
        if ($provider === 'openai') {
            return new OpenAIProvider($vectorConfig);
        }
        if ($provider === 'ollama') {
            return new OllamaProvider($vectorConfig);
        }

        throw new InvalidArgumentException('Unknown embedding provider: ' . $provider);
    }
}
