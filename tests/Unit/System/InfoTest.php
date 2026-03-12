<?php

namespace Pressmind\Tests\Unit\System;

use Pressmind\System\Info;
use Pressmind\Tests\Unit\AbstractTestCase;

class InfoTest extends AbstractTestCase
{
    public function testIBEImportVersionConstant(): void
    {
        $this->assertIsString(Info::IBE_IMPORT_VERSION);
        $this->assertNotEmpty(Info::IBE_IMPORT_VERSION);
        $this->assertMatchesRegularExpression('/^\d+_\d+$/', Info::IBE_IMPORT_VERSION);
    }

    public function testStaticModelsContainsExpectedEntries(): void
    {
        $this->assertIsArray(Info::STATIC_MODELS);
        $this->assertContains('\MediaObject', Info::STATIC_MODELS);
        $this->assertContains('\Brand', Info::STATIC_MODELS);
        $this->assertContains('\CheapestPriceSpeed', Info::STATIC_MODELS);
        $this->assertContains('\Touristic\Booking\Package', Info::STATIC_MODELS);
        $this->assertContains('\Touristic\Insurance\Surcharge', Info::STATIC_MODELS);
    }
}
