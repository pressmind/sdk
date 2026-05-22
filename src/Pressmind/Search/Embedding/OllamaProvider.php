<?php

declare(strict_types=1);

namespace Pressmind\Search\Embedding;

use InvalidArgumentException;
use RuntimeException;

/**
 * Ollama local embeddings API (/api/embeddings).
 */
final class OllamaProvider implements ProviderInterface
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(array $vectorConfig)
    {
        $this->config = $vectorConfig;
    }

    public function getDimensions(): int
    {
        return (int) ($this->config['dimensions'] ?? 768);
    }

    public function embed(string $text): array
    {
        $batch = $this->embedBatch([$text]);

        return $batch[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        $out = [];
        foreach ($texts as $t) {
            $out[] = $this->embedOne((string) $t);
        }

        return $out;
    }

    /**
     * @return list<float>
     */
    private function embedOne(string $text): array
    {
        $base = rtrim((string) ($this->config['api_url'] ?? 'http://127.0.0.1:11434'), '/');
        $url = $base . '/api/embeddings';
        $model = (string) ($this->config['model'] ?? 'nomic-embed-text');
        $payload = json_encode(['model' => $model, 'prompt' => $text], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $raw = $this->httpPostJson($url, $payload, ['Content-Type: application/json']);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (! isset($data['embedding']) || ! is_array($data['embedding'])) {
            throw new RuntimeException('Ollama embeddings: unexpected response');
        }
        $vec = array_map('floatval', $data['embedding']);
        $dims = $this->getDimensions();
        if (count($vec) !== $dims) {
            throw new InvalidArgumentException(
                'Ollama embedding dimension mismatch: config dimensions=' . $dims . ', model returned ' . count($vec)
            );
        }

        return $vec;
    }

    private function httpPostJson(string $url, string $body, array $headers): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) ($this->config['http_timeout'] ?? 120),
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Ollama HTTP error: ' . $err);
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('Ollama embeddings HTTP ' . $code . ': ' . $raw);
        }

        return $raw;
    }
}
