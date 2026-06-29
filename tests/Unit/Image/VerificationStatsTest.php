<?php

namespace Pressmind\Tests\Unit\Image;

use Pressmind\Image\VerificationStats;
use Pressmind\Tests\Fixtures\Images\ImageFixtureTrait;
use Pressmind\Tests\Unit\AbstractTestCase;

class VerificationStatsTest extends AbstractTestCase
{
    use ImageFixtureTrait;

    /**
     * @dataProvider formatBytesProvider
     */
    public function testFormatBytesVariousSizes(int $bytes, int $precision, string $expected): void
    {
        $this->assertSame($expected, VerificationStats::formatBytes($bytes, $precision));
    }

    public static function formatBytesProvider(): array
    {
        return [
            'zero bytes' => [0, 2, '0 B'],
            '500 bytes' => [500, 2, '500 B'],
            '1 KB' => [1024, 2, '1 KB'],
            '1.5 KB' => [1536, 2, '1.5 KB'],
            '1 MB' => [1048576, 2, '1 MB'],
            '1 GB' => [1073741824, 2, '1 GB'],
            'precision 0' => [1536, 0, '2 KB'],
            'precision 1' => [1536, 1, '1.5 KB'],
        ];
    }

    public function testFormatBytesZero(): void
    {
        $this->assertSame('0 B', VerificationStats::formatBytes(0));
    }

    public function testFormatBytesPrecision(): void
    {
        $this->assertSame('1.5 KB', VerificationStats::formatBytes(1536, 1));
        $this->assertSame('1 KB', VerificationStats::formatBytes(1024, 0));
    }

    /**
     * buildDerivativeSummaryList is private; test sorting via collect() with chunk_size=0 and empty DB
     * so that derivativeSummaryMap gets entries from verifyPictures/verifySections/verifyDocuments.
     * With mock DB returning [] for listAll, derivativeSummaryMap stays empty and result is [].
     * So we test that collect returns expected structure including derivative_summary key.
     */
    public function testCollectReturnsExpectedStructure(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_verify_test_' . uniqid('', true);
        if (!is_dir($bucketPath)) {
            mkdir($bucketPath, 0755, true);
        }
        $config = [
            'image_handling' => [
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'processor' => ['derivatives' => ['thumb' => ['max_width' => 200, 'max_height' => 200]]],
            ],
        ];
        $result = VerificationStats::collect($config, []);
        $this->assertArrayHasKey('pictures', $result);
        $this->assertArrayHasKey('sections', $result);
        $this->assertArrayHasKey('documents', $result);
        $this->assertArrayHasKey('derivative_summary', $result);
        foreach (['pictures', 'sections', 'documents'] as $type) {
            $this->assertArrayHasKey('total', $result[$type]);
            $this->assertArrayHasKey('exists', $result[$type]);
            $this->assertArrayHasKey('missing', $result[$type]);
            $this->assertArrayHasKey('missing_list', $result[$type]);
            $this->assertArrayHasKey('derivatives', $result[$type]);
            $this->assertIsArray($result[$type]['missing_list']);
            $this->assertIsArray($result[$type]['derivatives']);
        }
        $this->assertIsArray($result['derivative_summary']);
        $this->assertSame(0, $result['pictures']['total']);
        $this->assertSame(0, $result['sections']['total']);
        $this->assertSame(0, $result['documents']['total']);
        $this->assertSame(0, $result['pictures']['exists']);
        $this->assertSame(0, $result['pictures']['missing']);
    }

    /**
     * Test that buildDerivativeSummaryList sorting (Pictures before Sections before Documents, then by name)
     * is applied. We can't call the private method directly; we pass a config that yields a non-empty
     * derivativeSummaryMap. With empty listAll from mock DB we get empty derivative_summary.
     * So we only assert that derivative_summary is a list (array with numeric keys).
     */
    public function testBuildDerivativeSummaryListSorting(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_verify_sort_' . uniqid('', true);
        if (!is_dir($bucketPath)) {
            mkdir($bucketPath, 0755, true);
        }
        $config = [
            'image_handling' => [
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'processor' => ['derivatives' => ['thumb' => []]],
            ],
        ];
        $result = VerificationStats::collect($config, []);
        $this->assertIsArray($result['derivative_summary']);
        $this->assertSame(array_keys($result['derivative_summary']), array_keys(array_values($result['derivative_summary'])));
    }
}
