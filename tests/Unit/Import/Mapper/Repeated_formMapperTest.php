<?php

namespace Pressmind\Tests\Unit\Import\Mapper;

use Pressmind\Import\Mapper\Repeated_form;
use Pressmind\Tests\Unit\AbstractTestCase;

class Repeated_formMapperTest extends AbstractTestCase
{
    public function testMapReturnsEmptyWhenObjectNull(): void
    {
        $mapper = new Repeated_form();
        $result = $mapper->map(1, 'de', 'var', null);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testMapReturnsEmptyWhenValuesEmpty(): void
    {
        $mapper = new Repeated_form();
        $object = [
            'columns' => [],
            'values' => [],
        ];

        $result = $mapper->map(1, 'de', 'var', $object);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testMapReturnsMappedRowsForNestedRepeatedFormTextValues(): void
    {
        $mapper = new Repeated_form();
        $object = [
            'columns' => [
                (object) ['sort' => 0, 'name' => 'Headline', 'var_name' => 'headline'],
                (object) ['sort' => 1, 'name' => 'Description', 'var_name' => 'description'],
            ],
            'values' => [
                (object) ['sort' => 0, 'value_0_string' => 'Tag 1', 'value_1_string' => 'Anreise'],
                (object) ['sort' => 1, 'value_0_string' => 'Tag 2', 'value_1_string' => 'Ausflug'],
            ],
        ];

        $result = $mapper->map(42, 'de', 'programm_default', $object);

        $this->assertCount(1, $result);
        $this->assertSame(42, $result[0]->id_media_object);
        $this->assertSame('de', $result[0]->language);
        $this->assertSame('programm_default', $result[0]->var_name);
        $this->assertCount(2, $result[0]->rows);
        $this->assertSame('Tag 1', $result[0]->rows[0]->columns[0]->value_string);
        $this->assertSame('Anreise', $result[0]->rows[0]->columns[1]->value_string);
        $this->assertSame('string', $result[0]->rows[0]->columns[1]->datatype);
    }

    public function testMapReturnsMappedRowsForWebcoreRepeatedFormPayload(): void
    {
        $mapper = new Repeated_form();
        $object = [
            'columns' => [
                (object) [
                    'type' => 'input_text',
                    'label' => 'Text',
                    'varName' => 'text',
                    'sort' => 1,
                ],
                (object) [
                    'type' => 'tinymce',
                    'label' => 'Inhalt',
                    'varName' => 'inhalt',
                    'sort' => 2,
                ],
            ],
            'values' => [
                (object) [
                    'id' => '1779275848944-4tfmcprf',
                    'sorting' => 1,
                    'values' => (object) [
                        'text' => 'Titel',
                        'inhalt' => '<p>Content</p>',
                    ],
                ],
            ],
        ];

        $result = $mapper->map(869750, 'de', 'auflistung_default', $object);

        $this->assertCount(1, $result);
        $this->assertSame(869750, $result[0]->id_media_object);
        $this->assertSame('auflistung_default', $result[0]->var_name);
        $this->assertCount(1, $result[0]->rows);
        $this->assertSame(1, $result[0]->rows[0]->sort);
        $this->assertCount(2, $result[0]->rows[0]->columns);
        $this->assertSame('Text', $result[0]->rows[0]->columns[0]->title);
        $this->assertSame('text', $result[0]->rows[0]->columns[0]->var_name);
        $this->assertSame('Titel', $result[0]->rows[0]->columns[0]->value_string);
        $this->assertSame('Inhalt', $result[0]->rows[0]->columns[1]->title);
        $this->assertSame('inhalt', $result[0]->rows[0]->columns[1]->var_name);
        $this->assertSame('<p>Content</p>', $result[0]->rows[0]->columns[1]->value_string);
        $this->assertSame('string', $result[0]->rows[0]->columns[1]->datatype);
    }

    public function testMapIgnoresUnsupportedNonScalarValues(): void
    {
        $mapper = new Repeated_form();
        $object = [
            'columns' => [
                (object) ['sort' => 1, 'label' => 'Bild', 'varName' => 'bild'],
                (object) ['sort' => 2, 'label' => 'Meta', 'varName' => 'meta'],
            ],
            'values' => [
                (object) [
                    'sorting' => 1,
                    'values' => (object) [
                        'bild' => ['id' => 123],
                        'meta' => (object) ['id' => 456],
                    ],
                ],
            ],
        ];

        $result = $mapper->map(42, 'de', 'bilder_default', $object);

        $this->assertNull($result[0]->rows[0]->columns[0]->value_string);
        $this->assertNull($result[0]->rows[0]->columns[1]->value_string);
        $this->assertSame('string', $result[0]->rows[0]->columns[0]->datatype);
    }

    public function testMapNormalizesObjectValueCollections(): void
    {
        $mapper = new Repeated_form();
        $values = new \stdClass();
        $values->first = (object) ['sort' => 0, 'value_0_string' => 'Erste Zeile'];
        $values->second = (object) ['sort' => 1, 'value_0_string' => 'Zweite Zeile'];

        $object = [
            'columns' => [
                (object) ['sort' => 0, 'name' => 'Text', 'var_name' => 'text'],
            ],
            'values' => $values,
        ];

        $result = $mapper->map(7, 'de', 'text_default', $object);

        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]->rows);
        $this->assertSame('Zweite Zeile', $result[0]->rows[1]->columns[0]->value_string);
    }
}
