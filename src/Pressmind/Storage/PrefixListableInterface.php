<?php

namespace Pressmind\Storage;

/**
 * Optional interface for storage providers that support listing objects by prefix.
 * Returns key => size map for efficient bulk existence/size checks without per-file HEAD requests.
 */
interface PrefixListableInterface
{
    /**
     * Lists all objects whose key starts with $prefix.
     *
     * @param string $prefix Key prefix (e.g. "image_12345_")
     * @param Bucket $bucket
     * @return array<string, int> Associative array: filename => filesize in bytes
     */
    public function listByPrefix(string $prefix, Bucket $bucket): array;
}
