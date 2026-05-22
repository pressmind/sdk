<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\Import\Itinerary;
use Pressmind\ORM\Object\Itinerary\Step;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject\Derivative;
use Pressmind\ORM\Object\Itinerary\Variant;
use Pressmind\Registry;
use Pressmind\REST\Client;
use Pressmind\Tests\Unit\AbstractTestCase;

class ItineraryTest extends AbstractTestCase
{
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $tempDir) {
            $this->removeTempDir($tempDir);
        }
        $this->tempDirs = [];
        parent::tearDown();
    }

    public function testConstructorStoresIdMediaObject(): void
    {
        $import = new Itinerary(12345);
        $this->assertInstanceOf(Itinerary::class, $import);
        $this->assertCount(0, $import->getLog());
        $this->assertCount(0, $import->getErrors());
    }

    public function testConstructorCastsIdToInteger(): void
    {
        $import = new Itinerary('999');
        $this->assertInstanceOf(Itinerary::class, $import);
    }

    public function testImportWithMockedClientEmptyResult(): void
    {
        $client = $this->createMock(Client::class);
        // _checkApiResponse accepts result when it is an array; empty array = no itinerary data
        $client->method('sendRequest')->willReturn((object) ['result' => [], 'error' => false]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Itinerary(999);
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientExceptionAddsError(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('sendRequest')->willThrowException(new \Exception('API failed'));
        Registry::getInstance()->add('rest_client', $client);
        $import = new Itinerary(999);
        $import->import();
        $this->assertCount(1, $import->getErrors());
    }

    public function testImportWithApiErrorAddsError(): void
    {
        $client = $this->createMock(Client::class);
        $client->method('sendRequest')->willReturn((object) ['error' => true, 'msg' => 'Not found']);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Itinerary(999);
        $import->import();
        $this->assertCount(1, $import->getErrors());
    }

    public function testImportPersistsBoardDistanceAndSectionTags(): void
    {
        $inserted = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturn([]);
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('');
        $db->method('inTransaction')->willReturn(false);
        $db->method('execute')->willReturn(null);
        $db->method('insert')->willReturnCallback(function (string $tableName, array $data) use (&$inserted) {
            $inserted[$tableName][] = $data;
            return $data['id'] ?? 1;
        });
        $db->method('delete')->willReturn(null);
        $db->method('update')->willReturn(null);
        $db->method('replace')->willReturn(null);
        $db->method('truncate')->willReturn(null);
        $db->method('batchInsert')->willReturn(1);
        $db->method('beginTransaction')->willReturn(null);
        $db->method('commit')->willReturn(null);
        $db->method('rollback')->willReturn(null);
        Registry::getInstance()->add('db', $db);

        $client = $this->createMock(Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'error' => false,
            'result' => (object) [
                'type' => 'itinerary_dateless',
                'steps' => [
                    (object) [
                        'id' => 42029,
                        'type' => 'course_port',
                        'sections' => [
                            (object) [
                                'id' => 'DF66F264-52D1-6DD4-E24D-633DD2EB1907',
                                'name' => 'default',
                                'varname' => '',
                                'language' => '',
                                'content' => (object) [
                                    'headline' => '1. Tag:',
                                    'description' => 'Beschreibung',
                                ],
                                'tags' => ['Hafen', 'Anreise'],
                            ],
                        ],
                        'board' => [
                            (object) [
                                'breakfast' => 0,
                                'lunch' => 0,
                                'dinner' => 0,
                                'distance' => '456',
                            ],
                        ],
                        'geopoints' => [],
                        'ports' => [],
                        'document_media_objects' => [],
                        'text_media_objects' => [],
                    ],
                ],
            ],
        ]);
        Registry::getInstance()->add('rest_client', $client);

        $import = new Itinerary(869750);
        $import->import();

        $this->assertSame('456', $inserted['pmt2core_itinerary_step_boards'][0]['distance']);
        $this->assertSame('Hafen,Anreise', $inserted['pmt2core_itinerary_step_sections'][0]['tags']);
    }

    public function testDatelessImportDeletesStaleItineraryImageFilesBeforeReplacingSteps(): void
    {
        $bucketDir = $this->createTempImageBucket();
        $this->configureImageStorage($bucketDir);

        $fileNames = [
            'itinerary_42029_3916225.jpg',
            'itinerary_42029_3916225_detail.jpg',
            'itinerary_42029_3916225_detail.webp',
        ];
        foreach ($fileNames as $fileName) {
            file_put_contents($bucketDir . DIRECTORY_SEPARATOR . $fileName, 'stale');
        }

        $document = new DocumentMediaObject();
        $document->id = 77;
        $document->id_step = 42029;
        $document->id_media_object = 3916225;
        $document->file_name = 'itinerary_42029_3916225.jpg';

        $oldStep = new Step();
        $oldStep->id = 42029;
        $oldStep->id_media_object = 3916225;
        $oldStep->document_media_objects = [$document];

        Registry::getInstance()->add('db', $this->createItineraryDbMock([$oldStep]));

        $client = $this->createMock(Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'error' => false,
            'result' => (object) [
                'type' => 'itinerary_dateless',
                'steps' => [],
            ],
        ]);
        Registry::getInstance()->add('rest_client', $client);

        $import = new Itinerary(3916225);
        $import->import();

        foreach ($fileNames as $fileName) {
            $this->assertFileDoesNotExist($bucketDir . DIRECTORY_SEPARATOR . $fileName);
        }
    }

    public function testVariantImportDeletesStaleItineraryImageFilesBeforeReplacingVariants(): void
    {
        $bucketDir = $this->createTempImageBucket();
        $this->configureImageStorage($bucketDir);

        $fileNames = [
            'itinerary_510_3538051.jpg',
            'itinerary_510_3538051_detail.jpg',
            'itinerary_510_3538051_detail.webp',
        ];
        foreach ($fileNames as $fileName) {
            file_put_contents($bucketDir . DIRECTORY_SEPARATOR . $fileName, 'stale');
        }

        $document = new DocumentMediaObject();
        $document->id = 88;
        $document->id_step = 510;
        $document->file_name = 'itinerary_510_3538051.jpg';

        $oldStep = new Step();
        $oldStep->id = 510;
        $oldStep->document_media_objects = [$document];

        $oldVariant = new Variant();
        $oldVariant->id = 42;
        $oldVariant->id_media_object = 3538051;
        $oldVariant->steps = [$oldStep];

        Registry::getInstance()->add('db', $this->createItineraryDbMock([], [$oldVariant]));

        $client = $this->createMock(Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'error' => false,
            'result' => (object) [
                'type' => 'itinerary_to_touristic',
                'variants' => [],
            ],
        ]);
        Registry::getInstance()->add('rest_client', $client);

        $import = new Itinerary(3538051);
        $import->import();

        foreach ($fileNames as $fileName) {
            $this->assertFileDoesNotExist($bucketDir . DIRECTORY_SEPARATOR . $fileName);
        }
    }

    public function testItineraryImageUriUsesRemoteUntilImageProcessorHasLocalizedFile(): void
    {
        $bucketDir = $this->createTempImageBucket();
        $this->configureImageStorage($bucketDir);

        $document = new DocumentMediaObject();
        $document->id = 99;
        $document->width = 2000;
        $document->height = 1200;
        $document->file_name = 'itinerary_42029_3916225.jpg';
        $document->tmp_url = 'https://pm.remote/image/3916225.jpg?v=web';
        $document->download_successful = false;

        $remoteUri = $document->getUri('detail', false, null);

        $this->assertStringStartsWith('https://pm.remote/image/3916225.jpg?', $remoteUri);
        $this->assertStringContainsString('h=800', $remoteUri);
        $this->assertStringNotContainsString('/local-images/', $remoteUri);

        $derivative = new Derivative();
        $derivative->id = 1;
        $derivative->name = 'detail';
        $derivative->file_name = 'itinerary_42029_3916225_detail.jpg';

        $document->download_successful = true;
        $document->derivatives = [$derivative];

        $this->assertSame('/local-images/itinerary_42029_3916225_detail.jpg', $document->getUri('detail', false, null));
    }

    /**
     * @param Step[] $oldSteps
     * @param Variant[] $oldVariants
     */
    private function createItineraryDbMock(array $oldSteps, array $oldVariants = []): AdapterInterface
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchAll')->willReturnCallback(function (string $query, $params = null, ?string $className = null) use ($oldSteps, $oldVariants) {
            if ($className === Step::class && str_contains($query, 'pmt2core_itinerary_steps')) {
                return $oldSteps;
            }
            if ($className === Variant::class && str_contains($query, 'pmt2core_itinerary_variants')) {
                return $oldVariants;
            }
            return [];
        });
        $db->method('fetchRow')->willReturn(null);
        $db->method('fetchOne')->willReturn(null);
        $db->method('getAffectedRows')->willReturn(0);
        $db->method('getTablePrefix')->willReturn('');
        $db->method('inTransaction')->willReturn(false);
        $db->method('execute')->willReturn(null);
        $db->method('insert')->willReturn(1);
        $db->method('delete')->willReturn(null);
        $db->method('update')->willReturn(null);
        $db->method('replace')->willReturn(null);
        $db->method('truncate')->willReturn(null);
        $db->method('batchInsert')->willReturn(1);
        $db->method('beginTransaction')->willReturn(null);
        $db->method('commit')->willReturn(null);
        $db->method('rollback')->willReturn(null);
        return $db;
    }

    private function createTempImageBucket(): string
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_itinerary_image_test_' . uniqid('', true);
        mkdir($tempDir, 0755, true);
        $this->tempDirs[] = $tempDir;
        return $tempDir;
    }

    private function configureImageStorage(string $bucketDir): void
    {
        $config = $this->createMockConfig([
            'image_handling' => [
                'storage' => [
                    'provider' => 'filesystem',
                    'bucket' => $bucketDir,
                ],
                'http_src' => '/local-images',
                'processor' => [
                    'webp_support' => false,
                    'derivatives' => [
                        'detail' => [
                            'max_width' => 1200,
                            'max_height' => 800,
                            'webp_create' => true,
                        ],
                    ],
                ],
            ],
        ]);
        Registry::getInstance()->add('config', $config);
    }

    private function removeTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . DIRECTORY_SEPARATOR . '*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        @rmdir($dir);
    }
}
