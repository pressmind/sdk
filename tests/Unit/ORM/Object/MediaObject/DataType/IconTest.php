<?php

namespace Pressmind\Tests\Unit\ORM\Object\MediaObject\DataType;

use Pressmind\ORM\Object\MediaObject\DataType\Icon;
use Pressmind\Tests\Unit\AbstractTestCase;

class IconTest extends AbstractTestCase
{
    public function testDefinesCompleteIconContractAndRoundTripsVariants(): void
    {
        $icon = new Icon();
        foreach (['id_icon', 'name', 'slug', 'url', 'mime', 'style', 'variants'] as $property) {
            $this->assertTrue($icon->hasProperty($property));
        }

        $icon->variants = [
            ['style' => 'solid', 'url' => 'https://example.test/ship.svg', 'mime' => 'image/svg+xml'],
        ];
        $serialized = $icon->toStdClass(false);

        $this->assertSame('solid', $serialized->variants[0]['style']);
    }
}
