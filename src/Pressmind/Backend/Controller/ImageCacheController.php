<?php

namespace Pressmind\Backend\Controller;

use Pressmind\Image\VerificationStats;
use Pressmind\Registry;

/**
 * Image Cache: verification report (summary, detailed stats, derivatives, missing images).
 */
class ImageCacheController extends AbstractController
{
    public function indexAction(): void
    {
        $config = $this->getConfig();
        $stats = VerificationStats::collect($config, [
            'chunk_size' => VerificationStats::DEFAULT_CHUNK_SIZE,
            'max_missing_list' => VerificationStats::DEFAULT_MAX_MISSING_LIST,
        ]);
        $derivativeSummary = $this->normalizeDerivativeSummary($stats);
        $totalChecked = $stats['pictures']['total'] + $stats['sections']['total'] + $stats['documents']['total'];
        $totalExists = $stats['pictures']['exists'] + $stats['sections']['exists'] + $stats['documents']['exists'];
        $totalMissing = $stats['pictures']['missing'] + $stats['sections']['missing'] + $stats['documents']['missing'];
        $totalSize = $this->computeTotalSize($stats);
        $baseUrl = $this->baseUrl();

        $this->render('imagecache/index.php', [
            'title' => 'Image Cache',
            'stats' => $stats,
            'derivativeSummary' => $derivativeSummary,
            'totalChecked' => $totalChecked,
            'totalExists' => $totalExists,
            'totalMissing' => $totalMissing,
            'totalSize' => $totalSize,
            'totalSizeFormatted' => VerificationStats::formatBytes($totalSize),
            'baseUrl' => $baseUrl,
        ]);
    }

    /**
     * Uses stats[derivative_summary] when present (chunked mode); otherwise builds from derivatives arrays.
     *
     * @return array<int, array{name: string, extension: string, total_count: int, exists_count: int, total_size: int, total_size_formatted: string, type: string, percentage: float, avg_size: int, avg_size_formatted: string}>
     */
    private function normalizeDerivativeSummary(array $stats): array
    {
        $rows = isset($stats['derivative_summary']) && is_array($stats['derivative_summary'])
            ? $stats['derivative_summary']
            : $this->buildDerivativeSummaryFromStats($stats);

        $result = [];
        foreach ($rows as $row) {
            $avgSize = !empty($row['exists_count']) ? (int) ($row['total_size'] / $row['exists_count']) : 0;
            $percentage = !empty($row['total_count']) ? round(($row['exists_count'] / $row['total_count']) * 100, 1) : 0.0;
            $result[] = [
                'name' => $row['name'],
                'extension' => $row['extension'],
                'total_count' => (int) ($row['total_count'] ?? 0),
                'exists_count' => (int) ($row['exists_count'] ?? 0),
                'total_size' => (int) ($row['total_size'] ?? 0),
                'total_size_formatted' => VerificationStats::formatBytes((int) ($row['total_size'] ?? 0)),
                'type' => $row['type'] ?? '',
                'percentage' => $percentage,
                'avg_size' => $avgSize,
                'avg_size_formatted' => VerificationStats::formatBytes($avgSize),
            ];
        }
        return $result;
    }

    /**
     * Build from full derivatives arrays (when not chunked).
     *
     * @return list<array{name: string, extension: string, total_count: int, exists_count: int, total_size: int, type: string}>
     */
    private function buildDerivativeSummaryFromStats(array $stats): array
    {
        $map = [];
        foreach (['pictures' => 'Pictures', 'sections' => 'Sections', 'documents' => 'Documents'] as $type => $typeName) {
            foreach ($stats[$type]['derivatives'] as $data) {
                foreach ($data['derivatives'] as $derivative) {
                    $key = $derivative['name'] . '.' . $derivative['extension'] . '.' . $type;
                    if (!isset($map[$key])) {
                        $map[$key] = [
                            'name' => $derivative['name'],
                            'extension' => $derivative['extension'],
                            'total_count' => 0,
                            'exists_count' => 0,
                            'total_size' => 0,
                            'type' => $typeName
                        ];
                    }
                    $map[$key]['total_count']++;
                    if ($derivative['exists']) {
                        $map[$key]['exists_count']++;
                        $map[$key]['total_size'] += $derivative['size'];
                    }
                }
            }
        }
        return array_values($map);
    }

    private function computeTotalSize(array $stats): int
    {
        if (isset($stats['derivative_summary']) && is_array($stats['derivative_summary'])) {
            $total = 0;
            foreach ($stats['derivative_summary'] as $row) {
                $total += (int) ($row['total_size'] ?? 0);
            }
            return $total;
        }
        $total = 0;
        foreach (['pictures', 'sections', 'documents'] as $type) {
            foreach ($stats[$type]['derivatives'] as $data) {
                foreach ($data['derivatives'] as $derivative) {
                    $total += $derivative['size'];
                }
            }
        }
        return $total;
    }

    private function getConfig(): array
    {
        try {
            return Registry::getInstance()->get('config');
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function baseUrl(): string
    {
        $base = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
        return ($base !== '' ? $base . '?' : '?');
    }
}
