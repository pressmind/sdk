<?php

namespace Pressmind\Tests\Unit\ORM\Object\MediaObject\DataType;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Derivative;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Section;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject;
use Pressmind\ORM\Object\Itinerary\Step\DocumentMediaObject\Derivative as DocumentDerivative;
use Pressmind\Registry;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Tests\Unit\AbstractTestCase;

class PictureTest extends AbstractTestCase
{
    private function getImageConfig(): array
    {
        return [
            'image_handling' => [
                'processor' => [
                    'derivatives' => [
                        'thumbnail' => ['max_width' => 200, 'max_height' => 150],
                        'large' => ['max_width' => 1200, 'max_height' => 800],
                    ],
                    'webp_support' => false,
                ],
                'storage' => ['provider' => 'filesystem', 'bucket' => '/tmp/test'],
                'http_src' => '/images',
            ],
        ];
    }

    private function createPictureWithImageConfig(): Picture
    {
        $config = $this->createMockConfig($this->getImageConfig());
        Registry::getInstance()->add('config', $config);
        return new Picture();
    }

    public function testGetSizesReturnsHtmlAttributesWithWidthAndHeight(): void
    {
        $picture = $this->createPictureWithImageConfig();

        $result = $picture->getSizes('thumbnail', true);

        $this->assertIsString($result);
        $this->assertStringContainsString('width="200"', $result);
        $this->assertStringContainsString('height="150"', $result);
    }

    public function testGetSizesReturnsHtmlAttributesForDifferentDerivative(): void
    {
        $picture = $this->createPictureWithImageConfig();

        $result = $picture->getSizes('large', true);

        $this->assertStringContainsString('width="1200"', $result);
        $this->assertStringContainsString('height="800"', $result);
    }

    public function testGetSizesReturnsArrayWhenHtmlAttributesDisabled(): void
    {
        $picture = $this->createPictureWithImageConfig();

        $result = $picture->getSizes('thumbnail', false);

        $this->assertIsArray($result);
        $this->assertSame(200, $result['width']);
        $this->assertSame(150, $result['height']);
    }

    public function testGetSizesReturnsEmptyStringForUnknownDerivative(): void
    {
        $picture = $this->createPictureWithImageConfig();

        $result = $picture->getSizes('nonexistent', true);

        $this->assertSame('', $result);
    }

    public function testGetSizesReturnsEmptyArrayForUnknownDerivative(): void
    {
        $picture = $this->createPictureWithImageConfig();

        $result = $picture->getSizes('nonexistent', false);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetSizesReturnsEmptyWhenNoImageHandlingConfig(): void
    {
        $picture = new Picture();

        $result = $picture->getSizes('thumbnail', true);

        $this->assertSame('', $result);
    }

    public function testGetSizesOnlyWidthWhenHeightMissing(): void
    {
        $config = $this->createMockConfig([
            'image_handling' => [
                'processor' => [
                    'derivatives' => [
                        'wide' => ['max_width' => 500],
                    ],
                ],
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        $picture = new Picture();

        $resultStr = $picture->getSizes('wide', true);
        $resultArr = $picture->getSizes('wide', false);

        $this->assertStringContainsString('width="500"', $resultStr);
        $this->assertStringNotContainsString('height=', $resultStr);
        $this->assertArrayHasKey('width', $resultArr);
        $this->assertArrayNotHasKey('height', $resultArr);
    }

    public function testRemoveDerivativesCallsDeleteOnEachDerivative(): void
    {
        $picture = $this->createPictureWithImageConfig();

        $derivative1 = $this->createMock(Derivative::class);
        $derivative1->expects($this->once())->method('delete');

        $derivative2 = $this->createMock(Derivative::class);
        $derivative2->expects($this->once())->method('delete');

        $picture->derivatives = [$derivative1, $derivative2];

        $picture->removeDerivatives();
    }

    public function testRemoveDerivativesWithEmptyArray(): void
    {
        $picture = $this->createPictureWithImageConfig();
        $picture->derivatives = [];

        $picture->removeDerivatives();

        $this->assertTrue(true);
    }

    public function testGetUriUsesExistingPictureDerivativeWhileImageIsMarkedForReprocessing(): void
    {
        $picture = $this->createPictureWithImageConfig();
        $picture->id = 1;
        $picture->file_name = 'image.jpg';
        $picture->tmp_url = 'https://remote.example/image.jpg?v=original';
        $picture->download_successful = false;
        $picture->derivatives = [$this->createPictureDerivative('thumbnail', 'image_thumbnail.jpg')];

        $this->assertSame('/images/image_thumbnail.jpg', $picture->getUri('thumbnail'));
    }

    public function testGetUriUsesExistingSectionDerivativeWhileSectionIsMarkedForReprocessing(): void
    {
        $section = new Section();
        Registry::getInstance()->add('config', $this->createMockConfig($this->getImageConfig()));
        $section->id = 2;
        $section->file_name = 'section.jpg';
        $section->tmp_url = 'https://remote.example/section.jpg?v=original';
        $section->download_successful = false;
        $section->derivatives = [$this->createSectionDerivative('thumbnail', 'section_thumbnail.jpg')];

        $this->assertSame('/images/section_thumbnail.jpg', $section->getUri('thumbnail'));
    }

    public function testGetUriUsesExistingDocumentDerivativeWhileDocumentIsMarkedForReprocessing(): void
    {
        $document = new DocumentMediaObject();
        Registry::getInstance()->add('config', $this->createMockConfig($this->getImageConfig()));
        $document->id = 3;
        $document->file_name = 'document.jpg';
        $document->tmp_url = 'https://remote.example/document.jpg?v=original';
        $document->download_successful = false;
        $document->derivatives = [$this->createDocumentDerivative('thumbnail', 'document_thumbnail.jpg')];

        $this->assertSame('/images/document_thumbnail.jpg', $document->getUri('thumbnail'));
    }

    public function testGetUriTreatsNullForceWebpAsFalseForPictureDerivativeWithSectionName(): void
    {
        $picture = $this->createPictureWithImageConfig();
        $picture->id = 1;
        $picture->file_name = 'image.jpg';
        $picture->tmp_url = 'https://remote.example/image.jpg?v=original';
        $picture->download_successful = true;
        $picture->sections = [];
        $picture->derivatives = [$this->createPictureDerivative('thumbnail', 'image_thumbnail.jpg')];

        $this->assertSame('/images/image_thumbnail.jpg', $picture->getUri('thumbnail', null, 'base'));
    }

    public function testGetUriTreatsNullForceWebpAsFalseWhenDelegatingToSection(): void
    {
        $picture = $this->createPictureWithImageConfig();
        $picture->id = 1;
        $picture->file_name = 'image.jpg';
        $picture->tmp_url = 'https://remote.example/image.jpg?v=original';
        $picture->download_successful = true;

        $section = new Section();
        $section->id = 2;
        $section->section_name = 'base';
        $section->file_name = 'section.jpg';
        $section->tmp_url = 'https://remote.example/section.jpg?v=original';
        $section->download_successful = true;
        $section->derivatives = [$this->createSectionDerivative('thumbnail', 'section_thumbnail.jpg')];

        $picture->sections = [$section];

        $this->assertSame('/images/section_thumbnail.jpg', $picture->getUri('thumbnail', null, 'base'));
    }

    public function testGetUriTreatsNullForceWebpAsFalseForFallbackDerivative(): void
    {
        $picture = $this->createPictureWithImageConfig();
        $picture->file_name = 'image.jpg';
        $picture->tmp_url = 'https://remote.example/image.jpg?v=original';
        $picture->download_successful = true;
        $picture->sections = [];
        $picture->derivatives = [$this->createPictureDerivative('fallback', 'image_fallback.jpg')];

        $this->assertSame('/images/image_fallback.jpg', $picture->getUri('missing', null, null));
    }

    public function testGetUriTreatsNullForceWebpAsFalseForDocumentDerivative(): void
    {
        $document = new DocumentMediaObject();
        Registry::getInstance()->add('config', $this->createMockConfig($this->getImageConfig()));
        $document->id = 3;
        $document->file_name = 'document.jpg';
        $document->tmp_url = 'https://remote.example/document.jpg?v=original';
        $document->download_successful = true;
        $document->derivatives = [$this->createDocumentDerivative('thumbnail', 'document_thumbnail.jpg')];

        $this->assertSame('/images/document_thumbnail.jpg', $document->getUri('thumbnail', null, null));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetUriKeepsJpgWhenAutomaticWebpSidecarIsMissing(): void
    {
        define('WEBP_SUPPORT', true);
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_picture_uri_webp_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'processor' => [
                    'derivatives' => [
                        'thumbnail' => ['max_width' => 200, 'max_height' => 150, 'webp_create' => true],
                    ],
                    'webp_support' => true,
                ],
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'http_src' => '/images',
            ],
        ]));
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeStorageFile($bucket, 'image_thumbnail.jpg', 'jpg');

        $picture = new Picture();
        $picture->id = 1;
        $picture->file_name = 'image.jpg';
        $picture->tmp_url = 'https://remote.example/image.jpg?v=original';
        $picture->download_successful = true;
        $picture->derivatives = [$this->createPictureDerivative('thumbnail', 'image_thumbnail.jpg')];

        $this->assertSame('/images/image_thumbnail.jpg', $picture->getUri('thumbnail'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetUriKeepsJpgWhenImageIsMarkedForReprocessingAndAutomaticWebpIsMissing(): void
    {
        define('WEBP_SUPPORT', true);
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_picture_uri_webp_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'processor' => [
                    'derivatives' => [
                        'thumbnail' => ['max_width' => 200, 'max_height' => 150, 'webp_create' => true],
                    ],
                    'webp_support' => true,
                ],
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'http_src' => '/images',
            ],
        ]));
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeStorageFile($bucket, 'image_thumbnail.jpg', 'jpg');

        $picture = new Picture();
        $picture->id = 1;
        $picture->file_name = 'image.jpg';
        $picture->tmp_url = 'https://remote.example/image.jpg?v=original';
        $picture->download_successful = false;
        $picture->derivatives = [$this->createPictureDerivative('thumbnail', 'image_thumbnail.jpg')];

        $this->assertSame('/images/image_thumbnail.jpg', $picture->getUri('thumbnail'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetUriUsesWebpWhenAutomaticWebpSidecarExists(): void
    {
        define('WEBP_SUPPORT', true);
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_picture_uri_webp_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'processor' => [
                    'derivatives' => [
                        'thumbnail' => ['max_width' => 200, 'max_height' => 150, 'webp_create' => true],
                    ],
                    'webp_support' => true,
                ],
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'http_src' => '/images',
            ],
        ]));
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeStorageFile($bucket, 'image_thumbnail.jpg', 'jpg');
        $this->writeValidWebpFile($bucket, 'image_thumbnail.webp');

        $picture = new Picture();
        $picture->id = 1;
        $picture->file_name = 'image.jpg';
        $picture->tmp_url = 'https://remote.example/image.jpg?v=original';
        $picture->download_successful = true;
        $picture->derivatives = [$this->createPictureDerivative('thumbnail', 'image_thumbnail.jpg')];

        $this->assertSame('/images/image_thumbnail.webp', $picture->getUri('thumbnail'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetUriKeepsJpgWhenAutomaticWebpSidecarIsCorrupt(): void
    {
        define('WEBP_SUPPORT', true);
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_picture_uri_webp_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'processor' => [
                    'derivatives' => [
                        'thumbnail' => ['max_width' => 200, 'max_height' => 150, 'webp_create' => true],
                    ],
                    'webp_support' => true,
                ],
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'http_src' => '/images',
            ],
        ]));
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeStorageFile($bucket, 'image_thumbnail.jpg', 'jpg');
        $this->writeStorageFile($bucket, 'image_thumbnail.webp', 'broken webp');

        $picture = new Picture();
        $picture->id = 1;
        $picture->file_name = 'image.jpg';
        $picture->tmp_url = 'https://remote.example/image.jpg?v=original';
        $picture->download_successful = true;
        $picture->derivatives = [$this->createPictureDerivative('thumbnail', 'image_thumbnail.jpg')];

        $this->assertSame('/images/image_thumbnail.jpg', $picture->getUri('thumbnail'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetUriForceWebpStillFallsBackWhenWebpSidecarIsMissing(): void
    {
        define('WEBP_SUPPORT', true);
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_picture_uri_webp_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'processor' => [
                    'derivatives' => [
                        'thumbnail' => ['max_width' => 200, 'max_height' => 150, 'webp_create' => false],
                    ],
                    'webp_support' => false,
                ],
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'http_src' => '/images',
            ],
        ]));
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeStorageFile($bucket, 'image_thumbnail.jpg', 'jpg');

        $picture = new Picture();
        $picture->id = 1;
        $picture->file_name = 'image.jpg';
        $picture->tmp_url = 'https://remote.example/image.jpg?v=original';
        $picture->download_successful = true;
        $picture->derivatives = [$this->createPictureDerivative('thumbnail', 'image_thumbnail.jpg')];

        $this->assertSame('/images/image_thumbnail.jpg', $picture->getUri('thumbnail', true));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetUriUsesWebpSidecarInDerivativeSubdirectory(): void
    {
        define('WEBP_SUPPORT', true);
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_picture_uri_webp_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        Registry::getInstance()->add('config', $this->createMockConfig([
            'image_handling' => [
                'processor' => [
                    'derivatives' => [
                        'thumbnail' => ['max_width' => 200, 'max_height' => 150, 'webp_create' => true],
                    ],
                    'webp_support' => true,
                ],
                'storage' => ['provider' => 'filesystem', 'bucket' => $bucketPath],
                'http_src' => '/images',
            ],
        ]));
        $bucket = new Bucket(['provider' => 'filesystem', 'bucket' => $bucketPath]);
        $this->writeStorageFile($bucket, 'subdir/image_thumbnail.jpg', 'jpg');
        $this->writeValidWebpFile($bucket, 'subdir/image_thumbnail.webp');

        $picture = new Picture();
        $picture->id = 1;
        $picture->file_name = 'subdir/image.jpg';
        $picture->tmp_url = 'https://remote.example/subdir/image.jpg?v=original';
        $picture->download_successful = true;
        $picture->derivatives = [$this->createPictureDerivative('thumbnail', 'subdir/image_thumbnail.jpg')];

        $this->assertSame('/images/subdir/image_thumbnail.webp', $picture->getUri('thumbnail'));
    }

    private function createPictureDerivative(string $name, string $fileName): Derivative
    {
        $derivative = new Derivative();
        $derivative->id = random_int(1, 999999);
        $derivative->id_image = 1;
        $derivative->name = $name;
        $derivative->file_name = $fileName;
        $derivative->download_successful = true;
        return $derivative;
    }

    private function createSectionDerivative(string $name, string $fileName): Derivative
    {
        $derivative = new Derivative();
        $derivative->id = random_int(1, 999999);
        $derivative->id_image_section = 2;
        $derivative->name = $name;
        $derivative->file_name = $fileName;
        $derivative->download_successful = true;
        return $derivative;
    }

    private function createDocumentDerivative(string $name, string $fileName): DocumentDerivative
    {
        $derivative = new DocumentDerivative();
        $derivative->id = random_int(1, 999999);
        $derivative->id_document_media_object = 3;
        $derivative->name = $name;
        $derivative->file_name = $fileName;
        return $derivative;
    }

    private function writeStorageFile(Bucket $bucket, string $name, string $content): void
    {
        $file = new File($bucket);
        $file->name = $name;
        $file->content = $content;
        $file->save();
    }

    private function writeValidWebpFile(Bucket $bucket, string $name): void
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagewebp($image);
        $content = ob_get_clean();
        imagedestroy($image);

        $this->writeStorageFile($bucket, $name, $content);
    }
}
