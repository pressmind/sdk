<?php

namespace Pressmind\Tests\Unit\System;

use PHPUnit\Framework\TestCase;
use Pressmind\System\I18n;

/**
 * Unit tests for Pressmind\System\I18n.
 */
class I18nTest extends TestCase
{
    public function testTranslateReturnsInputText(): void
    {
        $this->assertSame('Hello World', I18n::translate('Hello World'));
    }

    public function testTranslateWithDomainReturnsInputText(): void
    {
        $this->assertSame('Some text', I18n::translate('Some text', 'my_domain'));
    }

    public function testTranslateWithLocaleReturnsInputText(): void
    {
        $this->assertSame('Localized', I18n::translate('Localized', null, 'de_DE'));
    }

    public function testTranslateWithEmptyString(): void
    {
        $this->assertSame('', I18n::translate(''));
    }
}
