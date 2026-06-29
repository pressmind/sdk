<?php

namespace Pressmind\Image;

use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

class WebpSidecar
{
    private static array $validityCache = [];

    public static function fileName(string $sourceFileName): string
    {
        $pathInfo = pathinfo($sourceFileName);
        $dirname = $pathInfo['dirname'] ?? '';
        $prefix = ($dirname !== '' && $dirname !== '.') ? rtrim($dirname, '/\\') . '/' : '';
        return $prefix . ($pathInfo['filename'] ?? $sourceFileName) . '.webp';
    }

    public static function derivativePrefix(string $sourceFileName): string
    {
        $pathInfo = pathinfo($sourceFileName);
        $dirname = $pathInfo['dirname'] ?? '';
        $prefix = ($dirname !== '' && $dirname !== '.') ? rtrim($dirname, '/\\') . '/' : '';
        return $prefix . ($pathInfo['filename'] ?? $sourceFileName) . '_';
    }

    public static function isValid(Bucket $bucket, string $fileName): bool
    {
        $cacheKey = md5(json_encode($bucket->storage)) . ':' . $fileName;
        if (array_key_exists($cacheKey, self::$validityCache)) {
            return self::$validityCache[$cacheKey];
        }

        $file = new File($bucket);
        $file->name = $fileName;
        if (!$file->exists()) {
            self::$validityCache[$cacheKey] = false;
            return self::$validityCache[$cacheKey];
        }

        try {
            if ($file->filesize() <= 0) {
                self::$validityCache[$cacheKey] = false;
                return self::$validityCache[$cacheKey];
            }
            $file->read();
        } catch (\Exception $e) {
            self::$validityCache[$cacheKey] = false;
            return self::$validityCache[$cacheKey];
        }

        if ($file->content === null || $file->content === '') {
            self::$validityCache[$cacheKey] = false;
            return self::$validityCache[$cacheKey];
        }

        $image = @imagecreatefromstring($file->content);
        if ($image === false) {
            self::$validityCache[$cacheKey] = false;
            return self::$validityCache[$cacheKey];
        }
        imagedestroy($image);
        self::$validityCache[$cacheKey] = true;
        return self::$validityCache[$cacheKey];
    }

    public static function clearValidityCache(Bucket $bucket, string $fileName): void
    {
        unset(self::$validityCache[md5(json_encode($bucket->storage)) . ':' . $fileName]);
    }
}
