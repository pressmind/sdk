<?php

namespace Pressmind\Tests\Unit\Search\OpenSearch;

use Pressmind\Search\OpenSearch\Indexer;
use Pressmind\Tests\Unit\AbstractTestCase;

class IndexerDeleteTest extends AbstractTestCase
{
    public function testDeleteMediaObjectDeletesEveryIdFromEveryLanguageIndex(): void
    {
        $deleted = new \ArrayObject();
        $client = new class($deleted) {
            private \ArrayObject $deleted;

            public function __construct(\ArrayObject $deleted)
            {
                $this->deleted = $deleted;
            }

            public function delete(array $params): void
            {
                $this->deleted->append($params);
            }
        };

        $indexer = $this->getMockBuilder(Indexer::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $ref = new \ReflectionClass(Indexer::class);
        $configProp = $ref->getParentClass()->getProperty('_config');
        $configProp->setAccessible(true);
        $configProp->setValue($indexer, [
            'uri' => 'http://localhost:9200',
            'index' => [
                'fulltext' => [
                    'object_type_mapping' => [
                        1 => [
                            ['language' => 'de'],
                            ['language' => 'en'],
                        ],
                    ],
                ],
            ],
        ]);

        $languagesProp = $ref->getParentClass()->getProperty('_languages');
        $languagesProp->setAccessible(true);
        $languagesProp->setValue($indexer, ['de', 'en']);

        $indexer->client = $client;

        $indexer->deleteMediaObject('123,456');

        $deletedArray = $deleted->getArrayCopy();
        $this->assertCount(4, $deletedArray);
        $this->assertSame([123, 123, 456, 456], array_column($deletedArray, 'id'));
        $this->assertStringEndsWith('_de', $deletedArray[0]['index']);
        $this->assertStringEndsWith('_en', $deletedArray[1]['index']);
    }
}
