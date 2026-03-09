<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\StartingPointOptions;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class StartingPointOptionsTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $ids = ['id1', 'id2'];
        $import = new StartingPointOptions($ids);
        $this->assertInstanceOf(StartingPointOptions::class, $import);
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientEmptyResult(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) ['result' => [], 'error' => false]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new StartingPointOptions(['1']);
        $import->import();
        $this->assertNotEmpty($import->getLog());
    }

    public function testRemoveOrphans(): void
    {
        $import = new StartingPointOptions([1]);
        $import->remove_orphans('1', ['opt1', 'opt2']);
        $this->addToAssertionCount(1);
    }
}
