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
            if ($chunkSize > 0) {
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
