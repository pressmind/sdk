<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\MediaObjectDepublisher;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class MediaObjectDepublisherTest extends AbstractTestCase
{
    public function testDepublishSetsNobodyAndRunsAllConfiguredCleanupSteps(): void
    {
        $calls = new \ArrayObject();
        $mediaObject = new class($calls) {
            public int $id = 123;
            public int $visibility = 30;
            private \ArrayObject $calls;

            public function __construct(\ArrayObject $calls)
            {
                $this->calls = $calls;
            }

            public function update(): void
            {
                $this->calls->append(['mysql', $this->visibility]);
            }

            public function removeFromCache(): void
            {
                $this->calls->append(['cache', $this->id]);
            }

            public function createMongoDBIndex(): void
            {
                $this->calls->append(['mongodb_search', $this->id]);
            }

            public function deleteMongoDBCalendar(): void
            {
                $this->calls->append(['mongodb_calendar', $this->id]);
            }
        };

        $db = $this->createMockDb();
        $db->expects($this->once())
            ->method('delete')
            ->with('pmt2core_fulltext_search', ['id_media_object = ?', 123]);

        $config = $this->createMockConfig([
            'cache' => [
                'enabled' => true,
                'types' => ['OBJECT'],
            ],
            'data' => [
                'media_types_fulltext_index_fields' => [1 => ['name']],
                'search_mongodb' => ['enabled' => true],
                'search_opensearch' => ['enabled' => true],
            ],
        ]);

        Registry::clear();
        Registry::getInstance()->add('config', $config);
        Registry::getInstance()->add('db', $db);

        $depublisher = new MediaObjectDepublisher(
            fn (int $id) => $mediaObject,
            function (int $id) use ($calls): void {
                $calls->append(['opensearch', $id]);
            }
        );

        $result = $depublisher->depublish(123);

        $this->assertFalse($result->hasErrors(), json_encode($result->getErrors()));
        $this->assertSame(10, $mediaObject->visibility);
        $this->assertSame(
            [
                ['mysql', 10],
                ['cache', 123],
                ['mongodb_search', 123],
                ['mongodb_calendar', 123],
                ['opensearch', 123],
            ],
            $calls->getArrayCopy()
        );
        $this->assertTrue($result->isSuccessfulFor(123, 'mysql'));
        $this->assertTrue($result->isSuccessfulFor(123, 'fulltext'));
        $this->assertTrue($result->isSuccessfulFor(123, 'mongodb_search'));
        $this->assertTrue($result->isSuccessfulFor(123, 'mongodb_calendar'));
        $this->assertTrue($result->isSuccessfulFor(123, 'opensearch'));
    }
}
