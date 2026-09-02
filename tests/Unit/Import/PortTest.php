<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\Import\Port;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class PortTest extends AbstractTestCase
{
    public function testConstructorAndGetters(): void
    {
        $import = new Port();
        $this->assertIsArray($import->getLog());
        $this->assertIsArray($import->getErrors());
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientEmptyResult(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) ['result' => [], 'error' => false]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Port();
        $import->import();
        $this->assertCount(0, $import->getErrors());
    }

    public function testImportWithMockedClientWithPorts(): void
    {
        $batch = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('getTablePrefix')->willReturn('');
        $db->method('batchInsert')->willReturnCallback(
            function (string $table, array $columns, array $rows) use (&$batch): int {
                $batch = compact('table', 'columns', 'rows');
                return count($rows);
            }
        );
        Registry::getInstance()->add('db', $db);

        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'result' => [
                (object) [
                    'id' => 1,
                    'code' => 'HAM',
                    'active' => 1,
                    'name' => 'Hamburg',
                    'description' => 'Port',
                    'coordinates' => (object) ['lat' => 53.5439, 'lng' => 9.9666],
                ],
            ],
            'error' => false,
        ]);
        Registry::getInstance()->add('rest_client', $client);
        $import = new Port();
        $import->import();

        $this->assertCount(0, $import->getErrors());
        $this->assertSame('pmt2core_ports', $batch['table']);
        $this->assertSame(53.5439, $batch['rows'][0][array_search('lat', $batch['columns'], true)]);
        $this->assertSame(9.9666, $batch['rows'][0][array_search('lng', $batch['columns'], true)]);
    }

    public function testImportRejectsOutOfRangeCoordinatesWithoutDroppingPort(): void
    {
        $batch = [];
        $db = $this->createMock(AdapterInterface::class);
        $db->method('getTablePrefix')->willReturn('');
        $db->method('batchInsert')->willReturnCallback(
            function (string $table, array $columns, array $rows) use (&$batch): int {
                $batch = compact('table', 'columns', 'rows');
                return count($rows);
            }
        );
        Registry::getInstance()->add('db', $db);

        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willReturn((object) [
            'result' => [
                (object) [
                    'id' => 2,
                    'code' => 'BAD',
                    'active' => 1,
                    'name' => 'Invalid',
                    'description' => null,
                    'coordinates' => (object) ['lat' => 91, 'lng' => 9.9666],
                ],
            ],
            'error' => false,
        ]);
        Registry::getInstance()->add('rest_client', $client);

        $import = new Port();
        $import->import();

        $this->assertCount(1, $import->getErrors());
        $this->assertNull($batch['rows'][0][array_search('lat', $batch['columns'], true)]);
        $this->assertNull($batch['rows'][0][array_search('lng', $batch['columns'], true)]);
    }

    public function testImportAddsErrorOnException(): void
    {
        $client = $this->createMock(\Pressmind\REST\Client::class);
        $client->method('sendRequest')->willThrowException(new \Exception('Network error'));
        Registry::getInstance()->add('rest_client', $client);
        $import = new Port();
        $import->import();
        $this->assertCount(1, $import->getErrors());
    }
}
