<?php

declare(strict_types=1);

namespace Pressmind\Search\Embedding;

/**
 * Pluggable text embedding provider (OpenAI, Ollama, etc.).
 */
interface ProviderInterface
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array;

    public function getDimensions(): int;
}
