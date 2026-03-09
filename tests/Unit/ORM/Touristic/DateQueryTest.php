<?php

namespace Pressmind\Tests\Unit\ORM\Touristic;

use DateTime;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item;
use Pressmind\Tests\Unit\AbstractTestCase;

class DateQueryTest extends AbstractTestCase
{
    private function createDate(array $props = []): Date
    {
        $date = new Date();
        $defaults = [
            'id' => 'date-1',
            'id_media_object' => 100,
            'id_booking_package' => 'bp-1',
            'season' => 'S24',
            'departure' => new DateTime('2026-06-01'),
            'arrival' => new DateTime('2026-06-08'),
            'state' => 3,
        ];
        foreach (array_merge($defaults, $props) as $k => $v) {
            $date->$k = $v;
        }
        return $date;
    }

    private function createEarlyBirdItem(array $props = []): Item
    {
        $item = new Item();
        $defaults = [
            'id' => 'item-' . uniqid(),
            'id_early_bird_discount_group' => 'ebdg-1',
            'type' => 'P',
            'discount_value' => 10.0,
            'agency' => null,
        ];
        foreach (array_merge($defaults, $props) as $k => $v) {
            $item->$k = $v;
        }
        return $item;
    }

    // --- getEarlybirds() ---

    public function testGetEarlybirdsReturnsEmptyWhenGroupIsNull(): void
    {
        $date = $this->createDate();
        $date->early_bird_discount_group = null;

        $this->assertSame([], $date->getEarlybirds());
    }

    public function testGetEarlybirdsReturnsEmptyWhenItemsAreEmpty(): void
    {
        $group = new EarlyBirdDiscountGroup();
        $group->id = 'ebdg-1';
        $group->items = [];

        $date = $this->createDate(['early_bird_discount_group' => $group]);

        $this->assertSame([], $date->getEarlybirds());
    }

    public function testGetEarlybirdsReturnsOnlyAgencylessItemsWhenAgencyIsNull(): void
    {
        $item1 = $this->createEarlyBirdItem(['id' => 'i1', 'agency' => 'A']);
        $item2 = $this->createEarlyBirdItem(['id' => 'i2', 'agency' => null]);
        $item3 = $this->createEarlyBirdItem(['id' => 'i3', 'agency' => '']);

        $group = new EarlyBirdDiscountGroup();
        $group->id = 'ebdg-1';
        $group->items = [$item1, $item2, $item3];

        $date = $this->createDate(['early_bird_discount_group' => $group]);

        $result = $date->getEarlybirds(null);
        $this->assertCount(2, $result);
        $ids = array_map(fn($i) => $i->id, $result);
        $this->assertContains('i2', $ids);
        $this->assertContains('i3', $ids);
        $this->assertNotContains('i1', $ids);
    }

    public function testGetEarlybirdsFiltersItemsByAgency(): void
    {
        $item1 = $this->createEarlyBirdItem(['id' => 'i1', 'agency' => 'A']);
        $item2 = $this->createEarlyBirdItem(['id' => 'i2', 'agency' => 'B']);
        $item3 = $this->createEarlyBirdItem(['id' => 'i3', 'agency' => null]);

        $group = new EarlyBirdDiscountGroup();
        $group->id = 'ebdg-1';
        $group->items = [$item1, $item2, $item3];

        $date = $this->createDate(['early_bird_discount_group' => $group]);

        $result = $date->getEarlybirds('A');
        $this->assertCount(2, $result);
        $ids = array_map(fn($i) => $i->id, $result);
        $this->assertContains('i1', $ids);
        $this->assertContains('i3', $ids);
    }

    // --- getAllOptionsButExcludePriceMixOptions() ---

    public function testGetAllOptionsButExcludePriceMixReturnsEmptyForUnknownMix(): void
    {
        $date = $this->createDate();
        $result = $date->getAllOptionsButExcludePriceMixOptions('unknown_mix');
        $this->assertSame([], $result);
    }

    public function testGetAllOptionsButExcludePriceMixDateTransportMapsToTicketSightseeingExtra(): void
    {
        $db = $this->createMockDb();
        $db->expects($this->once())
            ->method('fetchAll')
            ->with($this->callback(function (string $query) {
                return str_contains($query, "'ticket'")
                    && str_contains($query, "'sightseeing'")
                    && str_contains($query, "'extra'");
            }))
            ->willReturn([]);

        $registry = \Pressmind\Registry::getInstance();
        $registry->add('db', $db);

        $date = $this->createDate();
        $result = $date->getAllOptionsButExcludePriceMixOptions('date_transport');
        $this->assertIsArray($result);
    }

    public function testGetAllOptionsButExcludePriceMixDateHousingMapsToTicketSightseeingExtra(): void
    {
        $db = $this->createMockDb();
        $db->expects($this->once())
            ->method('fetchAll')
            ->with($this->callback(function (string $query) {
                return str_contains($query, "'ticket'")
                    && str_contains($query, "'sightseeing'")
                    && str_contains($query, "'extra'");
            }))
            ->willReturn([]);

        $registry = \Pressmind\Registry::getInstance();
        $registry->add('db', $db);

        $date = $this->createDate();
        $result = $date->getAllOptionsButExcludePriceMixOptions('date_housing');
        $this->assertIsArray($result);
    }

    // --- getAllOptionsByPriceMix() ---

    public function testGetAllOptionsByPriceMixReturnsEmptyForUnknownMix(): void
    {
        $date = $this->createDate();
        $result = $date->getAllOptionsByPriceMix('unknown_mix');
        $this->assertSame([], $result);
    }

    public function testGetAllOptionsByPriceMixDateTransportMapsToTransportType(): void
    {
        $db = $this->createMockDb();
        $db->expects($this->once())
            ->method('fetchAll')
            ->with($this->callback(function (string $query) {
                return str_contains($query, "'transport'")
                    && !str_contains($query, "'ticket'");
            }))
            ->willReturn([]);

        $registry = \Pressmind\Registry::getInstance();
        $registry->add('db', $db);

        $date = $this->createDate();
        $result = $date->getAllOptionsByPriceMix('date_transport');
        $this->assertIsArray($result);
    }
}
