<?php

namespace Pressmind\Tests\Unit\Tools;

use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\Registry;
use Pressmind\Tests\Unit\AbstractTestCase;
use Pressmind\Tools\PriceHandler;

class PriceHandlerTest extends AbstractTestCase
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

    public function testGetDiscountReturnsEarlybirdPercent(): void
    {
        $cps = new CheapestPriceSpeed(null, false);
        $cps->earlybird_discount = 10.0;
        $cps->price_regular_before_discount = 1000.0;
        $cps->earlybird_discount_date_to = new \DateTime('+30 days');
        $cps->earlybird_name = '';
        $cps->earlybird_discount_f = null;
        $cps->price_option_pseudo = null;
        $cps->price_total = 900.0;

        $display = PriceHandler::getDiscount($cps, 'Frühbucher', 'Ihr Vorteil');
        $this->assertNotFalse($display);
        $this->assertSame('earlybird', $display->type);
        $this->assertSame('-10%', $display->price_delta);
        $this->assertSame('Frühbucher', $display->name);
    }

    public function testGetDiscountReturnsEarlybirdFixed(): void
    {
        $cps = new CheapestPriceSpeed(null, false);
        $cps->earlybird_discount = null;
        $cps->earlybird_discount_f = 50.0;
        $cps->price_regular_before_discount = 500.0;
        $cps->earlybird_discount_date_to = new \DateTime('+14 days');
        $cps->earlybird_name = 'Test Earlybird';
        $cps->price_option_pseudo = null;
        $cps->price_total = 450.0;

        $display = PriceHandler::getDiscount($cps, 'Frühbucher', 'Ihr Vorteil');
        $this->assertNotFalse($display);
        $this->assertSame('earlybird', $display->type);
        $this->assertSame('-50,00&nbsp;€', $display->price_delta);
        $this->assertSame('Test Earlybird', $display->name);
    }

    public function testGetDiscountReturnsPseudoWhenPseudoPriceHigher(): void
    {
        $cps = new CheapestPriceSpeed(null, false);
        $cps->earlybird_discount = null;
        $cps->earlybird_discount_f = null;
        $cps->price_option_pseudo = 1000.0;
        $cps->price_total = 800.0;
        $cps->price_regular_before_discount = 800.0;

        $display = PriceHandler::getDiscount($cps, 'Frühbucher', 'Ihr Vorteil');
        $this->assertNotFalse($display);
        $this->assertSame('pseudo', $display->type);
        $this->assertSame('Ihr Vorteil', $display->name);
        $this->assertStringContainsString('%', $display->price_delta);
    }

    public function testGetDiscountReturnsFalseWhenNoDiscount(): void
    {
        $cps = new CheapestPriceSpeed(null, false);
        $cps->earlybird_discount = null;
        $cps->earlybird_discount_f = null;
        $cps->price_option_pseudo = null;
        $cps->price_total = 500.0;
        $cps->price_regular_before_discount = 500.0;

        $this->assertFalse(PriceHandler::getDiscount($cps));
    }

    public function testFormatUsesConfig(): void
    {
        $formatted = PriceHandler::format(1234.5, 'de');
        $this->assertStringContainsString('1.234,50', $formatted);
        $this->assertStringContainsString('€', $formatted);
    }

    public function testFormatPositionLeft(): void
    {
        $config = Registry::getInstance()->get('config');
        $config['price_format']['en'] = [
            'decimals' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
            'position' => 'LEFT',
            'currency' => '$',
        ];
        Registry::getInstance()->add('config', $config);
        $formatted = PriceHandler::format(99.99, 'en');
        $this->assertStringStartsWith('$', $formatted);
        $this->assertStringContainsString('99.99', $formatted);
    }

    public function testGetDiscountReturnsFalseWhenPseudoEqualsTotal(): void
    {
        $cps = new CheapestPriceSpeed(null, false);
        $cps->earlybird_discount = null;
        $cps->earlybird_discount_f = null;
        $cps->price_option_pseudo = 800.0;
        $cps->price_total = 800.0;
        $cps->price_regular_before_discount = 800.0;

        $this->assertFalse(PriceHandler::getDiscount($cps));
    }

    public function testGetDiscountReturnsFalseWhenPseudoZero(): void
    {
        $cps = new CheapestPriceSpeed(null, false);
        $cps->earlybird_discount = null;
        $cps->earlybird_discount_f = null;
        $cps->price_option_pseudo = 0.0;
        $cps->price_total = 500.0;
        $cps->price_regular_before_discount = 500.0;

        $this->assertFalse(PriceHandler::getDiscount($cps));
    }

    public function testGetDiscountEarlybirdHasPriorityOverPseudo(): void
    {
        $cps = new CheapestPriceSpeed(null, false);
        $cps->earlybird_discount = 10.0;
        $cps->earlybird_discount_f = null;
        $cps->earlybird_discount_date_to = new \DateTime('+30 days');
        $cps->earlybird_name = 'Frühbucher';
        $cps->price_regular_before_discount = 1000.0;
        $cps->price_total = 900.0;
        $cps->price_option_pseudo = 1200.0;

        $display = PriceHandler::getDiscount($cps, 'Frühbucher', 'Ihr Vorteil');
        $this->assertNotFalse($display);
        $this->assertSame('earlybird', $display->type);
    }

    public function testGetDiscountReturnsEarlybirdEvenWhenDateToPassed(): void
    {
        $cps = new CheapestPriceSpeed(null, false);
        $cps->earlybird_discount = 10.0;
        $cps->earlybird_discount_f = null;
        $cps->earlybird_discount_date_to = new \DateTime('-1 day');
        $cps->earlybird_name = 'Expired';
        $cps->price_regular_before_discount = 1000.0;
        $cps->price_total = 900.0;
        $cps->price_option_pseudo = null;

        $display = PriceHandler::getDiscount($cps, 'Frühbucher', 'Ihr Vorteil');
        $this->assertNotFalse($display);
        $this->assertSame('earlybird', $display->type);
    }

    public function testGetDiscountPseudoRequiresPseudoGreaterThanTotal(): void
    {
        $cps = new CheapestPriceSpeed(null, false);
        $cps->earlybird_discount = null;
        $cps->earlybird_discount_f = null;
        $cps->price_option_pseudo = 600.0;
        $cps->price_total = 800.0;
        $cps->price_regular_before_discount = 800.0;

        $this->assertFalse(PriceHandler::getDiscount($cps));
    }

    public function testGetDiscountPseudoNegativeGivesZeroPercent(): void
    {
        $cps = new CheapestPriceSpeed(null, false);
        $cps->earlybird_discount = null;
        $cps->earlybird_discount_f = null;
        $cps->price_option_pseudo = -5.0;
        $cps->price_total = -10.0;
        $cps->price_regular_before_discount = -10.0;

        $display = PriceHandler::getDiscount($cps, 'Frühbucher', 'Ihr Vorteil');
        $this->assertNotFalse($display);
        $this->assertSame('pseudo', $display->type);
        $this->assertSame('-0%', $display->price_delta);
    }

    public function testFormatFallbacksWhenLocaleNotConfigured(): void
    {
        $config = Registry::getInstance()->get('config');
        unset($config['price_format']);
        Registry::getInstance()->add('config', $config);

        $formatted = PriceHandler::format(100.0, 'fr');
        $this->assertStringContainsString('100', $formatted);
        $this->assertStringContainsString('€', $formatted);
    }
}
