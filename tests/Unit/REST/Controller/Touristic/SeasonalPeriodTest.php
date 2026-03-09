<?php

namespace Pressmind\Tests\Unit\REST\Controller\Touristic;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\Touristic\SeasonalPeriod;

class SeasonalPeriodTest extends AbstractTestCase
{
    public function testListAllReturnsArray(): void
    {
        $controller = new SeasonalPeriod();
        $this->assertIsArray($controller->listAll([]));
    }

    public function testReadWithIdReturnsNullWhenNotFound(): void
    {
        $controller = new SeasonalPeriod();
        $this->assertNull($controller->read(0));
    }
}
