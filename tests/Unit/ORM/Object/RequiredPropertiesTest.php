<?php

namespace Pressmind\Tests\Unit\ORM\Object;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup\Item;
use Pressmind\ORM\Object\Touristic\Option\Discount\Scale;
use Pressmind\Tests\Unit\AbstractTestCase;

/**
 * Tests checkForRequiredProperties() via Reflection on concrete ORM classes.
 */
class RequiredPropertiesTest extends AbstractTestCase
{
    private function invokeCheckForRequiredProperties(AbstractObject $object): array|bool
    {
        $method = new \ReflectionMethod(AbstractObject::class, 'checkForRequiredProperties');
        $method->setAccessible(true);
        return $method->invoke($object);
    }

    public function testScaleMissingTypeReturnsMissingProperties(): void
    {
        $scale = new Scale(null, false);
        $scale->id = 'scale-1';
        $scale->id_touristic_option_discount = 'disc-1';
        $scale->name = 'Test';
        $scale->type = '';
        $scale->value = 10.0;
        $scale->occupancy = 2;
        $result = $this->invokeCheckForRequiredProperties($scale);
        $this->assertIsArray($result);
        $this->assertContains('type', $result);
    }

    public function testEarlyBirdItemMissingTypeReturnsMissingProperties(): void
    {
        $item = new Item(null, false);
        $item->id = 'item-1';
        $item->id_early_bird_discount_group = 'group-1';
        $item->type = '';
        $item->discount_value = 10.0;
        $result = $this->invokeCheckForRequiredProperties($item);
        $this->assertIsArray($result);
        $this->assertContains('type', $result);
    }

    public function testScaleAllRequiredSetReturnsTrue(): void
    {
        $scale = new Scale(null, false);
        $scale->id = 'scale-1';
        $scale->id_touristic_option_discount = 'disc-1';
        $scale->name = 'Test';
        $scale->type = 'P';
        $scale->value = 10.0;
        $scale->occupancy = 2;
        $result = $this->invokeCheckForRequiredProperties($scale);
        $this->assertTrue($result);
    }

    public function testCheapestPriceSpeedWithIdReturnsTrue(): void
    {
        $cps = new CheapestPriceSpeed(null, false);
        $cps->id = 1;
        $result = $this->invokeCheckForRequiredProperties($cps);
        $this->assertTrue($result);
    }
}
