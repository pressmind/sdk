<?php

namespace Pressmind\Storage\Provider;

use Pressmind\Storage\Bucket;

/**
 * Filesystem provider that counts prefix listings (each one is a full directory scan via glob()).
 * Resolved by Factory via storage provider name 'countingFilesystem'.
 */
class CountingFilesystem extends Filesystem
{
    public static int $listByPrefixCalls = 0;

    public function listByPrefix(string $prefix, Bucket $bucket): array
    {
        self::$listByPrefixCalls++;
        return parent::listByPrefix($prefix, $bucket);
    }
}
