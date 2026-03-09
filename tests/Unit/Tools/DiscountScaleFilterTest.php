<?php

namespace Pressmind\Tests\Unit\Tools;

use DateTime;
use Pressmind\ORM\Object\Touristic\Option\Discount\Scale;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\Tools\PriceHandler;

/**
 * Tests PriceHandler::getCheapestOptionDiscount() with valid_from/valid_to filtering and age groups.
 */
class DiscountScaleFilterTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $config = Registry::getInstance()->get('config');
        $config['price_format'] = [
            'de' => [
                'decimals' => 2,
                'decimal_separator' => ',',
                'thousands_separator' => '.',
                'position' => 'RIGHT',
                'currency' => '€',
            ],
        ];
        Registry::getInstance()->add('config', $config);
    }

    private function createScale(array $overrides = []): Scale
    {
        $scale = new Scale(null, false);
        $defaults = [
            'id' => 'scale-' . uniqid(),
            'id_touristic_option_discount' => 'disc-1',
            'name' => 'Test',
            'type' => 'P',
            'value' => 10.0,
            'occupancy' => 2,
            'age_from' => 0,
            'age_to' => 17,
            'valid_from' => new DateTime('-30 days'),
            'valid_to' => new DateTime('+30 days'),
        ];
        foreach (array_merge($defaults, $overrides) as $key => $value) {
            $scale->$key = $value;
        }
        return $scale;
    }

    public function testScaleWithValidWindowIncludingTodayIsIncluded(): void
    {
        $scale = $this->createScale([
            'valid_from' => new DateTime('-10 days'),
            'valid_to' => new DateTime('+10 days'),
            'age_from' => 0,
            'age_to' => 2,
            'type' => 'P',
            'value' => 100,
        ]);
        $result = PriceHandler::getCheapestOptionDiscount([$scale]);
        $this->assertStringContainsString('Kinderrabatt', $result);
        $this->assertStringContainsString('100%', $result);
        $this->assertStringContainsString('0&#8209;2', $result);
    }

    public function testScaleWithValidToInPastIsExcluded(): void
    {
        $scale = $this->createScale([
            'valid_from' => new DateTime('-60 days'),
            'valid_to' => new DateTime('-1 day'),
            'age_from' => 2,
            'age_to' => 13,
            'type' => 'P',
            'value' => 15,
        ]);
        $result = PriceHandler::getCheapestOptionDiscount([$scale]);
        $this->assertSame('', $result);
    }

    public function testScaleWithValidFromInFutureIsExcluded(): void
    {
        $scale = $this->createScale([
            'valid_from' => new DateTime('+1 day'),
            'valid_to' => new DateTime('+30 days'),
            'age_from' => 0,
            'age_to' => 2,
            'type' => 'P',
            'value' => 100,
        ]);
        $result = PriceHandler::getCheapestOptionDiscount([$scale]);
        $this->assertSame('', $result);
    }

    public function testTwoScalesSameAgeGroupBiggerDiscountWins(): void
    {
        $scale1 = $this->createScale([
            'age_from' => 2,
            'age_to' => 13,
            'type' => 'P',
            'value' => 8,
        ]);
        $scale2 = $this->createScale([
            'id' => 'scale-2',
            'age_from' => 2,
            'age_to' => 13,
            'type' => 'P',
            'value' => 15,
        ]);
        $result = PriceHandler::getCheapestOptionDiscount([$scale1, $scale2]);
        $this->assertStringContainsString('15%', $result);
        $this->assertStringNotContainsString('8%', $result);
    }

    public function testMixedTypesPAndF(): void
    {
        $scaleP = $this->createScale([
            'age_from' => 0,
            'age_to' => 2,
            'type' => 'P',
            'value' => 100,
        ]);
        $scaleF = $this->createScale([
            'id' => 'scale-f',
            'age_from' => 2,
            'age_to' => 13,
            'type' => 'F',
            'value' => 18,
        ]);
        $result = PriceHandler::getCheapestOptionDiscount([$scaleP, $scaleF]);
        $this->assertStringContainsString('Kinderrabatt', $result);
        $this->assertStringContainsString('100%', $result);
        $this->assertStringContainsString('18', $result);
    }

    public function testOutputFormatBabiesAndChildren(): void
    {
        $scale1 = $this->createScale([
            'age_from' => 0,
            'age_to' => 2,
            'type' => 'P',
            'value' => 100,
        ]);
        $scale2 = $this->createScale([
            'id' => 'scale-2',
            'age_from' => 2,
            'age_to' => 13,
            'type' => 'P',
            'value' => 15,
        ]);
        $result = PriceHandler::getCheapestOptionDiscount([$scale1, $scale2]);
        $this->assertStringContainsString('Kinderrabatt:', $result);
        $this->assertStringContainsString('0&#8209;2', $result);
        $this->assertStringContainsString('2&#8209;13', $result);
        $this->assertStringContainsString('bis zu', $result);
    }

    public function testAgeToAbove17GoesToOthers(): void
    {
        $scale = $this->createScale([
            'age_from' => 18,
            'age_to' => 65,
            'type' => 'P',
            'value' => 5,
        ]);
        $result = PriceHandler::getCheapestOptionDiscount([$scale]);
        $this->assertStringContainsString('Weitere altersbezogene Rabatte', $result);
        $this->assertStringContainsString('5%', $result);
    }

    public function testTypeESkippedInOutput(): void
    {
        $scale = $this->createScale([
            'age_from' => 0,
            'age_to' => 2,
            'type' => 'E',
            'value' => 50,
        ]);
        $result = PriceHandler::getCheapestOptionDiscount([$scale]);
        $this->assertSame('', $result);
    }
}
