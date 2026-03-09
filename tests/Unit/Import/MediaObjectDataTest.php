<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\MediaObjectData;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class MediaObjectDataTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $data = (object) [
            'id_media_objects_data_type' => 10,
            'data' => [],
        ];
        $import = new MediaObjectData($data, 123, 'full', true);
        $this->assertInstanceOf(MediaObjectData::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithEmptyDataReturnsStructure(): void
    {
        $config = $this->createMockConfig([
            'data' => [
                'languages' => ['default' => 'de', 'allowed' => ['de']],
                'media_types' => [],
                'sections' => ['replace' => []],
            ],
        ]);
        Registry::getInstance()->add('config', $config);
        $data = (object) ['id_media_objects_data_type' => 10, 'data' => []];
        $import = new MediaObjectData($data, 123, 'full', true);
        $result = $import->import();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('linked_media_object_ids', $result);
        $this->assertArrayHasKey('category_tree_ids', $result);
        $this->assertArrayHasKey('languages', $result);
        $this->assertSame([], $result['linked_media_object_ids']);
        $this->assertSame([], $result['category_tree_ids']);
    }
}
