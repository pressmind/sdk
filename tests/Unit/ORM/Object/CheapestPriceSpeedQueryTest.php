<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use stdClass;

class CheapestPriceSpeedQueryTest extends AbstractTestCase
{
    public function testGetLowestPriceReturnsNullWhenFetchRowReturnsNull(): void
    {
        $object = new CheapestPriceSpeed();
        $this->assertNull($object->getLowestPrice());
    }

    public function testGetLowestPriceReturnsMinPriceValue(): void
    {
        $row = new stdClass();
        $row->min_price = 99.50;

        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchRow')->willReturn($row);
        $db->method('fetchAll')->willReturn([]);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('inTransaction')->willReturn(false);

        Registry::clear();
        $registry = Registry::getInstance();
        $registry->add('config', $this->createMockConfig([]));
        $registry->add('db', $db);

        $object = new CheapestPriceSpeed();
        $this->assertSame(99.50, $object->getLowestPrice());
    }

    public function testGetHighestPriceReturnsNullWhenFetchRowReturnsNull(): void
    {
        $object = new CheapestPriceSpeed();
        $this->assertNull($object->getHighestPrice());
    }

    public function testGetHighestPriceReturnsMaxPriceValue(): void
    {
        $row = new stdClass();
        $row->max_price = 2500.00;

        $db = $this->createMock(AdapterInterface::class);
        $db->method('fetchRow')->willReturn($row);
        $db->method('fetchAll')->willReturn([]);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('inTransaction')->willReturn(false);

        Registry::clear();
        $registry = Registry::getInstance();
        $registry->add('config', $this->createMockConfig([]));
        $registry->add('db', $db);

        $object = new CheapestPriceSpeed();
        $this->assertSame(2500.00, $object->getHighestPrice());
    }

    public function testGetMinMaxPricesReturnsArrayOfLowestAndHighest(): void
    {
        $result = CheapestPriceSpeed::getMinMaxPrices();
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertNull($result[0]);
        $this->assertNull($result[1]);
    }

    public function testDeleteByMediaObjectIdCallsDeleteOnDb(): void
    {
        $db = $this->createMock(AdapterInterface::class);
        $db->expects($this->once())
            ->method('delete')
            ->with(
                'pmt2core_pmt2core_cheapest_price_speed',
                ['id_media_object = ?', 42]
            );
        $db->method('fetchAll')->willReturn([]);
        $db->method('getTablePrefix')->willReturn('pmt2core_');
        $db->method('inTransaction')->willReturn(false);

        Registry::clear();
        $registry = Registry::getInstance();
        $registry->add('config', $this->createMockConfig([]));
        $registry->add('db', $db);

        $object = new CheapestPriceSpeed();
        $object->deleteByMediaObjectId(42);
    }
}
