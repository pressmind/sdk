<?php

namespace Pressmind\Tests\Unit\Import;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\Import;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;

class ImportRouteBatchInsertTest extends AbstractTestCase
{
    public function testBatchInsertRoutesIncludesMetaColumns(): void
    {
        Registry::getInstance()->add('config', $this->createMockConfig([
            'data' => [
                'schema_migration' => [
                    'mode' => 'log_only',
                ],
            ],
        ]));

        $db = $this->createMock(AdapterInterface::class);
        $db->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function ($sql) {
                    return strpos($sql, 'id_media_object, id_object_type, route, language, title, description') !== false
                        && strpos($sql, '(?, ?, ?, ?, ?, ?)') !== false;
                }),
                [
                    123,
                    2752,
                    '/verpflegung/venedig/',
                    'de',
                    'Venedig Reise',
                    'Kurzbeschreibung Venedig',
                ]
            );
        Registry::getInstance()->add('db', $db);

        $import = new Import('mediaobject');
        $callBatchInsertRoutes = \Closure::bind(function ($routes) {
            return $this->_batchInsertRoutes($routes);
        }, $import, Import::class);
        $callBatchInsertRoutes([[
            'id_media_object' => 123,
            'id_object_type' => 2752,
            'route' => '/verpflegung/venedig/',
            'language' => 'de',
            'title' => 'Venedig Reise',
            'description' => 'Kurzbeschreibung Venedig',
        ]]);
    }
}
