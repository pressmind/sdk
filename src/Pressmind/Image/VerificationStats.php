<?php

namespace Pressmind\Image;

use Exception;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Section;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;

/**
 * Collects image verification statistics (pictures, sections, documents) by checking
 * derivative files on storage. Used by ImageProcessorCommand and Backend ImageCacheController.
 *
 * Supports chunked processing for large buckets (e.g. 1M+ files): pass chunk_size and
 * max_missing_list in options to avoid holding all entities and derivative lists in memory.
 */
class VerificationStats
{
    /** Default chunk size when processing in chunks (0 = load all at once, legacy). */
    public const DEFAULT_CHUNK_SIZE = 2000;

    /** Default max entries in missing_list per type when using chunked processing. */
    public const DEFAULT_MAX_MISSING_LIST = 10000;

    /**
     * Formats bytes into human-readable size.
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Runs verification and returns statistics (no side effects).
     *
     * @param array $config Full SDK config (must contain image_handling.processor.derivatives and image_handling.storage)
     * @param array $options Optional: chunk_size (e.g. 2000 = load 2000 at a time), max_missing_list (cap per type). If chunk_size > 0, derivatives arrays are not stored (only derivative_summary and capped missing_list).
     * @return array Verification stats with keys pictures, sections, documents; each has total, exists, missing, missing_list, derivatives (empty when chunked). When chunked, also contains derivative_summary (aggregated).
     */
    public static function collect(array $config, array $options = []): array
    {
        $chunkSize = isset($options['chunk_size']) ? (int) $options['chunk_size'] : 0;
        $maxMissingList = isset($options['max_missing_list']) ? (int) $options['max_missing_list'] : self::DEFAULT_MAX_MISSING_LIST;
        $storeDerivatives = ($chunkSize <= 0);

        $verificationStats = [
            'pictures' => ['total' => 0, 'exists' => 0, 'missing' => 0, 'missing_list' => [], 'derivatives' => []],
            'sections' => ['total' => 0, 'exists' => 0, 'missing' => 0, 'missing_list' => [], 'derivatives' => []],
            'documents' => ['total' => 0, 'exists' => 0, 'missing' => 0, 'missing_list' => [], 'derivatives' => []],
            'derivative_summary' => []
        ];

        $derivativeSummaryMap = [];

        try {
            $bucket = new Bucket($config['image_handling']['storage']);
            if ($chunkSize > 0 && $bucket->supportsFullScan()) {
                $progressCallback = $options['progress_callback'] ?? null;
                self::collectStreaming($config, $verificationStats, $derivativeSummaryMap, $maxMissingList, $bucket, $progressCallback);
            } elseif ($chunkSize > 0) {
                self::collectChunked($config, $verificationStats, $derivativeSummaryMap, $chunkSize, $maxMissingList, $bucket);
            } else {
                $pictures = Picture::listAll(['download_successful' => 1]);
                $sections = Section::listAll(['download_successful' => 1]);
                $documents = DocumentMediaObject::listAll(['download_successful' => 1]);
                self::verifyPictures($pictures, $config, $verificationStats, $storeDerivatives, $maxMissingList, $derivativeSummaryMap, $bucket);
                self::verifySections($sections, $config, $verificationStats, $storeDerivatives, $maxMissingList, $derivativeSummaryMap, $bucket);
                self::verifyDocuments($documents, $config, $verificationStats, $storeDerivatives, $maxMissingList, $derivativeSummaryMap, $bucket);
            }
        } catch (Exception $e) {
            // Return partial stats; caller may log
        }

        $verificationStats['derivative_summary'] = self::buildDerivativeSummaryList($derivativeSummaryMap);
        return $verificationStats;
    }

    /**
     * Process all three types in chunks; only aggregate counts and capped missing_list, no per-entity derivatives.
     */
    private static function collectChunked(array $config, array &$stats, array &$derivativeSummaryMap, int $chunkSize, int $maxMissingList, Bucket $bucket): void
    {
        $offset = 0;
        do {
            $chunk = Picture::listAll(['download_successful' => 1], null, [$offset, $chunkSize]);
            self::verifyPictures($chunk, $config, $stats, false, $maxMissingList, $derivativeSummaryMap, $bucket);
            $offset += count($chunk);
        } while (count($chunk) >= $chunkSize);

        $offset = 0;
        do {
            $chunk = Section::listAll(['download_successful' => 1], null, [$offset, $chunkSize]);
            self::verifySections($chunk, $config, $stats, false, $maxMissingList, $derivativeSummaryMap, $bucket);
            $offset += count($chunk);
        } while (count($chunk) >= $chunkSize);

        $offset = 0;
        do {
            $chunk = DocumentMediaObject::listAll(['download_successful' => 1], null, [$offset, $chunkSize]);
            self::verifyDocuments($chunk, $config, $stats, false, $maxMissingList, $derivativeSummaryMap, $bucket);
            $offset += count($chunk);
        } while (count($chunk) >= $chunkSize);
    }

    /**
     * Streaming verification: build entity prefix map from DB, then scan bucket once and match keys.
     * Memory-efficient for 1M+ files. Requires bucket to support FullScanInterface.
     *
     * @param array $config
     * @param array $stats
     * @param array $derivativeSummaryMap
     * @param int $maxMissingList
     * @param Bucket $bucket
     * @param callable|null $progressCallback Optional, called as (int $keysProcessed, bool $isFinal = false) periodically and at end
     */
    private static function collectStreaming(
        array $config,
        array &$stats,
        array &$derivativeSummaryMap,
        int $maxMissingList,
        Bucket $bucket,
        ?callable $progressCallback = null
    ): void {
        $derivativesConfig = $config['image_handling']['processor']['derivatives'] ?? [];
        $chunkSize = self::DEFAULT_CHUNK_SIZE;

        // Build derivative suffix list: suffix => [name, extension] for matching
        $suffixList = [];
        foreach ($derivativesConfig as $derivativeName => $derivativeConfig) {
            $extensions = ['jpg'];
            if (!empty($derivativeConfig['webp_create'])) {
                $extensions[] = 'webp';
            }
            foreach ($extensions as $extension) {
                $suffix = $derivativeName . '.' . $extension;
                $suffixList[$suffix] = ['name' => $derivativeName, 'extension' => $extension];
            }
        }

        // Phase 1: Build entity prefix map and counts from DB (chunked)
        $entityMap = [];
        $countByType = ['pictures' => 0, 'sections' => 0, 'documents' => 0];

        $offset = 0;
        do {
            $chunk = Picture::listAll(['download_successful' => 1], null, [$offset, $chunkSize]);
            foreach ($chunk as $picture) {
                $prefix = pathinfo($picture->file_name, PATHINFO_FILENAME) . '_';
                $entityMap[$prefix] = [
                    'type' => 'pictures',
                    'id' => $picture->getId(),
                    'file_name' => $picture->file_name,
                    'id_media_object' => $picture->id_media_object ?? 'N/A',
                    'section_name' => null,
                    'id_step' => null
                ];
                $countByType['pictures']++;
            }
            $offset += count($chunk);
        } while (count($chunk) >= $chunkSize);

        $offset = 0;
        do {
            $chunk = Section::listAll(['download_successful' => 1], null, [$offset, $chunkSize]);
            foreach ($chunk as $section) {
                $prefix = pathinfo($section->file_name, PATHINFO_FILENAME) . '_';
                $entityMap[$prefix] = [
                    'type' => 'sections',
                    'id' => $section->getId(),
                    'file_name' => $section->file_name,
                    'id_media_object' => $section->id_media_object ?? 'N/A',
                    'section_name' => $section->section_name ?? 'N/A',
                    'id_step' => null
                ];
                $countByType['sections']++;
            }
            $offset += count($chunk);
        } while (count($chunk) >= $chunkSize);

        $offset = 0;
        do {
            $chunk = DocumentMediaObject::listAll(['download_successful' => 1], null, [$offset, $chunkSize]);
            foreach ($chunk as $document) {
                $prefix = pathinfo($document->file_name, PATHINFO_FILENAME) . '_';
                $entityMap[$prefix] = [
                    'type' => 'documents',
                    'id' => $document->getId(),
                    'file_name' => $document->file_name,
                    'id_media_object' => $document->id_media_object ?? 'N/A',
                    'section_name' => null,
                    'id_step' => $document->id_step ?? 'N/A'
                ];
                $countByType['documents']++;
            }
            $offset += count($chunk);
        } while (count($chunk) >= $chunkSize);

        $typeNames = ['pictures' => 'Pictures', 'sections' => 'Sections', 'documents' => 'Documents'];

        // Init derivative summary with total_count per (name, extension, type)
        foreach (['pictures', 'sections', 'documents'] as $type) {
            foreach ($suffixList as $suffix => $info) {
                $key = $info['name'] . '.' . $info['extension'] . '.' . $type;
                $derivativeSummaryMap[$key] = [
                    'name' => $info['name'],
                    'extension' => $info['extension'],
                    'total_count' => $countByType[$type],
                    'exists_count' => 0,
                    'total_size' => 0,
                    'type' => $typeNames[$type]
                ];
            }
        }

        $stats['pictures']['total'] = $countByType['pictures'];
        $stats['sections']['total'] = $countByType['sections'];
        $stats['documents']['total'] = $countByType['documents'];

        $foundPrefixes = [];
        $keysProcessed = 0;
        $progressInterval = 100000;

        $scanCallback = function (string $key, int $size) use (
            $entityMap,
            $suffixList,
            &$derivativeSummaryMap,
            $typeNames,
            &$foundPrefixes,
            &$keysProcessed,
            $progressInterval,
            $progressCallback
) {
            $keysProcessed++;
            if (is_callable($progressCallback) && ($keysProcessed % $progressInterval) === 0) {
                $progressCallback($keysProcessed, false);
            }

            // Support keys with path (e.g. "subdir/abc123_thumb.jpg") by matching on the filename part
            $keyBasename = strpos($key, '/') !== false ? basename($key) : $key;
            foreach ($suffixList as $suffix => $info) {
                if (strlen($keyBasename) >= strlen($suffix) && substr($keyBasename, -strlen($suffix)) === $suffix) {
                    $candidatePrefix = substr($keyBasename, 0, -strlen($suffix));
                    if (substr($candidatePrefix, -1) !== '_') {
                        continue;
                    }
                    if (!isset($entityMap[$candidatePrefix])) {
                        continue;
                    }
                    $entity = $entityMap[$candidatePrefix];
                    $foundPrefixes[$candidatePrefix] = true;
                    $summaryKey = $info['name'] . '.' . $info['extension'] . '.' . $entity['type'];
                    if (isset($derivativeSummaryMap[$summaryKey])) {
                        $derivativeSummaryMap[$summaryKey]['exists_count']++;
                        $derivativeSummaryMap[$summaryKey]['total_size'] += $size;
                    }
                    break;
                }
            }
        };

        $bucket->scanAllKeys($scanCallback);

        if (is_callable($progressCallback)) {
            $progressCallback($keysProcessed, true);
        }

        // Derive exists/missing and missing_list from entityMap and foundPrefixes
        foreach (['pictures', 'sections', 'documents'] as $type) {
            $exists = 0;
            $missingListCount = 0;
            foreach ($entityMap as $prefix => $entity) {
                if ($entity['type'] !== $type) {
                    continue;
                }
                if (!empty($foundPrefixes[$prefix])) {
                    $exists++;
                } else {
                    $stats[$type]['missing']++;
                    if ($missingListCount < $maxMissingList) {
                        $entry = [
                            'id' => $entity['id'],
                            'file_name' => $entity['file_name'],
                            'id_media_object' => $entity['id_media_object']
                        ];
                        if ($type === 'sections' && $entity['section_name'] !== null) {
                            $entry['section_name'] = $entity['section_name'];
                        }
                        if ($type === 'documents' && $entity['id_step'] !== null) {
                            $entry['id_step'] = $entity['id_step'];
                        }
                        $stats[$type]['missing_list'][] = $entry;
                        $missingListCount++;
                    }
                }
            }
            $stats[$type]['exists'] = $exists;
        }
    }

    /**
     * @param array<int, array{name: string, extension: string, total_count: int, exists_count: int, total_size: int, type: string}> $derivativeSummaryMap
     * @return list<array{name: string, extension: string, total_count: int, exists_count: int, total_size: int, type: string}>
     */
    private static function buildDerivativeSummaryList(array $derivativeSummaryMap): array
    {
        $list = array_values($derivativeSummaryMap);
        usort($list, function ($a, $b) {
            $typeOrder = ['Pictures' => 1, 'Sections' => 2, 'Documents' => 3];
            $typeCmp = ($typeOrder[$a['type']] ?? 0) <=> ($typeOrder[$b['type']] ?? 0);
            if ($typeCmp !== 0) {
                return $typeCmp;
            }
            return strcmp($a['name'], $b['name']);
        });
        return $list;
    }

    /**
     * Verifies pictures and collects statistics.
     *
     * @param array<int, Picture> $pictures
     * @param array $config
     * @param array $stats
     * @param bool $storeDerivatives Whether to append to stats[derivatives] (memory-heavy)
     * @param int $maxMissingList Cap for missing_list length
     * @param array $derivativeSummaryMap Running aggregate: key => [name, extension, type, total_count, exists_count, total_size]
     * @param Bucket $bucket Shared bucket (reused for S3 client / prefix listing)
     */
    private static function verifyPictures(array $pictures, array $config, array &$stats, bool $storeDerivatives, int $maxMissingList, array &$derivativeSummaryMap, Bucket $bucket): void
    {
        $derivativesConfig = $config['image_handling']['processor']['derivatives'] ?? [];
        $type = 'pictures';
        $typeName = 'Pictures';
        $usePrefixListing = $bucket->supportsPrefixListing();

        foreach ($pictures as $picture) {
            $stats['pictures']['total']++;
            $hasAnyDerivative = false;
            $pictureDerivatives = [];

            $prefix = pathinfo($picture->file_name, PATHINFO_FILENAME) . '_';
            $existingFiles = $usePrefixListing ? $bucket->listByPrefix($prefix) : [];

            foreach ($derivativesConfig as $derivativeName => $derivativeConfig) {
                $extensions = ['jpg'];
                if (!empty($derivativeConfig['webp_create'])) {
                    $extensions[] = 'webp';
                }
                foreach ($extensions as $extension) {
                    $expectedKey = $prefix . $derivativeName . '.' . $extension;
                    $derivativeInfo = [
                        'name' => $derivativeName,
                        'extension' => $extension,
                        'file_name' => $expectedKey,
                        'exists' => false,
                        'size' => 0,
                        'size_formatted' => '0 B'
                    ];

                    if ($usePrefixListing) {
                        $derivativeInfo['exists'] = isset($existingFiles[$expectedKey]);
                        $derivativeInfo['size'] = $existingFiles[$expectedKey] ?? 0;
                        if ($derivativeInfo['exists']) {
                            $hasAnyDerivative = true;
                            $derivativeInfo['size_formatted'] = self::formatBytes($derivativeInfo['size']);
                        }
                    } else {
                        $file = new File($bucket);
                        $file->name = $expectedKey;
                        if ($file->exists()) {
                            $hasAnyDerivative = true;
                            try {
                                $fileSize = $file->filesize();
                                $derivativeInfo['exists'] = true;
                                $derivativeInfo['size'] = $fileSize;
                                $derivativeInfo['size_formatted'] = self::formatBytes($fileSize);
                            } catch (Exception $e) {
                                $derivativeInfo['size_formatted'] = 'Error: ' . $e->getMessage();
                            }
                        }
                    }
                    $pictureDerivatives[] = $derivativeInfo;

                    $key = $derivativeName . '.' . $extension . '.' . $type;
                    if (!isset($derivativeSummaryMap[$key])) {
                        $derivativeSummaryMap[$key] = [
                            'name' => $derivativeName,
                            'extension' => $extension,
                            'total_count' => 0,
                            'exists_count' => 0,
                            'total_size' => 0,
                            'type' => $typeName
                        ];
                    }
                    $derivativeSummaryMap[$key]['total_count']++;
                    if ($derivativeInfo['exists']) {
                        $derivativeSummaryMap[$key]['exists_count']++;
                        $derivativeSummaryMap[$key]['total_size'] += $derivativeInfo['size'];
                    }
                }
            }

            if ($storeDerivatives) {
                $stats['pictures']['derivatives'][] = [
                    'id' => $picture->getId(),
                    'file_name' => $picture->file_name,
                    'id_media_object' => $picture->id_media_object ?? 'N/A',
                    'derivatives' => $pictureDerivatives
                ];
            }

            if ($hasAnyDerivative) {
                $stats['pictures']['exists']++;
            } else {
                $stats['pictures']['missing']++;
                if (count($stats['pictures']['missing_list']) < $maxMissingList) {
                    $stats['pictures']['missing_list'][] = [
                        'id' => $picture->getId(),
                        'file_name' => $picture->file_name,
                        'id_media_object' => $picture->id_media_object ?? 'N/A'
                    ];
                }
            }
        }
    }

    /**
     * Verifies sections and collects statistics.
     *
     * @param array<int, Section> $sections
     * @param Bucket $bucket Shared bucket (reused for S3 client / prefix listing)
     */
    private static function verifySections(array $sections, array $config, array &$stats, bool $storeDerivatives, int $maxMissingList, array &$derivativeSummaryMap, Bucket $bucket): void
    {
        $derivativesConfig = $config['image_handling']['processor']['derivatives'] ?? [];
        $type = 'sections';
        $typeName = 'Sections';
        $usePrefixListing = $bucket->supportsPrefixListing();

        foreach ($sections as $section) {
            $stats['sections']['total']++;
            $hasAnyDerivative = false;
            $sectionDerivatives = [];

            $prefix = pathinfo($section->file_name, PATHINFO_FILENAME) . '_';
            $existingFiles = $usePrefixListing ? $bucket->listByPrefix($prefix) : [];

            foreach ($derivativesConfig as $derivativeName => $derivativeConfig) {
                $extensions = ['jpg'];
                if (!empty($derivativeConfig['webp_create'])) {
                    $extensions[] = 'webp';
                }
                foreach ($extensions as $extension) {
                    $expectedKey = $prefix . $derivativeName . '.' . $extension;
                    $derivativeInfo = [
                        'name' => $derivativeName,
                        'extension' => $extension,
                        'file_name' => $expectedKey,
                        'exists' => false,
                        'size' => 0,
                        'size_formatted' => '0 B'
                    ];

                    if ($usePrefixListing) {
                        $derivativeInfo['exists'] = isset($existingFiles[$expectedKey]);
                        $derivativeInfo['size'] = $existingFiles[$expectedKey] ?? 0;
                        if ($derivativeInfo['exists']) {
                            $hasAnyDerivative = true;
                            $derivativeInfo['size_formatted'] = self::formatBytes($derivativeInfo['size']);
                        }
                    } else {
                        $file = new File($bucket);
                        $file->name = $expectedKey;
                        if ($file->exists()) {
                            $hasAnyDerivative = true;
                            try {
                                $fileSize = $file->filesize();
                                $derivativeInfo['exists'] = true;
                                $derivativeInfo['size'] = $fileSize;
                                $derivativeInfo['size_formatted'] = self::formatBytes($fileSize);
                            } catch (Exception $e) {
                                $derivativeInfo['size_formatted'] = 'Error: ' . $e->getMessage();
                            }
                        }
                    }
                    $sectionDerivatives[] = $derivativeInfo;

                    $key = $derivativeName . '.' . $extension . '.' . $type;
                    if (!isset($derivativeSummaryMap[$key])) {
                        $derivativeSummaryMap[$key] = [
                            'name' => $derivativeName,
                            'extension' => $extension,
                            'total_count' => 0,
                            'exists_count' => 0,
                            'total_size' => 0,
                            'type' => $typeName
                        ];
                    }
                    $derivativeSummaryMap[$key]['total_count']++;
                    if ($derivativeInfo['exists']) {
                        $derivativeSummaryMap[$key]['exists_count']++;
                        $derivativeSummaryMap[$key]['total_size'] += $derivativeInfo['size'];
                    }
                }
            }

            if ($storeDerivatives) {
                $stats['sections']['derivatives'][] = [
                    'id' => $section->getId(),
                    'file_name' => $section->file_name,
                    'id_media_object' => $section->id_media_object ?? 'N/A',
                    'section_name' => $section->section_name ?? 'N/A',
                    'derivatives' => $sectionDerivatives
                ];
            }

            if ($hasAnyDerivative) {
                $stats['sections']['exists']++;
            } else {
                $stats['sections']['missing']++;
                if (count($stats['sections']['missing_list']) < $maxMissingList) {
                    $stats['sections']['missing_list'][] = [
                        'id' => $section->getId(),
                        'file_name' => $section->file_name,
                        'id_media_object' => $section->id_media_object ?? 'N/A',
                        'section_name' => $section->section_name ?? 'N/A'
                    ];
                }
            }
        }
    }

    /**
     * Verifies document media objects and collects statistics.
     *
     * @param array<int, DocumentMediaObject> $documents
     * @param Bucket $bucket Shared bucket (reused for S3 client / prefix listing)
     */
    private static function verifyDocuments(array $documents, array $config, array &$stats, bool $storeDerivatives, int $maxMissingList, array &$derivativeSummaryMap, Bucket $bucket): void
    {
        $derivativesConfig = $config['image_handling']['processor']['derivatives'] ?? [];
        $type = 'documents';
        $typeName = 'Documents';
        $usePrefixListing = $bucket->supportsPrefixListing();

        foreach ($documents as $document) {
            $stats['documents']['total']++;
            $hasAnyDerivative = false;
            $documentDerivatives = [];

            $prefix = pathinfo($document->file_name, PATHINFO_FILENAME) . '_';
            $existingFiles = $usePrefixListing ? $bucket->listByPrefix($prefix) : [];

            foreach ($derivativesConfig as $derivativeName => $derivativeConfig) {
                $extensions = ['jpg'];
                if (!empty($derivativeConfig['webp_create'])) {
                    $extensions[] = 'webp';
                }
                foreach ($extensions as $extension) {
                    $expectedKey = $prefix . $derivativeName . '.' . $extension;
                    $derivativeInfo = [
                        'name' => $derivativeName,
                        'extension' => $extension,
                        'file_name' => $expectedKey,
                        'exists' => false,
                        'size' => 0,
                        'size_formatted' => '0 B'
                    ];

                    if ($usePrefixListing) {
                        $derivativeInfo['exists'] = isset($existingFiles[$expectedKey]);
                        $derivativeInfo['size'] = $existingFiles[$expectedKey] ?? 0;
                        if ($derivativeInfo['exists']) {
                            $hasAnyDerivative = true;
                            $derivativeInfo['size_formatted'] = self::formatBytes($derivativeInfo['size']);
                        }
                    } else {
                        $file = new File($bucket);
                        $file->name = $expectedKey;
                        if ($file->exists()) {
                            $hasAnyDerivative = true;
                            try {
                                $fileSize = $file->filesize();
                                $derivativeInfo['exists'] = true;
                                $derivativeInfo['size'] = $fileSize;
                                $derivativeInfo['size_formatted'] = self::formatBytes($fileSize);
                            } catch (Exception $e) {
                                $derivativeInfo['size_formatted'] = 'Error: ' . $e->getMessage();
                            }
                        }
                    }
                    $documentDerivatives[] = $derivativeInfo;

                    $key = $derivativeName . '.' . $extension . '.' . $type;
                    if (!isset($derivativeSummaryMap[$key])) {
                        $derivativeSummaryMap[$key] = [
                            'name' => $derivativeName,
                            'extension' => $extension,
                            'total_count' => 0,
                            'exists_count' => 0,
                            'total_size' => 0,
                            'type' => $typeName
                        ];
                    }
                    $derivativeSummaryMap[$key]['total_count']++;
                    if ($derivativeInfo['exists']) {
                        $derivativeSummaryMap[$key]['exists_count']++;
                        $derivativeSummaryMap[$key]['total_size'] += $derivativeInfo['size'];
                    }
                }
            }

            if ($storeDerivatives) {
                $stats['documents']['derivatives'][] = [
                    'id' => $document->getId(),
                    'file_name' => $document->file_name,
                    'id_media_object' => $document->id_media_object ?? 'N/A',
                    'id_step' => $document->id_step ?? 'N/A',
                    'derivatives' => $documentDerivatives
                ];
            }

            if ($hasAnyDerivative) {
                $stats['documents']['exists']++;
            } else {
                $stats['documents']['missing']++;
                if (count($stats['documents']['missing_list']) < $maxMissingList) {
                    $stats['documents']['missing_list'][] = [
                        'id' => $document->getId(),
                        'file_name' => $document->file_name,
                        'id_media_object' => $document->id_media_object ?? 'N/A',
                        'id_step' => $document->id_step ?? 'N/A'
                    ];
                }
            }
        }
    }
}
