<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\Port;
use Pressmind\Tests\Unit\AbstractTestCase;

class PortTest extends AbstractTestCase
{
    public function testDefinesAndReturnsCoordinates(): void
    {
        $port = new Port();

        $this->assertTrue($port->hasProperty('lat'));
        $this->assertTrue($port->hasProperty('lng'));
        $this->assertNull($port->getCoordinates());

        $port->lat = 53.5439;
        $port->lng = 9.9666;

        $this->assertSame(['lat' => 53.5439, 'lng' => 9.9666], $port->getCoordinates());
    }
}
