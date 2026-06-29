<?php

namespace Pressmind\Tests\Unit\CLI;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\CLI\ImageProcessorCommand;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Derivative;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Tests\Unit\AbstractTestCase;

class ImageProcessorCommandTest extends AbstractTestCase
{
    /**
     * @dataProvider formatBytesProvider
     */
    public function testFormatBytes(int $bytes, int $precision, string $expected): void
    {
        $this->assertSame($expected, ImageProcessorCommand::formatBytes($bytes, $precision));
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

    public function testMediaobjectModeRejectsEmptyOrInvalidIds(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_image_processor_invalid_ids_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'storage' => [
                    'provider' => 'filesystem',
                    'bucket' => $bucketPath,
                ],
                'processor' => [
                    'adapter' => 'ImageMagick',
                    'derivatives' => [],
                ],
            ],
        ]));

        $command = new ImageProcessorCommand();

        ob_start();
        try {
            $exitCode = $command->run(['image_processor.php', 'mediaobject', '0,abc']);
        } finally {
            ob_end_clean();
        }

        $this->assertSame(1, $exitCode);
    }

    public function testMediaobjectModeRejectsPartiallyNumericIds(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_image_processor_partially_numeric_ids_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'storage' => [
                    'provider' => 'filesystem',
                    'bucket' => $bucketPath,
                ],
                'processor' => [
                    'adapter' => 'ImageMagick',
                    'derivatives' => [],
                ],
            ],
        ]));

        $command = new ImageProcessorCommand();

        ob_start();
        try {
            $exitCode = $command->run(['image_processor.php', 'mediaobject', '123abc,12.9']);
        } finally {
            ob_end_clean();
        }

        $this->assertSame(1, $exitCode);
    }

    public function testMediaobjectModeFiltersPicturesSectionsAndDocuments(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_image_processor_filtered_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $queries = [];
        Registry::getInstance()->add('db', $this->createRecordingDb($queries));
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'storage' => [
                    'provider' => 'filesystem',
                    'bucket' => $bucketPath,
                ],
                'processor' => [
                    'adapter' => 'ImageMagick',
                    'derivatives' => [],
                ],
            ],
        ]));

        $command = new ImageProcessorCommand();

        ob_start();
        try {
            $exitCode = $command->run(['image_processor.php', 'mediaobject', '123,456']);
        } finally {
            ob_end_clean();
        }

        $this->assertSame(0, $exitCode);
        $pendingQueries = array_values(array_filter($queries, static function (array $query): bool {
            return strpos($query['sql'], '`download_successful` = ?') !== false
                && in_array(0, $query['params'], true);
        }));
        $this->assertCount(3, $pendingQueries, 'Expected pending queries for pictures, sections and document media objects.');
        foreach ($pendingQueries as $query) {
            $this->assertStringContainsString('`id_media_object` IN (?,?)', $query['sql']);
            $this->assertContains(123, array_map('intval', $query['params']));
            $this->assertContains(456, array_map('intval', $query['params']));
        }
        $combinedSql = implode("\n", array_column($pendingQueries, 'sql'));
        $this->assertStringContainsString('pmt2core_media_object_images', $combinedSql);
        $this->assertStringContainsString('pmt2core_media_object_image_sections', $combinedSql);
        $this->assertStringContainsString('pmt2core_itinerary_step_document_media_objects', $combinedSql);

        $successfulQueries = array_values(array_filter($queries, static function (array $query): bool {
            return strpos($query['sql'], '`download_successful` = ?') !== false
                && in_array(1, array_map('intval', $query['params']), true)
                && (
                    strpos($query['sql'], 'pmt2core_media_object_images') !== false
                    || strpos($query['sql'], 'pmt2core_media_object_image_sections') !== false
                    || strpos($query['sql'], 'pmt2core_itinerary_step_document_media_objects') !== false
                );
        }));
        $this->assertCount(3, $successfulQueries, 'Expected cleanup queries for pictures, sections and document media objects.');
        foreach ($successfulQueries as $query) {
            $this->assertStringContainsString('`id_media_object` IN (?,?)', $query['sql']);
        }
    }

    public function testMediaobjectReportUsesSameFilter(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_image_processor_report_filtered_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $queries = [];
        Registry::getInstance()->add('db', $this->createRecordingDb($queries));
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'storage' => [
                    'provider' => 'filesystem',
                    'bucket' => $bucketPath,
                ],
                'processor' => [
                    'adapter' => 'ImageMagick',
                    'derivatives' => [],
                ],
            ],
        ]));

        $command = new ImageProcessorCommand();

        ob_start();
        try {
            $exitCode = $command->run(['image_processor.php', 'mediaobject', '123,456', '--report']);
        } finally {
            ob_end_clean();
        }

        $this->assertSame(0, $exitCode);
        $successfulQueries = array_values(array_filter($queries, static function (array $query): bool {
            return strpos($query['sql'], '`download_successful` = ?') !== false
                && in_array(1, array_map('intval', $query['params']), true)
                && (
                    strpos($query['sql'], 'pmt2core_media_object_images') !== false
                    || strpos($query['sql'], 'pmt2core_media_object_image_sections') !== false
                    || strpos($query['sql'], 'pmt2core_itinerary_step_document_media_objects') !== false
                );
        }));
        $this->assertNotEmpty($successfulQueries);
        foreach ($successfulQueries as $query) {
            $this->assertStringContainsString('`id_media_object` IN (?,?)', $query['sql']);
            $this->assertContains(123, array_map('intval', $query['params']));
            $this->assertContains(456, array_map('intval', $query['params']));
        }
    }

    public function testResetMissingCanUseMediaobjectFilter(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_image_processor_reset_filtered_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $queries = [];
        Registry::getInstance()->add('db', $this->createRecordingDb($queries));
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'storage' => [
                    'provider' => 'filesystem',
                    'bucket' => $bucketPath,
                ],
                'processor' => [
                    'adapter' => 'ImageMagick',
                    'derivatives' => [],
                ],
            ],
        ]));

        $command = new ImageProcessorCommand();

        ob_start();
        try {
            $exitCode = $command->run(['image_processor.php', 'reset-missing', 'mediaobject', '123,456']);
        } finally {
            ob_end_clean();
        }

        $this->assertSame(0, $exitCode);
        $successfulQueries = array_values(array_filter($queries, static function (array $query): bool {
            return strpos($query['sql'], '`download_successful` = ?') !== false
                && in_array(1, array_map('intval', $query['params']), true)
                && (
                    strpos($query['sql'], 'pmt2core_media_object_images') !== false
                    || strpos($query['sql'], 'pmt2core_media_object_image_sections') !== false
                    || strpos($query['sql'], 'pmt2core_itinerary_step_document_media_objects') !== false
                );
        }));
        $this->assertNotEmpty($successfulQueries);
        foreach ($successfulQueries as $query) {
            $this->assertStringContainsString('`id_media_object` IN (?,?)', $query['sql']);
            $this->assertContains(123, array_map('intval', $query['params']));
            $this->assertContains(456, array_map('intval', $query['params']));
        }
    }

    public function testHasWorkToDoRejectsDuplicateDerivativeRowsEvenWhenFilesExist(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_image_processor_duplicates_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $command = $this->createConfiguredCommand($bucketPath, [
            'teaser' => ['webp_create' => true],
        ]);
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeBucketFile($bucket, 'image_teaser.jpg', 'jpg');
        $this->writeBucketFile($bucket, 'image_teaser.webp', 'webp');

        $picture = new Picture();
        $picture->id = 1;
        $picture->id_media_object = 123;
        $picture->file_name = 'image.jpg';
        $picture->download_successful = true;
        $picture->derivatives = [
            $this->createDerivative(10, 'teaser', 'image_teaser.jpg'),
            $this->createDerivative(11, 'teaser', 'image_teaser_duplicate.jpg'),
        ];

        $method = new \ReflectionMethod(ImageProcessorCommand::class, 'hasWorkToDo');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($command, $picture));
    }

    public function testProcessSingleImageKeepsDownloadUnsuccessfulWhenCompletenessFails(): void
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_image_processor_incomplete_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        $command = $this->createConfiguredCommand($bucketPath, [
            'teaser' => [
                'max_width' => 100,
                'max_height' => 100,
                'webp_create' => true,
            ],
        ]);
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $picture = new IncompleteWebpPicture($bucket);
        $picture->id = 1;
        $picture->id_media_object = 123;
        $picture->file_name = 'image.jpg';
        $picture->tmp_url = 'https://example.test/image.jpg';
        $picture->download_successful = false;

        $method = new \ReflectionMethod(ImageProcessorCommand::class, 'processSingleImage');
        $method->setAccessible(true);
        $method->invoke($command, $picture);

        $this->assertFalse((bool)$picture->download_successful);
        $this->assertSame(0, $picture->updateCount);
    }

    public function testVerificationReportShowsMissingKeysAndDuplicateDerivativeNames(): void
    {
        $command = new ImageProcessorCommand();
        $stats = [
            'pictures' => [
                'total' => 1,
                'exists' => 0,
                'missing' => 1,
                'missing_list' => [
                    [
                        'id' => 1,
                        'file_name' => 'image.jpg',
                        'id_media_object' => 123,
                        'missing_keys' => ['image_teaser.webp'],
                        'duplicate_derivative_names' => ['teaser'],
                    ],
                ],
                'derivatives' => [],
            ],
            'sections' => ['total' => 0, 'exists' => 0, 'missing' => 0, 'missing_list' => [], 'derivatives' => []],
            'documents' => ['total' => 0, 'exists' => 0, 'missing' => 0, 'missing_list' => [], 'derivatives' => []],
            'derivative_summary' => [],
        ];
        $method = new \ReflectionMethod(ImageProcessorCommand::class, 'outputVerificationReport');
        $method->setAccessible(true);

        ob_start();
        try {
            $method->invoke($command, $stats);
            $output = ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        $this->assertStringContainsString('image_teaser.webp', $output);
        $this->assertStringContainsString('teaser', $output);
    }

    private function createRecordingDb(array &$queries): AdapterInterface
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

    private function createConfiguredCommand(string $bucketPath, array $derivatives): ImageProcessorCommand
    {
        $config = $this->createMockConfig([
            'image_handling' => [
                'storage' => [
                    'provider' => 'filesystem',
                    'bucket' => $bucketPath,
                ],
                'processor' => [
                    'adapter' => 'ImageMagick',
                    'derivatives' => $derivatives,
                ],
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        $command = new ImageProcessorCommand();

        $configProperty = new \ReflectionProperty(ImageProcessorCommand::class, 'config');
        $configProperty->setAccessible(true);
        $configProperty->setValue($command, $config);

        $bucketProperty = new \ReflectionProperty(ImageProcessorCommand::class, 'bucket');
        $bucketProperty->setAccessible(true);
        $bucketProperty->setValue($command, new Bucket($config['image_handling']['storage']));

        return $command;
    }

    private function writeBucketFile(Bucket $bucket, string $name, string $content): void
    {
        $file = new File($bucket);
        $file->name = $name;
        $file->content = $content;
        $file->save();
    }

    private function createDerivative(int $id, string $name, string $fileName): Derivative
    {
        $derivative = new Derivative();
        $derivative->id = $id;
        $derivative->id_image = 1;
        $derivative->id_media_object = 123;
        $derivative->name = $name;
        $derivative->file_name = $fileName;
        $derivative->download_successful = true;
        $derivative->width = 100;
        $derivative->height = 100;
        return $derivative;
    }
}

class IncompleteWebpPicture extends Picture
{
    public int $updateCount = 0;
    private File $binaryFile;

    public function __construct(Bucket $bucket)
    {
        parent::__construct();
        $this->binaryFile = new File($bucket);
        $this->binaryFile->name = 'image.jpg';
        $this->binaryFile->content = 'source';
    }

    public function exists()
    {
        return true;
    }

    public function getBinaryFile()
    {
        return $this->binaryFile;
    }

    public function createDerivative($derivative_config, $image_processor, $image)
    {
        $file = new File($image->getBucket());
        $file->name = pathinfo($this->file_name, PATHINFO_FILENAME) . '_' . $derivative_config->name . '.jpg';
        $file->content = 'jpg';
        $file->save();

        $derivative = new Derivative();
        $derivative->id = 10;
        $derivative->id_image = $this->getId();
        $derivative->id_media_object = $this->id_media_object;
        $derivative->name = $derivative_config->name;
        $derivative->file_name = $file->name;
        $derivative->download_successful = true;
        $derivative->width = $derivative_config->max_width;
        $derivative->height = $derivative_config->max_height;
        $this->derivatives = [$derivative];
    }

    public function update()
    {
        $this->updateCount++;
        return true;
    }
}
