<?php

namespace Pressmind\Tests\Unit\Search\OpenSearch;

use Pressmind\Search\OpenSearch\Indexer;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Unit tests for Search\OpenSearch\Indexer: getFields (pure logic with config).
 * No real OpenSearch client or MediaObject.
 */
class IndexerTest extends AbstractTestCase
{
    private function createIndexerStub(): Indexer
    {
        $stub = $this->getMockBuilder(Indexer::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionClass(Indexer::class);
        $config = [
            'uri' => 'https://localhost:9200',
            'index' => [
                'fulltext' => [
                    'object_type_mapping' => [
                        100 => [
                            ['language' => 'de', 'field' => ['name' => 'fulltext_de']],
                            ['language' => 'en', 'field' => ['name' => 'fulltext_en']],
                        ],
                    ],
                ],
                'code' => [
                    'object_type_mapping' => [
                        100 => [['language' => null, 'field' => ['name' => 'code']]],
                    ],
                ],
            ],
        ];
        $configProp = $ref->getProperty('_config');
        $configProp->setAccessible(true);
        $configProp->setValue($stub, $config);

        $languagesProp = $ref->getProperty('_languages');
        $languagesProp->setAccessible(true);
        $languagesProp->setValue($stub, ['de', 'en', null]);

        return $stub;
    }

    public function testGetFieldsReturnsFieldsForLanguageAndObjectType(): void
    {
        $indexer = $this->createIndexerStub();
        $fields = $indexer->getFields('de', 100);
        $this->assertIsArray($fields);
        $this->assertArrayHasKey('fulltext', $fields);
        $this->assertSame(['name' => 'fulltext_de'], $fields['fulltext']);
    }

    public function testGetFieldsDifferentLanguage(): void
    {
        $indexer = $this->createIndexerStub();
        $fields = $indexer->getFields('en', 100);
        $this->assertArrayHasKey('fulltext', $fields);
        $this->assertSame(['name' => 'fulltext_en'], $fields['fulltext']);
    }

    public function testGetFieldsUnknownObjectTypeReturnsEmpty(): void
    {
        $indexer = $this->createIndexerStub();
        $fields = $indexer->getFields('de', 999);
        $this->assertSame([], $fields);
    }

    public function testGetFieldsWithNullLanguage(): void
    {
        $indexer = $this->createIndexerStub();
        $fields = $indexer->getFields(null, 100);
        $this->assertIsArray($fields);
    }

    public function testGetIndexTemplateName(): void
    {
        $indexer = $this->createIndexerStub();
        $name = $indexer->getIndexTemplateName('de');
        $this->assertStringStartsWith('index_', $name);
        $this->assertStringEndsWith('_de', $name);
    }
}
