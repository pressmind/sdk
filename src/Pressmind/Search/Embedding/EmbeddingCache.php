<?php

declare(strict_types=1);

namespace Pressmind\Search\Embedding;

use DateTimeImmutable;
use DateTimeZone;
use MongoDB\Database;
use Pressmind\Registry;
use Pressmind\Search\MongoDB;

/**
 * MongoDB cache for document and query embeddings (reduces provider API cost).
 */
final class EmbeddingCache
{
    private const COLLECTION_DOCUMENT = 'embedding_cache';

    private const COLLECTION_QUERY = 'query_embedding_cache';

    private Database $db;

    private bool $indexesEnsured = false;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public static function fromRegistry(): self
    {
        $config = Registry::getInstance()->get('config');
        $uri = $config['data']['search_mongodb']['database']['uri'] ?? '';
        $name = $config['data']['search_mongodb']['database']['db'] ?? '';
        if ($uri === '' || $name === '') {
            throw new \RuntimeException('search_mongodb.database.uri / db must be configured for EmbeddingCache');
        }

        return new self(MongoDB::getDatabase($uri, $name));
    }

    public function ensureIndexes(): void
    {
        if ($this->indexesEnsured) {
            return;
        }
        $this->indexesEnsured = true;
        try {
            $coll = $this->db->{self::COLLECTION_QUERY};
            $coll->createIndex(
                ['expires_at' => 1],
                ['expireAfterSeconds' => 0, 'name' => 'expires_at_ttl']
            );
        } catch (\Throwable $e) {
            // index may already exist
        }
    }

    /**
     * @return list<float>|null
     */
    public function getDocumentEmbedding(string $text, string $model, int $dims): ?array
    {
        $id = $this->hashKey($text, $model, $dims);
        $doc = $this->db->{self::COLLECTION_DOCUMENT}->findOne(['_id' => $id]);
        if ($doc === null) {
            return null;
        }
        $v = $doc['vector'] ?? null;
        if (! is_array($v)) {
            return null;
        }

        return array_map('floatval', $v);
    }

    /**
     * @param  list<float>  $vector
     */
    public function putDocumentEmbedding(string $text, string $model, int $dims, array $vector): void
    {
        $id = $this->hashKey($text, $model, $dims);
        $this->db->{self::COLLECTION_DOCUMENT}->replaceOne(
            ['_id' => $id],
            [
                '_id' => $id,
                'text_hash' => md5($text),
                'model' => $model,
                'dimensions' => $dims,
                'vector' => $vector,
                'created_at' => new \MongoDB\BSON\UTCDateTime(),
            ],
            ['upsert' => true]
        );
    }

    /**
     * @return list<float>|null
     */
    public function getQueryEmbedding(string $query, string $model, int $dims): ?array
    {
        $norm = mb_strtolower(trim($query));
        $id = $this->hashKey($norm, $model, $dims);
        $doc = $this->db->{self::COLLECTION_QUERY}->findOne(['_id' => $id]);
        if ($doc === null) {
            return null;
        }
        $exp = $doc['expires_at'] ?? null;
        if ($exp instanceof \MongoDB\BSON\UTCDateTime) {
            if ($exp->toDateTime() < new \DateTimeImmutable('now', new DateTimeZone('UTC'))) {
                return null;
            }
        }
        $v = $doc['vector'] ?? null;
        if (! is_array($v)) {
            return null;
        }

        return array_map('floatval', $v);
    }

    /**
     * @param  list<float>  $vector
     */
    public function putQueryEmbedding(string $query, string $model, int $dims, array $vector, int $ttlSeconds): void
    {
        $norm = mb_strtolower(trim($query));
        $id = $this->hashKey($norm, $model, $dims);
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expires = $now->modify('+' . max(1, $ttlSeconds) . ' seconds');

        $this->db->{self::COLLECTION_QUERY}->replaceOne(
            ['_id' => $id],
            [
                '_id' => $id,
                'query' => $norm,
                'model' => $model,
                'dimensions' => $dims,
                'vector' => $vector,
                'created_at' => new \MongoDB\BSON\UTCDateTime(),
                'expires_at' => new \MongoDB\BSON\UTCDateTime($expires->getTimestamp() * 1000),
            ],
            ['upsert' => true]
        );
    }

    private function hashKey(string $text, string $model, int $dims): string
    {
        return md5($model . '|' . $dims . '|' . $text);
    }
}
