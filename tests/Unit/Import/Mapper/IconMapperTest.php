<?php

namespace Pressmind\Tests\Unit\Import\Mapper;

use Pressmind\Import\Mapper\Icon;
use Pressmind\Tests\Unit\AbstractTestCase;

class IconMapperTest extends AbstractTestCase
{
    public function testMapReturnsEmptyArrayForNull(): void
    {
        $this->assertSame([], (new Icon())->map(1, 'de', 'symbol', null));
    }

    public function testMapPreservesCompleteIconContract(): void
    {
        $icon = (object) [
            'id' => 'ship',
            'name' => 'Ship',
            'slug' => 'ship',
            'url' => 'https://example.test/ship.svg',
            'mime' => 'image/svg+xml',
            'style' => 'regular',
            'variants' => [
                (object) [
                    'style' => 'solid',
                    'url' => 'https://example.test/ship-solid.svg',
                    'mime' => 'image/svg+xml',
                ],
            ],
        ];

        $result = (new Icon())->map(42, 'de', 'symbol', $icon);

        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]->id_media_object);
        $this->assertSame('de', $result[0]->language);
        $this->assertSame('symbol', $result[0]->var_name);
        $this->assertSame('ship', $result[0]->id_icon);
        $this->assertSame('Ship', $result[0]->name);
        $this->assertSame('ship', $result[0]->slug);
        $this->assertSame('https://example.test/ship.svg', $result[0]->url);
        $this->assertSame('image/svg+xml', $result[0]->mime);
        $this->assertSame('regular', $result[0]->style);
        $this->assertSame('solid', $result[0]->variants[0]['style']);
    }
}
