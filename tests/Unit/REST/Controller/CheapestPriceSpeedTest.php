<?php

namespace Pressmind\Tests\Unit\REST\Controller;

use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\REST\Controller\CheapestPriceSpeed;

/**
 * CheapestPriceSpeed has empty orm_class_name; constructor fails when instantiating ORM class.
 */
class CheapestPriceSpeedTest extends AbstractTestCase
{
    public function testConstructorThrowsDueToInvalidOrmClassName(): void
    {
        $this->expectException(\Throwable::class);
        new CheapestPriceSpeed();
    }
}
