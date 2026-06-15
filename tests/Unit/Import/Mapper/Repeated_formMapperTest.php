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

    public function testMapAddsIbeTeaserColumnsFromConfiguredVarName(): void
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
                    'type' => 'ibe_teaser',
                    'label' => 'IBE Teaser',
                    'varName' => 'cta',
                    'template' => 'teaser',
                    'link' => '',
                    'sort' => 2,
                ],
            ],
            'values' => [
                (object) [
                    'id' => '1781515878851-6ezva1hf',
                    'sorting' => 1,
                    'validFrom' => '2026-06-15',
                    'validTo' => '2029-05-15',
                    'values' => (object) [
                        'text' => 'Titel',
                        'cta_template' => 'teaser-highlight',
                        'cta_link' => 'param1',
                    ],
                ],
            ],
        ];

        $result = $mapper->map(869750, 'de', 'auflistung_default', $object);

        $this->assertSame('2026-06-15 00:00:00', $result[0]->rows[0]->valid_from->format('Y-m-d H:i:s'));
        $this->assertSame('2029-05-15 00:00:00', $result[0]->rows[0]->valid_to->format('Y-m-d H:i:s'));
        $this->assertCount(3, $result[0]->rows[0]->columns);
        $this->assertSame('text', $result[0]->rows[0]->columns[0]->var_name);
        $this->assertSame('cta_template', $result[0]->rows[0]->columns[1]->var_name);
        $this->assertSame('IBE Teaser Template', $result[0]->rows[0]->columns[1]->title);
        $this->assertSame('teaser-highlight', $result[0]->rows[0]->columns[1]->value_string);
        $this->assertSame('cta_link', $result[0]->rows[0]->columns[2]->var_name);
        $this->assertSame('IBE Teaser Link', $result[0]->rows[0]->columns[2]->title);
        $this->assertSame('param1', $result[0]->rows[0]->columns[2]->value_string);
    }

    public function testMapUsesIbeTeaserDefinitionDefaultsWhenRowValuesMissing(): void
    {
        $mapper = new Repeated_form();
        $object = [
            'columns' => [
                (object) [
                    'type' => 'ibe_teaser',
                    'label' => 'IBE Teaser',
                    'varName' => 'teaser',
                    'template' => 'teaser',
                    'link' => '',
                    'sort' => 1,
                ],
            ],
            'values' => [
                (object) [
                    'sorting' => 1,
                    'values' => (object) [],
                ],
            ],
        ];

        $result = $mapper->map(42, 'de', 'auflistung_default', $object);

        $this->assertCount(2, $result[0]->rows[0]->columns);
        $this->assertSame('teaser_template', $result[0]->rows[0]->columns[0]->var_name);
        $this->assertSame('teaser', $result[0]->rows[0]->columns[0]->value_string);
        $this->assertSame('teaser_link', $result[0]->rows[0]->columns[1]->var_name);
        $this->assertNull($result[0]->rows[0]->columns[1]->value_string);
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
