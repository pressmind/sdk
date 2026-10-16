<?php
namespace Pressmind\Tests\Unit\Search\Geo;

use Pressmind\Registry;
use Pressmind\Search\Query;
use Pressmind\Tests\Unit\AbstractTestCase;

class RequestTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Registry::getInstance()->add('config', $this->createMockConfig(['data' => ['search_mongodb' => ['database' => ['uri' => 'mongodb://localhost:27017', 'db' => 'test']], 'languages' => ['allowed' => ['de'], 'default' => 'de']]]));
        Query::$atlas_active = false;
    }

    public function testNewParametersAndLegacyNegativeLongitudes(): void
    {
        $search = Query::fromRequest(['pm-geo' => '-120,45,0', 'pm-bounds' => '170,-20,-170,20', 'pm-loc' => ['hotel' => '-120,45,2']]);
        $this->assertSame([-120.0, 45.0, 0.0], $search->getConditionByType('GeoDistance')->getParameters());
        $this->assertSame([170.0, -20.0, -170.0, 20.0], $search->getConditionByType('GeoBounds')->getParameters());
        $this->assertSame(['hotel', -120.0, 45.0, 2000.0], $search->getConditionByType('GeoLocation')->getParameters());
    }

    public function testInvalidExplicitGeoDoesNotBecomeUnrestrictedSearch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Query::fromRequest(['pm-geo' => '13,95,5']);
    }
}
