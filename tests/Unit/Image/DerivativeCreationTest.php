<?php

namespace Pressmind\Tests\Unit\Image;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\Image\Processor\AdapterInterface as ImageProcessorAdapterInterface;
use Pressmind\Image\Processor\Config;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject\Derivative as DocumentDerivative;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Derivative;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Section;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Tests\Unit\AbstractTestCase;

class DerivativeCreationTest extends AbstractTestCase
{
    public function testPictureCreateDerivativeUpdatesExistingRowAndDeletesDuplicateRows(): void
    {
        $bucket = $this->createBucket();
        $derivatives = [];
        $updates = [];
        $deletes = [];
        Registry::getInstance()->add('db', $this->createDerivativeDb($derivatives, $updates, $deletes));
        $existing = $this->createPictureDerivative(55, 'old_teaser.jpg');
        $duplicate = $this->createPictureDerivative(56, 'old_teaser_duplicate.jpg');
        $derivatives = [$existing, $duplicate];

        $picture = new Picture();
        $picture->id = 1;
        $picture->id_media_object = 123;
        $picture->file_name = 'image.jpg';
        $source = $this->createSourceFile($bucket, 'image.jpg');

        $picture->createDerivative(
            Config::create('teaser', ['max_width' => 100, 'max_height' => 80, 'webp_create' => false]),
            new StaticImageProcessor($bucket),
            $source
        );

        $this->assertSame([], $this->getRecordedInserts());
        $this->assertCount(1, $updates);
        $this->assertSame('image_teaser.jpg', $updates[0]['values']['file_name']);
        $this->assertCount(1, $deletes);
        $this->assertSame(['id = ?', 56], $deletes[0]['where']);
    }

    public function testSectionCreateDerivativeUpdatesExistingRow(): void
    {
        $bucket = $this->createBucket();
        $derivatives = [];
        $updates = [];
        $deletes = [];
        Registry::getInstance()->add('db', $this->createDerivativeDb($derivatives, $updates, $deletes));
        $existing = $this->createPictureDerivative(57, 'old_section_teaser.jpg');
        $derivatives = [$existing];

        $section = new Section();
        $section->id = 2;
        $section->id_media_object = 123;
        $section->file_name = 'section.jpg';
        $source = $this->createSourceFile($bucket, 'section.jpg');

        $section->createDerivative(
            Config::create('teaser', ['max_width' => 100, 'max_height' => 80, 'webp_create' => false]),
            new StaticImageProcessor($bucket),
            $source
        );

        $this->assertSame([], $this->getRecordedInserts());
        $this->assertCount(1, $updates);
        $this->assertSame('section_teaser.jpg', $updates[0]['values']['file_name']);
        $this->assertSame([], $deletes);
    }

    public function testDocumentCreateDerivativeUpdatesExistingRow(): void
    {
        $bucket = $this->createBucket();
        $derivatives = [];
        $updates = [];
        $deletes = [];
        Registry::getInstance()->add('db', $this->createDerivativeDb($derivatives, $updates, $deletes));
        $existing = new DocumentDerivative();
        $existing->id = 58;
        $existing->id_document_media_object = 3;
        $existing->name = 'teaser';
        $existing->file_name = 'old_document_teaser.jpg';
        $existing->width = 50;
        $existing->height = 40;
        $derivatives = [$existing];

        $document = new DocumentMediaObject();
        $document->id = 3;
        $document->id_media_object = 123;
        $document->file_name = 'document.jpg';
        $source = $this->createSourceFile($bucket, 'document.jpg');

        $document->createDerivative(
            Config::create('teaser', ['max_width' => 100, 'max_height' => 80, 'webp_create' => false]),
            new StaticImageProcessor($bucket),
            $source
        );

        $this->assertSame([], $this->getRecordedInserts());
        $this->assertCount(1, $updates);
        $this->assertSame('document_teaser.jpg', $updates[0]['values']['file_name']);
        $this->assertSame([], $deletes);
    }

    private array $recordedInserts = [];

    private function createDerivativeDb(array &$derivatives, array &$updates, array &$deletes): AdapterInterface
    {
        $this->recordedInserts = [];
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('fetchAll')->willReturnCallback(static function () use (&$derivatives) {
            return $derivatives;
        });
        $adapter->method('fetchRow')->willReturn(null);
        $adapter->method('fetchOne')->willReturn(null);
        $adapter->method('getAffectedRows')->willReturn(0);
        $adapter->method('getTablePrefix')->willReturn('pmt2core_');
        $adapter->method('inTransaction')->willReturn(false);
        $adapter->method('execute')->willReturn(null);
        $adapter->method('insert')->willReturnCallback(function ($table, $values, $replace = false) {
            $this->recordedInserts[] = [
                'table' => $table,
                'values' => $values,
                'replace' => $replace,
            ];
            return 999;
        });
        $adapter->method('replace')->willReturn(null);
        $adapter->method('update')->willReturnCallback(static function ($table, $values, $where) use (&$updates) {
            $updates[] = [
                'table' => $table,
                'values' => $values,
                'where' => $where,
            ];
            return null;
        });
        $adapter->method('delete')->willReturnCallback(static function ($table, $where) use (&$deletes) {
            $deletes[] = [
                'table' => $table,
                'where' => $where,
            ];
            return null;
        });
        $adapter->method('truncate')->willReturn(null);
        $adapter->method('batchInsert')->willReturn(1);
        $adapter->method('beginTransaction')->willReturn(null);
        $adapter->method('commit')->willReturn(null);
        $adapter->method('rollback')->willReturn(null);
        return $adapter;
    }

    private function getRecordedInserts(): array
    {
        return $this->recordedInserts;
    }

    private function createBucket(): Bucket
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_derivative_creation_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'storage' => [
                    'provider' => 'filesystem',
                    'bucket' => $bucketPath,
                ],
            ],
        ]));
        return new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
    }

    private function createSourceFile(Bucket $bucket, string $name): File
    {
        $file = new File($bucket);
        $file->name = $name;
        $file->content = 'source';
        return $file;
    }

    private function createPictureDerivative(int $id, string $fileName): Derivative
    {
        $derivative = new Derivative();
        $derivative->id = $id;
        $derivative->id_image = 1;
        $derivative->id_media_object = 123;
        $derivative->name = 'teaser';
        $derivative->file_name = $fileName;
        $derivative->download_successful = true;
        $derivative->width = 50;
        $derivative->height = 40;
        return $derivative;
    }
}

class StaticImageProcessor implements ImageProcessorAdapterInterface
{
    public function __construct(private Bucket $bucket)
    {
    }

    public function process($config, $file, $derivativeName)
    {
        $derivative = new File($this->bucket);
        $derivative->name = pathinfo($file->name, PATHINFO_FILENAME) . '_' . $derivativeName . '.jpg';
        $derivative->content = 'derivative';
        return $derivative;
    }

    public function isImageCorrupted($file)
    {
        return false;
    }
}
