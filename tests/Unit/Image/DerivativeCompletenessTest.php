<?php

namespace Pressmind\Tests\Unit\Image;

use Exception;
use Pressmind\Image\DerivativeCompleteness;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Derivative;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
use Pressmind\Storage\ProviderInterface;
use Pressmind\Tests\Unit\AbstractTestCase;

class DerivativeCompletenessTest extends AbstractTestCase
{
    public function testReportsMissingWebpSidecarAsIncomplete(): void
    {
        $bucket = $this->createBucket();
        $this->writeFile($bucket, 'image_teaser.jpg', 'jpg');
        $picture = $this->createPictureWithDerivatives([
            $this->createDerivative('teaser', 'image_teaser.jpg'),
        ]);

        $result = DerivativeCompleteness::check($picture, $this->configWithDerivatives(), $bucket);

        $this->assertFalse($result->isComplete());
        $this->assertContains('image_teaser.webp', $result->missingKeys);
    }

    public function testAcceptsPngMainDerivativeWithoutExpectingJpgAlternative(): void
    {
        $bucket = $this->createBucket();
        $this->writeFile($bucket, 'image_teaser.png', 'png');
        $this->writeValidWebpFile($bucket, 'image_teaser.webp');
        $picture = $this->createPictureWithDerivatives([
            $this->createDerivative('teaser', 'image_teaser.png'),
        ]);

        $result = DerivativeCompleteness::check($picture, $this->configWithDerivatives(), $bucket);

        $this->assertTrue($result->isComplete());
        $this->assertNotContains('image_teaser.jpg', $result->missingKeys);
    }

    public function testDuplicateDerivativeRowsAreIncomplete(): void
    {
        $bucket = $this->createBucket();
        $this->writeFile($bucket, 'image_teaser.jpg', 'jpg');
        $this->writeValidWebpFile($bucket, 'image_teaser.webp');
        $picture = $this->createPictureWithDerivatives([
            $this->createDerivative('teaser', 'image_teaser.jpg'),
            $this->createDerivative('teaser', 'image_teaser_duplicate.jpg'),
        ]);

        $result = DerivativeCompleteness::check($picture, $this->configWithDerivatives(), $bucket);

        $this->assertFalse($result->isComplete());
        $this->assertContains('teaser', $result->duplicateDerivativeNames);
    }

    public function testFilesizeErrorsAreIncomplete(): void
    {
        $bucket = $this->createBucketWithProviderThatCannotReadSize();
        $picture = $this->createPictureWithDerivatives([
            $this->createDerivative('teaser', 'image_teaser.jpg'),
        ]);

        $result = DerivativeCompleteness::check($picture, $this->configWithoutWebp(), $bucket);

        $this->assertFalse($result->isComplete());
        $this->assertContains('image_teaser.jpg', $result->missingKeys);
    }

    public function testCorruptWebpSidecarIsIncomplete(): void
    {
        $bucket = $this->createBucket();
        $this->writeFile($bucket, 'image_teaser.jpg', 'jpg');
        $this->writeFile($bucket, 'image_teaser.webp', 'broken webp');
        $picture = $this->createPictureWithDerivatives([
            $this->createDerivative('teaser', 'image_teaser.jpg'),
        ]);

        $result = DerivativeCompleteness::check($picture, $this->configWithDerivatives(), $bucket);

        $this->assertFalse($result->isComplete());
        $this->assertContains('image_teaser.webp', $result->missingKeys);
    }

    public function testWebpSidecarKeepsDerivativeSubdirectory(): void
    {
        $bucket = $this->createBucket();
        $this->writeFile($bucket, 'subdir/image_teaser.jpg', 'jpg');
        $this->writeValidWebpFile($bucket, 'subdir/image_teaser.webp');
        $picture = $this->createPictureWithDerivatives([
            $this->createDerivative('teaser', 'subdir/image_teaser.jpg'),
        ]);

        $result = DerivativeCompleteness::check($picture, $this->configWithDerivatives(), $bucket);

        $this->assertTrue($result->isComplete());
        $this->assertNotContains('image_teaser.webp', $result->missingKeys);
        $this->assertNotContains('subdir/image_teaser.webp', $result->missingKeys);
    }

    private function createBucket(): Bucket
    {
        $bucketPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pm_derivative_completeness_' . uniqid('', true);
        mkdir($bucketPath, 0755, true);
        return new Bucket([
            'provider' => 'filesystem',
            'bucket' => $bucketPath,
        ]);
    }

    private function writeFile(Bucket $bucket, string $name, string $content): void
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

        $this->writeFile($bucket, $name, $content);
    }

    /**
     * @param Derivative[] $derivatives
     */
    private function createPictureWithDerivatives(array $derivatives): Picture
    {
        $picture = new Picture();
        $picture->id = 1;
        $picture->id_media_object = 123;
        $picture->file_name = 'image.jpg';
        $picture->download_successful = true;
        $picture->derivatives = $derivatives;
        return $picture;
    }

    private function createDerivative(string $name, string $fileName): Derivative
    {
        $derivative = new Derivative();
        $derivative->id = random_int(1, 999999);
        $derivative->id_image = 1;
        $derivative->id_media_object = 123;
        $derivative->name = $name;
        $derivative->file_name = $fileName;
        $derivative->download_successful = true;
        return $derivative;
    }

    private function configWithDerivatives(): array
    {
        return [
            'image_handling' => [
                'processor' => [
                    'derivatives' => [
                        'teaser' => [
                            'webp_create' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function configWithoutWebp(): array
    {
        return [
            'image_handling' => [
                'processor' => [
                    'derivatives' => [
                        'teaser' => [
                            'webp_create' => false,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function createBucketWithProviderThatCannotReadSize(): Bucket
    {
        $bucket = $this->createBucket();
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('fileExists')->willReturn(true);
        $provider->method('filesize')->willThrowException(new Exception('size unavailable'));

        $reflection = new \ReflectionProperty(Bucket::class, '_provider');
        $reflection->setAccessible(true);
        $reflection->setValue($bucket, $provider);

        return $bucket;
    }
}
