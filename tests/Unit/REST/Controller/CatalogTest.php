<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Catalog;

class CatalogTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->createMockConfig([
            'data' => [
                'search_mongodb' => [
                    'database' => ['uri' => '', 'db' => ''],
                    'search' => ['build_for' => []],
                    'enabled' => false,
                ],
                'search_opensearch' => ['enabled' => false],
                'languages' => ['allowed' => ['de'], 'default' => 'de'],
                'touristic' => [],
                'media_types_allowed_visibilities' => [],
                'media_types_fulltext_index_fields' => [],
            ],
        ]);
        Registry::getInstance()->add('config', $config);
    }

    public function testIndexWithEmptyParamsReturnsArray(): void
    {
        $catalog = new Catalog();
        $result = $catalog->index([]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    public function testIndexWithParamsReturnsArray(): void
    {
        $catalog = new Catalog();
        $result = $catalog->index(['pm-ot' => '123']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }
}
