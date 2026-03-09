<?php

namespace Pressmind\Tests\Unit\ORM\Object\MediaObject\DataType;

use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\ORM\Object\MediaObject\DataType\Picture\Derivative;
use Pressmind\Registry;
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
}
