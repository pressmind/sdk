<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\Import\Season;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class SeasonTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new Season();
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientEmptyResult(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) ['result' => [], 'error' => false]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Season();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientWithSeasons(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'result' => [
                (object) [
                    'id' => 1,
                    'name' => '2024',
                    'active' => 1,
                    'saison_from' => '2024-01-01',
                    'saison_to' => '2024-12-31',
                    'time_of_year' => null,
                ],
            ],
            'error' => false,
        ]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Season();
        $import->import();
        $this->assertIsArray($import->getErrors());
    }
}
