<?php

namespace Pressmind\Tests\Unit\Import\Mapper;

use Pressmind\Import\Mapper\Location;
use Pressmind\Tests\Unit\AbstractTestCase;

class LocationMapperTest extends AbstractTestCase
{
    public function testMapReturnsEmptyArrayWhenNotArray(): void
    {
        $mapper = new Location();
        $result = $mapper->map(1, 'de', 'var', (object) []);
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testMapReturnsMappedLocations(): void
    {
        $mapper = new Location();
        $input = [
            (object) [
                'lat' => 52.52,
                'lng' => 13.405,
                'address' => 'Berlin, Germany',
                'title' => 'Office',
            ],
        ];
        $result = $mapper->map(5, 'en', 'locations', $input);
        $this->assertCount(1, $result);
        $mapped = $result[0];
        $this->assertSame(5, $mapped->id_media_object);
        $this->assertSame('en', $mapped->language);
        $this->assertSame('locations', $mapped->var_name);
        $this->assertSame(52.52, $mapped->lat);
        $this->assertSame('Berlin, Germany', $mapped->address);
        $this->assertSame('Office', $mapped->title);
    }
}
