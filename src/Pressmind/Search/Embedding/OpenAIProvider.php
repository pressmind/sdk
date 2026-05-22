<?php

declare(strict_types=1);

namespace Pressmind\Search\Embedding;

use InvalidArgumentException;
use RuntimeException;

/**
 * OpenAI embeddings API (e.g. text-embedding-3-small).
 */
final class OpenAIProvider implements ProviderInterface
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(array $vectorConfig)
    {
        $this->config = $vectorConfig;
    }

    public function getDimensions(): int
    {
        return (int) ($this->config['dimensions'] ?? 1536);
    }

    public function embed(string $text): array
    {
        $batch = $this->embedBatch([$text]);

        return $batch[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }
        $url = (string) ($this->config['api_url'] ?? 'https://api.openai.com/v1/embeddings');
        $model = (string) ($this->config['model'] ?? 'text-embedding-3-small');
        $envKey = (string) ($this->config['api_key_env'] ?? 'OPENAI_API_KEY');
        $apiKey = getenv($envKey);
        if ($apiKey === false || $apiKey === '') {
            throw new RuntimeException('Embedding API key not set in environment: ' . $envKey);
        }
        $dims = $this->getDimensions();
        $payload = [
            'model' => $model,
            'input' => count($texts) === 1 ? $texts[0] : array_values($texts),
            'dimensions' => $dims,
        ];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $response = $this->httpPostJson($url, $body, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (! isset($data['data']) || ! is_array($data['data'])) {
            throw new RuntimeException('OpenAI embeddings: unexpected response');
        }
        usort($data['data'], static function ($a, $b) {
            return ((int) ($a['index'] ?? 0)) <=> ((int) ($b['index'] ?? 0));
        });
        $out = [];
        foreach ($data['data'] as $row) {
            if (! isset($row['embedding']) || ! is_array($row['embedding'])) {
                throw new RuntimeException('OpenAI embeddings: missing embedding array');
            }
            $vec = array_map('floatval', $row['embedding']);
            if (count($vec) !== $dims) {
                throw new InvalidArgumentException('Embedding dimension mismatch: expected ' . $dims . ', got ' . count($vec));
            }
            $out[] = $vec;
        }

        return $out;
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
            throw new RuntimeException('OpenAI HTTP error: ' . $err);
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException('OpenAI embeddings HTTP ' . $code . ': ' . $raw);
        }

        return $raw;
    }
}
