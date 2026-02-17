<?php

namespace Pressmind\Storage;

/**
 * Interface for storage providers that support scanning all keys without loading them into memory.
 * Used for verification statistics over large buckets (e.g. 1M+ files).
 */
interface FullScanInterface
{
    /**
     * Iterates over all object keys in the bucket, invoking the callback for each key.
     * Keys and sizes are streamed page-by-page; nothing is held in memory.
     *
     * @param callable $callback Called as (string $key, int $sizeInBytes) for each object
     * @param Bucket $bucket
     * @return void
     */
    public function scanAllKeys(callable $callback, Bucket $bucket): void;
}
