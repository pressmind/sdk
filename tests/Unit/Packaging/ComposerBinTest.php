<?php

namespace Pressmind\Tests\Unit\Packaging;

use Pressmind\Tests\Unit\AbstractTestCase;

class ComposerBinTest extends AbstractTestCase
{
    public function testComposerExposesAiCatalogBinary(): void
    {
        $sdkRoot = dirname(__DIR__, 3);
        $composer = json_decode((string) file_get_contents($sdkRoot . '/composer.json'), true);

        $this->assertContains('bin/ai-catalog', $composer['bin']);

        $binary = $sdkRoot . '/bin/ai-catalog';
        $this->assertFileExists($binary);

        $content = (string) file_get_contents($binary);
        $this->assertStringStartsWith("#!/usr/bin/env php\n", $content);
        $this->assertStringContainsString("__DIR__ . '/../vendor/autoload.php'", $content);
        $this->assertStringContainsString("__DIR__ . '/../../../autoload.php'", $content);
        $this->assertStringContainsString('new \Pressmind\CLI\AiCatalogCommand()', $content);
    }
}
