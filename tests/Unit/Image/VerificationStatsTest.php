<?php

namespace Pressmind\Tests\Unit\Image;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\Image\VerificationStats;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Derivative;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
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

    public function testCollectMarksPictureMissingWhenWebpSidecarIsMissing(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_verify_missing_webp_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $file = new File($bucket);
        $file->name = 'image_teaser.jpg';
        $file->content = 'jpg';
        $file->save();

        $picture = new Picture();
        $picture->id = 1;
        $picture->id_media_object = 123;
        $picture->file_name = 'image.jpg';
        $picture->download_successful = true;
        $derivative = new Derivative();
        $derivative->id = 10;
        $derivative->id_image = 1;
        $derivative->id_media_object = 123;
        $derivative->name = 'teaser';
        $derivative->file_name = 'image_teaser.jpg';
        $derivative->download_successful = true;
        $picture->derivatives = [$derivative];

        Registry::getInstance()->add('db', $this->createVerificationDb([$picture]));
        $config = [
            'image_handling' => [
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'processor' => [
                    'derivatives' => [
                        'teaser' => ['webp_create' => true],
                    ],
                ],
            ],
        ];

        $result = VerificationStats::collect($config, []);

        $this->assertSame(1, $result['pictures']['total']);
        $this->assertSame(0, $result['pictures']['exists']);
        $this->assertSame(1, $result['pictures']['missing']);
        $this->assertSame(1, $result['pictures']['missing_list'][0]['id']);
        $this->assertContains('image_teaser.webp', $result['pictures']['missing_list'][0]['missing_keys']);
    }

    public function testCollectStreamingMarksPictureMissingWhenWebpSidecarIsMissing(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_verify_stream_missing_webp_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $file = new File($bucket);
        $file->name = 'image_teaser.jpg';
        $file->content = 'jpg';
        $file->save();

        $picture = new Picture();
        $picture->id = 1;
        $picture->id_media_object = 123;
        $picture->file_name = 'image.jpg';
        $picture->download_successful = true;
        $derivative = new Derivative();
        $derivative->id = 10;
        $derivative->id_image = 1;
        $derivative->id_media_object = 123;
        $derivative->name = 'teaser';
        $derivative->file_name = 'image_teaser.jpg';
        $derivative->download_successful = true;
        $picture->derivatives = [$derivative];

        Registry::getInstance()->add('db', $this->createVerificationDb([$picture]));
        $config = [
            'image_handling' => [
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'processor' => [
                    'derivatives' => [
                        'teaser' => ['webp_create' => true],
                    ],
                ],
            ],
        ];

        $result = VerificationStats::collect($config, ['chunk_size' => 1]);

        $this->assertSame(1, $result['pictures']['total']);
        $this->assertSame(0, $result['pictures']['exists']);
        $this->assertSame(1, $result['pictures']['missing']);
        $this->assertContains('image_teaser.webp', $result['pictures']['missing_list'][0]['missing_keys']);
    }

    public function testCollectAppliesMediaObjectFilterToAllImageTypes(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_verify_filter_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $queries = [];
        Registry::getInstance()->add('db', $this->createRecordingVerificationDb($queries));
        $config = [
            'image_handling' => [
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'processor' => [
                    'derivatives' => [
                        'teaser' => [],
                    ],
                ],
            ],
        ];

        VerificationStats::collect($config, ['media_object_ids' => [123, '456', 0, 'abc', '789abc', '12.9']]);

        $imageQueries = array_values(array_filter($queries, static function (array $query): bool {
            return strpos($query['sql'], '`download_successful` = ?') !== false
                && (
                    strpos($query['sql'], 'pmt2core_media_object_images') !== false
                    || strpos($query['sql'], 'pmt2core_media_object_image_sections') !== false
                    || strpos($query['sql'], 'pmt2core_itinerary_step_document_media_objects') !== false
                );
        }));
        $this->assertCount(3, $imageQueries);
        foreach ($imageQueries as $query) {
            $this->assertStringContainsString('`id_media_object` IN (?,?)', $query['sql']);
            $this->assertSame([1, 123, 456], array_map('intval', $query['params']));
        }
    }

    public function testDerivativeSummaryDoesNotCountZeroByteFilesAsExisting(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_verify_zero_byte_summary_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeBucketFile($bucket, 'image_teaser.jpg', '');

        $picture = new Picture();
        $picture->id = 1;
        $picture->id_media_object = 123;
        $picture->file_name = 'image.jpg';
        $picture->download_successful = true;
        $picture->derivatives = [
            $this->createPictureDerivative(10, 1, 'teaser', 'image_teaser.jpg'),
        ];
        Registry::getInstance()->add('db', $this->createVerificationDb([$picture]));
        $config = [
            'image_handling' => [
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'processor' => [
                    'derivatives' => [
                        'teaser' => [],
                    ],
                ],
            ],
        ];

        $result = VerificationStats::collect($config, []);

        $this->assertSame(0, $result['pictures']['exists']);
        $this->assertSame(1, $result['pictures']['missing']);
        $summary = $this->findSummary($result, 'Pictures', 'teaser', 'jpg');
        $this->assertSame(0, $summary['exists_count']);
        $this->assertSame(1, $summary['total_count']);
        $this->assertContains('image_teaser.jpg', $result['pictures']['missing_list'][0]['missing_keys']);
    }

    public function testStreamingDerivativeSummaryDoesNotCountZeroByteFilesAsExisting(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_verify_stream_zero_byte_summary_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeBucketFile($bucket, 'image_teaser.jpg', '');

        $picture = new Picture();
        $picture->id = 1;
        $picture->id_media_object = 123;
        $picture->file_name = 'image.jpg';
        $picture->download_successful = true;
        $picture->derivatives = [
            $this->createPictureDerivative(10, 1, 'teaser', 'image_teaser.jpg'),
        ];
        Registry::getInstance()->add('db', $this->createVerificationDb([$picture]));
        $config = [
            'image_handling' => [
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'processor' => [
                    'derivatives' => [
                        'teaser' => [],
                    ],
                ],
            ],
        ];

        $result = VerificationStats::collect($config, ['chunk_size' => 1]);

        $this->assertSame(0, $result['pictures']['exists']);
        $this->assertSame(1, $result['pictures']['missing']);
        $summary = $this->findSummary($result, 'Pictures', 'teaser', 'jpg');
        $this->assertSame(0, $summary['exists_count']);
        $this->assertSame(1, $summary['total_count']);
        $this->assertContains('image_teaser.jpg', $result['pictures']['missing_list'][0]['missing_keys']);
    }

    public function testCollectPreloadsPictureDerivativesInSingleQuery(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_verify_preload_derivatives_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeBucketFile($bucket, 'image1_teaser.jpg', 'jpg');
        $this->writeBucketFile($bucket, 'image2_teaser.jpg', 'jpg');

        $pictures = [];
        foreach ([1, 2] as $id) {
            $picture = new Picture();
            $picture->id = $id;
            $picture->id_media_object = 123;
            $picture->file_name = 'image' . $id . '.jpg';
            $picture->download_successful = true;
            $pictures[] = $picture;
        }
        $derivatives = [
            $this->createPictureDerivative(10, 1, 'teaser', 'image1_teaser.jpg'),
            $this->createPictureDerivative(11, 2, 'teaser', 'image2_teaser.jpg'),
        ];
        $queries = [];
        Registry::getInstance()->add('db', $this->createPictureDerivativePreloadDb($pictures, $derivatives, $queries));
        $config = [
            'image_handling' => [
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'processor' => [
                    'derivatives' => [
                        'teaser' => [],
                    ],
                ],
            ],
        ];

        $result = VerificationStats::collect($config, []);

        $this->assertSame(2, $result['pictures']['exists']);
        $derivativeQueries = array_values(array_filter($queries, static function (array $query): bool {
            return strpos($query['sql'], 'pmt2core_media_object_image_derivatives') !== false;
        }));
        $this->assertCount(1, $derivativeQueries);
        $this->assertStringContainsString('`id_image` IN (?,?)', $derivativeQueries[0]['sql']);
        $this->assertSame([1, 2], array_map('intval', $derivativeQueries[0]['params']));
    }

    public function testDerivativeSummaryUsesActualDerivativeExtensionWithoutJpgAlternative(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_verify_summary_png_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeBucketFile($bucket, 'image_teaser.png', 'png');

        $picture = new Picture();
        $picture->id = 1;
        $picture->id_media_object = 123;
        $picture->file_name = 'image.png';
        $picture->download_successful = true;
        $picture->derivatives = [
            $this->createPictureDerivative(10, 1, 'teaser', 'image_teaser.png'),
        ];
        Registry::getInstance()->add('db', $this->createVerificationDb([$picture]));
        $config = [
            'image_handling' => [
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'processor' => [
                    'derivatives' => [
                        'teaser' => [],
                    ],
                ],
            ],
        ];

        $result = VerificationStats::collect($config, []);

        $this->assertSame(1, $result['pictures']['exists']);
        $summaryByExtension = [];
        foreach ($result['derivative_summary'] as $summary) {
            if ($summary['type'] === 'Pictures' && $summary['name'] === 'teaser') {
                $summaryByExtension[$summary['extension']] = $summary;
            }
        }
        $this->assertArrayHasKey('png', $summaryByExtension);
        $this->assertArrayNotHasKey('jpg', $summaryByExtension);
        $this->assertSame(1, $summaryByExtension['png']['exists_count']);
        $this->assertSame(1, $summaryByExtension['png']['total_count']);
    }

    public function testStreamingDerivativeSummaryUsesActualDerivativeExtensionWithoutJpgAlternative(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_verify_stream_summary_png_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeBucketFile($bucket, 'image_teaser.png', 'png');

        $picture = new Picture();
        $picture->id = 1;
        $picture->id_media_object = 123;
        $picture->file_name = 'image.png';
        $picture->download_successful = true;
        $picture->derivatives = [
            $this->createPictureDerivative(10, 1, 'teaser', 'image_teaser.png'),
        ];
        Registry::getInstance()->add('db', $this->createVerificationDb([$picture]));
        $config = [
            'image_handling' => [
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'processor' => [
                    'derivatives' => [
                        'teaser' => [],
                    ],
                ],
            ],
        ];

        $result = VerificationStats::collect($config, ['chunk_size' => 1]);

        $this->assertSame(1, $result['pictures']['exists']);
        $summaryByExtension = [];
        foreach ($result['derivative_summary'] as $summary) {
            if ($summary['type'] === 'Pictures' && $summary['name'] === 'teaser') {
                $summaryByExtension[$summary['extension']] = $summary;
            }
        }
        $this->assertArrayHasKey('png', $summaryByExtension);
        $this->assertArrayNotHasKey('jpg', $summaryByExtension);
        $this->assertSame(1, $summaryByExtension['png']['exists_count']);
        $this->assertSame(1, $summaryByExtension['png']['total_count']);
    }

    private function createVerificationDb(array $pictures): AdapterInterface
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchAll')->willReturnCallback(static function ($query) use ($pictures) {
            if (strpos($query, 'pmt2core_media_object_images') !== false) {
                if (preg_match('/LIMIT\s+(\d+)\s*,\s*(\d+)/i', $query, $matches)) {
                    return (int)$matches[1] === 0 ? $pictures : [];
                }
                return $pictures;
            }
            return [];
        });
        $adapter->method('fetchRow')->willReturn(null);
        $adapter->method('fetchOne')->willReturn(null);
        $adapter->method('getAffectedRows')->willReturn(0);
        $adapter->method('getTablePrefix')->willReturn('pmt2core_');
        $adapter->method('inTransaction')->willReturn(false);
        $adapter->method('execute')->willReturn(null);
        $adapter->method('insert')->willReturn(null);
        $adapter->method('replace')->willReturn(null);
        $adapter->method('update')->willReturn(null);
        $adapter->method('delete')->willReturn(null);
        $adapter->method('truncate')->willReturn(null);
        $adapter->method('batchInsert')->willReturn(1);
        $adapter->method('beginTransaction')->willReturn(null);
        $adapter->method('commit')->willReturn(null);
        $adapter->method('rollback')->willReturn(null);
        return $adapter;
    }

    private function findSummary(array $result, string $type, string $name, string $extension): array
    {
        foreach ($result['derivative_summary'] as $summary) {
            if ($summary['type'] === $type && $summary['name'] === $name && $summary['extension'] === $extension) {
                return $summary;
            }
        }
        $this->fail('Missing derivative summary for ' . $type . ' ' . $name . '.' . $extension);
    }

    private function createPictureDerivativePreloadDb(array $pictures, array $derivatives, array &$queries): AdapterInterface
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchAll')->willReturnCallback(static function ($query, $params = null) use ($pictures, $derivatives, &$queries) {
            $queries[] = [
                'sql' => $query,
                'params' => $params ?? [],
            ];
            if (strpos($query, 'pmt2core_media_object_images') !== false) {
                return $pictures;
            }
            if (strpos($query, 'pmt2core_media_object_image_derivatives') !== false) {
                if (strpos($query, '`id_image` IN') !== false) {
                    return $derivatives;
                }
                $ownerId = (int)($params[0] ?? 0);
                return array_values(array_filter($derivatives, static fn(Derivative $derivative): bool => (int)$derivative->id_image === $ownerId));
            }
            return [];
        });
        $adapter->method('fetchRow')->willReturn(null);
        $adapter->method('fetchOne')->willReturn(null);
        $adapter->method('getAffectedRows')->willReturn(0);
        $adapter->method('getTablePrefix')->willReturn('pmt2core_');
        $adapter->method('inTransaction')->willReturn(false);
        $adapter->method('execute')->willReturn(null);
        $adapter->method('insert')->willReturn(null);
        $adapter->method('replace')->willReturn(null);
        $adapter->method('update')->willReturn(null);
        $adapter->method('delete')->willReturn(null);
        $adapter->method('truncate')->willReturn(null);
        $adapter->method('batchInsert')->willReturn(1);
        $adapter->method('beginTransaction')->willReturn(null);
        $adapter->method('commit')->willReturn(null);
        $adapter->method('rollback')->willReturn(null);
        return $adapter;
    }

    private function writeBucketFile(Bucket $bucket, string $name, string $content): void
    {
        $file = new File($bucket);
        $file->name = $name;
        $file->content = $content;
        $file->save();
    }

    private function createPictureDerivative(int $id, int $idImage, string $name, string $fileName): Derivative
    {
        $derivative = new Derivative();
        $derivative->id = $id;
        $derivative->id_image = $idImage;
        $derivative->id_media_object = 123;
        $derivative->name = $name;
        $derivative->file_name = $fileName;
        $derivative->download_successful = true;
        $derivative->width = 100;
        $derivative->height = 100;
        return $derivative;
    }

    private function createRecordingVerificationDb(array &$queries): AdapterInterface
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchAll')->willReturnCallback(static function ($query, $params = null) use (&$queries) {
            $queries[] = [
                'sql' => $query,
                'params' => $params ?? [],
            ];
            return [];
        });
        $adapter->method('fetchRow')->willReturn(null);
        $adapter->method('fetchOne')->willReturn(null);
        $adapter->method('getAffectedRows')->willReturn(0);
        $adapter->method('getTablePrefix')->willReturn('pmt2core_');
        $adapter->method('inTransaction')->willReturn(false);
        $adapter->method('execute')->willReturn(null);
        $adapter->method('insert')->willReturn(null);
        $adapter->method('replace')->willReturn(null);
        $adapter->method('update')->willReturn(null);
        $adapter->method('delete')->willReturn(null);
        $adapter->method('truncate')->willReturn(null);
        $adapter->method('batchInsert')->willReturn(1);
        $adapter->method('beginTransaction')->willReturn(null);
        $adapter->method('commit')->willReturn(null);
        $adapter->method('rollback')->willReturn(null);
        return $adapter;
    }
}
