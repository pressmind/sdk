<?php

namespace Pressmind\Tests\Unit\Image;

use Pressmind\Image\DerivativeCompleteness;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Derivative;
use Pressmind\Storage\Bucket;
use Pressmind\Storage\File;
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
        $this->writeFile($bucket, 'image_teaser.webp', 'webp');
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
        $this->writeFile($bucket, 'image_teaser.webp', 'webp');
        $picture = $this->createPictureWithDerivatives([
            $this->createDerivative('teaser', 'image_teaser.jpg'),
            $this->createDerivative('teaser', 'image_teaser_duplicate.jpg'),
        ]);

        $result = DerivativeCompleteness::check($picture, $this->configWithDerivatives(), $bucket);

        $this->assertFalse($result->isComplete());
        $this->assertContains('teaser', $result->duplicateDerivativeNames);
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
}
