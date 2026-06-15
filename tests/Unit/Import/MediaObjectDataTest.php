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

    public function testExtractSectionValueReturnsRepeatedFormSectionPayload(): void
    {
        $data = (object) [
            'id_media_objects_data_type' => 10,
            'data' => [],
        ];
        $import = new MediaObjectData($data, 123, 'full', true);
        $field = (object) [
            'type' => 'repeated_form',
            'repeated_form' => (object) [
                'fields' => [
                    (object) ['sort' => 1, 'label' => 'Text', 'varName' => 'text'],
                ],
                'ibe_teaser' => [
                    (object) [
                        'id' => 'ibe-teaser-field',
                        'sort' => 2,
                        'type' => 'ibe_teaser',
                        'label' => 'IBE Teaser',
                        'varName' => 'cta',
                        'template' => 'teaser',
                        'link' => '',
                    ],
                ],
            ],
            'value' => (object) [
                'default-section' => [
                    (object) [
                        'sorting' => 1,
                        'values' => (object) ['text' => 'Titel'],
                    ],
                ],
            ],
        ];

        $method = new \ReflectionMethod(MediaObjectData::class, 'extractSectionValue');
        $method->setAccessible(true);
        $result = $method->invoke($import, $field, 'default-section');

        $this->assertIsArray($result);
        $this->assertCount(2, $result['columns']);
        $this->assertSame('text', $result['columns'][0]->varName);
        $this->assertSame('cta', $result['columns'][1]->varName);
        $this->assertSame('ibe_teaser', $result['columns'][1]->type);
        $this->assertSame($field->value->{'default-section'}, $result['values']);
    }

    public function testExtractSectionValueKeepsKeyValueSectionPayload(): void
    {
        $import = new MediaObjectData((object) ['id_media_objects_data_type' => 10, 'data' => []], 123, 'full', true);
        $field = (object) [
            'type' => 'key_value',
            'columns' => [
                (object) ['sort' => 0, 'name' => 'Headline', 'var_name' => 'headline'],
            ],
            'value' => (object) [
                'default-section' => [
                    (object) ['sort' => 0, 'value_0_string' => 'Tag 1'],
                ],
            ],
        ];

        $result = $this->invokeExtractSectionValue($import, $field, 'default-section');

        $this->assertSame($field->columns, $result['columns']);
        $this->assertSame($field->value->{'default-section'}, $result['values']);
    }

    public function testExtractSectionValueKeepsCategorytreePayloadAndAddsObjectType(): void
    {
        $data = (object) [
            'id_media_objects_data_type' => 607,
            'data' => [],
        ];
        $import = new MediaObjectData($data, 123, 'full', true);
        $field = (object) [
            'type' => 'categorytree',
            'value' => (object) ['id_category' => 99],
        ];

        $result = $this->invokeExtractSectionValue($import, $field, 'default-section');

        $this->assertSame($field->value, $result);
        $this->assertSame(607, $result->id_object_type);
    }

    public function testExtractSectionValueKeepsPlainSectionValue(): void
    {
        $import = new MediaObjectData((object) ['id_media_objects_data_type' => 10, 'data' => []], 123, 'full', true);
        $field = (object) [
            'type' => 'text',
            'value' => (object) [
                'default-section' => 'Plain text',
            ],
        ];

        $this->assertSame('Plain text', $this->invokeExtractSectionValue($import, $field, 'default-section'));
    }

    public function testExtractSectionValueReturnsNullForMissingSection(): void
    {
        $import = new MediaObjectData((object) ['id_media_objects_data_type' => 10, 'data' => []], 123, 'full', true);
        $field = (object) [
            'type' => 'text',
            'value' => (object) [],
        ];

        $this->assertNull($this->invokeExtractSectionValue($import, $field, 'default-section'));
    }

    private function invokeExtractSectionValue(MediaObjectData $import, $field, string $sectionId)
    {
        $method = new \ReflectionMethod(MediaObjectData::class, 'extractSectionValue');
        $method->setAccessible(true);

        return $method->invoke($import, $field, $sectionId);
    }
}
